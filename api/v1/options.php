<?php
/**
 * API v1 — Opciones para selects dependientes
 * GET /api/v1/options.php?tabla=X&parent_col=Y&parent_id=Z
 */
require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

// Tablas permitidas (whitelist de seguridad)
$TABLAS_PERMITIDAS = [
    'modelos_equipo'      => ['texto' => 'nombre',       'parent_col' => 'marca_id'],
    'sucursales'          => ['texto' => 'nombre',       'parent_col' => 'empresa_id'],
    'centros_costo'       => ['texto' => 'descripcion',  'parent_col' => 'obra_id'],
    'contratos'           => ['texto' => 'numero',       'parent_col' => 'obra_id'],
    'conductores'         => ['texto' => 'nombre_cond',  'parent_col' => null],
    'operadores'          => ['texto' => 'nombre_oper',  'parent_col' => null],
];

$tabla      = get('tabla');
$parent_id  = getInt('parent_id');

if (!array_key_exists($tabla, $TABLAS_PERMITIDAS)) {
    http_response_code(400);
    echo json_encode(['error' => 'Tabla no permitida']);
    exit;
}

$cfg        = $TABLAS_PERMITIDAS[$tabla];
$emp        = Auth::empresaId();
$results    = [];

try {
    switch ($tabla) {
        case 'modelos_equipo':
            $results = DB::all(
                "SELECT id, nombre FROM modelos_equipo WHERE marca_id = ? AND activo = 1 ORDER BY nombre",
                [$parent_id]
            );
            break;

        case 'sucursales':
            $results = DB::all(
                "SELECT id, nombre FROM sucursales WHERE empresa_id = ? AND activo = 1 ORDER BY nombre",
                [$parent_id ?: $emp]
            );
            break;

        case 'centros_costo':
            $results = DB::all(
                "SELECT id, codigo AS nombre FROM centros_costo WHERE obra_id = ? AND activo = 1 ORDER BY codigo",
                [$parent_id]
            );
            break;

        case 'contratos':
            $results = DB::all(
                "SELECT id, COALESCE(numero, CONCAT('Contrato #', id)) AS nombre FROM contratos WHERE obra_id = ? AND empresa_id = ? AND activo = 1",
                [$parent_id, $emp]
            );
            break;

        case 'conductores':
            $results = DB::all(
                "SELECT c.id, CONCAT(t.nombre, ' ', t.apellido) AS nombre
                 FROM conductores c INNER JOIN trabajadores t ON t.id = c.trabajador_id
                 WHERE t.empresa_id = ? AND c.activo = 1 ORDER BY t.apellido",
                [$emp]
            );
            break;

        case 'operadores':
            $results = DB::all(
                "SELECT o.id, CONCAT(t.nombre, ' ', t.apellido) AS nombre
                 FROM operadores o INNER JOIN trabajadores t ON t.id = o.trabajador_id
                 WHERE t.empresa_id = ? AND o.activo = 1 ORDER BY t.apellido",
                [$emp]
            );
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno']);
    exit;
}

echo json_encode($results, JSON_UNESCAPED_UNICODE);
