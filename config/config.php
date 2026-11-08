<?php
/**
 * TriTransportes — configuración general.
 * Los secretos NO viven en el código: se leen desde variables de entorno
 * o desde el archivo .env de la raíz (excluido del repositorio y bloqueado por .htaccess).
 * Ver .env.example para la lista de variables.
 */

// ── Carga de .env (formato CLAVE=valor) ──────────────────────────────────────
(function (): void {
    $file = dirname(__DIR__) . '/.env';
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"'");
        if (getenv($k) === false) { putenv("$k=$v"); $_ENV[$k] = $v; }
    }
})();

function env(string $key, string $default = ''): string {
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

define('APP_NAME',    'TriTransportes');
define('APP_VERSION', '0.1.0');
define('APP_URL',     env('APP_URL', '/TriTransportes'));
define('APP_ENV',     env('APP_ENV', 'production'));  // development | testing | production

define('DB_HOST',    env('DB_HOST', 'localhost'));
define('DB_PORT',    env('DB_PORT', '3306'));
define('DB_NAME',    env('DB_NAME'));
define('DB_USER',    env('DB_USER'));
define('DB_PASS',    env('DB_PASS'));
define('DB_CHARSET', 'utf8mb4');

define('JWT_SECRET', env('JWT_SECRET'));
define('JWT_TTL',    (int) env('JWT_TTL', '3600'));

// Aislamiento multiempresa: off | auditar | estricto
define('TENANT_MODO', 'auditar');

define('SESSION_LIFETIME', 1800);
define('SESSION_NAME',     'TRITRANS'); // nombre propio de la cookie de sesión

define('LOGIN_MAX_INTENTOS', 6);
define('LOGIN_LOCKOUT_MIN',  15);

define('UPLOAD_PATH',      ROOT . '/uploads/');
define('UPLOAD_MAX_SIZE',  20971520);
define('RECORDS_PER_PAGE', 25);

// Servicio de IA externo (Groq, API compatible con OpenAI)
define('GROQ_API_KEY', env('GROQ_API_KEY'));
define('AI_TIMEOUT',   (int) env('AI_TIMEOUT', '20'));

date_default_timezone_set('America/Santiago');

if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', ROOT . '/logs/php_errors.log');
}
