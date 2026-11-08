<?php
declare(strict_types=1);

namespace TriTransportes;

use TriTransportes\Controller\AuthController;
use TriTransportes\Controller\FlotaController;
use TriTransportes\Controller\HealthController;
use TriTransportes\Controller\IaController;
use TriTransportes\Controller\ViajeController;
use TriTransportes\Ia\GroqClient;
use TriTransportes\Ia\IaClient;
use TriTransportes\Repository\IndicadorRepository;
use TriTransportes\Service\IaService;
use TriTransportes\Infrastructure\Database;
use TriTransportes\Repository\AuditoriaRepository;
use TriTransportes\Repository\CargaRepository;
use TriTransportes\Repository\FlotaRepository;
use TriTransportes\Repository\GuiaRepository;
use TriTransportes\Repository\MantencionRepository;
use TriTransportes\Repository\UsuarioRepository;
use TriTransportes\Repository\ViajeRepository;
use TriTransportes\Security\Jwt;
use TriTransportes\Security\RateLimiter;
use TriTransportes\Service\AuthService;
use TriTransportes\Service\CargaService;
use TriTransportes\Service\MantencionService;
use TriTransportes\Service\ViajeService;

/** Composición de la aplicación: registra cada pieza en el contenedor (raíz de composición). */
final class App
{
    public static function container(?Database $db = null): Container
    {
        $c = new Container();

        $c->set(Database::class, fn () => $db ?? Database::connect(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS));
        $c->set(Jwt::class, fn () => new Jwt(JWT_SECRET, JWT_TTL));
        $c->set(RateLimiter::class, fn (Container $c) => new RateLimiter($c->get(Database::class)->pdo));

        foreach ([UsuarioRepository::class, ViajeRepository::class, FlotaRepository::class, CargaRepository::class,
                  GuiaRepository::class, MantencionRepository::class, AuditoriaRepository::class, IndicadorRepository::class] as $repo) {
            $c->set($repo, fn (Container $c) => new $repo($c->get(Database::class)));
        }

        $c->set(AuthService::class, fn (Container $c) => new AuthService(
            $c->get(UsuarioRepository::class), $c->get(Jwt::class), $c->get(RateLimiter::class)));
        $c->set(ViajeService::class, fn (Container $c) => new ViajeService(
            $c->get(Database::class), $c->get(ViajeRepository::class), $c->get(FlotaRepository::class),
            $c->get(GuiaRepository::class), $c->get(AuditoriaRepository::class)));
        $c->set(CargaService::class, fn (Container $c) => new CargaService(
            $c->get(Database::class), $c->get(CargaRepository::class), $c->get(FlotaRepository::class), $c->get(AuditoriaRepository::class)));
        $c->set(MantencionService::class, fn (Container $c) => new MantencionService(
            $c->get(Database::class), $c->get(MantencionRepository::class), $c->get(FlotaRepository::class), $c->get(AuditoriaRepository::class)));

        $c->set(IaClient::class, fn () => new GroqClient(GROQ_API_KEY, AI_TIMEOUT));
        $c->set(IaService::class, fn (Container $c) => new IaService(
            $c->get(IaClient::class), $c->get(IndicadorRepository::class), $c->get(RateLimiter::class), $c->get(AuditoriaRepository::class)));

        $c->set(AuthController::class, fn (Container $c) => new AuthController($c->get(AuthService::class)));
        $c->set(IaController::class, fn (Container $c) => new IaController($c->get(IaService::class)));
        $c->set(HealthController::class, fn (Container $c) => new HealthController($c->get(Database::class)));
        $c->set(ViajeController::class, fn (Container $c) => new ViajeController($c->get(ViajeService::class)));
        $c->set(FlotaController::class, fn (Container $c) => new FlotaController(
            $c->get(FlotaRepository::class), $c->get(CargaService::class), $c->get(MantencionService::class)));

        return $c;
    }
}
