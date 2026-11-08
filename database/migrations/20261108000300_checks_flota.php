<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Integridad de la flota: año y formato de patente chilena de los vehículos
 * (antigua AA1234 o nueva BBBB12) y costo no negativo de las mantenciones.
 */
final class ChecksFlota extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
            ALTER TABLE vehiculos
              ADD CONSTRAINT chk_veh_anio
                CHECK (anio IS NULL OR anio BETWEEN 1980 AND 2100),
              ADD CONSTRAINT chk_veh_patente
                CHECK (patente REGEXP '^([A-Z]{4}[0-9]{2}|[A-Z]{2}[0-9]{4})$')
            SQL);
        $this->execute(<<<'SQL'
            ALTER TABLE mantenciones
              ADD CONSTRAINT chk_mant_costo
                CHECK (costo_total IS NULL OR costo_total >= 0)
            SQL);
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE vehiculos DROP CONSTRAINT chk_veh_anio, DROP CONSTRAINT chk_veh_patente');
        $this->execute('ALTER TABLE mantenciones DROP CONSTRAINT chk_mant_costo');
    }
}
