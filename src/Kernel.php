<?php
declare(strict_types=1);

namespace TriTransportes;

use TriTransportes\Controller\AuthController;
use TriTransportes\Controller\FlotaController;
use TriTransportes\Controller\ViajeController;
use TriTransportes\Domain\Rol;
use TriTransportes\Exception\ApiException;
use TriTransportes\Http\Request;
use TriTransportes\Http\Response;
use TriTransportes\Http\Router;
use TriTransportes\Security\RateLimiter;
use TriTransportes\Service\AuthService;

/**
 * Núcleo de la API: ruta → autenticación JWT → límite de tasa → rol → controlador.
 * Todo error sale con el esquema `Error` del contrato y un request_id para rastrearlo en el log.
 */
final class Kernel
{
    /** Solicitudes por usuario y minuto en las rutas autenticadas. */
    public const API_LIMITE = 120;

    private Router $router;

    public function __construct(private readonly Container $c, private readonly ?string $logFile = null)
    {
        $this->router = self::rutas();
    }

    public static function rutas(): Router
    {
        $A = Rol::Administrador;
        $S = Rol::Supervisor;
        $C = Rol::Conductor;
        $G = Rol::Gerencia;
        $r = new Router();

        $r->add('POST', '/auth/login', [AuthController::class, 'login'], public: true);
        $r->add('GET', '/auth/me', [AuthController::class, 'me']);

        $r->add('GET', '/viajes', [ViajeController::class, 'index']);
        $r->add('POST', '/viajes', [ViajeController::class, 'store'], [$A, $S]);
        $r->add('GET', '/viajes/{id}', [ViajeController::class, 'show']);
        $r->add('PUT', '/viajes/{id}', [ViajeController::class, 'update'], [$A, $S]);
        $r->add('DELETE', '/viajes/{id}', [ViajeController::class, 'destroy'], [$A]);
        $r->add('PATCH', '/viajes/{id}/estado', [ViajeController::class, 'estado'], [$A, $S, $C]);
        $r->add('POST', '/viajes/{id}/guias', [ViajeController::class, 'guia'], [$A, $S, $C]);

        $r->add('GET', '/cargas', [FlotaController::class, 'cargasIndex']);
        $r->add('POST', '/cargas', [FlotaController::class, 'cargasStore'], [$A, $S, $C]);

        $r->add('GET', '/vehiculos', [FlotaController::class, 'vehiculos']);
        $r->add('GET', '/vehiculos/{id}/rendimiento', [FlotaController::class, 'rendimiento'], [$A, $S, $G]);

        $r->add('GET', '/mantenciones', [FlotaController::class, 'mantencionesIndex'], [$A, $S, $G]);
        $r->add('POST', '/mantenciones', [FlotaController::class, 'mantencionesStore'], [$A, $S]);
        $r->add('PATCH', '/mantenciones/{id}/estado', [FlotaController::class, 'mantencionesEstado'], [$A, $S]);

        return $r;
    }

    public function handle(Request $req): Response
    {
        try {
            if ($req->method === 'OPTIONS') {
                return new Response(204, '', ['Allow' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS']);
            }
            $ruta = $this->router->match($req->method, $req->path);
            $req->params = $ruta['params'];

            if (!$ruta['public']) {
                $token = $req->bearerToken() ?? throw ApiException::unauthorized('Falta el encabezado Authorization: Bearer <token>.');
                $req->user = $this->c->get(AuthService::class)->autenticar($token);
                $this->c->get(RateLimiter::class)->hit('api:' . $req->user->id, self::API_LIMITE, 60);
                if ($ruta['roles'] !== null && !$req->user->es(...$ruta['roles'])) {
                    throw ApiException::forbidden();
                }
            }

            [$clase, $metodo] = $ruta['handler'];
            $resp = $this->c->get($clase)->$metodo($req);
        } catch (ApiException $e) {
            $error = ['code' => $e->errorCode, 'message' => $e->getMessage()];
            if ($e->details) {
                $error['details'] = $e->details;
            }
            $error['request_id'] = $req->requestId;
            $resp = Response::json(['error' => $error], $e->status, $e->headers);
        } catch (\Throwable $e) {
            $this->log($req, $e);
            $resp = Response::json(['error' => [
                'code' => 'ERROR_INTERNO',
                'message' => 'Ocurrió un error inesperado. Informe este código a soporte: ' . $req->requestId,
                'request_id' => $req->requestId,
            ]], 500);
        }
        $resp->headers['X-Request-Id'] = $req->requestId;
        $resp->headers['Cache-Control'] ??= 'no-store';
        return $resp;
    }

    private function log(Request $req, \Throwable $e): void
    {
        $linea = sprintf("[%s] %s %s %s → %s: %s en %s:%d\n", date('c'), $req->requestId, $req->method, $req->path, $e::class, $e->getMessage(), $e->getFile(), $e->getLine());
        if ($this->logFile) {
            @file_put_contents($this->logFile, $linea, FILE_APPEND | LOCK_EX);
        } else {
            error_log($linea);
        }
    }
}
