<?php
declare(strict_types=1);

namespace TriTransportes\Repository;

/**
 * Agregados del período para el resumen con IA (y, en el Hito 4, para el dashboard).
 * Solo devuelve totales y promedios: ningún dato personal sale hacia el servicio de IA.
 */
final class IndicadorRepository extends Repository
{
    /** @return array<string, mixed> */
    public function resumenPeriodo(int $empresaId, string $desde, string $hasta): array
    {
        $v = $this->one(
            "SELECT COUNT(*) AS viajes,
                    COALESCE(SUM(estado = 'completado'), 0) AS completados,
                    COALESCE(SUM(estado = 'cancelado'), 0) AS cancelados,
                    COALESCE(SUM(estado IN ('programado', 'en_ruta') AND fecha < CURDATE()), 0) AS abiertos_vencidos,
                    COALESCE(SUM(CASE WHEN estado = 'completado' AND km_fin >= km_inicio THEN km_fin - km_inicio END), 0) AS km
             FROM viajes WHERE empresa_id = ? AND fecha BETWEEN ? AND ?",
            [$empresaId, $desde, $hasta]
        );
        $c = $this->one(
            "SELECT COALESCE(SUM(litros), 0) AS litros,
                    COALESCE(SUM(litros * COALESCE(precio_litro, 0)), 0) AS costo,
                    COALESCE(SUM(CASE WHEN tipo_unidad = 'vehiculo' THEN litros END), 0) AS litros_camiones,
                    COALESCE(SUM(CASE WHEN tipo_unidad = 'vehiculo' THEN litros * COALESCE(precio_litro, 0) END), 0) AS costo_camiones
             FROM cargas_combustible WHERE empresa_id = ? AND fecha BETWEEN ? AND ?",
            [$empresaId, $desde, $hasta]
        );
        $m = $this->one(
            "SELECT COUNT(*) AS mantenciones, COALESCE(SUM(tipo = 'correctiva'), 0) AS correctivas,
                    COALESCE(SUM(costo_total), 0) AS costo
             FROM mantenciones WHERE empresa_id = ? AND fecha BETWEEN ? AND ?",
            [$empresaId, $desde, $hasta]
        );
        $camiones = $this->all(
            'SELECT patente, SUM(km) AS km, SUM(litros) AS litros, ROUND(SUM(km) / NULLIF(SUM(litros), 0), 2) AS km_por_litro
             FROM v_rendimiento_mensual
             WHERE empresa_id = ? AND mes BETWEEN ? AND ?
             GROUP BY patente HAVING SUM(litros) > 0 ORDER BY km_por_litro DESC',
            [$empresaId, substr($desde, 0, 7), substr($hasta, 0, 7)]
        );

        $viajes = (int) $v['viajes'];
        $completados = (int) $v['completados'];
        return [
            'periodo' => ['desde' => $desde, 'hasta' => $hasta],
            'viajes' => [
                'total' => $viajes,
                'completados' => $completados,
                'cancelados' => (int) $v['cancelados'],
                'abiertos_con_fecha_pasada' => (int) $v['abiertos_vencidos'],
                'cumplimiento_pct' => $viajes ? round(100 * $completados / $viajes, 1) : null,
                'cancelacion_pct' => $viajes ? round(100 * (int) $v['cancelados'] / $viajes, 1) : null,
                'km_recorridos' => (int) $v['km'],
            ],
            'combustible' => [
                'litros' => round((float) $c['litros'], 1),
                'costo_clp' => (int) round((float) $c['costo']),
                'costo_por_viaje_clp' => $completados ? (int) round((float) $c['costo_camiones'] / $completados) : null,
                'rendimiento_flota_km_l' => (float) $c['litros_camiones'] > 0 ? round((int) $v['km'] / (float) $c['litros_camiones'], 2) : null,
            ],
            'mantenciones' => [
                'total' => (int) $m['mantenciones'],
                'correctivas' => (int) $m['correctivas'],
                'costo_clp' => (int) round((float) $m['costo']),
            ],
            'camiones_mejor_rendimiento' => array_slice($camiones, 0, 3),
            'camiones_peor_rendimiento' => array_reverse(array_slice($camiones, -3)),
        ];
    }
}
