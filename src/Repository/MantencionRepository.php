<?php
declare(strict_types=1);

namespace TriTransportes\Repository;

final class MantencionRepository extends Repository
{
    private const SELECT = 'm.id, m.equipo_id, e.codigo AS equipo_codigo, e.nombre AS equipo_nombre, m.tipo, m.fecha,
        m.descripcion, m.costo_total, m.estado, m.created_at, m.updated_at';

    private const FROM = 'FROM mantenciones m JOIN equipos e ON e.id = m.equipo_id';

    public function listar(int $empresaId, ?string $estado, int $page, int $perPage): array
    {
        $where = 'WHERE m.empresa_id = ?';
        $p = [$empresaId];
        if ($estado) {
            $where .= ' AND m.estado = ?';
            $p[] = $estado;
        }
        return $this->paginate(self::SELECT, self::FROM . " $where", $p, 'm.fecha DESC, m.id DESC', $page, $perPage);
    }

    public function buscar(int $empresaId, int $id): ?array
    {
        return $this->one('SELECT ' . self::SELECT . ' ' . self::FROM . ' WHERE m.empresa_id = ? AND m.id = ?', [$empresaId, $id]);
    }

    public function crear(array $datos): int
    {
        return $this->insert('mantenciones', $datos);
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        $this->update('mantenciones', $id, ['estado' => $estado]);
    }
}
