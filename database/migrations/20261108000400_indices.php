<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Índices para los filtros más usados de la API (RNF01):
 * - listado de viajes por empresa, estado y fecha;
 * - rendimiento por vehículo y fecha;
 * - guías: número único por empresa (dos guías no pueden compartir número).
 */
final class Indices extends AbstractMigration
{
    public function change(): void
    {
        $this->table('viajes')
            ->addIndex(['empresa_id', 'estado', 'fecha'], ['name' => 'idx_viajes_emp_estado_fecha'])
            ->update();

        $this->table('cargas_combustible')
            ->addIndex(['vehiculo_id', 'fecha'], ['name' => 'idx_cc_veh_fecha'])
            ->update();

        $this->table('guias_despacho')
            ->addIndex(['empresa_id', 'numero_guia'], ['name' => 'uq_guia_emp_numero', 'unique' => true])
            ->update();
    }
}
