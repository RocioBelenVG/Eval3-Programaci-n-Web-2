<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Vistas para reportes y dashboard:
 * - v_viajes_detalle: viaje con patente, conductor y obra (reporte operativo);
 * - v_rendimiento_mensual: km recorridos, litros, km/L y costo por vehículo y mes;
 * - v_kpi_mensual: viajes por estado, litros y costo de combustible por empresa y mes.
 */
final class VistasReportes extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
            CREATE OR REPLACE VIEW v_viajes_detalle AS
            SELECT v.id, v.empresa_id, v.fecha, v.hora_salida, v.hora_llegada, v.estado,
                   v.origen, v.destino, v.km_inicio, v.km_fin,
                   CASE WHEN v.estado = 'completado' AND v.km_fin >= v.km_inicio
                        THEN v.km_fin - v.km_inicio END AS km_recorridos,
                   v.cantidad, v.unidad,
                   v.vehiculo_id, ve.patente,
                   v.conductor_id, CONCAT(t.nombre, ' ', t.apellido) AS conductor,
                   v.obra_id, o.nombre AS obra
            FROM viajes v
            JOIN vehiculos ve   ON ve.id = v.vehiculo_id
            JOIN conductores c  ON c.id = v.conductor_id
            JOIN trabajadores t ON t.id = c.trabajador_id
            LEFT JOIN obras o   ON o.id = v.obra_id
            SQL);

        $this->execute(<<<'SQL'
            CREATE OR REPLACE VIEW v_rendimiento_mensual AS
            SELECT k.empresa_id, k.vehiculo_id, k.patente, k.mes, k.km,
                   COALESCE(l.litros, 0) AS litros,
                   CASE WHEN l.litros > 0 THEN ROUND(k.km / l.litros, 2) END AS km_por_litro,
                   COALESCE(l.costo, 0) AS costo
            FROM (
                SELECT v.empresa_id, v.vehiculo_id, ve.patente, DATE_FORMAT(v.fecha, '%Y-%m') AS mes,
                       SUM(v.km_fin - v.km_inicio) AS km
                FROM viajes v
                JOIN vehiculos ve ON ve.id = v.vehiculo_id
                WHERE v.estado = 'completado' AND v.km_fin >= v.km_inicio
                GROUP BY v.empresa_id, v.vehiculo_id, ve.patente, DATE_FORMAT(v.fecha, '%Y-%m')
            ) k
            LEFT JOIN (
                SELECT vehiculo_id, DATE_FORMAT(fecha, '%Y-%m') AS mes,
                       SUM(litros) AS litros, SUM(litros * COALESCE(precio_litro, 0)) AS costo
                FROM cargas_combustible
                WHERE tipo_unidad = 'vehiculo'
                GROUP BY vehiculo_id, DATE_FORMAT(fecha, '%Y-%m')
            ) l ON l.vehiculo_id = k.vehiculo_id AND l.mes = k.mes
            SQL);

        $this->execute(<<<'SQL'
            CREATE OR REPLACE VIEW v_kpi_mensual AS
            SELECT a.empresa_id, a.mes, a.viajes, a.completados, a.cancelados, a.programados_vencidos,
                   COALESCE(c.litros, 0) AS litros, COALESCE(c.costo, 0) AS costo_combustible
            FROM (
                SELECT empresa_id, DATE_FORMAT(fecha, '%Y-%m') AS mes,
                       COUNT(*) AS viajes,
                       SUM(estado = 'completado') AS completados,
                       SUM(estado = 'cancelado') AS cancelados,
                       SUM(estado IN ('programado', 'en_ruta') AND fecha < CURDATE()) AS programados_vencidos
                FROM viajes
                GROUP BY empresa_id, DATE_FORMAT(fecha, '%Y-%m')
            ) a
            LEFT JOIN (
                SELECT empresa_id, DATE_FORMAT(fecha, '%Y-%m') AS mes,
                       SUM(litros) AS litros, SUM(litros * COALESCE(precio_litro, 0)) AS costo
                FROM cargas_combustible
                GROUP BY empresa_id, DATE_FORMAT(fecha, '%Y-%m')
            ) c ON c.empresa_id = a.empresa_id AND c.mes = a.mes
            SQL);
    }

    public function down(): void
    {
        $this->execute('DROP VIEW IF EXISTS v_kpi_mensual');
        $this->execute('DROP VIEW IF EXISTS v_rendimiento_mensual');
        $this->execute('DROP VIEW IF EXISTS v_viajes_detalle');
    }
}
