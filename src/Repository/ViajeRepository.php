<?php
declare(strict_types=1);

namespace TriTransportes\Repository;

final class ViajeRepository extends Repository
{
    private const SELECT = 'v.id, v.vehiculo_id, v.conductor_id, v.obra_id, v.fecha,
        DATE_FORMAT(v.hora_salida, \'%H:%i\') AS hora_salida, DATE_FORMAT(v.hora_llegada, \'%H:%i\') AS hora_llegada,
        v.origen, v.destino, v.km_inicio, v.km_fin, v.cantidad, v.unidad, v.estado, v.observaciones,
        v.created_at, v.updated_at,
        ve.patente, CONCAT(t.nombre, \' \', t.apellido) AS conductor_nombre, o.nombre AS obra_nombre';

    private const FROM = 'FROM viajes v
        JOIN vehiculos ve   ON ve.id = v.vehiculo_id
        JOIN conductores c  ON c.id = v.conductor_id
        JOIN trabajadores t ON t.id = c.trabajador_id
        LEFT JOIN obras o   ON o.id = v.obra_id';

    /** Orden permitido (whitelist): clave del contrato => columna SQL. */
    private const ORDEN = [
        'fecha' => 'v.fecha %s, v.id %s',
        'estado' => 'v.estado %s, v.fecha DESC',
        'vehiculo' => 've.patente %s, v.fecha DESC',
    ];

    /**
     * @param array{q?: ?string, estado?: ?string, vehiculo_id?: ?int, conductor_id?: ?int, obra_id?: ?int, desde?: ?string, hasta?: ?string} $f
     */
    public function listar(int $empresaId, array $f, string $sort, int $page, int $perPage): array
    {
        $where = ['v.empresa_id = ?'];
        $p = [$empresaId];
        foreach (['estado' => 'v.estado = ?', 'vehiculo_id' => 'v.vehiculo_id = ?', 'conductor_id' => 'v.conductor_id = ?', 'obra_id' => 'v.obra_id = ?', 'desde' => 'v.fecha >= ?', 'hasta' => 'v.fecha <= ?'] as $k => $cond) {
            if (isset($f[$k]) && $f[$k] !== null) {
                $where[] = $cond;
                $p[] = $f[$k];
            }
        }
        if (!empty($f['q'])) {
            $where[] = '(v.origen LIKE ? OR v.destino LIKE ? OR ve.patente LIKE ? OR CONCAT(t.nombre, \' \', t.apellido) LIKE ? OR o.nombre LIKE ?)';
            $like = '%' . addcslashes($f['q'], '%_\\') . '%';
            array_push($p, $like, $like, $like, $like, $like);
        }
        $desc = str_starts_with($sort, '-');
        $campo = ltrim($sort, '-');
        $dir = $desc ? 'DESC' : 'ASC';
        $orden = sprintf(self::ORDEN[$campo] ?? self::ORDEN['fecha'], $dir, $dir);

        return $this->paginate(self::SELECT, self::FROM . ' WHERE ' . implode(' AND ', $where), $p, $orden, $page, $perPage);
    }

    public function buscar(int $empresaId, int $id): ?array
    {
        return $this->one('SELECT ' . self::SELECT . ' ' . self::FROM . ' WHERE v.empresa_id = ? AND v.id = ?', [$empresaId, $id]);
    }

    public function crear(array $datos): int
    {
        return $this->insert('viajes', $datos);
    }

    public function actualizar(int $id, array $datos): void
    {
        $this->update('viajes', $id, $datos);
    }

    public function eliminar(int $id): void
    {
        $this->exec('DELETE FROM viajes WHERE id = ?', [$id]);
    }

    public function cantidadGuias(int $id): int
    {
        return (int) $this->scalar('SELECT COUNT(*) FROM guias_despacho WHERE viaje_id = ?', [$id]);
    }
}
