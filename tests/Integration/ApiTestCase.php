<?php
declare(strict_types=1);

namespace TriTransportes\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TriTransportes\App;
use TriTransportes\Http\Request;
use TriTransportes\Http\Response;
use TriTransportes\Infrastructure\Database;
use TriTransportes\Kernel;

/**
 * Pruebas de integración: recorren la API completa (router, JWT, roles, validación,
 * servicios, repositorios y MariaDB) sin pasar por HTTP.
 *
 * Cada prueba corre dentro de una transacción que se revierte en tearDown(), así que
 * no deja rastros en la base. Los usuarios de prueba se crean dentro de esa transacción
 * con una contraseña generada al azar en cada ejecución.
 */
abstract class ApiTestCase extends TestCase
{
    protected const EMPRESA = 1;

    protected static ?Database $db = null;
    protected Kernel $kernel;
    protected \TriTransportes\Container $container;
    protected string $password;
    /** @var array<string, array{id: int, email: string, conductor_id: ?int}> */
    protected array $usuarios = [];
    protected int $vehiculoId;
    protected int $obraId;

    protected function setUp(): void
    {
        if (DB_NAME === '') {
            $this->markTestSkipped('Sin base de datos configurada (.env).');
        }
        self::$db ??= Database::connect(DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS);
        self::$db->pdo->beginTransaction();
        $this->container = App::container(self::$db);
        $this->kernel = new Kernel($this->container);
        $this->password = bin2hex(random_bytes(8));
        $this->crearFixtures();
    }

    protected function tearDown(): void
    {
        if (self::$db?->pdo->inTransaction()) {
            self::$db->pdo->rollBack();
        }
    }

    private function crearFixtures(): void
    {
        $pdo = self::$db->pdo;
        $hash = password_hash($this->password, PASSWORD_BCRYPT, ['cost' => 4]);
        $sufijo = bin2hex(random_bytes(3));

        // Conductor con licencia vigente
        $pdo->prepare("INSERT INTO trabajadores (empresa_id, rut, nombre, apellido, activo) VALUES (?, ?, 'Prueba', 'Conductor', 1)")
            ->execute([self::EMPRESA, "9$sufijo-0"]);
        $trabajadorId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO conductores (trabajador_id, licencia_tipo, licencia_vence, activo) VALUES (?, ?, ?, 1)')
            ->execute([$trabajadorId, 'A4', date('Y-m-d', strtotime('+1 year'))]);
        $conductorId = (int) $pdo->lastInsertId();

        $roles = ['Administrador' => 'Administrador General', 'Supervisor' => 'Supervisor', 'Conductor' => 'Conductor', 'Gerencia' => 'Gerencia', 'Mecanico' => 'Mecánico'];
        foreach ($roles as $clave => $rolBd) {
            $rolId = (int) $pdo->query('SELECT id FROM roles WHERE nombre = ' . $pdo->quote($rolBd))->fetchColumn();
            $email = strtolower("test.$clave.$sufijo@tritransportes.test");
            $pdo->prepare('INSERT INTO usuarios (empresa_id, trabajador_id, email, nombre, password_hash, rol_id, activo) VALUES (?, ?, ?, ?, ?, ?, 1)')
                ->execute([self::EMPRESA, $clave === 'Conductor' ? $trabajadorId : null, $email, "Prueba $clave", $hash, $rolId]);
            $this->usuarios[$clave] = ['id' => (int) $pdo->lastInsertId(), 'email' => $email, 'conductor_id' => $clave === 'Conductor' ? $conductorId : null];
        }

        $this->vehiculoId = (int) $pdo->query('SELECT id FROM vehiculos WHERE empresa_id = 1 AND activo = 1 ORDER BY id LIMIT 1')->fetchColumn();
        $this->obraId = (int) $pdo->query('SELECT id FROM obras WHERE empresa_id = 1 ORDER BY id LIMIT 1')->fetchColumn();
    }

    protected function request(string $method, string $path, array $body = [], array $query = [], ?string $token = null): Response
    {
        return $this->kernel->handle(Request::create($method, $path, $body, $query, $token));
    }

    protected function token(string $rol): string
    {
        $r = $this->request('POST', '/auth/login', ['email' => $this->usuarios[$rol]['email'], 'password' => $this->password]);
        $this->assertSame(200, $r->status, $r->body);
        return $r->data()['token'];
    }

    /** Programa un viaje como Supervisor para el conductor de prueba y devuelve su id. */
    protected function programarViaje(array $extra = []): int
    {
        $r = $this->request('POST', '/viajes', $extra + [
            'vehiculo_id' => $this->vehiculoId,
            'conductor_id' => $this->usuarios['Conductor']['conductor_id'],
            'obra_id' => $this->obraId,
            'fecha' => date('Y-m-d'),
            'origen' => 'Planta Lo Espejo',
            'destino' => 'Obra Ruta 68',
            'km_inicio' => 100000,
            'cantidad' => 14,
            'unidad' => 'm3',
        ], [], $this->token('Supervisor'));
        $this->assertSame(201, $r->status, $r->body);
        return $r->data()['id'];
    }

    protected function assertError(Response $r, int $status, string $code): void
    {
        $this->assertSame($status, $r->status, $r->body);
        $this->assertSame($code, $r->data()['error']['code'] ?? null, $r->body);
        $this->assertNotEmpty($r->data()['error']['request_id'] ?? null);
    }
}
