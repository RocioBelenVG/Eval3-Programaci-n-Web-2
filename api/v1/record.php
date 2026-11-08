<?php
/**
 * API v1 — Visualizador de registro
 * GET /api/v1/record.php?vista={vista}&id={id}
 *
 * Devuelve JSON: { ok, titulo, icono, edit_url, html }
 * El HTML es un fragmento listo para inyectarse en el modal visualizador.
 * Control de acceso: permiso del módulo + scope de empresa (el registro debe
 * pertenecer a la empresa activa).
 */
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once ROOT . '/core/DataViews.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'No autenticado']);
    exit;
}

$vista = get('vista');
$id    = getInt('id');
$def   = DataViews::get($vista);

if (!$def || $id <= 0) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Registro no encontrado']);
    exit;
}

if (!Auth::can($def['permiso'], 'ver')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Sin permisos para visualizar este módulo']);
    exit;
}

try {
    $built = DataViews::buildQuery($vista, Auth::empresaId(), $_GET, $id);
    $row   = DB::one($built['sql'], $built['params']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Error al cargar el registro']);
    exit;
}

if (!$row) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Registro no encontrado o pertenece a otra empresa']);
    exit;
}

// Subtítulo: identificador destacado del registro
$subtitle = '';
foreach (['numero_guia', 'guia_numero', 'numero', 'titulo', 'codigo'] as $k) {
    if (!empty($row[$k])) { $subtitle = (string)$row[$k]; break; }
}
if (!empty($row['fecha'])) {
    $subtitle = trim($subtitle . '  ·  ' . formatDate($row['fecha']));
}

// Estado destacado para el badge del encabezado
$estadoHero = '';
foreach (['estado', 'resultado', 'gravedad'] as $k) {
    if (!empty($row[$k])) { $estadoHero = (string)$row[$k]; break; }
}

$editUrl = APP_URL . '/' . str_replace('{id}', (string)$id, $def['edit']);

// ── Construir HTML del cuerpo ───────────────────────────────────────────────
// Se renderizan primero los campos definidos en la vista (orden/etiqueta/formato)
// y luego se completa AUTOMÁTICAMENTE con cualquier otra columna del registro,
// para garantizar que el visualizador muestre TODA la información.
$shownKeys   = [];
$shownLabels = [];
ob_start();
?>
<div class="rv-grid">
<?php
foreach ($def['campos'] as $c) {
    $shownKeys[$c['k']] = true;
    $shownLabels[rvLower($c['l'])] = true;
    $html = rvField($c, $row);
    if ($html !== null) echo $html;
}
echo rvExtras($row, $shownKeys, $shownLabels);
?>
</div>
<?php
$bodyHtml = ob_get_clean();

echo json_encode([
    'ok'       => true,
    'titulo'   => $def['titulo'],
    'icono'    => $def['icono'],
    'subtitle' => $subtitle,
    'estado'   => $estadoHero,
    'estado_clase' => rvBadgeClass($estadoHero),
    'edit_url' => $editUrl,
    'html'     => $bodyHtml,
], JSON_UNESCAPED_UNICODE);


// ── Render de un campo individual ───────────────────────────────────────────
function rvField(array $c, array $row): ?string
{
    $k   = $c['k'];
    $t   = $c['t'];
    $lbl = h($c['l']);

    // Tipos que ocupan toda la fila
    $full = in_array($t, ['largo', 'firma', 'foto', 'archivo', 'coords'], true);

    // ── Firma (base64) ──
    if ($t === 'firma') {
        $b64 = $row[$k] ?? '';
        if (!$b64) return null;
        $src = (strpos($b64, 'data:') === 0) ? $b64 : 'data:image/png;base64,' . $b64;
        return '<div class="rv-field rv-full"><div class="rv-label">' . $lbl . '</div>'
             . '<div class="rv-firma"><img src="' . h($src) . '" alt="Firma"></div></div>';
    }

    // ── Foto (ruta en /uploads) ──
    if ($t === 'foto') {
        $ruta = $row[$k] ?? '';
        if (!$ruta) return null;
        $url = APP_URL . '/uploads/' . ltrim((string)$ruta, '/');
        return '<div class="rv-field rv-full"><div class="rv-label">' . $lbl . '</div>'
             . '<a class="rv-foto" href="' . h($url) . '" target="_blank" rel="noopener">'
             . '<img src="' . h($url) . '" alt="Foto" loading="lazy"></a></div>';
    }

    // ── Archivo adjunto ──
    if ($t === 'archivo') {
        $ruta = $row[$k] ?? '';
        if (!$ruta) return null;
        $url = APP_URL . '/uploads/' . ltrim((string)$ruta, '/');
        return '<div class="rv-field rv-full"><div class="rv-label">' . $lbl . '</div>'
             . '<a class="rv-archivo" href="' . h($url) . '" target="_blank" rel="noopener">'
             . '<i class="fas fa-paperclip me-2"></i>Abrir archivo adjunto</a></div>';
    }

    // ── Coordenadas → mapa ──
    if ($t === 'coords') {
        $lat = $row['lat'] ?? $row['lat_origen'] ?? null;
        $lng = $row['lng'] ?? $row['lng_origen'] ?? null;
        if (!$lat || !$lng) return null;
        $maps = 'https://www.google.com/maps?q=' . urlencode($lat . ',' . $lng);
        return '<div class="rv-field rv-full"><div class="rv-label">' . $lbl . '</div>'
             . '<a class="rv-mapa" href="' . h($maps) . '" target="_blank" rel="noopener">'
             . '<i class="fas fa-map-marker-alt me-2"></i>Ver ubicación en el mapa '
             . '<small class="text-muted ms-2">(' . h(number_format((float)$lat, 5)) . ', ' . h(number_format((float)$lng, 5)) . ')</small></a></div>';
    }

    $raw = $row[$k] ?? null;

    // ── Texto largo ──
    if ($t === 'largo') {
        if ($raw === null || trim((string)$raw) === '') return null;
        return '<div class="rv-field rv-full"><div class="rv-label">' . $lbl . '</div>'
             . '<div class="rv-largo">' . nl2br(h($raw)) . '</div></div>';
    }

    // ── Badge ──
    if ($t === 'badge') {
        $val = (string)($raw ?? '');
        if ($val === '') $inner = '<span class="rv-value text-muted">—</span>';
        else $inner = '<span class="rv-badge ' . rvBadgeClass($val) . '">' . h(ucfirst(str_replace('_', ' ', $val))) . '</span>';
        return '<div class="rv-field"><div class="rv-label">' . $lbl . '</div>' . $inner . '</div>';
    }

    // ── Valor escalar ──
    $val = rvFormat($t, $raw);
    $cls = ($val === '—') ? ' text-muted' : '';
    return '<div class="rv-field"><div class="rv-label">' . $lbl . '</div>'
         . '<div class="rv-value' . $cls . '">' . h($val) . '</div></div>';
}

function rvFormat(string $t, $raw): string
{
    if ($raw === null || $raw === '') return '—';
    switch ($t) {
        case 'fecha':     return formatDate($raw);
        case 'fechahora': return formatDate($raw, 'd/m/Y H:i');
        case 'num':       return formatNum((float)$raw, 0);
        case 'num2':
        case 'litros':    return formatNum((float)$raw, 2);
        case 'dinero':    return formatMoney((float)$raw);
        default:          return (string)$raw;
    }
}

function rvBadgeClass(string $estado): string
{
    $e = strtolower(trim($estado));
    $map = [
        'cerrado' => 'rv-green', 'completado' => 'rv-green', 'aprobado' => 'rv-green', 'disponible' => 'rv-green',
        'borrador' => 'rv-amber', 'pendiente' => 'rv-amber', 'observado' => 'rv-amber', 'en_proceso' => 'rv-amber',
        'programado' => 'rv-slate', 'reportado' => 'rv-slate',
        'en_ruta' => 'rv-blue', 'investigando' => 'rv-blue',
        'rechazado' => 'rv-red', 'cancelado' => 'rv-red', 'vencido' => 'rv-red',
        'grave' => 'rv-red', 'fatal' => 'rv-red', 'moderado' => 'rv-amber', 'leve' => 'rv-green',
        'accidente' => 'rv-red', 'incidente' => 'rv-amber', 'cuasi_accidente' => 'rv-amber',
        'mañana' => 'rv-blue', 'tarde' => 'rv-amber', 'noche' => 'rv-slate',
        'vehiculo' => 'rv-blue', 'equipo' => 'rv-amber',
    ];
    return $map[$e] ?? 'rv-slate';
}

// ── Completar el visualizador con TODAS las columnas del registro ────────────
/**
 * Renderiza cualquier columna del registro que la vista no haya incluido,
 * resolviendo claves foráneas (*_id) a su nombre legible y sin duplicar
 * etiquetas ya mostradas. Garantiza información completa en el visualizador.
 */
function rvExtras(array $row, array $shownKeys, array $shownLabels): string
{
    // Columnas internas/técnicas que nunca se muestran
    $oculto = [
        'id'=>1, 'empresa_id'=>1, 'creado_por'=>1, 'password_hash'=>1,
        'entidad_id'=>1, 'viaje_id'=>1, 'estado_color'=>1, 'activo'=>1,
    ];

    // Resolución de claves foráneas → texto legible
    $fk = [
        'obra_id'          => ['Obra',          "SELECT nombre FROM obras WHERE id = ?"],
        'contrato_id'      => ['Contrato',      "SELECT numero FROM contratos WHERE id = ?"],
        'cliente_id'       => ['Cliente',       "SELECT razon_social FROM clientes WHERE id = ?"],
        'vehiculo_id'      => ['Vehículo',      "SELECT patente FROM vehiculos WHERE id = ?"],
        'equipo_id'        => ['Equipo',        "SELECT TRIM(CONCAT(codigo,' ',COALESCE(nombre,''))) FROM equipos WHERE id = ?"],
        'material_id'      => ['Material',      "SELECT nombre FROM materiales WHERE id = ?"],
        'clasificacion_id' => ['Clasificación', "SELECT nombre FROM clasificaciones_arido WHERE id = ?"],
        'fuente_id'        => ['Fuente',        "SELECT nombre FROM fuentes_combustible WHERE id = ?"],
        'estanque_id'      => ['Estanque',      "SELECT nombre FROM estanques WHERE id = ?"],
        'proveedor_id'     => ['Proveedor',     "SELECT nombre FROM proveedores_combustible WHERE id = ?"],
        'conductor_id'     => ['Conductor',     "SELECT CONCAT(t.nombre,' ',t.apellido) FROM conductores c JOIN trabajadores t ON t.id = c.trabajador_id WHERE c.id = ?"],
        'operador_id'      => ['Operador',      "SELECT CONCAT(t.nombre,' ',t.apellido) FROM operadores o JOIN trabajadores t ON t.id = o.trabajador_id WHERE o.id = ?"],
        'supervisor_id'    => ['Supervisor',    "SELECT CONCAT(nombre,' ',apellido) FROM trabajadores WHERE id = ?"],
        'trabajador_id'    => ['Trabajador',    "SELECT CONCAT(nombre,' ',apellido) FROM trabajadores WHERE id = ?"],
        'marca_id'         => ['Marca',         "SELECT nombre FROM marcas_equipo WHERE id = ?"],
        'modelo_id'        => ['Modelo',        "SELECT nombre FROM modelos_equipo WHERE id = ?"],
        'categoria_id'     => ['Categoría',     "SELECT nombre FROM categorias_equipo WHERE id = ?"],
        'tipo_id'          => ['Tipo',          null],
        'estado_id'        => ['Estado',        null],
        'cargo_id'         => ['Cargo',         "SELECT nombre FROM cargos WHERE id = ?"],
    ];

    $coords = ['lat'=>1,'lng'=>1,'lat_origen'=>1,'lng_origen'=>1,'lat_destino'=>1,'lng_destino'=>1];
    $out = '';

    foreach ($row as $col => $val) {
        if (isset($shownKeys[$col]) || isset($oculto[$col]) || isset($coords[$col])) continue;
        // Omitir alias de JOIN ya representados (p.ej. equipo_nombre, veh_marca, *_codigo)
        if (substr($col, -7) === '_nombre' || substr($col, -7) === '_codigo'
            || in_array($col, ['veh_marca', 'patente', 'mant_activa'], true)) continue;

        // Clave foránea (*_id)
        if (substr($col, -3) === '_id') {
            if (!isset($fk[$col]) || $fk[$col][1] === null) continue;   // sin resolución → no mostrar id crudo
            [$lbl, $sql] = $fk[$col];
            if (isset($shownLabels[rvLower($lbl)])) continue;
            if ($val === null || $val === '') continue;
            try { $r = DB::one($sql, [$val]); $nombre = $r ? reset($r) : null; }
            catch (Throwable $e) { $nombre = null; }
            if ($nombre === null || $nombre === '') continue;
            $shownLabels[rvLower($lbl)] = true;
            $out .= rvField(['k'=>'__fk','l'=>$lbl,'t'=>'texto'], ['__fk'=>$nombre]);
            continue;
        }

        // Imágenes / adjuntos
        if (strpos($col, 'firma') !== false) {
            $out .= rvField(['k'=>$col,'l'=>rvHumanize($col),'t'=>'firma'], $row); continue;
        }
        if (strpos($col, 'foto') !== false || substr($col, -5) === '_ruta') {
            $lbl = rvHumanize($col);
            $tipo = (strpos($col, 'foto') !== false) ? 'foto' : 'archivo';
            $out .= rvField(['k'=>$col,'l'=>$lbl,'t'=>$tipo], $row); continue;
        }

        // Columna de datos normal
        $lbl = rvHumanize($col);
        if (isset($shownLabels[rvLower($lbl)])) continue;
        $shownLabels[rvLower($lbl)] = true;
        $out .= rvField(['k'=>$col,'l'=>$lbl,'t'=>rvGuessType($col, $val)], $row);
    }

    // Bloques de coordenadas (sólo si la vista no mostró ya "Ubicación")
    if (!isset($shownLabels['ubicación']) && !isset($shownLabels['ubicacion'])) {
        $out .= rvCoordLink($row, 'lat', 'lng', 'Ubicación');
    }
    $out .= rvCoordLink($row, 'lat_origen', 'lng_origen', 'Coordenadas Origen');
    $out .= rvCoordLink($row, 'lat_destino', 'lng_destino', 'Coordenadas Destino');

    return $out;
}

function rvCoordLink(array $row, string $latK, string $lngK, string $label): string
{
    $lat = $row[$latK] ?? null; $lng = $row[$lngK] ?? null;
    if (!$lat || !$lng) return '';
    $maps = 'https://www.google.com/maps?q=' . urlencode($lat . ',' . $lng);
    return '<div class="rv-field rv-full"><div class="rv-label">' . h($label) . '</div>'
         . '<a class="rv-mapa" href="' . h($maps) . '" target="_blank" rel="noopener">'
         . '<i class="fas fa-map-marker-alt me-2"></i>Ver ubicación en el mapa '
         . '<small class="text-muted ms-2">(' . h(number_format((float)$lat, 5)) . ', ' . h(number_format((float)$lng, 5)) . ')</small></a></div>';
}

function rvHumanize(string $col): string
{
    $map = [
        'created_at'=>'Creado', 'updated_at'=>'Actualizado', 'anio'=>'Año',
        'numero_serie'=>'N° Serie', 'rut'=>'RUT', 'rut_receptor'=>'RUT Receptor',
        'km_inicio'=>'Km Inicio', 'km_fin'=>'Km Fin', 'nombre_archivo'=>'Nombre Archivo',
        'tipo_unidad'=>'Tipo Unidad', 'tipo_combustible'=>'Combustible', 'entidad_tipo'=>'Entidad',
        'hora_salida'=>'Hora Salida', 'hora_llegada'=>'Hora Llegada', 'dias_perdidos'=>'Días Perdidos',
        'fecha_emision'=>'Emisión', 'fecha_vencimiento'=>'Vencimiento',
        'fecha_inicio'=>'Inicio', 'fecha_termino'=>'Término', 'fecha_ingreso'=>'Ingreso',
        'precio_unitario'=>'Precio Unitario', 'precio_litro'=>'Precio/Litro',
        'numero_guia'=>'N° Guía', 'guia_numero'=>'N° Guía',
    ];
    if (isset($map[$col])) return $map[$col];
    return ucwords(str_replace('_', ' ', $col));
}

function rvGuessType(string $col, $val): string
{
    if (substr($col, -3) === '_at') return 'fechahora';
    if (strpos($col, 'fecha') !== false || substr($col, -6) === '_vence') return 'fecha';
    if (in_array($col, ['estado','resultado','gravedad','turno','tipo'], true)) return 'badge';
    if (in_array($col, ['monto','monto_contrato','monto_avanzado','precio_litro','precio_unitario'], true)) return 'dinero';
    if (in_array($col, ['observacion','observaciones','descripcion','acciones'], true)) return 'largo';
    if (is_numeric($val)) return (strpos((string)$val, '.') !== false) ? 'num2' : 'num';
    return 'texto';
}

/** Minúsculas con respaldo si no está disponible la extensión mbstring. */
function rvLower(string $s): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}
