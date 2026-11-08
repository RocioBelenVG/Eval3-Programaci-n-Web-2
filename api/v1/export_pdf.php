<?php
/**
 * API v1 — Exportar a PDF
 * GET /api/v1/export_pdf?vista={vista}&<filtros del listado>
 *
 * Espejo de export.php pero en PDF: usa la MISMA definición de DataViews, de
 * modo que el PDF, el Excel y lo que el usuario ve en pantalla siempre
 * coinciden en columnas, orden y filtros aplicados.
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once ROOT . '/core/DataViews.php';
require_once ROOT . '/core/PdfReport.php';

Auth::require();

$vista = get('vista');
$def   = DataViews::get($vista);

if (!$def) {
    http_response_code(404);
    die('Vista no encontrada.');
}

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
    die('No se pudo generar el reporte.');
}

// Columnas imprimibles (se omiten firmas, fotos y demás campos de solo vista)
$campos = array_values(array_filter($def['campos'], fn($c) => empty($c['view_only'])));

// Con muchas columnas el reporte sale apaisado
$pdf = new PdfReport(count($campos) > 5 ? 'L' : 'P');

$cols = [];
foreach ($campos as $c) {
    $cols[] = [
        'l'     => $c['l'],
        'w'     => $c['w'] ?? 14,
        'align' => in_array($c['t'], ['num', 'num2', 'dinero', 'litros'], true) ? 'R'
                 : (in_array($c['t'], ['badge', 'fecha'], true) ? 'C' : 'L'),
    ];
}

$pdf->configurar(
    $def['titulo'],
    Auth::empresaNombre(),
    resumenFiltrosPdf($_GET),
    Auth::user()['nombre'] ?? ''
);
$pdf->definirColumnas($cols);
$pdf->AddPage();

// ── Filas + acumulación de totales ──────────────────────────────────────────
$totales = [];
$filas   = [];

foreach ($rows as $r) {
    $fila = [];
    foreach ($campos as $i => $c) {
        $raw = $r[$c['k']] ?? null;
        $fila[] = celdaPdf($c, $raw);

        if (in_array($c['t'], ['num', 'num2', 'dinero', 'litros'], true) && is_numeric($raw)) {
            $totales[$i] = ($totales[$i] ?? 0) + (float)$raw;
        }
    }
    $filas[] = $fila;
}

$pdf->filas($filas);

// Fila de totales solo si hay alguna columna numérica
if ($totales) {
    $filaTot = [];
    foreach ($campos as $i => $c) {
        if (!isset($totales[$i])) { $filaTot[$i] = $i === 0 ? 'TOTALES' : ''; continue; }
        $filaTot[$i] = $c['t'] === 'dinero'
            ? formatMoney($totales[$i])
            : formatNum($totales[$i], $c['t'] === 'num' ? 0 : 1);
    }
    $pdf->totales($filaTot);
}

$pdf->resumenFinal(count($rows));

$nombre = 'TriTransportes_' . preg_replace('/[^A-Za-z0-9]+/', '_', $def['titulo']) . '_' . date('Ymd_His') . '.pdf';
$pdf->Output('I', $nombre);

// ── Helpers ─────────────────────────────────────────────────────────────────

/** Formatea el valor de una celda según el tipo declarado en DataViews. */
function celdaPdf(array $c, $raw): string
{
    if ($raw === null || $raw === '') return '';
    switch ($c['t']) {
        case 'fecha':     return formatDate((string)$raw);
        case 'fechahora': return formatDate((string)$raw, 'd/m/Y H:i');
        case 'dinero':    return formatMoney((float)$raw);
        case 'num':       return formatNum((float)$raw, 0);
        case 'num2':
        case 'litros':    return formatNum((float)$raw, 1);
        case 'badge':     return ucfirst(str_replace('_', ' ', (string)$raw));
        case 'largo':     return mb_substr((string)$raw, 0, 120);
        default:          return (string)$raw;
    }
}

/** Texto legible con los filtros que el usuario tenía aplicados. */
function resumenFiltrosPdf(array $g): string
{
    $etiquetas = [
        'desde'        => 'Desde',
        'hasta'        => 'Hasta',
        'obra_id'      => 'Obra',
        'material_id'  => 'Material',
        'tipo_unidad'  => 'Tipo de unidad',
        'estado'       => 'Estado',
        'estado_id'    => 'Estado',
        'tipo_id'      => 'Tipo de documento',
        'entidad_tipo' => 'Entidad',
        'alerta'       => 'Alerta',
        'q'            => 'Búsqueda',
    ];

    // Los identificadores se traducen a nombres legibles
    $traducciones = [
        'obra_id'     => "SELECT nombre FROM obras WHERE id = ?",
        'material_id' => "SELECT nombre FROM materiales WHERE id = ?",
        'tipo_id'     => "SELECT nombre FROM tipos_documento WHERE id = ?",
        'estado_id'   => "SELECT nombre FROM estados_contrato WHERE id = ?",
    ];

    $partes = [];
    foreach ($etiquetas as $k => $label) {
        if (!isset($g[$k])) continue;
        $val = trim((string)$g[$k]);
        if ($val === '' || $val === '0') continue;

        if (isset($traducciones[$k]) && ctype_digit($val)) {
            try {
                $fila = DB::one($traducciones[$k], [(int)$val]);
                if ($fila) $val = reset($fila);
            } catch (Throwable) { /* si falla, se muestra el id */ }
        }
        if ($k === 'desde' || $k === 'hasta') $val = formatDate($val);
        $partes[] = $label . ': ' . $val;
    }

    return $partes
        ? 'Filtros aplicados — ' . implode('   ·   ', $partes)
        : 'Sin filtros: se incluyen todos los registros disponibles.';
}
