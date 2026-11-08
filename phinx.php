<?php
/**
 * Configuración de Phinx (migraciones y seeders).
 * Las credenciales salen del .env (nunca del repositorio), igual que la aplicación.
 *
 *   vendor/bin/phinx migrate -c phinx.php          aplica las migraciones pendientes
 *   vendor/bin/phinx rollback -c phinx.php         revierte la última
 *   vendor/bin/phinx seed:run -c phinx.php         carga los datos de prueba
 */
if (!defined('ROOT')) {
    define('ROOT', __DIR__);
}
require_once __DIR__ . '/config/config.php';

$db = [
    'adapter'   => 'mysql',
    'host'      => DB_HOST,
    'port'      => (int) DB_PORT,
    'name'      => DB_NAME,
    'user'      => DB_USER,
    'pass'      => DB_PASS,
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/database/migrations',
        'seeds'      => '%%PHINX_CONFIG_DIR%%/database/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment'     => 'app',
        'app'                     => $db,
    ],
    'version_order' => 'creation',
];
