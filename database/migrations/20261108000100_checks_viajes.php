<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Validación en el tercer nivel (base de datos) para los viajes.
 * Aunque la API ya valida, estas reglas impiden datos incoherentes
 * aunque alguien escriba directo en la base.
 */
final class ChecksViajes extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
            ALTER TABLE viajes
              ADD CONSTRAINT chk_viajes_km
                CHECK (km_fin IS NULL OR km_inicio IS NULL OR km_fin = 0 OR km_fin >= km_inicio),
              ADD CONSTRAINT chk_viajes_horas
                CHECK (hora_llegada IS NULL OR hora_salida IS NULL OR hora_llegada >= hora_salida),
              ADD CONSTRAINT chk_viajes_cantidad
                CHECK (cantidad IS NULL OR cantidad >= 0)
            SQL);
    }

    public function down(): void
    {
        $this->execute(<<<'SQL'
            ALTER TABLE viajes
              DROP CONSTRAINT chk_viajes_km,
              DROP CONSTRAINT chk_viajes_horas,
              DROP CONSTRAINT chk_viajes_cantidad
            SQL);
    }
}
