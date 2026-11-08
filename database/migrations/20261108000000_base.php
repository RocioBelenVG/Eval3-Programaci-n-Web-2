<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Migración base: el esquema heredado (schema.sql + módulos m01..m07).
 *
 * En una base vacía (por ejemplo, el contenedor Docker o una instalación nueva) crea todas
 * las tablas. En una base que ya tiene el esquema (el servidor de demostración) no hace
 * nada y solo queda registrada en `phinxlog`, para que las migraciones siguientes partan
 * del mismo punto en cualquier ambiente.
 */
final class Base extends AbstractMigration
{
    // INSTALL.sql = schema.sql + módulos m01..m06 + catálogos y empresas de demostración;
    // m07 agrega firma y GPS. Los 12 meses de operación los genera después seed_masivo.php.
    private const ARCHIVOS = [
        'INSTALL.sql',
        'm07_firma_gps.sql',
    ];

    public function up(): void
    {
        if ($this->hasTable('empresas')) {
            $this->output->writeln('  El esquema base ya existe: solo se registra la migración.');
            return;
        }
        $dir = dirname(__DIR__);
        foreach (self::ARCHIVOS as $archivo) {
            $this->execute((string) file_get_contents($dir . '/' . $archivo));
        }
    }

    public function down(): void
    {
        // El esquema base no se revierte automáticamente: borrar todas las tablas
        // de negocio solo se hace a mano y con un respaldo verificado.
        throw new \RuntimeException('La migración base no es reversible.');
    }
}
