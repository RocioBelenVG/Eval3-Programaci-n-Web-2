<?php
/**
 * API v1 — Reporte PDF de un registro individual
 * GET /api/v1/record_report.php?vista={vista}&id={id}
 *
 * Genera un PDF profesional, descargable, con la cabecera de la empresa,
 * el detalle completo del registro (mismos campos que el visualizador) y
 * un pie con datos de emisión. Reutiliza DataViews como única fuente de verdad.
 */
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once ROOT . '/core/DataViews.php';
require_once ROOT . '/libs/fpdf/fpdf.php';

if (!Auth::check()) {
    http_response_code(401);
    exit('No autenticado');
}

$vista = get('vista');
$id    = getInt('id');
$def   = DataViews::get($vista);

if (!$def || $id <= 0) {
    http_response_code(404);
    exit('Registro no encontrado');
}
if (!Auth::can($def['permiso'], 'ver')) {
    http_response_code(403);
    exit('Sin permisos para este módulo');
}

try {
    $built = DataViews::buildQuery($vista, Auth::empresaId(), $_GET, $id);
    $row   = DB::one($built['sql'], $built['params']);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Error al generar el reporte');
}
if (!$row) {
    http_response_code(404);
    exit('Registro no encontrado o pertenece a otra empresa');
}

// Datos de la empresa para la cabecera
$empresa = [];
try {
    $empresa = DB::one("SELECT razon_social, rut, giro, direccion, ciudad, telefono, email
                        FROM empresas WHERE id = ?", [Auth::empresaId()]) ?: [];
} catch (Throwable $e) { $empresa = []; }

$tituloMod = $def['titulo'] ?? 'Registro';
$usuario   = Auth::user()['nombre'] ?? '';

// Identificador destacado
$ident = '';
foreach (['numero_guia', 'guia_numero', 'numero', 'titulo', 'codigo'] as $k) {
    if (!empty($row[$k])) { $ident = (string)$row[$k]; break; }
}
if (!empty($row['fecha'])) {
    $ident = trim($ident . ($ident ? '  ·  ' : '') . formatDate($row['fecha']));
}

// ── Helpers de formato (espejo del visualizador) ────────────────────────────
function rptFormat(string $t, $raw): string
{
    if ($raw === null || $raw === '') return '—';
    switch ($t) {
        case 'fecha':     return formatDate($raw);
        case 'fechahora': return formatDate($raw, 'd/m/Y H:i');
        case 'num':       return formatNum((float)$raw, 0);
        case 'num2':
        case 'litros':    return formatNum((float)$raw, 2);
        case 'dinero':    return formatMoney((float)$raw);
        case 'badge':     return ucfirst(str_replace('_', ' ', (string)$raw));
        default:          return (string)$raw;
    }
}

// ── PDF ─────────────────────────────────────────────────────────────────────
class RecordPDF extends FPDF
{
    public string $empresaNombre = '';
    public string $moduloTitulo  = '';
    public string $identificador = '';
    public string $emitidoPor    = '';

    // UTF-8 → Windows-1252 (FPDF core es Latin-1)
    public static function t(string $s): string
    {
        $r = iconv('UTF-8', 'windows-1252//TRANSLIT', $s);
        return $r !== false ? $r : utf8_decode($s);
    }

    function Header(): void
    {
        // Banda superior navy
        $this->SetFillColor(15, 23, 42);
        $this->Rect(0, 0, 210, 30, 'F');
        // Acento ámbar
        $this->SetFillColor(245, 158, 11);
        $this->Rect(0, 30, 210, 1.4, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetXY(14, 7);
        $this->SetFont('Helvetica', 'B', 15);
        $this->Cell(0, 7, self::t($this->empresaNombre ?: 'TriTransportes'), 0, 1, 'L');

        $this->SetX(14);
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(245, 158, 11);
        $this->Cell(0, 6, self::t($this->moduloTitulo), 0, 1, 'L');

        // Sello superior derecho
        $this->SetXY(150, 9);
        $this->SetFont('Helvetica', 'B', 9);
        $this->SetTextColor(203, 213, 225);
        $this->Cell(46, 5, 'TriTransportes', 0, 2, 'R');
        $this->SetFont('Helvetica', '', 8);
        $this->Cell(46, 4, self::t('Reporte de registro'), 0, 1, 'R');

        $this->SetY(40);
        $this->SetTextColor(15, 23, 42);
    }

    function Footer(): void
    {
        $this->SetY(-16);
        $this->SetDrawColor(226, 232, 240);
        $this->Line(14, $this->GetY(), 196, $this->GetY());
        $this->SetY(-13);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(120, 130, 145);
        $emit = 'Emitido el ' . date('d/m/Y H:i') . ($this->emitidoPor ? ' por ' . $this->emitidoPor : '');
        $this->Cell(0, 5, self::t($emit), 0, 0, 'L');
        $this->Cell(0, 5, self::t('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
    }

    // Fila etiqueta/valor en dos columnas
    function fieldRow(string $label, string $value, bool $full = false): void
    {
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetTextColor(120, 130, 145);
        if ($full) {
            $this->SetX(14);
            $this->Cell(0, 5, self::t(mb_strtoupper($label, 'UTF-8')), 0, 1, 'L');
            $this->SetFont('Helvetica', '', 10);
            $this->SetTextColor(30, 41, 59);
            $this->SetX(14);
            $this->MultiCell(182, 5.2, self::t($value !== '' ? $value : '—'), 0, 'L');
            $this->Ln(2);
        } else {
            // dos columnas de 91mm
            $x = $this->GetX();
            $colW = 91;
            $this->Cell($colW, 5, self::t(mb_strtoupper($label, 'UTF-8')), 0, 0, 'L');
            // segunda celda misma fila si cabe; FPDF maneja con posicionamiento manual
            $this->Ln(5);
            $this->SetFont('Helvetica', '', 10);
            $this->SetTextColor(30, 41, 59);
            $this->MultiCell($colW, 5.2, self::t($value !== '' ? $value : '—'), 0, 'L');
        }
    }
}

$pdf = new RecordPDF('P', 'mm', 'A4');
$pdf->empresaNombre = $empresa['razon_social'] ?? Auth::empresaNombre();
$pdf->moduloTitulo  = $tituloMod;
$pdf->identificador = $ident;
$pdf->emitidoPor    = $usuario;
$pdf->AliasNbPages();
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();

// Tarjeta de identificación del registro
$cardY = $pdf->GetY();
$pdf->SetFillColor(247, 249, 252);
$pdf->SetDrawColor(226, 232, 240);
$pdf->Rect(14, $cardY, 182, 18, 'DF');
// franja ámbar izquierda
$pdf->SetFillColor(245, 158, 11);
$pdf->Rect(14, $cardY, 1.6, 18, 'F');
$pdf->SetXY(20, $cardY + 3);
$pdf->SetFont('Helvetica', 'B', 12);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(0, 6, RecordPDF::t($ident !== '' ? $ident : $tituloMod), 0, 1, 'L');
$pdf->SetX(20);
$pdf->SetFont('Helvetica', '', 8.5);
$pdf->SetTextColor(110, 120, 135);
$rutTxt = !empty($empresa['rut']) ? 'RUT ' . $empresa['rut'] . '   ·   ' : '';
$pdf->Cell(0, 5, RecordPDF::t($rutTxt . ($empresa['ciudad'] ?? '')), 0, 1, 'L');
$pdf->SetY($cardY + 18);
$pdf->Ln(6);

// Campos en dos columnas (los "full" ocupan ancho completo)
$campos = $def['campos'] ?? [];
$pairBuffer = [];

function flushPairs(RecordPDF $pdf, array &$buf): void
{
    foreach (array_chunk($buf, 2) as $pair) {
        $yStart = $pdf->GetY();
        // Columna izquierda
        $pdf->SetXY(14, $yStart);
        renderCell($pdf, $pair[0]['l'], $pair[0]['v'], 14);
        $leftEnd = $pdf->GetY();
        // Columna derecha
        if (isset($pair[1])) {
            $pdf->SetXY(105, $yStart);
            renderCell($pdf, $pair[1]['l'], $pair[1]['v'], 105);
            $rightEnd = $pdf->GetY();
        } else {
            $rightEnd = $leftEnd;
        }
        $pdf->SetY(max($leftEnd, $rightEnd) + 2);
    }
    $buf = [];
}

function renderCell(RecordPDF $pdf, string $label, string $value, float $x): void
{
    $w = 91;
    $pdf->SetX($x);
    $pdf->SetFont('Helvetica', 'B', 7.5);
    $pdf->SetTextColor(130, 140, 155);
    $pdf->Cell($w, 4.5, RecordPDF::t(mb_strtoupper($label, 'UTF-8')), 0, 2, 'L');
    $pdf->SetX($x);
    $pdf->SetFont('Helvetica', '', 9.5);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->MultiCell($w, 5, RecordPDF::t($value !== '' ? $value : '—'), 0, 'L');
}

foreach ($campos as $c) {
    $t = $c['t'];
    $k = $c['k'];

    // Firma → imagen embebida
    if ($t === 'firma') {
        $b64 = $row[$k] ?? '';
        if (!$b64) continue;
        flushPairs($pdf, $pairBuffer);
        $data = (strpos($b64, 'base64,') !== false) ? substr($b64, strpos($b64, 'base64,') + 7) : $b64;
        $bin  = base64_decode($data, true);
        if ($bin !== false && strlen($bin) > 50) {
            $tmp = tempnam(sys_get_temp_dir(), 'fir') . '.png';
            file_put_contents($tmp, $bin);
            $pdf->SetFont('Helvetica', 'B', 7.5);
            $pdf->SetTextColor(130, 140, 155);
            $pdf->SetX(14);
            $pdf->Cell(0, 5, RecordPDF::t(mb_strtoupper($c['l'], 'UTF-8')), 0, 1, 'L');
            try { $pdf->Image($tmp, 16, $pdf->GetY(), 55); $pdf->Ln(26); }
            catch (Throwable $e) { $pdf->Ln(2); }
            @unlink($tmp);
        }
        continue;
    }

    // Foto local → embebida si existe
    if ($t === 'foto') {
        $ruta = $row[$k] ?? '';
        if (!$ruta) continue;
        flushPairs($pdf, $pairBuffer);
        $abs = ROOT . '/uploads/' . ltrim((string)$ruta, '/');
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetTextColor(130, 140, 155);
        $pdf->SetX(14);
        $pdf->Cell(0, 5, RecordPDF::t(mb_strtoupper($c['l'], 'UTF-8')), 0, 1, 'L');
        if (is_file($abs)) {
            try { $pdf->Image($abs, 16, $pdf->GetY(), 70); $pdf->Ln(54); }
            catch (Throwable $e) { $pdf->Ln(2); }
        } else {
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(30, 41, 59);
            $pdf->SetX(16);
            $pdf->MultiCell(180, 5, RecordPDF::t('Adjunto: ' . $ruta), 0, 'L');
        }
        continue;
    }

    // Archivo / coords → línea de texto a ancho completo
    if ($t === 'archivo') {
        $ruta = $row[$k] ?? '';
        if (!$ruta) continue;
        flushPairs($pdf, $pairBuffer);
        renderFull($pdf, $c['l'], 'Archivo adjunto: ' . $ruta);
        continue;
    }
    if ($t === 'coords') {
        $lat = $row['lat'] ?? $row['lat_origen'] ?? null;
        $lng = $row['lng'] ?? $row['lng_origen'] ?? null;
        if (!$lat || !$lng) continue;
        flushPairs($pdf, $pairBuffer);
        renderFull($pdf, $c['l'], number_format((float)$lat, 5) . ', ' . number_format((float)$lng, 5)
                   . '  (https://maps.google.com/?q=' . $lat . ',' . $lng . ')');
        continue;
    }

    // Texto largo → ancho completo
    if ($t === 'largo') {
        $raw = $row[$k] ?? null;
        if ($raw === null || trim((string)$raw) === '') continue;
        flushPairs($pdf, $pairBuffer);
        renderFull($pdf, $c['l'], (string)$raw);
        continue;
    }

    // Escalar → buffer de pares (2 columnas)
    $val = rptFormat($t, $row[$k] ?? null);
    $pairBuffer[] = ['l' => $c['l'], 'v' => $val];
}
flushPairs($pdf, $pairBuffer);

function renderFull(RecordPDF $pdf, string $label, string $value): void
{
    $pdf->SetFont('Helvetica', 'B', 7.5);
    $pdf->SetTextColor(130, 140, 155);
    $pdf->SetX(14);
    $pdf->Cell(0, 5, RecordPDF::t(mb_strtoupper($label, 'UTF-8')), 0, 1, 'L');
    $pdf->SetFont('Helvetica', '', 9.5);
    $pdf->SetTextColor(30, 41, 59);
    $pdf->SetX(14);
    $pdf->MultiCell(182, 5, RecordPDF::t($value), 0, 'L');
    $pdf->Ln(2);
}

// Nombre de archivo
$slug = preg_replace('/[^a-zA-Z0-9]+/', '_', $tituloMod);
$fname = $slug . '_' . $id . '_' . date('Ymd') . '.pdf';

$pdf->Output('D', $fname);
exit;
