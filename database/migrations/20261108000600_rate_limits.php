<?php
declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Contadores del límite de tasa de la API (ventana fija).
 * La clave identifica a quién se limita: "login:<ip>:<email>" o "api:<usuario>".
 */
final class RateLimits extends AbstractMigration
{
    public function change(): void
    {
        $this->table('rate_limits', ['id' => false, 'primary_key' => ['clave']])
            ->addColumn('clave', 'string', ['limit' => 190])
            ->addColumn('ventana', 'integer', ['signed' => false, 'comment' => 'Número de ventana (epoch / segundos de ventana)'])
            ->addColumn('conteo', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('actualizado', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['actualizado'])
            ->create();
    }
}
