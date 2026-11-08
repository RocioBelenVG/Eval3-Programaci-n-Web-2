<?php
declare(strict_types=1);

namespace TriTransportes\Repository;

final class CargaRepository extends Repository
{
    private const SELECT = 'cc.id, cc.tipo_unidad, cc.vehiculo_id, cc.equipo_id, cc.fecha, cc.litros, cc.precio_litro,
        cc.odometro, cc.horometro, cc.proveedor_id, ROUND(cc.litros * COALESCE(cc.precio_litro, 0)) AS costo_total,
        ve.patente, e.codigo AS equipo_codigo';

    private const FROM = 'FROM cargas_combustible cc
        LEFT JOIN vehiculos ve ON ve.id = cc.vehiculo_id
        LEFT JOIN equipos e ON e.id = cc.equipo_id';

    public function listar(int $empresaId, array $f, int $page, int $perPage): array
    {
        $where = ['cc.empresa_id = ?'];
        $p = [$empresaId];
        foreach (['vehiculo_id' => 'cc.vehiculo_id = ?', 'conductor_id' => 'cc.conductor_id = ?', 'desde' => 'cc.fecha >= ?', 'hasta' => 'cc.fecha <= ?'] as $k => $cond) {
            if (isset($f[$k])) {
                $where[] = $cond;
                $p[] = $f[$k];
            }
        }
        return $this->paginate(self::SELECT, self::FROM . ' WHERE ' . implode(' AND ', $where), $p, 'cc.fecha DESC, cc.id DESC', $page, $perPage);
    }

    public function buscar(int $empresaId, int $id): ?array
    {
        return $this->one('SELECT ' . self::SELECT . ' ' . self::FROM . ' WHERE cc.empresa_id = ? AND cc.id = ?', [$empresaId, $id]);
    }

    public function crear(array $datos): int
    {
        return $this->insert('cargas_combustible', $datos);
    }

    /** Último odómetro registrado en una carga del vehículo (para impedir que retroceda). */
    public function ultimoOdometro(int $vehiculoId): ?int
    {
        $v = $this->scalar('SELECT MAX(odometro) FROM cargas_combustible WHERE vehiculo_id = ?', [$vehiculoId]);
        return $v === null || $v === false ? null : (int) $v;
    }
}
