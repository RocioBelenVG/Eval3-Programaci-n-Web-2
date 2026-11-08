<?php
declare(strict_types=1);

namespace TriTransportes\Repository;

/** Vehículos, conductores, obras y equipos: los catálogos que usan viajes, cargas y mantenciones. */
final class FlotaRepository extends Repository
{
    public function listarVehiculos(int $empresaId, ?string $q, int $page, int $perPage): array
    {
        $where = 'FROM vehiculos v LEFT JOIN categorias_vehiculo cv ON cv.id = v.categoria_id WHERE v.empresa_id = ?';
        $p = [$empresaId];
        if ($q) {
            $where .= ' AND (v.patente LIKE ? OR v.marca LIKE ? OR v.modelo LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($p, $like, $like, $like);
        }
        return $this->paginate('v.id, v.patente, v.marca, v.modelo, v.anio, v.odometro, cv.nombre AS categoria, v.activo', $where, $p, 'v.patente ASC', $page, $perPage);
    }

    public function vehiculo(int $empresaId, int $id): ?array
    {
        return $this->one('SELECT id, patente, odometro, activo FROM vehiculos WHERE empresa_id = ? AND id = ?', [$empresaId, $id]);
    }

    /** Sube el odómetro del vehículo si el nuevo valor es mayor (nunca lo baja). */
    public function avanzarOdometro(int $id, int $km): void
    {
        $this->exec('UPDATE vehiculos SET odometro = GREATEST(COALESCE(odometro, 0), ?) WHERE id = ?', [$km, $id]);
    }

    public function conductor(int $empresaId, int $id): ?array
    {
        return $this->one(
            'SELECT c.id, c.licencia_vence, c.activo, CONCAT(t.nombre, \' \', t.apellido) AS nombre
             FROM conductores c JOIN trabajadores t ON t.id = c.trabajador_id
             WHERE t.empresa_id = ? AND c.id = ?',
            [$empresaId, $id]
        );
    }

    public function obra(int $empresaId, int $id): ?array
    {
        return $this->one('SELECT id, nombre, estado FROM obras WHERE empresa_id = ? AND id = ?', [$empresaId, $id]);
    }

    public function equipo(int $empresaId, int $id): ?array
    {
        return $this->one('SELECT id, codigo, nombre, activo FROM equipos WHERE empresa_id = ? AND id = ?', [$empresaId, $id]);
    }

    public function proveedorExiste(int $id): bool
    {
        return (bool) $this->scalar('SELECT 1 FROM proveedores_combustible WHERE id = ?', [$id]);
    }

    /** Serie mensual de km, litros, km/L y costo del vehículo (vista v_rendimiento_mensual). */
    public function rendimientoMensual(int $empresaId, int $vehiculoId, ?string $desde, ?string $hasta): array
    {
        $sql = 'SELECT mes, km, litros, km_por_litro, costo FROM v_rendimiento_mensual WHERE empresa_id = ? AND vehiculo_id = ?';
        $p = [$empresaId, $vehiculoId];
        if ($desde) {
            $sql .= ' AND mes >= ?';
            $p[] = substr($desde, 0, 7);
        }
        if ($hasta) {
            $sql .= ' AND mes <= ?';
            $p[] = substr($hasta, 0, 7);
        }
        return $this->all("$sql ORDER BY mes", $p);
    }
}
