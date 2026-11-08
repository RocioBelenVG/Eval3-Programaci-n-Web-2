<?php
declare(strict_types=1);

namespace TriTransportes\Infrastructure;

/**
 * Conexión PDO y transacciones anidables (con SAVEPOINT), para que un servicio pueda
 * abrir su transacción aunque ya esté dentro de otra (las pruebas de integración
 * envuelven cada caso en una transacción que se revierte al final).
 */
final class Database
{
    private int $nivel = 0;

    public function __construct(public readonly \PDO $pdo)
    {
    }

    public static function connect(string $host, string $port, string $name, string $user, string $pass): self
    {
        $pdo = new \PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_STRINGIFY_FETCHES => false,
            // Misma intercalación que las tablas y las vistas: evita el error 1267 (mezcla de collations)
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
        ]);
        return new self($pdo);
    }

    /**
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public function transactional(callable $fn): mixed
    {
        $externa = !$this->pdo->inTransaction();
        $punto = 'sp' . ++$this->nivel;
        $externa ? $this->pdo->beginTransaction() : $this->pdo->exec("SAVEPOINT $punto");
        try {
            $r = $fn();
            $externa ? $this->pdo->commit() : $this->pdo->exec("RELEASE SAVEPOINT $punto");
            return $r;
        } catch (\Throwable $e) {
            $externa ? $this->pdo->rollBack() : $this->pdo->exec("ROLLBACK TO SAVEPOINT $punto");
            throw $e;
        } finally {
            $this->nivel--;
        }
    }
}
