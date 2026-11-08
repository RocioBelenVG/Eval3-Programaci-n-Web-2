<?php
/**
 * TriTransportes — M01 Dashboard Gerencial
 * ----------------------------------------------------------------------------
 * Vista consolidada de la operación: indicadores del mes, alertas accionables
 * provenientes de todos los módulos, tendencias de 12 meses y actividad
 * reciente. Cada tarjeta enlaza al módulo que origina el dato.
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::require();

$emp   = Auth::empresaId();
$hoy   = date('Y-m-d');
$mes0  = date('Y-m-01');
$mes12 = date('Y-m-01', strtotime('-11 months'));

// ── Maestros ────────────────────────────────────────────────────────────────
$obras_activas = DB::count("SELECT COUNT(*) FROM obras        WHERE empresa_id=? AND estado='activa' AND activo=1", [$emp]);
$contratos_act = DB::count("SELECT COUNT(*) FROM contratos    WHERE empresa_id=? AND activo=1", [$emp]);
$equipos_total = DB::count("SELECT COUNT(*) FROM equipos      WHERE empresa_id=? AND activo=1", [$emp]);
$trabajadores  = DB::count("SELECT COUNT(*) FROM trabajadores WHERE empresa_id=? AND activo=1", [$emp]);
$vehiculos     = DB::count("SELECT COUNT(*) FROM vehiculos    WHERE empresa_id=? AND activo=1", [$emp]);
$clientes      = DB::count("SELECT COUNT(*) FROM clientes     WHERE empresa_id=? AND activo=1", [$emp]);

// ── Indicadores del mes en curso ────────────────────────────────────────────
$litros_mes = (float) (DB::one("SELECT COALESCE(SUM(litros),0) t FROM cargas_combustible
                                WHERE empresa_id=? AND fecha BETWEEN ? AND ?", [$emp, $mes0, $hoy])['t'] ?? 0);
$costo_mes  = (float) (DB::one("SELECT COALESCE(SUM(litros*precio_litro),0) t FROM cargas_combustible
                                WHERE empresa_id=? AND fecha BETWEEN ? AND ?", [$emp, $mes0, $hoy])['t'] ?? 0);
$viajes_mes = DB::count("SELECT COUNT(*) FROM viajes WHERE empresa_id=? AND fecha BETWEEN ? AND ?", [$emp, $mes0, $hoy]);
$km_mes     = (float) (DB::one("SELECT COALESCE(SUM(GREATEST(km_fin-km_inicio,0)),0) t FROM viajes
                                WHERE empresa_id=? AND fecha BETWEEN ? AND ? AND estado='completado'", [$emp, $mes0, $hoy])['t'] ?? 0);
$prod_mes   = (float) (DB::one("SELECT COALESCE(SUM(cantidad),0) t FROM produccion_aridos
                                WHERE empresa_id=? AND fecha BETWEEN ? AND ?", [$emp, $mes0, $hoy])['t'] ?? 0);
$desp_mes   = (float) (DB::one("SELECT COALESCE(SUM(cantidad),0) t FROM despachos_aridos
                                WHERE empresa_id=? AND fecha BETWEEN ? AND ?", [$emp, $mes0, $hoy])['t'] ?? 0);
$partes_mes = DB::count("SELECT COUNT(*) FROM partes_diarios WHERE empresa_id=? AND fecha BETWEEN ? AND ?", [$emp, $mes0, $hoy]);
$horas_mes  = (float) (DB::one("SELECT COALESCE(SUM(pe.horas_trabajo),0) t
                                FROM parte_equipos pe
                                INNER JOIN partes_diarios p ON p.id = pe.parte_id
                                WHERE p.empresa_id=? AND p.fecha BETWEEN ? AND ?", [$emp, $mes0, $hoy])['t'] ?? 0);

// Rendimiento global del mes (mismo criterio que el módulo de combustible)
$rend = DB::one("
    WITH base AS (
        SELECT tipo_unidad, litros, horometro, odometro,
               LAG(horometro) OVER (PARTITION BY equipo_id   ORDER BY fecha, id) AS horo_prev,
               LAG(odometro)  OVER (PARTITION BY vehiculo_id ORDER BY fecha, id) AS odo_prev
        FROM cargas_combustible
        WHERE empresa_id = ? AND fecha BETWEEN ? AND ?
    )
    SELECT
      SUM(CASE WHEN tipo_unidad='equipo'   AND horo_prev IS NOT NULL
                AND horometro-horo_prev BETWEEN 1 AND 400 THEN litros ELSE 0 END) AS litros_eq,
      SUM(CASE WHEN tipo_unidad='equipo'   AND horo_prev IS NOT NULL
                AND horometro-horo_prev BETWEEN 1 AND 400 THEN horometro-horo_prev ELSE 0 END) AS horas,
      SUM(CASE WHEN tipo_unidad='vehiculo' AND odo_prev IS NOT NULL
                AND odometro-odo_prev BETWEEN 10 AND 4000 THEN litros ELSE 0 END) AS litros_ve,
      SUM(CASE WHEN tipo_unidad='vehiculo' AND odo_prev IS NOT NULL
                AND odometro-odo_prev BETWEEN 10 AND 4000 THEN odometro-odo_prev ELSE 0 END) AS km
    FROM base", [$emp, date('Y-m-01', strtotime('-2 months')), $hoy]);

$lhFlota  = (float)($rend['horas'] ?? 0)     > 0 ? $rend['litros_eq'] / $rend['horas'] : 0;
$kmlFlota = (float)($rend['litros_ve'] ?? 0) > 0 ? $rend['km'] / $rend['litros_ve']    : 0;

// ── ALERTAS: lo que requiere acción hoy ─────────────────────────────────────
$alertas = [];

$docsVencidos = DB::count("SELECT COUNT(*) FROM documentos
                           WHERE empresa_id=? AND activo=1 AND fecha_vencimiento < ?", [$emp, $hoy]);
if ($docsVencidos) $alertas[] = [
    'nivel' => 'danger', 'icono' => 'file-circle-xmark', 'valor' => $docsVencidos,
    'titulo' => 'Documentos vencidos', 'detalle' => 'Requieren renovación inmediata',
    'url' => APP_URL . '/modules/documentos/?alerta=vencido',
];

$docsPorVencer = DB::count("SELECT COUNT(*) FROM documentos
                            WHERE empresa_id=? AND activo=1
                              AND fecha_vencimiento BETWEEN ? AND DATE_ADD(?, INTERVAL 30 DAY)", [$emp, $hoy, $hoy]);
if ($docsPorVencer) $alertas[] = [
    'nivel' => 'warning', 'icono' => 'file-circle-exclamation', 'valor' => $docsPorVencer,
    'titulo' => 'Documentos por vencer', 'detalle' => 'Vencen dentro de 30 días',
    'url' => APP_URL . '/modules/documentos/?alerta=prox30',
];

$licencias = DB::count("SELECT COUNT(*) FROM conductores c
                        INNER JOIN trabajadores t ON t.id=c.trabajador_id
                        WHERE t.empresa_id=? AND c.activo=1
                          AND c.licencia_vence IS NOT NULL
                          AND c.licencia_vence <= DATE_ADD(?, INTERVAL 30 DAY)", [$emp, $hoy]);
if ($licencias) $alertas[] = [
    'nivel' => 'warning', 'icono' => 'id-card', 'valor' => $licencias,
    'titulo' => 'Licencias por vencer', 'detalle' => 'Conductores con licencia crítica',
    'url' => APP_URL . '/modules/admin/maestros/conductores',
];

$certificaciones = DB::count("SELECT COUNT(*) FROM operadores o
                              INNER JOIN trabajadores t ON t.id=o.trabajador_id
                              WHERE t.empresa_id=? AND o.activo=1
                                AND o.certif_vence IS NOT NULL
                                AND o.certif_vence <= DATE_ADD(?, INTERVAL 30 DAY)", [$emp, $hoy]);
if ($certificaciones) $alertas[] = [
    'nivel' => 'warning', 'icono' => 'user-shield', 'valor' => $certificaciones,
    'titulo' => 'Certificaciones por vencer', 'detalle' => 'Operadores a renovar',
    'url' => APP_URL . '/modules/admin/maestros/operadores',
];

$mantPend = DB::count("SELECT COUNT(*) FROM mantenciones m
                       INNER JOIN equipos e ON e.id=m.equipo_id
                       WHERE e.empresa_id=? AND m.estado IN ('pendiente','en_proceso')", [$emp]);
if ($mantPend) $alertas[] = [
    'nivel' => 'info', 'icono' => 'wrench', 'valor' => $mantPend,
    'titulo' => 'Mantenciones abiertas', 'detalle' => 'Pendientes o en proceso',
    'url' => APP_URL . '/modules/maquinaria/',
];

$equiposDetenidos = DB::count("SELECT COUNT(DISTINCT equipo_id) FROM detenciones_equipo
                               WHERE empresa_id=? AND (fecha_fin IS NULL OR fecha_fin > NOW())", [$emp]);
if ($equiposDetenidos) $alertas[] = [
    'nivel' => 'danger', 'icono' => 'ban', 'valor' => $equiposDetenidos,
    'titulo' => 'Equipos detenidos', 'detalle' => 'Fuera de servicio ahora',
    'url' => APP_URL . '/modules/maquinaria/',
];

$incAbiertos = DB::count("SELECT COUNT(*) FROM incidentes
                          WHERE empresa_id=? AND estado <> 'cerrado'", [$emp]);
if ($incAbiertos) $alertas[] = [
    'nivel' => 'danger', 'icono' => 'triangle-exclamation', 'valor' => $incAbiertos,
    'titulo' => 'Incidentes abiertos', 'detalle' => 'En investigación o reportados',
    'url' => APP_URL . '/modules/seguridad/',
];

$chkRechazados = DB::count("SELECT COUNT(*) FROM checklists_registros
                            WHERE empresa_id=? AND resultado='rechazado'
                              AND fecha >= DATE_SUB(?, INTERVAL 30 DAY)", [$emp, $hoy]);
if ($chkRechazados) $alertas[] = [
    'nivel' => 'warning', 'icono' => 'clipboard-check', 'valor' => $chkRechazados,
    'titulo' => 'Checklists rechazados', 'detalle' => 'Últimos 30 días',
    'url' => APP_URL . '/modules/seguridad/',
];

$partesBorrador = DB::count("SELECT COUNT(*) FROM partes_diarios
                             WHERE empresa_id=? AND estado='borrador'
                               AND fecha >= DATE_SUB(?, INTERVAL 15 DAY)", [$emp, $hoy]);
if ($partesBorrador) $alertas[] = [
    'nivel' => 'info', 'icono' => 'file-pen', 'valor' => $partesBorrador,
    'titulo' => 'Partes sin cerrar', 'detalle' => 'Borradores de los últimos 15 días',
    'url' => APP_URL . '/modules/faena/',
];

// Días sin accidentes: indicador clásico de prevención
$ultAcc = DB::one("SELECT MAX(fecha) f FROM incidentes WHERE empresa_id=? AND tipo='accidente'", [$emp]);
$diasSinAcc = !empty($ultAcc['f'])
    ? (int) ((strtotime($hoy) - strtotime($ultAcc['f'])) / 86400)
    : null;

// ── Series de 12 meses ──────────────────────────────────────────────────────
$serieComb = DB::all("SELECT DATE_FORMAT(fecha,'%Y-%m') mes,
                             SUM(litros) litros,
                             SUM(litros*COALESCE(precio_litro,0)) costo
                      FROM cargas_combustible
                      WHERE empresa_id=? AND fecha >= ?
                      GROUP BY mes ORDER BY mes", [$emp, $mes12]);

$serieArid = DB::all("SELECT mes, SUM(prod) prod, SUM(desp) desp FROM (
                        SELECT DATE_FORMAT(fecha,'%Y-%m') mes, SUM(cantidad) prod, 0 desp
                        FROM produccion_aridos WHERE empresa_id=? AND fecha >= ? GROUP BY mes
                        UNION ALL
                        SELECT DATE_FORMAT(fecha,'%Y-%m') mes, 0 prod, SUM(cantidad) desp
                        FROM despachos_aridos  WHERE empresa_id=? AND fecha >= ? GROUP BY mes
                      ) x GROUP BY mes ORDER BY mes", [$emp, $mes12, $emp, $mes12]);

$serieViajes = DB::all("SELECT DATE_FORMAT(fecha,'%Y-%m') mes, COUNT(*) viajes,
                               SUM(GREATEST(km_fin-km_inicio,0)) km
                        FROM viajes WHERE empresa_id=? AND fecha >= ?
                        GROUP BY mes ORDER BY mes", [$emp, $mes12]);

// Normaliza las tres series a los mismos 12 meses
$meses = [];
for ($i = 11; $i >= 0; $i--) $meses[] = date('Y-m', strtotime("-{$i} months"));

$mapa = function (array $filas, string $campo) use ($meses) {
    $idx = [];
    foreach ($filas as $f) $idx[$f['mes']] = (float)$f[$campo];
    return array_map(fn($m) => round($idx[$m] ?? 0, 2), $meses);
};

$gLitros  = $mapa($serieComb,   'litros');
$gCosto   = $mapa($serieComb,   'costo');
$gProd    = $mapa($serieArid,   'prod');
$gDesp    = $mapa($serieArid,   'desp');
$gViajes  = $mapa($serieViajes, 'viajes');

$etiquetasMes = array_map(function ($m) {
    $nom = ['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr','05'=>'May','06'=>'Jun',
            '07'=>'Jul','08'=>'Ago','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'];
    [$a, $mm] = explode('-', $m);
    return $nom[$mm] . ' ' . substr($a, 2);
}, $meses);

// ── Avance de contratos (físico-financiero) ─────────────────────────────────
$contratos = DB::all("SELECT c.id, c.numero, c.descripcion, c.monto_contrato, c.fecha_termino,
                             o.nombre AS obra_nombre,
                             COALESCE((SELECT SUM(monto) FROM avances_contrato WHERE contrato_id=c.id),0) AS avanzado
                      FROM contratos c
                      LEFT JOIN obras o ON o.id=c.obra_id
                      WHERE c.empresa_id=? AND c.activo=1
                      ORDER BY c.fecha_termino ASC
                      LIMIT 6", [$emp]);

// ── Equipos con más horas del mes (cruce faena ↔ maquinaria) ────────────────
$topEquipos = DB::all("SELECT e.codigo, e.nombre, SUM(pe.horas_trabajo) horas,
                              SUM(pe.horas_detenciones) detenciones
                       FROM parte_equipos pe
                       INNER JOIN partes_diarios p ON p.id = pe.parte_id
                       INNER JOIN equipos e        ON e.id = pe.equipo_id
                       WHERE p.empresa_id=? AND p.fecha BETWEEN ? AND ?
                       GROUP BY e.id, e.codigo, e.nombre
                       ORDER BY horas DESC LIMIT 6", [$emp, $mes0, $hoy]);

// ── Actividad reciente consolidada de todos los módulos ─────────────────────
$actividad = DB::all("
    SELECT * FROM (
        SELECT 'Combustible' modulo, 'gas-pump' icono, 'warning' color, c.fecha, c.created_at,
               CONCAT('Carga de ', FORMAT(c.litros,0), ' L · ',
                      COALESCE(v.patente, e.codigo, 'unidad')) detalle,
               'modules/combustible/' url
        FROM cargas_combustible c
        LEFT JOIN vehiculos v ON v.id=c.vehiculo_id
        LEFT JOIN equipos   e ON e.id=c.equipo_id
        WHERE c.empresa_id=?
        UNION ALL
        SELECT 'Faena', 'hard-hat', 'primary', p.fecha, p.created_at,
               CONCAT('Parte diario · ', COALESCE(o.nombre,'sin obra')), 'modules/faena/'
        FROM partes_diarios p LEFT JOIN obras o ON o.id=p.obra_id WHERE p.empresa_id=?
        UNION ALL
        SELECT 'Transporte', 'truck', 'success', vj.fecha, vj.created_at,
               CONCAT('Viaje ', COALESCE(vj.origen,''), ' → ', COALESCE(vj.destino,'')), 'modules/transporte/'
        FROM viajes vj WHERE vj.empresa_id=?
        UNION ALL
        SELECT 'Áridos', 'mountain', 'danger', d.fecha, d.created_at,
               CONCAT('Despacho ', FORMAT(d.cantidad,1), ' m³ · ', COALESCE(m.nombre,'')), 'modules/aridos/'
        FROM despachos_aridos d LEFT JOIN materiales m ON m.id=d.material_id WHERE d.empresa_id=?
        UNION ALL
        SELECT 'Seguridad', 'shield-alt', 'danger', i.fecha, i.created_at,
               CONCAT('Incidente ', i.gravedad, ' · ', LEFT(i.descripcion, 60)), 'modules/seguridad/'
        FROM incidentes i WHERE i.empresa_id=?
        UNION ALL
        SELECT 'Maquinaria', 'wrench', 'info', mt.fecha, mt.created_at,
               CONCAT('Mantención ', mt.tipo, ' · ', e2.codigo), 'modules/maquinaria/'
        FROM mantenciones mt INNER JOIN equipos e2 ON e2.id=mt.equipo_id WHERE mt.empresa_id=?
    ) t
    ORDER BY t.fecha DESC, t.created_at DESC
    LIMIT 12", [$emp, $emp, $emp, $emp, $emp, $emp]);

$pageTitle = 'Dashboard';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb">
    <ol class="breadcrumb"><li class="breadcrumb-item active">Dashboard</li></ol>
</nav>

<!-- ============================================================
     ALERTAS ACCIONABLES
============================================================= -->
<?php if ($alertas): ?>
<div class="row g-3 mb-4">
    <div class="col-12 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <p class="text-muted fw-semibold text-uppercase mb-0" style="font-size:.7rem;letter-spacing:.1em">
            <i class="fas fa-bell me-1" style="color:var(--brand-amber)"></i>
            Requiere atención — <?= count($alertas) ?> <?= count($alertas) === 1 ? 'alerta' : 'alertas' ?>
        </p>
        <?php if ($diasSinAcc !== null): ?>
            <span class="badge bg-success-subtle text-success border border-success-subtle">
                <i class="fas fa-shield-heart me-1"></i><?= $diasSinAcc ?> días sin accidentes
            </span>
        <?php endif; ?>
    </div>
    <?php foreach ($alertas as $a): ?>
    <div class="col-6 col-md-4 col-xl-3">
        <a href="<?= h($a['url']) ?>" class="text-decoration-none">
            <div class="card alerta-card alerta-<?= $a['nivel'] ?> h-100">
                <div class="card-body p-3 d-flex align-items-center gap-3">
                    <div class="alerta-icono bg-<?= $a['nivel'] ?>-subtle text-<?= $a['nivel'] ?>">
                        <i class="fas fa-<?= $a['icono'] ?>"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-items-baseline gap-2">
                            <span class="fw-bold fs-5 text-<?= $a['nivel'] ?>"><?= (int)$a['valor'] ?></span>
                            <span class="fw-semibold text-dark small"><?= h($a['titulo']) ?></span>
                        </div>
                        <div class="text-muted" style="font-size:.72rem"><?= h($a['detalle']) ?></div>
                    </div>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ============================================================
     INDICADORES DEL MES
============================================================= -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <p class="text-muted fw-semibold text-uppercase mb-0" style="font-size:.7rem;letter-spacing:.1em">
            <i class="fas fa-circle-dot me-1" style="color:var(--brand-amber);font-size:.5rem;vertical-align:middle"></i>
            Mes en curso — <?= mesAnio($mes0) ?>
        </p>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= APP_URL ?>/modules/combustible/" class="text-decoration-none">
        <div class="card kpi-card kpi-amber h-100"><div class="card-body p-3">
            <div class="kpi-icon bg-warning-subtle text-warning mb-2"><i class="fas fa-gas-pump"></i></div>
            <div class="kpi-value text-warning"><?= formatNum($litros_mes, 0) ?></div>
            <div class="kpi-label">Litros consumidos</div>
        </div></div></a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= APP_URL ?>/modules/combustible/rendimiento" class="text-decoration-none">
        <div class="card kpi-card kpi-red h-100"><div class="card-body p-3">
            <div class="kpi-icon bg-danger-subtle text-danger mb-2"><i class="fas fa-sack-dollar"></i></div>
            <div class="kpi-value text-danger" style="font-size:1.3rem"><?= formatMoney($costo_mes) ?></div>
            <div class="kpi-label">Costo combustible</div>
        </div></div></a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= APP_URL ?>/modules/combustible/rendimiento" class="text-decoration-none">
        <div class="card kpi-card kpi-cyan h-100"><div class="card-body p-3">
            <div class="kpi-icon bg-info-subtle text-info mb-2"><i class="fas fa-gauge-high"></i></div>
            <div class="kpi-value text-info"><?= formatNum($lhFlota, 2) ?></div>
            <div class="kpi-label">Litros / hora flota</div>
        </div></div></a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= APP_URL ?>/modules/transporte/" class="text-decoration-none">
        <div class="card kpi-card kpi-green h-100"><div class="card-body p-3">
            <div class="kpi-icon bg-success-subtle text-success mb-2"><i class="fas fa-truck"></i></div>
            <div class="kpi-value text-success" data-count="<?= $viajes_mes ?>"><?= $viajes_mes ?></div>
            <div class="kpi-label">Viajes · <?= formatNum($km_mes, 0) ?> km</div>
        </div></div></a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= APP_URL ?>/modules/aridos/" class="text-decoration-none">
        <div class="card kpi-card kpi-slate h-100"><div class="card-body p-3">
            <div class="kpi-icon bg-secondary-subtle text-secondary mb-2"><i class="fas fa-mountain"></i></div>
            <div class="kpi-value text-secondary"><?= formatNum($prod_mes, 0) ?></div>
            <div class="kpi-label">m³ producidos</div>
        </div></div></a>
    </div>

    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= APP_URL ?>/modules/faena/" class="text-decoration-none">
        <div class="card kpi-card kpi-blue h-100"><div class="card-body p-3">
            <div class="kpi-icon bg-primary-subtle text-primary mb-2"><i class="fas fa-hard-hat"></i></div>
            <div class="kpi-value text-primary" data-count="<?= $partes_mes ?>"><?= $partes_mes ?></div>
            <div class="kpi-label">Partes · <?= formatNum($horas_mes, 0) ?> h equipo</div>
        </div></div></a>
    </div>
</div>

<!-- ============================================================
     TENDENCIAS
============================================================= -->
<div class="row g-4 mb-4">
    <div class="col-12 col-xl-8">
        <div class="card table-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <strong><i class="fas fa-chart-column text-warning me-2"></i>Consumo y costo de combustible · 12 meses</strong>
                <a href="<?= APP_URL ?>/modules/combustible/rendimiento" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-gauge-high me-1"></i>Ver rendimiento
                </a>
            </div>
            <div class="card-body">
                <div class="chart-box" style="height:300px"><canvas id="chartCombustible"></canvas></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4">
        <div class="card table-card h-100">
            <div class="card-header">
                <strong><i class="fas fa-mountain text-danger me-2"></i>Áridos: producción vs despacho</strong>
            </div>
            <div class="card-body">
                <div class="chart-box" style="height:300px"><canvas id="chartAridos"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-xl-5">
        <div class="card table-card h-100">
            <div class="card-header">
                <strong><i class="fas fa-truck text-success me-2"></i>Viajes por mes</strong>
            </div>
            <div class="card-body">
                <div class="chart-box" style="height:260px"><canvas id="chartViajes"></canvas></div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="card table-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <strong><i class="fas fa-file-contract text-secondary me-2"></i>Avance financiero de contratos</strong>
                <a href="<?= APP_URL ?>/modules/contratos/" class="btn btn-sm btn-outline-secondary">Ver todos</a>
            </div>
            <div class="card-body">
                <?php if (empty($contratos)): ?>
                    <p class="text-muted mb-0 small">Sin contratos registrados.</p>
                <?php endif; ?>
                <?php foreach ($contratos as $c):
                    $monto = (float)$c['monto_contrato'];
                    $av    = (float)$c['avanzado'];
                    $pct   = $monto > 0 ? min(100, ($av / $monto) * 100) : 0;
                    $color = $pct >= 85 ? 'success' : ($pct >= 50 ? 'primary' : 'warning');
                    $diasRest = $c['fecha_termino']
                        ? (int)((strtotime($c['fecha_termino']) - strtotime($hoy)) / 86400) : null;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-1 mb-1">
                        <div class="min-w-0">
                            <span class="fw-semibold small"><?= h($c['numero']) ?></span>
                            <span class="text-muted" style="font-size:.75rem"> · <?= h($c['obra_nombre'] ?? '') ?></span>
                        </div>
                        <div class="text-end" style="font-size:.75rem">
                            <span class="fw-bold text-<?= $color ?>"><?= formatNum($pct, 1) ?>%</span>
                            <span class="text-muted">de <?= formatMoney($monto) ?></span>
                            <?php if ($diasRest !== null): ?>
                                <span class="badge bg-<?= $diasRest < 30 ? 'danger' : 'light text-muted' ?> ms-1">
                                    <?= $diasRest < 0 ? 'vencido' : $diasRest . ' días' ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="progress" style="height:8px">
                        <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     ACTIVIDAD RECIENTE + EQUIPOS MÁS USADOS
============================================================= -->
<div class="row g-4 mb-4">
    <div class="col-12 col-xl-7">
        <div class="card table-card h-100">
            <div class="card-header">
                <strong><i class="fas fa-timeline text-primary me-2"></i>Actividad reciente en todos los módulos</strong>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($actividad)): ?>
                    <div class="empty-state py-4"><p class="mb-0">Sin movimientos registrados todavía.</p></div>
                <?php endif; ?>
                <?php foreach ($actividad as $act): ?>
                <a href="<?= APP_URL ?>/<?= h($act['url']) ?>" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2">
                    <span class="actividad-icono bg-<?= h($act['color']) ?>-subtle text-<?= h($act['color']) ?>">
                        <i class="fas fa-<?= h($act['icono']) ?>"></i>
                    </span>
                    <span class="flex-grow-1 min-w-0">
                        <span class="d-block small fw-semibold text-truncate"><?= h($act['detalle']) ?></span>
                        <span class="text-muted" style="font-size:.72rem"><?= h($act['modulo']) ?></span>
                    </span>
                    <span class="text-muted text-nowrap" style="font-size:.72rem"><?= formatDate($act['fecha']) ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card table-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <strong><i class="fas fa-cogs text-warning me-2"></i>Equipos con más horas del mes</strong>
                <a href="<?= APP_URL ?>/modules/maquinaria/" class="btn btn-sm btn-outline-secondary">Flota</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th>Equipo</th>
                        <th class="text-end">Horas</th>
                        <th class="text-end">Detenciones</th>
                        <th class="text-end">Disponibilidad</th>
                    </tr></thead>
                    <tbody>
                    <?php if (empty($topEquipos)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">Sin horas registradas este mes.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($topEquipos as $te):
                        $ht = (float)$te['horas']; $hd = (float)$te['detenciones'];
                        $disp = ($ht + $hd) > 0 ? ($ht / ($ht + $hd)) * 100 : 100;
                    ?>
                    <tr>
                        <td>
                            <span class="badge bg-secondary"><?= h($te['codigo']) ?></span>
                            <div class="text-muted" style="font-size:.72rem"><?= h(mb_substr($te['nombre'], 0, 26)) ?></div>
                        </td>
                        <td class="text-end fw-semibold"><?= formatNum($ht, 1) ?></td>
                        <td class="text-end text-muted"><?= formatNum($hd, 1) ?></td>
                        <td class="text-end">
                            <span class="badge bg-<?= $disp >= 90 ? 'success' : ($disp >= 75 ? 'warning text-dark' : 'danger') ?>">
                                <?= formatNum($disp, 0) ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     MAESTROS + ACCESO A MÓDULOS
============================================================= -->
<div class="row g-3 mb-4">
    <?php foreach ([
        ['Obras Activas', $obras_activas, 'hard-hat',      'blue',   'primary',   'modules/faena/'],
        ['Contratos',     $contratos_act, 'file-contract', 'green',  'success',   'modules/contratos/'],
        ['Equipos',       $equipos_total, 'cogs',          'amber',  'warning',   'modules/maquinaria/'],
        ['Vehículos',     $vehiculos,     'truck',         'cyan',   'info',      'modules/transporte/'],
        ['Trabajadores',  $trabajadores,  'users',         'red',    'danger',    'modules/admin/maestros/trabajadores'],
        ['Clientes',      $clientes,      'building',      'slate',  'secondary', 'modules/admin/maestros/clientes'],
    ] as [$label, $valor, $icono, $kpi, $bs, $url]): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= APP_URL ?>/<?= $url ?>" class="text-decoration-none">
        <div class="card kpi-card kpi-<?= $kpi ?> h-100"><div class="card-body p-3">
            <div class="kpi-icon bg-<?= $bs ?>-subtle text-<?= $bs ?> mb-2"><i class="fas fa-<?= $icono ?>"></i></div>
            <div class="kpi-value text-<?= $bs ?>" data-count="<?= $valor ?>"><?= $valor ?></div>
            <div class="kpi-label"><?= $label ?></div>
        </div></div></a>
    </div>
    <?php endforeach; ?>
</div>

<div class="card table-card">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="fas fa-th-large text-primary"></i><strong>Módulos Operacionales</strong>
    </div>
    <div class="card-body">
        <div class="row g-3">
        <?php
        $modulos = [
            ['faena',       'hard-hat',      'Partes de Faena', 'blue',   'primary',   'Registro diario de actividades en terreno'],
            ['combustible', 'gas-pump',      'Combustible',     'amber',  'warning',   'Cargas, consumo y rendimiento por activo'],
            ['maquinaria',  'cogs',          'Maquinaria',      'cyan',   'info',      'Mantenciones, horómetros y disponibilidad'],
            ['transporte',  'truck',         'Transporte',      'green',  'success',   'Viajes y guías de despacho'],
            ['aridos',      'mountain',      'Áridos',          'red',    'danger',    'Producción, stock y despachos'],
            ['contratos',   'file-contract', 'Contratos',       'slate',  'secondary', 'Avance físico y financiero'],
            ['documentos',  'folder-open',   'Documentos',      'blue',   'primary',   'Control documental con alertas'],
            ['seguridad',   'shield-alt',    'Seguridad',       'red',    'danger',    'Checklists, incidentes y prevención'],
        ];
        foreach ($modulos as [$mod, $icon, $title, $kpiColor, $bsColor, $desc]): ?>
        <div class="col-6 col-lg-3">
            <a href="<?= APP_URL ?>/modules/<?= $mod ?>/" class="text-decoration-none">
            <div class="card module-card kpi-<?= $kpiColor ?> h-100">
                <div class="card-body text-center py-4">
                    <div class="kpi-icon bg-<?= $bsColor ?>-subtle text-<?= $bsColor ?> mx-auto mb-3">
                        <i class="fas fa-<?= $icon ?>"></i>
                    </div>
                    <h6 class="fw-bold mb-1"><?= $title ?></h6>
                    <p class="text-muted small mb-0"><?= $desc ?></p>
                </div>
            </div></a>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
(function () {
    const meses  = <?= json_encode($etiquetasMes) ?>;
    const litros = <?= json_encode($gLitros) ?>;
    const costo  = <?= json_encode($gCosto) ?>;
    const prod   = <?= json_encode($gProd) ?>;
    const desp   = <?= json_encode($gDesp) ?>;
    const viajes = <?= json_encode($gViajes) ?>;

    const comunes = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
    };

    const c1 = document.getElementById('chartCombustible');
    if (c1) new Chart(c1, {
        data: {
            labels: meses,
            datasets: [
                { type: 'bar',  label: 'Litros', data: litros, backgroundColor: 'rgba(245,158,11,.65)',
                  borderRadius: 6, yAxisID: 'y' },
                { type: 'line', label: 'Costo ($)', data: costo, borderColor: '#ef4444',
                  backgroundColor: 'rgba(239,68,68,.08)', tension: .35, fill: true, yAxisID: 'y1' },
            ],
        },
        options: {
            ...comunes,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y:  { position: 'left',  title: { display: true, text: 'Litros' }, beginAtZero: true },
                y1: { position: 'right', beginAtZero: true, grid: { drawOnChartArea: false },
                      ticks: { callback: v => '$' + (v / 1e6).toFixed(1) + 'M' } },
            },
        },
    });

    const c2 = document.getElementById('chartAridos');
    if (c2) new Chart(c2, {
        type: 'bar',
        data: {
            labels: meses,
            datasets: [
                { label: 'Producción m³', data: prod, backgroundColor: 'rgba(100,116,139,.7)', borderRadius: 5 },
                { label: 'Despacho m³',   data: desp, backgroundColor: 'rgba(239,68,68,.7)',   borderRadius: 5 },
            ],
        },
        options: { ...comunes, scales: { y: { beginAtZero: true } } },
    });

    const c3 = document.getElementById('chartViajes');
    if (c3) new Chart(c3, {
        type: 'line',
        data: {
            labels: meses,
            datasets: [{
                label: 'Viajes', data: viajes, borderColor: '#22c55e',
                backgroundColor: 'rgba(34,197,94,.14)', tension: .35, fill: true,
            }],
        },
        options: { ...comunes, scales: { y: { beginAtZero: true } } },
    });
})();
</script>

<?php include ROOT . '/includes/footer.php'; ?>
