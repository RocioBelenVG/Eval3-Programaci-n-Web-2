<?php
/**
 * API v1 — Exportar a Excel
 * GET /api/v1/export.php?vista={vista}&<filtros del listado>
 *
 * Genera un .xlsx con TODOS los registros que cumplen los filtros que el usuario
 * aplicó en el listado del módulo (no sólo la página visible). El control de
 * acceso usa el mismo permiso del módulo y el scope de empresa activo.
 */
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once ROOT . '/core/DataViews.php';
require_once ROOT . '/core/Exporter.php';

Auth::require();

$vista = get('vista');
$def   = DataViews::get($vista);

if (!$def) {
    http_response_code(404);
    die('Vista no encontrada.');
}

// Permiso de lectura del módulo
if (!Auth::can($def['permiso'], 'ver')) {
    http_response_code(403);
    die('No tiene permisos para exportar este módulo.');
}

$empresaId = Auth::empresaId();

try {
    $built = DataViews::buildQuery($vista, $empresaId, $_GET, 0);
    $rows  = DB::all($built['sql'], $built['params']);
} catch (Throwable $e) {
    http_response_code(500);
    if (APP_ENV === 'development') { die('Error: ' . $e->getMessage()); }
    die('No se pudo generar la exportación.');
}

// Columnas exportables (se omiten las marcadas como view_only)
$campos = array_values(array_filter($def['campos'], fn($c) => empty($c['view_only'])));

// Formatear filas
$filas = [];
foreach ($rows as $r) {
    $fila = [];
    foreach ($campos as $c) {
        $fila[] = exportCell($c, $r);
    }
    $filas[] = $fila;
}

// Resumen de filtros para la cabecera del reporte
$resumen = filtrosResumen($_GET);

Exporter::download(
    $def['titulo'],
    $campos,
    $filas,
    [
        'empresa' => 'Empresa: ' . Auth::empresaNombre(),
        'filtros' => $resumen,
        'usuario' => Auth::user()['nombre'] ?? '',
    ]
);

// ── Helpers locales ─────────────────────────────────────────────────────────
function exportCell(array $c, array $r): array
{
    $raw = $r[$c['k']] ?? null;
    switch ($c['t']) {
        case 'fecha':
            return ['v' => $raw ? formatDate($raw) : '', 'num' => false];
        case 'fechahora':
            return ['v' => $raw ? formatDate($raw, 'd/m/Y H:i') : '', 'num' => false];
        case 'num':
        case 'num2':
        case 'litros':
        case 'dinero':
            if ($raw === null || $raw === '') return ['v' => '', 'num' => false];
            return ['v' => (float)$raw, 'num' => true];
        case 'badge':
            return ['v' => humanize((string)($raw ?? '')), 'num' => false];
        default:
            return ['v' => (string)($raw ?? ''), 'num' => false];
    }
}

function humanize(string $s): string
{
    if ($s === '') return '';
    return ucfirst(str_replace('_', ' ', $s));
}

function filtrosResumen(array $g): string
{
    $map = [
        'desde'        => 'Desde',
        'hasta'        => 'Hasta',
        'obra_id'      => 'Obra',
        'material_id'  => 'Material',
        'tipo_unidad'  => 'Tipo',
        'estado'       => 'Estado',
        'estado_id'    => 'Estado',
        'tipo_id'      => 'Tipo doc.',
        'entidad_tipo' => 'Entidad',
        'alerta'       => 'Alerta',
        'q'            => 'Búsqueda',
        'tab'          => 'Pestaña',
    ];
    $parts = [];
    foreach ($map as $k => $label) {
        if (!isset($g[$k])) continue;
        $val = trim((string)$g[$k]);
        if ($val === '' || $val === '0') continue;
        $parts[] = $label . ': ' . $val;
    }
    return $parts ? 'Filtros — ' . implode('  ·  ', $parts) : 'Sin filtros (todos los registros)';
}
