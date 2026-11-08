<?php
/**
 * TriTransportes — Generador de datos de demostración a gran escala
 * ----------------------------------------------------------------------------
 * Simula ~12 meses de operación real de las empresas cargadas en el sistema:
 * cada equipo y vehículo tiene un consumo base propio, acumula horómetro /
 * odómetro día a día y se abastece cuando corresponde. De ahí se derivan todos
 * los demás módulos (faena, transporte, áridos, mantención, seguridad,
 * documentos y avance de contratos), de modo que los KPIs y el análisis de
 * rendimiento tengan datos coherentes entre sí.
 *
 * Uso:  php database/seed_masivo.php [--purgar]
 *       --purgar  borra los datos operacionales previos antes de generar.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se ejecuta por línea de comandos.\n");
}

define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/config.php';
require_once ROOT . '/config/database.php';

mt_srand(20260724); // reproducible

$FECHA_INI = '2025-08-01';
$FECHA_FIN = date('Y-m-d');

$pdo = DB::conn();
$pdo->exec('SET NAMES utf8mb4');

$t0 = microtime(true);
echo "TriTransportes — generación de datos de demostración\n";
echo "Período: {$FECHA_INI} → {$FECHA_FIN}\n\n";

// ── Utilidades ───────────────────────────────────────────────────────────────

/** Entero aleatorio inclusivo. */
function ri(int $a, int $b): int { return mt_rand($a, $b); }

/** Flotante aleatorio. */
function rf(float $a, float $b): float { return $a + (mt_rand() / mt_getrandmax()) * ($b - $a); }

/** Elemento aleatorio de un arreglo. */
function pick(array $a) { return $a[array_rand($a)]; }

/** Ocurre con probabilidad $p (0..1). */
function chance(float $p): bool { return (mt_rand() / mt_getrandmax()) < $p; }

/** Ruido multiplicativo centrado en 1 (±$pct). */
function ruido(float $pct): float { return 1 + rf(-$pct, $pct); }

/** Dígito verificador de un RUT chileno. */
function dvRut(int $num): string {
    $s = 1; $m = 0;
    while ($num > 0) {
        $s = ($s + ($num % 10) * (9 - $m++ % 6)) % 11;
        $num = (int)($num / 10);
    }
    return $s ? (string)($s - 1) : 'K';
}

/** RUT chileno formateado a partir de un correlativo. */
function rutChileno(int $num): string {
    return number_format($num, 0, '', '.') . '-' . dvRut($num);
}

/** Inserta una fila y devuelve el id generado. */
function ins(string $tabla, array $datos): int {
    $cols = array_keys($datos);
    $ph   = implode(',', array_fill(0, count($cols), '?'));
    DB::run("INSERT INTO {$tabla} (" . implode(',', $cols) . ") VALUES ({$ph})", array_values($datos));
    return (int) DB::lastId();
}

/**
 * Acumulador de INSERT multi-fila. Evita miles de round-trips contra MySQL
 * en un hosting compartido.
 */
class Lote
{
    private array $filas = [];

    public function __construct(
        private string $tabla,
        private array $cols,
        private int $tamano = 400
    ) {}

    public function add(array $fila): void
    {
        $this->filas[] = $fila;
        if (count($this->filas) >= $this->tamano) $this->flush();
    }

    public function flush(): void
    {
        if (!$this->filas) return;
        $n      = count($this->cols);
        $tupla  = '(' . implode(',', array_fill(0, $n, '?')) . ')';
        $vals   = implode(',', array_fill(0, count($this->filas), $tupla));
        $params = [];
        foreach ($this->filas as $f) foreach ($f as $v) $params[] = $v;

        DB::run("INSERT INTO {$this->tabla} (" . implode(',', $this->cols) . ") VALUES {$vals}", $params);
        $this->filas = [];
    }

    public function total(): int { return count($this->filas); }
}

/** Días hábiles (lunes a sábado) del período. */
function diasHabiles(string $ini, string $fin): array {
    $out = [];
    $d   = new DateTime($ini);
    $f   = new DateTime($fin);
    while ($d <= $f) {
        if ((int)$d->format('N') !== 7) $out[] = $d->format('Y-m-d');
        $d->modify('+1 day');
    }
    return $out;
}

/** Precio del litro de petróleo vigente en una fecha (deriva mensual). */
function precioLitro(string $fecha): float {
    static $cache = [];
    $mes = substr($fecha, 0, 7);
    if (!isset($cache[$mes])) {
        $base = 935;
        $t    = (strtotime($mes . '-01') - strtotime('2025-08-01')) / 2592000; // meses
        $cache[$mes] = round($base + $t * 7 + rf(-28, 28));
    }
    return (float)$cache[$mes];
}

// ── 1. Purga de datos operacionales previos ──────────────────────────────────

$purgar = in_array('--purgar', $argv ?? [], true);
if ($purgar) {
    echo "Purgando datos operacionales previos...\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $tablas = [
        'checklist_items_resp', 'checklists_registros', 'incidentes',
        'mantencion_repuestos', 'mantenciones', 'detenciones_equipo',
        'parte_fotos', 'parte_equipos', 'parte_personal', 'parte_actividades', 'partes_diarios',
        'guias_despacho', 'viajes',
        'despachos_aridos', 'produccion_aridos', 'stock_aridos',
        'cargas_combustible', 'documentos', 'avances_contrato', 'items_contrato',
    ];
    foreach ($tablas as $t) {
        $pdo->exec("DELETE FROM {$t}");
        $pdo->exec("ALTER TABLE {$t} AUTO_INCREMENT = 1");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo "  " . count($tablas) . " tablas vaciadas.\n\n";
}

// ── 2. Ampliación de tablas maestras ─────────────────────────────────────────

echo "Ampliando tablas maestras...\n";

$empresas = DB::all('SELECT id, razon_social FROM empresas WHERE activo=1 ORDER BY id');
$usuarios = [];
foreach (DB::all('SELECT id, empresa_id FROM usuarios') as $u) {
    $usuarios[$u['empresa_id']][] = (int)$u['id'];
}

// 2.1 Clientes adicionales
$clientesNuevos = [
    1 => [
        ['Constructora Sigdo Koppers S.A.', 'Construcción pesada'],
        ['Áridos y Hormigones Melón S.A.',  'Venta de áridos'],
        ['Vialidad Región Metropolitana',   'Servicio público'],
        ['Minera Los Bronces SpA',          'Minería'],
    ],
    2 => [
        ['Inmobiliaria Aconcagua S.A.',     'Desarrollo inmobiliario'],
        ['Municipalidad de Rancagua',       'Servicio público'],
        ['Constructora Independencia Ltda.', 'Edificación'],
    ],
];
$rutSeq = 76500000;
foreach ($clientesNuevos as $empId => $lista) {
    foreach ($lista as [$razon, $giro]) {
        $existe = DB::count('SELECT COUNT(*) FROM clientes WHERE empresa_id=? AND razon_social=?', [$empId, $razon]);
        if ($existe) continue;
        ins('clientes', [
            'empresa_id'   => $empId,
            'razon_social' => $razon,
            'rut'          => rutChileno($rutSeq += ri(1111, 9999)),
            'tipo'         => 'empresa',
            'giro'         => $giro,
            'direccion'    => 'Av. ' . pick(['Providencia', 'Apoquindo', 'Libertador', 'Alameda', 'Kennedy']) . ' ' . ri(1200, 9800),
            'ciudad'       => pick(['Santiago', 'Rancagua', 'Buin', 'Machalí', 'San Bernardo']),
            'telefono'     => '+56 2 2' . ri(200, 899) . ' ' . ri(1000, 9999),
            'email'        => 'contacto@' . strtolower(preg_replace('/[^a-z]/i', '', explode(' ', $razon)[0])) . '.cl',
            'activo'       => 1,
        ]);
    }
}

$clientes = [];
foreach (DB::all('SELECT id, empresa_id FROM clientes WHERE activo=1') as $c) {
    $clientes[$c['empresa_id']][] = (int)$c['id'];
}

// 2.2 Obras adicionales + rango temporal que cubra el período simulado
$obrasNuevas = [
    1 => [
        ['Conservación Ruta G-46 Tramo Norte', 'OBR-004', '2025-07-15', '2026-08-30'],
        ['Planta de Áridos Cerro Negro — Ampliación', 'OBR-005', '2025-08-01', '2026-12-20'],
        ['Habilitación Camino Interior Los Bronces', 'OBR-006', '2025-09-10', '2026-10-15'],
    ],
    2 => [
        ['Urbanización Sector Poniente Rancagua', 'AND-003', '2025-07-20', '2026-09-30'],
        ['Conservación Camino Machalí — Coya', 'AND-004', '2025-08-15', '2026-11-30'],
    ],
];
foreach ($obrasNuevas as $empId => $lista) {
    foreach ($lista as [$nombre, $codigo, $ini, $fin]) {
        if (DB::count('SELECT COUNT(*) FROM obras WHERE empresa_id=? AND codigo=?', [$empId, $codigo])) continue;
        ins('obras', [
            'empresa_id'    => $empId,
            'nombre'        => $nombre,
            'codigo'        => $codigo,
            'cliente_id'    => pick($clientes[$empId]),
            'descripcion'   => 'Obra generada para demostración operacional del sistema.',
            'fecha_inicio'  => $ini,
            'fecha_termino' => $fin,
            'estado'        => 'activa',
            'lat'           => round(rf(-34.35, -33.35), 7),
            'lng'           => round(rf(-70.95, -70.45), 7),
            'activo'        => 1,
        ]);
    }
}

// Las obras preexistentes arrancaban en 2026; se adelantan para cubrir el período
DB::run("UPDATE obras SET fecha_inicio = ? WHERE fecha_inicio > ?", ['2025-08-05', '2025-08-05']);

$obras = [];
foreach (DB::all("SELECT id, empresa_id, nombre, cliente_id FROM obras WHERE activo=1 AND estado='activa' ORDER BY id") as $o) {
    $obras[$o['empresa_id']][] = ['id' => (int)$o['id'], 'nombre' => $o['nombre'], 'cliente_id' => (int)$o['cliente_id']];
}

// 2.3 Contratos adicionales + ítems presupuestados
$estadoEjec = (int)(DB::one("SELECT id FROM estados_contrato WHERE nombre='En Ejecución'")['id'] ?? 3);
DB::run("UPDATE contratos SET fecha_inicio = ?, estado_id = ? WHERE fecha_inicio > ?", ['2025-08-10', $estadoEjec, '2025-08-10']);

$contratosNuevos = [
    1 => [
        ['CT-2025-014', 'Conservación global Ruta G-46 tramo norte', 'Conservación vial', 1240000000, '2025-07-20', '2026-08-30'],
        ['CT-2025-021', 'Suministro y transporte de áridos planta Cerro Negro', 'Suministro', 680000000, '2025-08-05', '2026-12-15'],
    ],
    2 => [
        ['AND-CT-003', 'Urbanización y movimiento de tierra sector poniente', 'Movimiento de tierra', 540000000, '2025-07-25', '2026-09-30'],
    ],
];
foreach ($contratosNuevos as $empId => $lista) {
    foreach ($lista as [$numero, $desc, $tipo, $monto, $ini, $fin]) {
        if (DB::count('SELECT COUNT(*) FROM contratos WHERE empresa_id=? AND numero=?', [$empId, $numero])) continue;
        $obra = pick($obras[$empId]);
        ins('contratos', [
            'empresa_id'     => $empId,
            'obra_id'        => $obra['id'],
            'cliente_id'     => $obra['cliente_id'] ?: pick($clientes[$empId]),
            'estado_id'      => $estadoEjec,
            'numero'         => $numero,
            'descripcion'    => $desc,
            'tipo'           => $tipo,
            'monto_contrato' => $monto,
            'fecha_inicio'   => $ini,
            'fecha_termino'  => $fin,
            'activo'         => 1,
        ]);
    }
}

$contratos = [];
foreach (DB::all('SELECT id, empresa_id, obra_id, monto_contrato, fecha_inicio, fecha_termino FROM contratos WHERE activo=1') as $c) {
    $contratos[$c['empresa_id']][] = $c;
}

// 2.4 Equipos y vehículos adicionales
$marcas  = [];
foreach (DB::all('SELECT id, nombre FROM marcas_equipo') as $m) $marcas[$m['nombre']] = (int)$m['id'];
$modelos = [];
foreach (DB::all('SELECT id, marca_id, nombre FROM modelos_equipo') as $m) $modelos[(int)$m['marca_id']][] = (int)$m['id'];

$equiposNuevos = [
    1 => [
        ['EXC-03', 'Excavadora Volvo EC220',        1, 'Volvo',       2019, 5200],
        ['RET-01', 'Retroexcavadora JCB 3CX',       4, 'JCB',         2020, 4100],
        ['ROD-01', 'Rodillo Dynapac CA2500',        8, 'Terex',       2018, 6300],
        ['ROD-02', 'Rodillo Caterpillar CS533',     8, 'Caterpillar', 2021, 2450],
        ['CAR-02', 'Cargador Frontal Volvo L120',   5, 'Volvo',       2017, 9800],
        ['MOT-02', 'Motoniveladora John Deere 670G',3, 'John Deere',  2020, 4600],
        ['COM-01', 'Compactador Bomag BW211',       6, 'Terex',       2019, 5100],
        ['GRU-01', 'Grúa Horquilla Hyster H80',     7, 'Case',        2016, 7700],
    ],
    2 => [
        ['EXC-A2', 'Excavadora Doosan DX300',       1, 'Doosan',      2020, 3400],
        ['BUL-A1', 'Bulldozer Caterpillar D6T',     2, 'Caterpillar', 2018, 6800],
        ['ROD-A1', 'Rodillo Hamm HD90',             8, 'Terex',       2019, 4300],
        ['MOT-A1', 'Motoniveladora Caterpillar 120K',3,'Caterpillar', 2017, 8100],
        ['RET-A2', 'Retroexcavadora Case 580N',     4, 'Case',        2021, 1900],
    ],
];
foreach ($equiposNuevos as $empId => $lista) {
    foreach ($lista as [$codigo, $nombre, $catId, $marca, $anio, $horoIni]) {
        if (DB::count('SELECT COUNT(*) FROM equipos WHERE empresa_id=? AND codigo=?', [$empId, $codigo])) continue;
        $marcaId = $marcas[$marca] ?? null;
        ins('equipos', [
            'empresa_id'   => $empId,
            'codigo'       => $codigo,
            'nombre'       => $nombre,
            'marca_id'     => $marcaId,
            'modelo_id'    => $marcaId && !empty($modelos[$marcaId]) ? pick($modelos[$marcaId]) : null,
            'categoria_id' => $catId,
            'anio'         => $anio,
            'numero_serie' => strtoupper(substr(md5($codigo . $empId), 0, 10)),
            'horometro'    => $horoIni,
            'activo'       => 1,
        ]);
    }
}

$vehiculosNuevos = [
    1 => [
        ['KLPR23', 'Mercedes-Benz', 'Arocs 4142', 2021, 1,  68400],
        ['KLPR45', 'Scania',        'P410',       2019, 1, 121500],
        ['KLPR67', 'Volvo',         'FMX 500',    2022, 1,  41200],
        ['KLPR89', 'Mitsubishi',    'Fuso FJ',    2018, 1, 187300],
        ['LMNO12', 'Toyota',        'Hilux 4x4',  2022, 4,  28600],
        ['LMNO34', 'Chevrolet',     'D-Max',      2020, 4,  64800],
        ['LMNO56', 'Hyundai',       'H100',       2019, 5,  93400],
    ],
    2 => [
        ['PQRS78', 'Mercedes-Benz', 'Actros 2648', 2020, 1, 108700],
        ['PQRS90', 'Scania',        'R450',        2021, 1,  72300],
        ['TUVW12', 'Ford',          'Ranger',      2022, 4,  33900],
        ['TUVW34', 'Renault',       'Master',      2019, 5,  81600],
    ],
];
foreach ($vehiculosNuevos as $empId => $lista) {
    foreach ($lista as [$patente, $marca, $modelo, $anio, $catId, $odo]) {
        if (DB::count('SELECT COUNT(*) FROM vehiculos WHERE empresa_id=? AND patente=?', [$empId, $patente])) continue;
        ins('vehiculos', [
            'empresa_id'   => $empId,
            'patente'      => $patente,
            'marca'        => $marca,
            'modelo'       => $modelo,
            'anio'         => $anio,
            'categoria_id' => $catId,
            'color'        => pick(['Blanco', 'Amarillo', 'Rojo', 'Gris', 'Azul']),
            'odometro'     => $odo,
            'activo'       => 1,
        ]);
    }
}

// 2.5 Trabajadores, conductores y operadores adicionales
$nombresH = ['Luis','Carlos','Juan','Pedro','Marco','Iván','Hernán','Álvaro','Nelson','Rubén','Claudio','Fabián','Gonzalo','Óscar','Mario','Raúl','Sebastián','Ignacio','Camilo','Matías','Diego','Javier','Eduardo','Ricardo','Alejandro'];
$nombresM = ['Carolina','Paulina','Marcela','Valentina','Daniela','Camila','Francisca','Andrea','Javiera','Constanza'];
$apellidos = ['González','Rojas','Contreras','Silva','Fuentes','Espinoza','Valenzuela','Sepúlveda','Torres','Reyes','Navarro','Ortega','Cortés','Bravo','Miranda','Fernández','Guzmán','Peña','Sandoval','Molina','Herrera','Campos','Aravena','Godoy','Lagos','Riquelme'];

$objetivoTrab = [1 => 34, 2 => 22];
$rutTrab = 9200000;
foreach ($objetivoTrab as $empId => $objetivo) {
    $actual = DB::count('SELECT COUNT(*) FROM trabajadores WHERE empresa_id=?', [$empId]);
    for ($i = $actual; $i < $objetivo; $i++) {
        $esM    = chance(0.18);
        $nombre = $esM ? pick($nombresM) : pick($nombresH);
        $rutTrab += ri(15000, 90000);
        $cargoId = pick([3, 4, 5, 5, 5, 6, 6, 6, 7, 8, 9, 10, 11]);
        ins('trabajadores', [
            'empresa_id'    => $empId,
            'rut'           => rutChileno($rutTrab),
            'nombre'        => $nombre,
            'apellido'      => pick($apellidos),
            'cargo_id'      => $cargoId,
            'telefono'      => '+569 ' . ri(4000, 9999) . ' ' . ri(1000, 9999),
            'email'         => strtolower($nombre) . ri(10, 99) . '@' . ($empId === 1 ? 'tritransportes.cl' : 'cordillera.cl'),
            'fecha_ingreso' => date('Y-m-d', strtotime('-' . ri(120, 2200) . ' days')),
            'activo'        => 1,
        ]);
    }
}

// Conductores: trabajadores con cargo "Conductor" (6) que aún no lo son
foreach (DB::all("SELECT t.id FROM trabajadores t
                  LEFT JOIN conductores c ON c.trabajador_id = t.id
                  WHERE t.cargo_id = 6 AND c.id IS NULL AND t.activo = 1") as $t) {
    ins('conductores', [
        'trabajador_id'   => (int)$t['id'],
        'licencia_tipo'   => pick(['A-4', 'A-5', 'A-3']),
        'licencia_numero' => 'LC-' . ri(100000, 999999),
        // Vencimientos escalonados: algunos ya vencidos, otros por vencer
        'licencia_vence'  => date('Y-m-d', strtotime('+' . ri(-60, 700) . ' days')),
        'activo'          => 1,
    ]);
}

// Operadores: trabajadores con cargo "Operador de Maquinaria" (5)
foreach (DB::all("SELECT t.id FROM trabajadores t
                  LEFT JOIN operadores o ON o.trabajador_id = t.id
                  WHERE t.cargo_id = 5 AND o.id IS NULL AND t.activo = 1") as $t) {
    ins('operadores', [
        'trabajador_id' => (int)$t['id'],
        'certificacion' => pick(['Operador Excavadora', 'Operador Cargador Frontal', 'Operador Motoniveladora',
                                 'Operador Rodillo', 'Operador Bulldozer', 'Operador Retroexcavadora']),
        'certif_numero' => 'CERT-' . ri(10000, 99999),
        'certif_vence'  => date('Y-m-d', strtotime('+' . ri(-45, 640) . ' days')),
        'activo'        => 1,
    ]);
}

echo "  Maestros listos.\n\n";

// ── 3. Carga de maestros a memoria ───────────────────────────────────────────

/** Consumo nominal por categoría de equipo, en litros/hora. */
$CONSUMO_EQUIPO = [
    1 => 17.0,  // Excavadora
    2 => 22.5,  // Bulldozer
    3 => 15.0,  // Motoniveladora
    4 => 11.0,  // Retroexcavadora
    5 => 14.0,  // Cargador Frontal
    6 => 9.0,   // Compactador
    7 => 6.0,   // Grúa Horquilla
    8 => 8.5,   // Rodillo Compactador
    9 => 12.0,  // Camión Grúa
    10 => 20.0, // Perforadora
];

/** Rendimiento nominal por categoría de vehículo, en km/litro. */
$RENDIMIENTO_VEHICULO = [
    1 => 2.60, // Camión Tolva
    2 => 2.40, // Camión Mixer
    3 => 3.00, // Camión Pluma
    4 => 8.60, // Camioneta
    5 => 9.40, // Furgón
    6 => 4.20, // Bus
    7 => 2.10, // Retroexcavadora
    8 => 2.30, // Motoniveladora
];

$equipos = [];
foreach (DB::all('SELECT id, empresa_id, codigo, categoria_id, horometro FROM equipos WHERE activo=1 ORDER BY id') as $e) {
    $catId = (int)$e['categoria_id'];
    $equipos[$e['empresa_id']][] = [
        'id'        => (int)$e['id'],
        'codigo'    => $e['codigo'],
        'cat'       => $catId,
        'horometro' => (float)$e['horometro'],
        // Consumo real de cada máquina: se aparta algo del nominal de su categoría
        'consumo'   => ($CONSUMO_EQUIPO[$catId] ?? 12.0) * rf(0.88, 1.14),
        // Un activo de cada seis se va degradando durante el año → alerta de rendimiento
        'degrada'   => chance(0.17) ? rf(0.10, 0.22) : 0.0,
        'obra'      => null,
        'pend'      => rf(0, 120),   // litros consumidos aún no abastecidos
        'hMant'     => 0.0,          // horas desde la última mantención preventiva
    ];
}

$vehiculos = [];
foreach (DB::all('SELECT id, empresa_id, patente, categoria_id, odometro FROM vehiculos WHERE activo=1 ORDER BY id') as $v) {
    $catId = (int)$v['categoria_id'];
    $vehiculos[$v['empresa_id']][] = [
        'id'       => (int)$v['id'],
        'patente'  => $v['patente'],
        'cat'      => $catId,
        'odometro' => (float)$v['odometro'],
        'rend'     => ($RENDIMIENTO_VEHICULO[$catId] ?? 3.0) * rf(0.90, 1.12),
        'degrada'  => chance(0.15) ? rf(0.08, 0.18) : 0.0,
        'pend'     => rf(0, 80),
        'esCamion' => in_array($catId, [1, 2, 3], true),
    ];
}

$trabajadores = [];
foreach (DB::all('SELECT id, empresa_id, cargo_id FROM trabajadores WHERE activo=1') as $t) {
    $trabajadores[$t['empresa_id']][] = ['id' => (int)$t['id'], 'cargo' => (int)$t['cargo_id']];
}

$conductores = [];
foreach (DB::all('SELECT c.id, t.empresa_id FROM conductores c INNER JOIN trabajadores t ON t.id=c.trabajador_id WHERE c.activo=1') as $c) {
    $conductores[$c['empresa_id']][] = (int)$c['id'];
}

$operadores = [];
foreach (DB::all('SELECT o.id, t.empresa_id FROM operadores o INNER JOIN trabajadores t ON t.id=o.trabajador_id WHERE o.activo=1') as $o) {
    $operadores[$o['empresa_id']][] = (int)$o['id'];
}

$estanques = [];
foreach (DB::all('SELECT id, empresa_id FROM estanques WHERE activo=1') as $e) $estanques[$e['empresa_id']][] = (int)$e['id'];

$proveedores = [];
foreach (DB::all('SELECT id, empresa_id FROM proveedores_combustible WHERE activo=1') as $p) $proveedores[$p['empresa_id']][] = (int)$p['id'];

$materiales = DB::all('SELECT id, nombre, unidad_medida FROM materiales WHERE activo=1');
$clasifs    = [];
foreach (DB::all('SELECT id, material_id FROM clasificaciones_arido WHERE activo=1') as $c) {
    $clasifs[(int)$c['material_id']][] = (int)$c['id'];
}

// Cada equipo y vehículo queda adscrito a una obra
foreach ($equipos as $empId => &$lst) {
    foreach ($lst as &$eq) $eq['obra'] = pick($obras[$empId])['id'];
}
unset($lst, $eq);

$dias = diasHabiles($FECHA_INI, $FECHA_FIN);
echo "Simulando " . count($dias) . " días hábiles de operación...\n";

// ── 4. Simulación operacional día a día ──────────────────────────────────────

$loteCargas = new Lote('cargas_combustible', [
    'empresa_id','obra_id','contrato_id','fecha','tipo_unidad','vehiculo_id','equipo_id','conductor_id','operador_id',
    'litros','precio_litro','tipo_combustible','horometro','odometro','fuente_id','estanque_id','proveedor_id',
    'lat','lng','observacion','creado_por','created_at',
]);
$loteViajes = new Lote('viajes', [
    'empresa_id','obra_id','contrato_id','vehiculo_id','conductor_id','fecha','hora_salida','hora_llegada',
    'origen','destino','km_inicio','km_fin','material_id','cantidad','unidad','estado','observaciones','creado_por','created_at',
]);
$loteProd = new Lote('produccion_aridos', [
    'empresa_id','obra_id','material_id','clasificacion_id','fecha','cantidad','unidad','equipo_id','operador_id',
    'turno','observaciones','creado_por','created_at',
]);
$loteDesp = new Lote('despachos_aridos', [
    'empresa_id','obra_id','cliente_id','material_id','clasificacion_id','fecha','guia_numero','vehiculo_id','conductor_id',
    'cantidad','unidad','precio_unitario','destino','receptor','rut_receptor','observaciones','creado_por','created_at',
]);
$loteDeten = new Lote('detenciones_equipo', [
    'empresa_id','equipo_id','fecha_inicio','fecha_fin','motivo','tipo','creado_por','created_at',
]);
// Hijos: el id del padre ya se conoce al momento de encolar
$lotePersonal   = new Lote('parte_personal',       ['parte_id','trabajador_id','horas']);
$loteEquiposP   = new Lote('parte_equipos',        ['parte_id','equipo_id','horas_trabajo','horas_detenciones']);
$loteActividad  = new Lote('parte_actividades',    ['parte_id','descripcion','unidad','cantidad']);
$loteChkItems   = new Lote('checklist_items_resp', ['registro_id','item','cumple','observacion']);
$loteRepuestos  = new Lote('mantencion_repuestos', ['mantencion_id','descripcion','cantidad','costo_unitario']);

$origenes  = ['Planta Áridos Maipo', 'Pozo Río Maipo', 'Cantera Cerro Negro', 'Acopio Faena', 'Taller Central', 'Pozo Cachapoal'];
$destinos  = ['Obra Ruta G-46 Km 12', 'Frente de trabajo Norte', 'Planta Hormigón Buin', 'Acopio Obra', 'Sector Poniente', 'Botadero Autorizado'];
$actividadesFaena = [
    ['Escarpe y despeje de faja', 'm2'], ['Excavación en corte', 'm3'], ['Relleno estructural compactado', 'm3'],
    ['Colocación de base granular', 'm3'], ['Perfilado y compactación de subrasante', 'm2'],
    ['Transporte de material a botadero', 'm3'], ['Conformación de terraplén', 'm3'],
    ['Riego de imprimación', 'm2'], ['Colocación de tubería de drenaje', 'ml'], ['Despeje y limpieza de cunetas', 'ml'],
];
$climas = ['Despejado', 'Nublado', 'Parcial', 'Lluvia', 'Viento', 'Neblina'];

$guiaSeq   = ['1' => 4200, '2' => 7100];
$stock     = []; // [empresa][obra][material][clasif] => cantidad
$partesIds = [];
$contadores = ['cargas' => 0, 'viajes' => 0, 'guias' => 0, 'partes' => 0, 'prod' => 0, 'desp' => 0,
               'mant' => 0, 'deten' => 0, 'chk' => 0, 'inc' => 0, 'doc' => 0, 'avances' => 0];

$pdo->beginTransaction();

foreach ($dias as $diaIdx => $fecha) {
    $progreso = $diaIdx / max(1, count($dias) - 1); // 0..1 para la degradación
    $mesDia   = (int)date('n', strtotime($fecha));
    // Estacionalidad: menor actividad en invierno (jun-jul), mayor en verano
    $factorTemporada = in_array($mesDia, [6, 7], true) ? 0.78 : (in_array($mesDia, [12, 1, 2], true) ? 1.12 : 1.0);
    $esSabado = (int)date('N', strtotime($fecha)) === 6;
    if ($esSabado) $factorTemporada *= 0.55;

    foreach ($empresas as $emp) {
        $empId  = (int)$emp['id'];
        $userId = pick($usuarios[$empId] ?? [1]);

        // ─── EQUIPOS: horas de trabajo, consumo y abastecimiento ───
        foreach ($equipos[$empId] as $k => $eq) {
            if (chance(0.08)) continue; // equipo sin actividad ese día

            $horas = round(rf(5.5, 9.5) * $factorTemporada, 1);
            if ($horas <= 0.5) continue;

            $equipos[$empId][$k]['horometro'] += $horas;
            $equipos[$empId][$k]['hMant']     += $horas;

            // Consumo efectivo: nominal + degradación progresiva del activo
            $consumoHoy = $eq['consumo'] * (1 + $eq['degrada'] * $progreso) * ruido(0.07);
            $equipos[$empId][$k]['pend'] += $horas * $consumoHoy;

            // Se abastece al superar el umbral del estanque de la máquina
            $umbral = 180 + ($CONSUMO_EQUIPO[$eq['cat']] ?? 12) * 9;
            if ($equipos[$empId][$k]['pend'] >= $umbral) {
                $litros = $equipos[$empId][$k]['pend'];
                // Anomalías puntuales: sobreconsumo o carga irregular
                if (chance(0.022)) $litros *= rf(1.35, 1.75);
                $equipos[$empId][$k]['pend'] = 0;

                $esEstanque = chance(0.72);
                $loteCargas->add([
                    $empId, $eq['obra'], null, $fecha, 'equipo', null, $eq['id'], null,
                    !empty($operadores[$empId]) ? pick($operadores[$empId]) : null,
                    round($litros, 2), precioLitro($fecha), 'Petróleo',
                    round($equipos[$empId][$k]['horometro'], 1), null,
                    $esEstanque ? 1 : pick([2, 3]),
                    $esEstanque && !empty($estanques[$empId]) ? pick($estanques[$empId]) : null,
                    !$esEstanque && !empty($proveedores[$empId]) ? pick($proveedores[$empId]) : null,
                    round(rf(-34.30, -33.40), 7), round(rf(-70.90, -70.50), 7),
                    chance(0.12) ? pick(['Carga en frente de trabajo', 'Abastecimiento matinal', 'Recarga tras mantención']) : null,
                    $userId, $fecha . ' ' . sprintf('%02d:%02d:00', ri(7, 18), ri(0, 59)),
                ]);
                $contadores['cargas']++;
            }

            // Mantención preventiva cada ~250 horas de horómetro
            if ($equipos[$empId][$k]['hMant'] >= 250) {
                $equipos[$empId][$k]['hMant'] = 0;
                $mantId = ins('mantenciones', [
                    'empresa_id'    => $empId,
                    'equipo_id'     => $eq['id'],
                    'tipo'          => 'preventiva',
                    'fecha'         => $fecha,
                    'horometro'     => round($equipos[$empId][$k]['horometro'], 1),
                    'descripcion'   => pick(['Cambio de aceite y filtros', 'Mantención programada 250 h',
                                             'Revisión de sistema hidráulico', 'Engrase general y revisión de tren de rodado']),
                    'tecnico'       => pick(['Jorge Vega', 'Patricio Díaz', 'Taller Externo Maipo', 'Víctor Riquelme']),
                    'costo_total'   => 0,
                    'estado'        => 'completada',
                    'observaciones' => 'Mantención preventiva según pauta del fabricante.',
                    'creado_por'    => $userId,
                    'created_at'    => $fecha . ' 09:00:00',
                ]);
                $costo = 0;
                $nRep  = ri(2, 5);
                for ($r = 0; $r < $nRep; $r++) {
                    $cu = ri(12000, 320000);
                    $cant = ri(1, 6);
                    $costo += $cu * $cant;
                    $loteRepuestos->add([
                        $mantId,
                        pick(['Filtro de aceite', 'Filtro de aire', 'Aceite hidráulico 20L',
                              'Filtro de petróleo', 'Grasa EP2', 'Correa de ventilador',
                              'Refrigerante', 'Filtro separador de agua']),
                        $cant, $cu,
                    ]);
                }
                DB::run('UPDATE mantenciones SET costo_total=? WHERE id=?', [$costo, $mantId]);
                $contadores['mant']++;

                // Detención asociada a la mantención
                $loteDeten->add([
                    $empId, $eq['id'], $fecha . ' 08:00:00',
                    date('Y-m-d', strtotime($fecha . ' +' . ri(0, 1) . ' day')) . ' ' . sprintf('%02d:00:00', ri(13, 19)),
                    'Mantención preventiva programada', 'mantencion', $userId, $fecha . ' 08:00:00',
                ]);
                $contadores['deten']++;
            }

            // Falla correctiva ocasional
            if (chance(0.004)) {
                $mantId = ins('mantenciones', [
                    'empresa_id'    => $empId,
                    'equipo_id'     => $eq['id'],
                    'tipo'          => 'correctiva',
                    'fecha'         => $fecha,
                    'horometro'     => round($equipos[$empId][$k]['horometro'], 1),
                    'descripcion'   => pick(['Falla en bomba hidráulica', 'Reemplazo de manguera de alta presión',
                                             'Reparación de sistema eléctrico', 'Cambio de zapatas de oruga',
                                             'Falla de turbo', 'Reparación de sistema de frenos']),
                    'tecnico'       => pick(['Jorge Vega', 'Patricio Díaz', 'Servicio Técnico Autorizado']),
                    'costo_total'   => ri(280000, 3400000),
                    'estado'        => chance(0.82) ? 'completada' : pick(['pendiente', 'en_proceso']),
                    'observaciones' => 'Detención no programada por falla en terreno.',
                    'creado_por'    => $userId,
                    'created_at'    => $fecha . ' 10:30:00',
                ]);
                $diasDet = ri(1, 6);
                $loteDeten->add([
                    $empId, $eq['id'], $fecha . ' 10:00:00',
                    date('Y-m-d', strtotime($fecha . " +{$diasDet} day")) . ' 17:00:00',
                    'Falla mecánica en terreno', 'falla', $userId, $fecha . ' 10:00:00',
                ]);
                $contadores['mant']++;
                $contadores['deten']++;
            }
        }

        // ─── VEHÍCULOS: viajes, kilometraje y abastecimiento ───
        foreach ($vehiculos[$empId] as $k => $ve) {
            if (chance(0.10)) continue;

            $obraV   = pick($obras[$empId]);
            $nViajes = $ve['esCamion'] ? (int)round(ri(2, 5) * $factorTemporada) : (chance(0.45) ? 1 : 0);
            $kmDia   = 0;

            for ($v = 0; $v < $nViajes; $v++) {
                $kmViaje = $ve['esCamion'] ? rf(28, 95) : rf(35, 140);
                $kmIni   = (int)round($ve['odometro'] + $kmDia);
                $kmDia  += $kmViaje;
                $kmFin   = (int)round($ve['odometro'] + $kmDia);

                $hSalida = ri(7, 16);
                $mat     = pick($materiales);
                $estado  = chance(0.94) ? 'completado' : pick(['cancelado', 'en_ruta', 'programado']);
                $cantidad = $ve['esCamion'] ? round(rf(8, 22), 2) : null;

                $loteViajes->add([
                    $empId, $obraV['id'], null, $ve['id'],
                    !empty($conductores[$empId]) ? pick($conductores[$empId]) : null,
                    $fecha, sprintf('%02d:%02d:00', $hSalida, ri(0, 59)),
                    sprintf('%02d:%02d:00', min(23, $hSalida + ri(1, 3)), ri(0, 59)),
                    pick($origenes), pick($destinos), $kmIni, $kmFin,
                    $ve['esCamion'] ? (int)$mat['id'] : null, $cantidad,
                    $ve['esCamion'] ? $mat['unidad_medida'] : null,
                    $estado,
                    chance(0.10) ? pick(['Retraso por congestión en ruta', 'Carga verificada en origen', 'Ruta alternativa por obras']) : null,
                    $userId, $fecha . ' ' . sprintf('%02d:%02d:00', $hSalida, ri(0, 59)),
                ]);
                $contadores['viajes']++;

                // Guía de despacho para los viajes de camión completados
                if ($ve['esCamion'] && $estado === 'completado' && chance(0.72)) {
                    $guiaSeq[(string)$empId]++;
                    ins('guias_despacho', [
                        'empresa_id'    => $empId,
                        'viaje_id'      => null,
                        'obra_id'       => $obraV['id'],
                        'numero_guia'   => ($empId === 1 ? 'GD-' : 'AND-') . $guiaSeq[(string)$empId],
                        'fecha'         => $fecha,
                        'vehiculo_id'   => $ve['id'],
                        'conductor_id'  => !empty($conductores[$empId]) ? pick($conductores[$empId]) : null,
                        'material_id'   => (int)$mat['id'],
                        'cantidad'      => $cantidad,
                        'unidad'        => $mat['unidad_medida'],
                        'origen'        => pick($origenes),
                        'destino'       => pick($destinos),
                        'receptor'      => pick($nombresH) . ' ' . pick($apellidos),
                        'rut_receptor'  => rutChileno(ri(7000000, 21000000)),
                        'observaciones' => chance(0.15) ? 'Recepción conforme en destino.' : null,
                        'creado_por'    => $userId,
                        'created_at'    => $fecha . ' 17:00:00',
                    ]);
                    $contadores['guias']++;
                }
            }

            if ($kmDia <= 0) continue;

            $vehiculos[$empId][$k]['odometro'] += $kmDia;
            $rendHoy = $ve['rend'] / (1 + $ve['degrada'] * $progreso);
            $vehiculos[$empId][$k]['pend'] += $kmDia / max(0.5, $rendHoy * ruido(0.06));

            $umbralV = $ve['esCamion'] ? 260 : 55;
            if ($vehiculos[$empId][$k]['pend'] >= $umbralV) {
                $litros = $vehiculos[$empId][$k]['pend'];
                if (chance(0.02)) $litros *= rf(1.3, 1.6);
                $vehiculos[$empId][$k]['pend'] = 0;

                $esEstanque = chance(0.55);
                $loteCargas->add([
                    $empId, $obraV['id'], null, $fecha, 'vehiculo', $ve['id'], null,
                    !empty($conductores[$empId]) ? pick($conductores[$empId]) : null, null,
                    round($litros, 2), precioLitro($fecha), 'Petróleo',
                    null, (int)round($vehiculos[$empId][$k]['odometro']),
                    $esEstanque ? 1 : 2,
                    $esEstanque && !empty($estanques[$empId]) ? pick($estanques[$empId]) : null,
                    !$esEstanque && !empty($proveedores[$empId]) ? pick($proveedores[$empId]) : null,
                    round(rf(-34.30, -33.40), 7), round(rf(-70.90, -70.50), 7),
                    chance(0.10) ? pick(['Carga en bencinera de ruta', 'Estanque lleno', 'Abastecimiento previo a viaje largo']) : null,
                    $userId, $fecha . ' ' . sprintf('%02d:%02d:00', ri(7, 19), ri(0, 59)),
                ]);
                $contadores['cargas']++;
            }
        }

        // ─── ÁRIDOS: producción y despacho ───
        $nProd = (int)round(ri(1, 3) * $factorTemporada);
        for ($p = 0; $p < $nProd; $p++) {
            $mat     = pick($materiales);
            $matId   = (int)$mat['id'];
            $clasId  = !empty($clasifs[$matId]) ? pick($clasifs[$matId]) : null;
            $obraP   = pick($obras[$empId]);
            $cant    = round(rf(80, 420) * $factorTemporada, 2);

            $loteProd->add([
                $empId, $obraP['id'], $matId, $clasId, $fecha, $cant, 'm3',
                !empty($equipos[$empId]) ? pick($equipos[$empId])['id'] : null,
                !empty($operadores[$empId]) ? pick($operadores[$empId]) : null,
                pick(['mañana', 'mañana', 'tarde', 'noche']),
                chance(0.12) ? pick(['Producción con planta a 80% de capacidad', 'Detención por mantención de chancador']) : null,
                $userId, $fecha . ' 18:00:00',
            ]);
            $contadores['prod']++;

            $stock[$empId][$obraP['id']][$matId][(int)$clasId] =
                ($stock[$empId][$obraP['id']][$matId][(int)$clasId] ?? 0) + $cant;
        }

        $nDesp = (int)round(ri(1, 4) * $factorTemporada);
        for ($d = 0; $d < $nDesp; $d++) {
            $mat    = pick($materiales);
            $matId  = (int)$mat['id'];
            $clasId = !empty($clasifs[$matId]) ? pick($clasifs[$matId]) : null;
            $obraD  = pick($obras[$empId]);
            $cant   = round(rf(15, 95), 2);

            $disp = $stock[$empId][$obraD['id']][$matId][(int)$clasId] ?? 0;
            if ($disp < $cant) continue; // no se despacha más de lo producido

            $stock[$empId][$obraD['id']][$matId][(int)$clasId] = $disp - $cant;
            $guiaSeq[(string)$empId]++;

            $loteDesp->add([
                $empId, $obraD['id'], pick($clientes[$empId]), $matId, $clasId, $fecha,
                ($empId === 1 ? 'DA-' : 'ADA-') . $guiaSeq[(string)$empId],
                !empty($vehiculos[$empId]) ? pick($vehiculos[$empId])['id'] : null,
                !empty($conductores[$empId]) ? pick($conductores[$empId]) : null,
                $cant, 'm3', ri(7200, 16500), pick($destinos),
                pick($nombresH) . ' ' . pick($apellidos), rutChileno(ri(7000000, 21000000)),
                chance(0.10) ? 'Material recepcionado conforme.' : null,
                $userId, $fecha . ' ' . sprintf('%02d:%02d:00', ri(8, 18), ri(0, 59)),
            ]);
            $contadores['desp']++;
        }

        // ─── PARTES DIARIOS DE FAENA ───
        foreach ($obras[$empId] as $ob) {
            if (chance(0.55)) continue; // no toda obra emite parte todos los días

            $parteId = ins('partes_diarios', [
                'empresa_id'    => $empId,
                'obra_id'       => $ob['id'],
                'contrato_id'   => !empty($contratos[$empId]) ? (int)pick($contratos[$empId])['id'] : null,
                'fecha'         => $fecha,
                'supervisor_id' => !empty($trabajadores[$empId]) ? pick($trabajadores[$empId])['id'] : null,
                'clima'         => pick($climas),
                'estado'        => chance(0.88) ? 'cerrado' : 'borrador',
                'observaciones' => pick([
                    'Jornada sin novedades. Avance conforme a programa.',
                    'Se registró detención por lluvia durante la tarde.',
                    'Trabajo normal. Se solicita reposición de material.',
                    'Frente de trabajo con rendimiento sobre lo programado.',
                    'Atraso por espera de camiones en planta.',
                ]),
                'lat'        => round(rf(-34.30, -33.40), 7),
                'lng'        => round(rf(-70.90, -70.50), 7),
                'creado_por' => $userId,
                'created_at' => $fecha . ' 18:30:00',
            ]);
            $contadores['partes']++;

            // Personal del parte
            $nPers = ri(4, 9);
            $usados = [];
            for ($i = 0; $i < $nPers; $i++) {
                $tr = pick($trabajadores[$empId]);
                if (in_array($tr['id'], $usados, true)) continue;
                $usados[] = $tr['id'];
                $lotePersonal->add([$parteId, $tr['id'], round(rf(6, 10), 1)]);
            }

            // Equipos del parte
            $nEq = ri(2, 4);
            $usadosEq = [];
            for ($i = 0; $i < $nEq; $i++) {
                $eq = pick($equipos[$empId]);
                if (in_array($eq['id'], $usadosEq, true)) continue;
                $usadosEq[] = $eq['id'];
                $loteEquiposP->add([
                    $parteId, $eq['id'], round(rf(4, 9.5), 1),
                    chance(0.3) ? round(rf(0.5, 3), 1) : 0,
                ]);
            }

            // Actividades ejecutadas
            $nAct = ri(2, 4);
            for ($i = 0; $i < $nAct; $i++) {
                [$desc, $uni] = pick($actividadesFaena);
                $loteActividad->add([$parteId, $desc, $uni, round(rf(20, 850), 2)]);
            }
        }

        // ─── SEGURIDAD: checklists pre-operacionales ───
        $nChk = (int)round(ri(1, 4) * $factorTemporada);
        for ($c = 0; $c < $nChk; $c++) {
            $esEquipo = chance(0.6);
            $resultado = chance(0.80) ? 'aprobado' : (chance(0.7) ? 'observado' : 'rechazado');

            $chkId = ins('checklists_registros', [
                'empresa_id'    => $empId,
                'tipo_id'       => $esEquipo ? pick([1, 2, 3]) : 4,
                'obra_id'       => pick($obras[$empId])['id'],
                'equipo_id'     => $esEquipo ? pick($equipos[$empId])['id'] : null,
                'vehiculo_id'   => $esEquipo ? null : pick($vehiculos[$empId])['id'],
                'trabajador_id' => pick($trabajadores[$empId])['id'],
                'fecha'         => $fecha,
                'turno'         => pick(['mañana', 'mañana', 'tarde', 'noche']),
                'resultado'     => $resultado,
                'observaciones' => $resultado === 'aprobado' ? null
                    : pick(['Se detecta fuga menor de aceite, se programa revisión.',
                            'Falta extintor vigente en cabina.',
                            'Luces traseras con falla intermitente.',
                            'Neumático con desgaste irregular.',
                            'Cinturón de seguridad con hebilla dañada.']),
                'creado_por'    => $userId,
                'created_at'    => $fecha . ' 07:30:00',
            ]);
            $contadores['chk']++;

            $items = ['Niveles de aceite y refrigerante', 'Estado de neumáticos / tren de rodado',
                      'Luces y señalización', 'Frenos y freno de mano', 'Extintor y botiquín',
                      'Alarma de retroceso', 'Cinturón de seguridad', 'Espejos y vidrios'];
            shuffle($items);
            foreach (array_slice($items, 0, 5) as $it) {
                $cumple = $resultado === 'aprobado' ? 1 : (chance(0.75) ? 1 : 0);
                $loteChkItems->add([
                    $chkId, $it, $cumple,
                    $cumple ? null : 'Requiere corrección antes de operar.',
                ]);
            }
        }

        // ─── SEGURIDAD: incidentes ocasionales ───
        if (chance(0.055)) {
            $grav = chance(0.62) ? 'leve' : (chance(0.75) ? 'moderado' : 'grave');
            $tipo = chance(0.45) ? 'cuasi_accidente' : (chance(0.6) ? 'incidente' : 'accidente');
            ins('incidentes', [
                'empresa_id'    => $empId,
                'obra_id'       => pick($obras[$empId])['id'],
                'fecha'         => $fecha,
                'hora'          => sprintf('%02d:%02d:00', ri(8, 18), ri(0, 59)),
                'tipo'          => $tipo,
                'gravedad'      => $grav,
                'descripcion'   => pick([
                    'Trabajador sufre golpe en mano al manipular herramienta sin guantes.',
                    'Camión tolva desliza en pendiente por terreno húmedo, sin daños.',
                    'Caída de material desde altura en zona demarcada, sin personas expuestas.',
                    'Contacto menor entre excavadora y camión durante maniobra de carga.',
                    'Trabajador reporta dolor lumbar tras levantamiento manual de carga.',
                    'Amago de incendio en tablero eléctrico de planta, controlado con extintor.',
                ]),
                'lugar'         => pick(['Frente de trabajo Norte', 'Planta de áridos', 'Taller de mantención',
                                         'Acceso a faena', 'Sector acopio']),
                'trabajador_id' => pick($trabajadores[$empId])['id'],
                'dias_perdidos' => $grav === 'leve' ? 0 : ($grav === 'moderado' ? ri(1, 5) : ri(6, 30)),
                'acciones'      => pick([
                    'Charla de seguridad al equipo y refuerzo de uso de EPP.',
                    'Investigación de incidente y actualización de procedimiento.',
                    'Señalización adicional y demarcación de la zona.',
                    'Capacitación en manejo manual de cargas.',
                ]),
                'estado'        => chance(0.7) ? 'cerrado' : pick(['reportado', 'investigando']),
                'creado_por'    => $userId,
                'created_at'    => $fecha . ' 19:00:00',
            ]);
            $contadores['inc']++;
        }
    }

    // Vuelca lotes cada 20 días para no acumular memoria
    if ($diaIdx % 20 === 0) {
        $loteCargas->flush(); $loteViajes->flush();   $loteProd->flush();
        $loteDesp->flush();   $loteDeten->flush();    $lotePersonal->flush();
        $loteEquiposP->flush(); $loteActividad->flush(); $loteChkItems->flush();
        $loteRepuestos->flush();
        echo "  " . str_pad($fecha, 12) . " (" . round($progreso * 100) . "%)\n";
    }
}

$loteCargas->flush(); $loteViajes->flush();   $loteProd->flush();
$loteDesp->flush();   $loteDeten->flush();    $lotePersonal->flush();
$loteEquiposP->flush(); $loteActividad->flush(); $loteChkItems->flush();
$loteRepuestos->flush();

$pdo->commit();
echo "\nOperación simulada.\n";

// ── 5. Cierres derivados ─────────────────────────────────────────────────────

$pdo->beginTransaction();

// 5.1 Horómetros y odómetros finales en las fichas de los activos
foreach ($equipos as $empId => $lst) {
    foreach ($lst as $eq) {
        DB::run('UPDATE equipos SET horometro=? WHERE id=?', [round($eq['horometro'], 1), $eq['id']]);
    }
}
foreach ($vehiculos as $empId => $lst) {
    foreach ($lst as $ve) {
        DB::run('UPDATE vehiculos SET odometro=? WHERE id=?', [(int)round($ve['odometro']), $ve['id']]);
    }
}

// 5.2 Stock de áridos consolidado
foreach ($stock as $empId => $porObra) {
    foreach ($porObra as $obraId => $porMat) {
        foreach ($porMat as $matId => $porClas) {
            foreach ($porClas as $clasId => $cant) {
                if ($cant <= 0) continue;
                $clasId = $clasId ?: null;
                $ya = DB::one('SELECT id FROM stock_aridos WHERE empresa_id=? AND obra_id=? AND material_id=? AND (clasificacion_id <=> ?)',
                              [$empId, $obraId, $matId, $clasId]);
                if ($ya) {
                    DB::run('UPDATE stock_aridos SET cantidad=? WHERE id=?', [round($cant, 2), $ya['id']]);
                } else {
                    ins('stock_aridos', [
                        'empresa_id'       => $empId,
                        'obra_id'          => $obraId,
                        'material_id'      => $matId,
                        'clasificacion_id' => $clasId,
                        'cantidad'         => round($cant, 2),
                        'unidad'           => 'm3',
                    ]);
                }
            }
        }
    }
}

// 5.3 Ítems de contrato y avances mensuales
$partidas = [
    ['P-01', 'Movimiento de tierra y excavaciones', 'm3',  9800, 4200],
    ['P-02', 'Base y sub-base granular',            'm3',  6400, 8900],
    ['P-03', 'Transporte de material',              'm3', 15200, 2600],
    ['P-04', 'Obras de drenaje',                    'ml',  1800, 24500],
    ['P-05', 'Señalización y seguridad vial',       'gl',     1, 18500000],
    ['P-06', 'Perfilado y compactación',            'm2', 24000, 1350],
];

foreach ($contratos as $empId => $lista) {
    foreach ($lista as $ct) {
        $ctId = (int)$ct['id'];
        if (DB::count('SELECT COUNT(*) FROM items_contrato WHERE contrato_id=?', [$ctId])) continue;

        $items = [];
        foreach (array_slice($partidas, 0, ri(4, 6)) as [$cod, $desc, $uni, $cant, $pu]) {
            $cantAj = round($cant * rf(0.7, 1.3), 2);
            $puAj   = round($pu * rf(0.85, 1.2));
            $items[] = [
                'id'   => ins('items_contrato', [
                    'contrato_id'     => $ctId,
                    'codigo'          => $cod,
                    'descripcion'     => $desc,
                    'unidad'          => $uni,
                    'cantidad_total'  => $cantAj,
                    'precio_unitario' => $puAj,
                    'activo'          => 1,
                ]),
                'cant' => $cantAj,
                'pu'   => $puAj,
            ];
        }

        // Avance mensual desde el inicio del contrato hasta hoy
        $mes    = new DateTime(max($ct['fecha_inicio'], $FECHA_INI));
        $hoyDt  = new DateTime($FECHA_FIN);
        $avance = [];
        while ($mes <= $hoyDt) {
            foreach ($items as $it) {
                $pctMes = rf(0.04, 0.13);
                $acum   = ($avance[$it['id']] ?? 0) + $pctMes;
                if ($acum > 0.98) continue;
                $avance[$it['id']] = $acum;

                $cantMes = round($it['cant'] * $pctMes, 2);
                ins('avances_contrato', [
                    'contrato_id' => $ctId,
                    'item_id'     => $it['id'],
                    'fecha'       => $mes->format('Y-m-t'),
                    'cantidad'    => $cantMes,
                    'monto'       => round($cantMes * $it['pu']),
                    'observacion' => 'Estado de pago mensual ' . $mes->format('m/Y'),
                    'creado_por'  => pick($usuarios[$empId] ?? [1]),
                    'created_at'  => $mes->format('Y-m-t') . ' 12:00:00',
                ]);
                $contadores['avances']++;
            }
            $mes->modify('first day of next month');
        }
    }
}

// 5.4 Documentos con vencimientos escalonados (alimenta las alertas)
$tiposDoc = DB::all('SELECT id, nombre, aplica_a, requiere_vencimiento FROM tipos_documento WHERE activo=1');
$estVigente  = 1; $estPorVencer = 2; $estVencido = 3;

foreach ($empresas as $emp) {
    $empId = (int)$emp['id'];
    if (DB::count('SELECT COUNT(*) FROM documentos WHERE empresa_id=? AND activo=1', [$empId]) > 40) continue;

    // Documentos de vehículos: seguro, revisión técnica y permiso de circulación
    foreach (DB::all('SELECT id, patente FROM vehiculos WHERE empresa_id=? AND activo=1', [$empId]) as $v) {
        foreach ([4 => 'Seguro Obligatorio', 5 => 'Revisión Técnica', 6 => 'Permiso de Circulación'] as $tipoId => $nom) {
            $diasVence = ri(-40, 330);
            ins('documentos', [
                'empresa_id'        => $empId,
                'tipo_id'           => $tipoId,
                'estado_id'         => $diasVence < 0 ? $estVencido : ($diasVence <= 30 ? $estPorVencer : $estVigente),
                'obra_id'           => null,
                'entidad_tipo'      => 'vehiculo',
                'entidad_id'        => (int)$v['id'],
                'titulo'            => $nom . ' — ' . $v['patente'],
                'numero'            => strtoupper(substr(md5($v['patente'] . $tipoId), 0, 8)),
                'fecha_emision'     => date('Y-m-d', strtotime("{$diasVence} days -1 year")),
                'fecha_vencimiento' => date('Y-m-d', strtotime("{$diasVence} days")),
                'observaciones'     => null,
                'activo'            => 1,
                'creado_por'        => pick($usuarios[$empId] ?? [1]),
            ]);
            $contadores['doc']++;
        }
    }

    // Certificados de equipos
    foreach (DB::all('SELECT id, codigo FROM equipos WHERE empresa_id=? AND activo=1', [$empId]) as $e) {
        $diasVence = ri(-30, 400);
        ins('documentos', [
            'empresa_id'        => $empId,
            'tipo_id'           => 8,
            'estado_id'         => $diasVence < 0 ? $estVencido : ($diasVence <= 30 ? $estPorVencer : $estVigente),
            'entidad_tipo'      => 'equipo',
            'entidad_id'        => (int)$e['id'],
            'titulo'            => 'Certificado de Calidad — ' . $e['codigo'],
            'numero'            => 'CC-' . ri(10000, 99999),
            'fecha_emision'     => date('Y-m-d', strtotime("{$diasVence} days -1 year")),
            'fecha_vencimiento' => date('Y-m-d', strtotime("{$diasVence} days")),
            'activo'            => 1,
            'creado_por'        => pick($usuarios[$empId] ?? [1]),
        ]);
        $contadores['doc']++;
    }

    // Permisos de obra
    foreach ($obras[$empId] as $ob) {
        $diasVence = ri(20, 420);
        ins('documentos', [
            'empresa_id'        => $empId,
            'tipo_id'           => 9,
            'estado_id'         => $diasVence <= 30 ? $estPorVencer : $estVigente,
            'obra_id'           => $ob['id'],
            'entidad_tipo'      => 'general',
            'titulo'            => 'Permiso de Obra — ' . $ob['nombre'],
            'numero'            => 'PO-' . ri(1000, 9999),
            'fecha_emision'     => date('Y-m-d', strtotime("{$diasVence} days -1 year")),
            'fecha_vencimiento' => date('Y-m-d', strtotime("{$diasVence} days")),
            'activo'            => 1,
            'creado_por'        => pick($usuarios[$empId] ?? [1]),
        ]);
        $contadores['doc']++;
    }
}

$pdo->commit();

// ── 6. Resumen ───────────────────────────────────────────────────────────────

echo "\n=== RESUMEN DE GENERACIÓN ===\n";
foreach ([
    'cargas_combustible' => 'Cargas de combustible',
    'viajes'             => 'Viajes de transporte',
    'guias_despacho'     => 'Guías de despacho',
    'partes_diarios'     => 'Partes de faena',
    'parte_personal'     => '  · personal en partes',
    'parte_equipos'      => '  · equipos en partes',
    'parte_actividades'  => '  · actividades en partes',
    'produccion_aridos'  => 'Producción de áridos',
    'despachos_aridos'   => 'Despachos de áridos',
    'stock_aridos'       => 'Saldos de stock',
    'mantenciones'       => 'Mantenciones',
    'mantencion_repuestos' => '  · repuestos',
    'detenciones_equipo' => 'Detenciones de equipo',
    'checklists_registros' => 'Checklists',
    'checklist_items_resp' => '  · ítems de checklist',
    'incidentes'         => 'Incidentes',
    'documentos'         => 'Documentos',
    'items_contrato'     => 'Ítems de contrato',
    'avances_contrato'   => 'Avances de contrato',
    'equipos'            => 'Equipos (maestro)',
    'vehiculos'          => 'Vehículos (maestro)',
    'trabajadores'       => 'Trabajadores (maestro)',
] as $tabla => $label) {
    printf("  %-26s %8d\n", $label, DB::count("SELECT COUNT(*) FROM {$tabla}"));
}

printf("\nTiempo total: %.1f s\n", microtime(true) - $t0);
echo "Listo.\n";
