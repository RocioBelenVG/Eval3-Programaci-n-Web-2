<?php
/**
 * TriTransportes — Auditoría de aislamiento multiempresa
 * ----------------------------------------------------------------------------
 * Recorre el código, extrae las consultas SQL que se envían a la capa DB y
 * clasifica cada una:
 *
 *   OK           → no toca tablas con empresa_id, o la filtra correctamente.
 *   RIESGO       → toca una tabla con empresa_id y no la filtra. Hay que corregir.
 *   REVISAR      → la consulta se arma con variables ($where, {$filtro}); el
 *                  análisis estático no puede concluir y requiere lectura humana.
 *
 * Uso:  php tools/auditar_tenant.php
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo CLI\n"); }

define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/config.php';
require_once ROOT . '/config/database.php';
require_once ROOT . '/core/Tenant.php';

$tablasTenant = Tenant::tablasTenant();
echo "Tablas con empresa_id detectadas: " . count($tablasTenant) . "\n\n";

// ── Recorrido de archivos ───────────────────────────────────────────────────
$directorios = ['modules', 'api', 'core', 'includes'];
$archivos = [];
foreach ($directorios as $d) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOT . '/' . $d));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') $archivos[] = $f->getPathname();
    }
}
$archivos[] = ROOT . '/login.php';
sort($archivos);

$resultados = ['RIESGO' => [], 'REVISAR' => [], 'OK' => 0];
$totalConsultas = 0;

foreach ($archivos as $ruta) {
    $codigo = file_get_contents($ruta);
    $rel    = str_replace(ROOT . DIRECTORY_SEPARATOR, '', $ruta);

    // Consultas entregadas a la capa de datos
    if (!preg_match_all('/DB::(all|one|run|count)\s*\(\s*(["\'])(.*?)\2/s', $codigo, $m, PREG_OFFSET_CAPTURE)) {
        continue;
    }

    foreach ($m[3] as $i => $captura) {
        $sql   = $captura[0];
        $linea = substr_count(substr($codigo, 0, $captura[1]), "\n") + 1;
        $totalConsultas++;

        // ¿La consulta se completa con variables PHP?
        $tieneVariables = (bool) preg_match('/\$\w+|\{\$/', $sql);

        $motivo = Tenant::revisar($sql);

        if ($motivo === null) {
            $resultados['OK']++;
            continue;
        }

        $registro = [
            'archivo' => $rel,
            'linea'   => $linea,
            'motivo'  => $motivo,
            'sql'     => mb_substr(Tenant::normalizar($sql), 0, 150),
        ];

        if ($tieneVariables) {
            $resultados['REVISAR'][] = $registro;
        } else {
            $resultados['RIESGO'][] = $registro;
        }
    }
}

// ── Informe ─────────────────────────────────────────────────────────────────
echo "Consultas analizadas: {$totalConsultas}\n";
echo "  Correctas : {$resultados['OK']}\n";
echo "  Riesgo    : " . count($resultados['RIESGO']) . "\n";
echo "  A revisar : " . count($resultados['REVISAR']) . "\n";

if ($resultados['RIESGO']) {
    echo "\n================= RIESGO — corregir =================\n";
    foreach ($resultados['RIESGO'] as $r) {
        printf("%s:%d\n  %s\n  %s\n\n", $r['archivo'], $r['linea'], $r['motivo'], $r['sql']);
    }
}

if ($resultados['REVISAR']) {
    echo "\n============ A REVISAR (SQL con variables) ============\n";
    foreach ($resultados['REVISAR'] as $r) {
        printf("%s:%d\n  %s\n  %s\n\n", $r['archivo'], $r['linea'], $r['motivo'], $r['sql']);
    }
}

echo "\nFin de la auditoría.\n";
exit(count($resultados['RIESGO']) > 0 ? 1 : 0);
