<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Integridad de las cargas de combustible.
 *
 * Tope de litros: 600 L para camiones (capacidad del estanque) y 1.500 L para equipos.
 * Al revisar los datos antes de aplicar la migración se encontraron 16 cargas de equipos
 * entre 600 y 807 L (excavadoras y cargadores con estanques más grandes), por eso el tope
 * de 600 L se aplica solo a vehículos.
 */
final class ChecksCombustible extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
            ALTER TABLE cargas_combustible
              ADD CONSTRAINT chk_cc_litros
                CHECK (litros > 0 AND litros <= 1500 AND (tipo_unidad = 'equipo' OR litros <= 600)),
              ADD CONSTRAINT chk_cc_precio
                CHECK (precio_litro IS NULL OR precio_litro > 0),
              ADD CONSTRAINT chk_cc_horometro
                CHECK (horometro IS NULL OR horometro >= 0),
              ADD CONSTRAINT chk_cc_unidad
                CHECK (
                  (tipo_unidad = 'vehiculo' AND vehiculo_id IS NOT NULL AND equipo_id IS NULL)
                  OR (tipo_unidad = 'equipo' AND equipo_id IS NOT NULL AND vehiculo_id IS NULL)
                )
            SQL);
    }

    public function down(): void
    {
        $this->execute(<<<'SQL'
            ALTER TABLE cargas_combustible
              DROP CONSTRAINT chk_cc_litros,
              DROP CONSTRAINT chk_cc_precio,
              DROP CONSTRAINT chk_cc_horometro,
              DROP CONSTRAINT chk_cc_unidad
            SQL);
    }
}
