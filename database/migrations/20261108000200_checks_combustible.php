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
 *
 * Exclusividad vehículo/equipo: MariaDB no permite un CHECK sobre columnas que tienen una
 * clave foránea ON DELETE SET NULL (error 1901), como vehiculo_id y equipo_id. Por eso esa
 * regla se asegura con dos triggers que rechazan la fila con SQLSTATE 45000.
 */
final class ChecksCombustible extends AbstractMigration
{
    private const REGLA = <<<'SQL'
        BEGIN
          IF NOT ((NEW.tipo_unidad = 'vehiculo' AND NEW.vehiculo_id IS NOT NULL AND NEW.equipo_id IS NULL)
               OR (NEW.tipo_unidad = 'equipo' AND NEW.equipo_id IS NOT NULL AND NEW.vehiculo_id IS NULL)) THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Una carga es de un vehículo o de un equipo, según tipo_unidad';
          END IF;
        END
        SQL;

    public function up(): void
    {
        $this->execute(<<<'SQL'
            ALTER TABLE cargas_combustible
              ADD CONSTRAINT chk_cc_litros
                CHECK (litros > 0 AND litros <= 1500 AND (tipo_unidad = 'equipo' OR litros <= 600)),
              ADD CONSTRAINT chk_cc_precio
                CHECK (precio_litro IS NULL OR precio_litro > 0),
              ADD CONSTRAINT chk_cc_horometro
                CHECK (horometro IS NULL OR horometro >= 0)
            SQL);
        $this->execute('CREATE TRIGGER trg_cc_unidad_ins BEFORE INSERT ON cargas_combustible FOR EACH ROW ' . self::REGLA);
        $this->execute('CREATE TRIGGER trg_cc_unidad_upd BEFORE UPDATE ON cargas_combustible FOR EACH ROW ' . self::REGLA);
    }

    public function down(): void
    {
        $this->execute('DROP TRIGGER IF EXISTS trg_cc_unidad_ins');
        $this->execute('DROP TRIGGER IF EXISTS trg_cc_unidad_upd');
        $this->execute(<<<'SQL'
            ALTER TABLE cargas_combustible
              DROP CONSTRAINT chk_cc_litros,
              DROP CONSTRAINT chk_cc_precio,
              DROP CONSTRAINT chk_cc_horometro
            SQL);
    }
}
