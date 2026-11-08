<?php
declare(strict_types=1);

namespace TriTransportes\Repository;

use TriTransportes\Infrastructure\Database;

/**
 * Base de los repositorios: toda consulta usa sentencias preparadas y toda consulta
 * de negocio filtra por empresa_id (aislamiento entre empresas).
 */
abstract class Repository
{
    protected \PDO $pdo;

    public function __construct(protected readonly Database $db)
    {
        $this->pdo = $db->pdo;
    }

    /** @return list<array<string, mixed>> */
    protected function all(string $sql, array $params = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** @return array<string, mixed>|null */
    protected function one(string $sql, array $params = []): ?array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        $r = $st->fetch();
        return $r === false ? null : $r;
    }

    protected function scalar(string $sql, array $params = []): mixed
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->fetchColumn();
    }

    protected function exec(string $sql, array $params = []): int
    {
        $st = $this->pdo->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    /** INSERT con columnas whitelisted por el llamador (las claves vienen del código, no del cliente). */
    protected function insert(string $tabla, array $datos): int
    {
        $cols = array_keys($datos);
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $tabla, implode(', ', $cols), implode(', ', array_fill(0, count($cols), '?')));
        $this->exec($sql, array_values($datos));
        return (int) $this->pdo->lastInsertId();
    }

    protected function update(string $tabla, int $id, array $datos): void
    {
        if ($datos === []) {
            return;
        }
        $set = implode(', ', array_map(fn ($c) => "$c = ?", array_keys($datos)));
        $this->exec("UPDATE $tabla SET $set WHERE id = ?", [...array_values($datos), $id]);
    }

    /**
     * Ejecuta una consulta paginada. $sqlBase empieza en "FROM ..." e incluye el WHERE.
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int, total_pages: int}}
     */
    protected function paginate(string $select, string $sqlBase, array $params, string $orderBy, int $page, int $perPage): array
    {
        $total = (int) $this->scalar("SELECT COUNT(*) $sqlBase", $params);
        $offset = ($page - 1) * $perPage;
        $data = $this->all("SELECT $select $sqlBase ORDER BY $orderBy LIMIT $perPage OFFSET $offset", $params);
        return [
            'data' => $data,
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int) ceil($total / $perPage)],
        ];
    }
}
