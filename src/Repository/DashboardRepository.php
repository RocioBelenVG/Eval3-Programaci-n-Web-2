<?php
declare(strict_types=1);

namespace TriTransportes\Repository;

/**
 * Consultas de agregación del dashboard y de los reportes gerenciales.
 * Los filtros opcionales (obra, vehículo) se aplican a viajes y cargas; los indicadores
 * de equipos (disponibilidad y MTBF) no dependen de esos filtros.
 */
final class DashboardRepository extends Repository
{
    /** Partes comunes de WHERE para viajes y cargas con los filtros del dashboard. */
    private function filtros(string $alias, array $f): array
    {
        $w = [];
        $p = [];
        if (!empty($f['obra_id'])) {
            $w[] = "$alias.obra_id = ?";
            $p[] = $f['obra_id'];
        }
        if (!empty($f['vehiculo_id'])) {
            $w[] = "$alias.vehiculo_id = ?";
            $p[] = $f['vehiculo_id'];
        }
        return [$w ? ' AND ' . implode(' AND ', $w) : '', $p];
    }

    /** @return array<string, float|int> Componentes de los 6 indicadores en el período. */
    public function componentes(int $empresaId, string $desde, string $hasta, array $f = []): array
    {
        $hastaReal = min($hasta, date('Y-m-d')); // los viajes futuros aún no pueden cumplirse
        [$wv, $pv] = $this->filtros('v', $f);
        $v = $this->one(
            "SELECT COUNT(*) AS viajes,
                    COALESCE(SUM(v.estado = 'completado'), 0) AS completados,
                    COALESCE(SUM(v.estado = 'cancelado'), 0) AS cancelados,
                    COALESCE(SUM(CASE WHEN v.estado = 'completado' AND v.km_fin >= v.km_inicio THEN v.km_fin - v.km_inicio END), 0) AS km
             FROM viajes v WHERE v.empresa_id = ? AND v.fecha BETWEEN ? AND ? $wv",
            [$empresaId, $desde, $hastaReal, ...$pv]
        );
        [$wc, $pc] = $this->filtros('c', $f);
        $c = $this->one(
            "SELECT COALESCE(SUM(c.litros), 0) AS litros, COALESCE(SUM(c.litros * COALESCE(c.precio_litro, 0)), 0) AS costo
             FROM cargas_combustible c WHERE c.empresa_id = ? AND c.tipo_unidad = 'vehiculo' AND c.fecha BETWEEN ? AND ? $wc",
            [$empresaId, $desde, $hastaReal, ...$pc]
        );
        $equipos = (int) $this->scalar('SELECT COUNT(*) FROM equipos WHERE empresa_id = ? AND activo = 1', [$empresaId]);
        $dias = (int) ((strtotime($hastaReal) - strtotime($desde)) / 86400) + 1;
        $ini = "$desde 00:00:00";
        $fin = "$hastaReal 23:59:59";
        $det = $this->one(
            "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, GREATEST(fecha_inicio, ?), LEAST(COALESCE(fecha_fin, NOW()), ?, NOW()))), 0) / 60 AS horas,
                    COALESCE(SUM(tipo = 'falla'), 0) AS fallas
             FROM detenciones_equipo
             WHERE empresa_id = ? AND fecha_inicio <= ? AND COALESCE(fecha_fin, NOW()) >= ?",
            [$ini, $fin, $empresaId, $fin, $ini]
        );
        $operacion = (float) $this->scalar(
            "SELECT COALESCE(SUM(d), 0) FROM (
                SELECT MAX(horometro) - MIN(horometro) AS d FROM cargas_combustible
                WHERE empresa_id = ? AND tipo_unidad = 'equipo' AND horometro IS NOT NULL AND fecha BETWEEN ? AND ?
                GROUP BY equipo_id) x",
            [$empresaId, $desde, $hastaReal]
        );
        return [
            'viajes' => (int) $v['viajes'], 'completados' => (int) $v['completados'], 'cancelados' => (int) $v['cancelados'],
            'km' => (int) $v['km'], 'litros' => (float) $c['litros'], 'costo' => (float) $c['costo'],
            'horas_calendario' => max(0, $equipos * $dias * 24), 'horas_detenidas' => (float) $det['horas'],
            'fallas' => (int) $det['fallas'], 'horas_operacion' => $operacion,
        ];
    }

    /** Viajes por mes y estado. */
    public function viajesPorMes(int $empresaId, string $desde, string $hasta, array $f = []): array
    {
        [$w, $p] = $this->filtros('v', $f);
        return $this->all(
            "SELECT DATE_FORMAT(v.fecha, '%Y-%m') AS mes, COUNT(*) AS viajes,
                    SUM(v.estado = 'completado') AS completados, SUM(v.estado = 'cancelado') AS cancelados,
                    SUM(v.estado IN ('programado', 'en_ruta')) AS abiertos,
                    COALESCE(SUM(CASE WHEN v.estado = 'completado' AND v.km_fin >= v.km_inicio THEN v.km_fin - v.km_inicio END), 0) AS km
             FROM viajes v WHERE v.empresa_id = ? AND v.fecha BETWEEN ? AND ? $w
             GROUP BY mes ORDER BY mes",
            [$empresaId, $desde, $hasta, ...$p]
        );
    }

    /** Combustible de vehículos por mes. */
    public function combustiblePorMes(int $empresaId, string $desde, string $hasta, array $f = []): array
    {
        [$w, $p] = $this->filtros('c', $f);
        return $this->all(
            "SELECT DATE_FORMAT(c.fecha, '%Y-%m') AS mes, SUM(c.litros) AS litros, SUM(c.litros * COALESCE(c.precio_litro, 0)) AS costo
             FROM cargas_combustible c WHERE c.empresa_id = ? AND c.tipo_unidad = 'vehiculo' AND c.fecha BETWEEN ? AND ? $w
             GROUP BY mes ORDER BY mes",
            [$empresaId, $desde, $hasta, ...$p]
        );
    }

    /**
     * Totales por vehículo en el período: viajes completados, km, cargas, litros, costo y km/L.
     * $soloCamiones excluye camionetas y furgones (otra clase de consumo).
     */
    public function porVehiculo(int $empresaId, string $desde, string $hasta, bool $soloCamiones = false, array $f = []): array
    {
        $cam = $soloCamiones ? "AND cv.tipo = 'transporte'" : '';
        $obra = !empty($f['obra_id']) ? 'AND v2.obra_id = ' . (int) $f['obra_id'] : '';
        $veh = !empty($f['vehiculo_id']) ? 'AND ve.id = ' . (int) $f['vehiculo_id'] : '';
        return $this->all(
            "SELECT ve.id, ve.patente, cv.nombre AS categoria,
                    COALESCE(k.viajes, 0) AS viajes, COALESCE(k.km, 0) AS km,
                    COALESCE(l.cargas, 0) AS cargas, COALESCE(l.litros, 0) AS litros, COALESCE(l.costo, 0) AS costo
             FROM vehiculos ve
             LEFT JOIN categorias_vehiculo cv ON cv.id = ve.categoria_id
             LEFT JOIN (SELECT v2.vehiculo_id, COUNT(*) AS viajes, SUM(v2.km_fin - v2.km_inicio) AS km
                        FROM viajes v2 WHERE v2.empresa_id = ? AND v2.estado = 'completado' AND v2.km_fin >= v2.km_inicio
                          AND v2.fecha BETWEEN ? AND ? $obra GROUP BY v2.vehiculo_id) k ON k.vehiculo_id = ve.id
             LEFT JOIN (SELECT vehiculo_id, COUNT(*) AS cargas, SUM(litros) AS litros, SUM(litros * COALESCE(precio_litro, 0)) AS costo
                        FROM cargas_combustible WHERE empresa_id = ? AND tipo_unidad = 'vehiculo' AND fecha BETWEEN ? AND ?
                        GROUP BY vehiculo_id) l ON l.vehiculo_id = ve.id
             WHERE ve.empresa_id = ? $cam $veh AND (k.viajes IS NOT NULL OR l.cargas IS NOT NULL)
             ORDER BY ve.patente",
            [$empresaId, $desde, $hasta, $empresaId, $desde, $hasta, $empresaId]
        );
    }

    /** Viajes por estado en el período. */
    public function viajesPorEstado(int $empresaId, string $desde, string $hasta, array $f = []): array
    {
        [$w, $p] = $this->filtros('v', $f);
        return $this->all(
            "SELECT v.estado, COUNT(*) AS n FROM viajes v WHERE v.empresa_id = ? AND v.fecha BETWEEN ? AND ? $w GROUP BY v.estado",
            [$empresaId, $desde, $hasta, ...$p]
        );
    }

    /** Detalle operativo de viajes (vista v_viajes_detalle) con filtros, paginado o completo. */
    public function detalleViajes(int $empresaId, array $f, ?int $page, ?int $perPage): array
    {
        $where = ['empresa_id = ?', 'fecha BETWEEN ? AND ?'];
        $p = [$empresaId, $f['desde'], $f['hasta']];
        foreach (['estado' => 'estado = ?', 'vehiculo_id' => 'vehiculo_id = ?', 'conductor_id' => 'conductor_id = ?', 'obra_id' => 'obra_id = ?'] as $k => $cond) {
            if (!empty($f[$k])) {
                $where[] = $cond;
                $p[] = $f[$k];
            }
        }
        $from = 'FROM v_viajes_detalle WHERE ' . implode(' AND ', $where);
        $totales = $this->one("SELECT COUNT(*) AS viajes, COALESCE(SUM(km_recorridos), 0) AS km, COALESCE(SUM(cantidad), 0) AS cantidad $from", $p);
        $select = 'id, fecha, patente, conductor, origen, destino, obra, cantidad, unidad, km_recorridos, estado';
        if ($page === null) {
            return ['data' => $this->all("SELECT $select $from ORDER BY fecha, id", $p), 'totales' => $totales];
        }
        $r = $this->paginate($select, $from, $p, 'fecha DESC, id DESC', $page, $perPage);
        return $r + ['totales' => $totales];
    }
}
