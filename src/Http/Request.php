<?php
declare(strict_types=1);

namespace TriTransportes\Http;

use TriTransportes\Exception\ApiException;
use TriTransportes\Security\AuthUser;

/** Solicitud HTTP inmutable salvo por los atributos que agregan el router y la autenticación. */
final class Request
{
    /** @var array<string, string> */
    public array $params = [];
    public ?AuthUser $user = null;
    public string $requestId;

    /**
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers  nombres en minúsculas
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $headers = [],
        public readonly string $rawBody = '',
        public readonly string $ip = '0.0.0.0',
    ) {
        $this->requestId = bin2hex(random_bytes(8));
    }

    /** Construye la solicitud desde las variables globales de PHP; $basePath es el prefijo a quitar (/TriTransportes/api/v1). */
    public static function fromGlobals(string $basePath): self
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $basePath = rtrim($basePath, '/');
        if ($basePath !== '' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }
        $uri = preg_replace('#/index\.php$#', '', $uri);
        $path = '/' . trim((string) $uri, '/');

        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = (string) $v;
            }
        }
        // Apache/LiteSpeed con PHP-FPM a veces entregan Authorization solo vía REDIRECT_
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
        if ($auth !== null) {
            $headers['authorization'] = (string) $auth;
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            $_GET,
            $headers,
            (string) file_get_contents('php://input'),
            (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
        );
    }

    /** Atajo para pruebas: crea una solicitud con cuerpo JSON. */
    public static function create(string $method, string $path, array $body = [], array $query = [], ?string $token = null, string $ip = '127.0.0.1'): self
    {
        $headers = ['content-type' => 'application/json'];
        if ($token !== null) {
            $headers['authorization'] = 'Bearer ' . $token;
        }
        return new self(strtoupper($method), $path, $query, $headers, $body === [] ? '' : json_encode($body, JSON_THROW_ON_ERROR), $ip);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function bearerToken(): ?string
    {
        $h = $this->header('authorization') ?? '';
        return preg_match('/^Bearer\s+(\S+)$/i', trim($h), $m) ? $m[1] : null;
    }

    /** Cuerpo JSON como arreglo; 400 si no es JSON válido. */
    public function json(): array
    {
        if (trim($this->rawBody) === '') {
            return [];
        }
        try {
            $data = json_decode($this->rawBody, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiException(400, 'JSON_INVALIDO', 'El cuerpo de la solicitud no es JSON válido.');
        }
        if (!is_array($data) || array_is_list($data) && $data !== []) {
            throw new ApiException(400, 'JSON_INVALIDO', 'El cuerpo debe ser un objeto JSON.');
        }
        return $data;
    }

    public function param(string $name): string
    {
        return $this->params[$name] ?? '';
    }

    public function user(): AuthUser
    {
        if ($this->user === null) {
            throw ApiException::unauthorized();
        }
        return $this->user;
    }
}
