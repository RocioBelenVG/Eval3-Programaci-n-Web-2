<?php
declare(strict_types=1);

if (!defined('ROOT')) {
    define('ROOT', dirname(__DIR__));
}
// La configuración lee el .env; las pruebas de integración usan la misma base de datos
// pero cada caso corre dentro de una transacción que se revierte al terminar.
require ROOT . '/config/config.php';
require ROOT . '/vendor/autoload.php';
