<?php
declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Crea (o actualiza) una cuenta de demostración por cada rol de la API en TRITRANSPORTES SpA:
 * Supervisor, Conductor y Gerencia (el Administrador ya existe: admin@tritransportes.cl).
 *
 * La contraseña NO está en el repositorio: se lee de DEMO_PASSWORD en el .env del servidor.
 * Es idempotente: se puede ejecutar varias veces sin duplicar cuentas.
 *
 * Los datos operacionales de prueba (12 meses de viajes, cargas y mantenciones) los genera
 * database/seed_masivo.php; este seeder solo agrega las cuentas para probar los roles.
 */
final class UsuariosDemoSeeder extends AbstractSeed
{
    private const EMPRESA = 1;

    public function run(): void
    {
        $password = getenv('DEMO_PASSWORD') ?: '';
        if (strlen($password) < 12) {
            throw new \RuntimeException('Defina DEMO_PASSWORD (12 caracteres o más) en el .env antes de ejecutar este seeder.');
        }
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $cuentas = [
            // email, nombre, rol, trabajador (el conductor queda enlazado a su ficha de conductor)
            ['supervisor@tritransportes.cl', 'Carlos Contreras', 'Supervisor', $this->trabajador('Carlos', 'Contreras')],
            ['conductor@tritransportes.cl',  'Manuel Tapia',     'Conductor',  $this->trabajador('Manuel', 'Tapia')],
            ['gerencia@tritransportes.cl',   'Ricardo Molina',   'Gerencia',   $this->trabajador('Ricardo', 'Molina')],
        ];

        $pdo = $this->getAdapter()->getConnection();
        foreach ($cuentas as [$email, $nombre, $rol, $trabajadorId]) {
            $q = $pdo->prepare('SELECT id FROM roles WHERE nombre = ?');
            $q->execute([$rol]);
            $rolId = $q->fetchColumn();
            if ($rolId === false) {
                throw new \RuntimeException("No existe el rol $rol.");
            }
            $st = $pdo->prepare(
                'INSERT INTO usuarios (empresa_id, trabajador_id, email, nombre, password_hash, rol_id, activo)
                 VALUES (?, ?, ?, ?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE trabajador_id = VALUES(trabajador_id), nombre = VALUES(nombre),
                     password_hash = VALUES(password_hash), rol_id = VALUES(rol_id), activo = 1'
            );
            $st->execute([self::EMPRESA, $trabajadorId, $email, $nombre, $hash, $rolId]);
            $this->output->writeln("  $rol: $email");
        }
    }

    private function trabajador(string $nombre, string $apellido): ?int
    {
        $pdo = $this->getAdapter()->getConnection();
        $st = $pdo->prepare('SELECT id FROM trabajadores WHERE empresa_id = ? AND nombre = ? AND apellido = ? LIMIT 1');
        $st->execute([self::EMPRESA, $nombre, $apellido]);
        $id = $st->fetchColumn();
        return $id === false ? null : (int) $id;
    }
}
