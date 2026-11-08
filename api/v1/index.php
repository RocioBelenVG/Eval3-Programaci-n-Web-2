<?php
declare(strict_types=1);

/**
 * Controlador frontal de la API REST /api/v1 (contrato: docs/openapi.yaml).
 * Los archivos antiguos de esta carpeta (record.php, export.php, …) siguen sirviendo
 * a la interfaz web heredada; todo lo demás entra por aquí.
 */
define('ROOT', dirname(__DIR__, 2));
require ROOT . '/config/config.php';
require ROOT . '/vendor/autoload.php';

use TriTransportes\App;
use TriTransportes\Http\Request;
use TriTransportes\Kernel;

ini_set('display_errors', '0');
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/api/v1/index.php')), '/');
$kernel = new Kernel(App::container(), ROOT . '/logs/api.log');
$kernel->handle(Request::fromGlobals($base))->send();
