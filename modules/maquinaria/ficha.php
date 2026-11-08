<?php
/**
 * TriTransportes — Ficha 360° del activo
 * ----------------------------------------------------------------------------
 * Reúne en una sola pantalla todo lo que los distintos módulos saben de un
 * equipo o de un vehículo: consumo y rendimiento (M03), mantenciones y
 * detenciones (M04), horas trabajadas en partes de faena (M02), viajes (M05),
 * checklists (M09) y documentación vigente (M08).
 *
 * Uso: ficha?equipo_id=N  |  ficha?vehiculo_id=N
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireAnyPermiso('maquinaria');

$emp       = Auth::empresaId();
$hoy       = date('Y-m-d');
$equipoId  = getInt('equipo_id');
$vehicId   = getInt('vehiculo_id');

if (!$equipoId && !$vehicId) {
    Flash::warning('Seleccione un equipo o vehículo para ver su ficha.');
    redirect('/modules/maquinaria/');
}

$esEquipo = $equipoId > 0;

// ── Datos del activo ────────────────────────────────────────────────────────
if ($esEquipo) {
    $activo = DB::one(
        "SELECT e.*, m.nombre AS marca_nombre, mo.nombre AS modelo_nombre, c.nombre AS categoria_nombre
         FROM equipos e
         LEFT JOIN marcas_equipo   m  ON m.id  = e.marca_id
         LEFT JOIN modelos_equipo  mo ON mo.id = e.modelo_id
         LEFT JOIN categorias_equipo c ON c.id = e.categoria_id
         WHERE e.id = ? AND e.empresa_id = ?", [$equipoId, $emp]);
    $titulo   = $activo ? $activo['codigo'] . ' · ' . $activo['nombre'] : '';
    $subtitulo = $activo ? trim(($activo['marca_nombre'] ?? '') . ' ' . ($activo['modelo_nombre'] ?? '')) : '';
} else {
    $activo = DB::one(
        "SELECT v.*, c.nombre AS categoria_nombre
         FROM vehiculos v
         LEFT JOIN categorias_vehiculo c ON c.id = v.categoria_id
         WHERE v.id = ? AND v.empresa_id = ?", [$vehicId, $emp]);
    $titulo    = $activo ? $activo['patente'] : '';
    $subtitulo = $activo ? trim(($activo['marca'] ?? '') . ' ' . ($activo['modelo'] ?? '')) : '';
}

if (!$activo) {
    Flash::error('El activo solicitado no existe o no pertenece a la empresa activa.');
    redirect('/modules/maquinaria/');
}

$idActivo = (int)$activo['id'];

// ── M03 · Consumo y rendimiento (últimos 12 meses) ──────────────────────────
if ($esEquipo) {
    $rend = DB::one("
        WITH base AS (
            SELECT fecha, litros, precio_litro, horometro,
                   LAG(horometro) OVER (ORDER BY fecha, id) AS prev
            FROM cargas_combustible
            WHERE empresa_id=? AND equipo_id=? AND horometro IS NOT NULL
              AND fecha >= DATE_SUB(?, INTERVAL 12 MONTH)
        )
        SELECT COUNT(*) cargas, SUM(litros) litros, SUM(horometro-prev) horas,
               SUM(litros*COALESCE(precio_litro,0)) costo
        FROM base
        WHERE prev IS NOT NULL AND horometro > prev AND horometro-prev BETWEEN 1 AND 400",
        [$emp, $idActivo, $hoy]);

    $unidadUso = 'horas';
    $usoTotal  = (float)($rend['horas'] ?? 0);
    $indicador = $usoTotal > 0 ? (float)$rend['litros'] / $usoTotal : 0;
    $indLabel  = 'Litros por hora';
    $costoUso  = $usoTotal > 0 ? (float)$rend['costo'] / $usoTotal : 0;
    $costoLabel = 'Costo por hora';
} else {
    $rend = DB::one("
        WITH base AS (
            SELECT fecha, litros, precio_litro, odometro,
                   LAG(odometro) OVER (ORDER BY fecha, id) AS prev
            FROM cargas_combustible
            WHERE empresa_id=? AND vehiculo_id=? AND odometro IS NOT NULL
              AND fecha >= DATE_SUB(?, INTERVAL 12 MONTH)
        )
        SELECT COUNT(*) cargas, SUM(litros) litros, SUM(odometro-prev) km,
               SUM(litros*COALESCE(precio_litro,0)) costo
        FROM base
        WHERE prev IS NOT NULL AND odometro > prev AND odometro-prev BETWEEN 10 AND 4000",
        [$emp, $idActivo, $hoy]);

    $unidadUso = 'km';
    $usoTotal  = (float)($rend['km'] ?? 0);
    $indicador = (float)($rend['litros'] ?? 0) > 0 ? $usoTotal / (float)$rend['litros'] : 0;
    $indLabel  = 'Kilómetros por litro';
    $costoUso  = $usoTotal > 0 ? (float)$rend['costo'] / $usoTotal : 0;
    $costoLabel = 'Costo por kilómetro';
}

// Serie mensual de consumo del activo
$campoId  = $esEquipo ? 'equipo_id' : 'vehiculo_id';
$serie = DB::all("SELECT DATE_FORMAT(fecha,'%Y-%m') mes, SUM(litros) litros
                  FROM cargas_combustible
                  WHERE empresa_id=? AND {$campoId}=? AND fecha >= DATE_SUB(?, INTERVAL 12 MONTH)
                  GROUP BY mes ORDER BY mes", [$emp, $idActivo, $hoy]);

// ── Últimas cargas (M03) ────────────────────────────────────────────────────
$cargas = DB::all("SELECT c.*, o.nombre AS obra_nombre, f.nombre AS fuente_nombre
                   FROM cargas_combustible c
                   LEFT JOIN obras o ON o.id=c.obra_id
                   LEFT JOIN fuentes_combustible f ON f.id=c.fuente_id
                   WHERE c.empresa_id=? AND c.{$campoId}=?
                   ORDER BY c.fecha DESC, c.id DESC LIMIT 10", [$emp, $idActivo]);

// ── M04 · Mantenciones y detenciones (solo equipos) ─────────────────────────
$mantenciones = $detenciones = [];
$costoMant = 0; $horasDetenido = 0;
if ($esEquipo) {
    $mantenciones = DB::all("SELECT * FROM mantenciones
                             WHERE empresa_id=? AND equipo_id=?
                             ORDER BY fecha DESC LIMIT 10", [$emp, $idActivo]);
    $costoMant = (float)(DB::one("SELECT COALESCE(SUM(costo_total),0) t FROM mantenciones
                                  WHERE empresa_id=? AND equipo_id=?
                                    AND fecha >= DATE_SUB(?, INTERVAL 12 MONTH)",
                                 [$emp, $idActivo, $hoy])['t'] ?? 0);

    $detenciones = DB::all("SELECT * FROM detenciones_equipo
                            WHERE empresa_id=? AND equipo_id=?
                            ORDER BY fecha_inicio DESC LIMIT 10", [$emp, $idActivo]);
    $horasDetenido = (float)(DB::one(
        "SELECT COALESCE(SUM(TIMESTAMPDIFF(HOUR, fecha_inicio, COALESCE(fecha_fin, NOW()))),0) t
         FROM detenciones_equipo
         WHERE empresa_id=? AND equipo_id=? AND fecha_inicio >= DATE_SUB(?, INTERVAL 12 MONTH)",
        [$emp, $idActivo, $hoy])['t'] ?? 0);
}

// ── M02 · Horas en partes de faena (solo equipos) ───────────────────────────
$horasFaena = ['trabajo' => 0, 'detencion' => 0];
if ($esEquipo) {
    $hf = DB::one("SELECT COALESCE(SUM(pe.horas_trabajo),0) trabajo,
                          COALESCE(SUM(pe.horas_detenciones),0) detencion
                   FROM parte_equipos pe
                   INNER JOIN partes_diarios p ON p.id=pe.parte_id
                   WHERE p.empresa_id=? AND pe.equipo_id=?
                     AND p.fecha >= DATE_SUB(?, INTERVAL 12 MONTH)", [$emp, $idActivo, $hoy]);
    $horasFaena = ['trabajo' => (float)$hf['trabajo'], 'detencion' => (float)$hf['detencion']];
}

// ── M05 · Viajes (solo vehículos) ───────────────────────────────────────────
$viajes = []; $viajesTot = 0; $kmViajes = 0;
if (!$esEquipo) {
    $viajes = DB::all("SELECT v.*, o.nombre AS obra_nombre, m.nombre AS material_nombre
                       FROM viajes v
                       LEFT JOIN obras o ON o.id=v.obra_id
                       LEFT JOIN materiales m ON m.id=v.material_id
                       WHERE v.empresa_id=? AND v.vehiculo_id=?
                       ORDER BY v.fecha DESC, v.id DESC LIMIT 10", [$emp, $idActivo]);
    $vt = DB::one("SELECT COUNT(*) n, COALESCE(SUM(GREATEST(km_fin-km_inicio,0)),0) km
                   FROM viajes WHERE empresa_id=? AND vehiculo_id=?
                     AND fecha >= DATE_SUB(?, INTERVAL 12 MONTH)", [$emp, $idActivo, $hoy]);
    $viajesTot = (int)$vt['n']; $kmViajes = (float)$vt['km'];
}

// ── M09 · Checklists ────────────────────────────────────────────────────────
$checklists = DB::all("SELECT cr.*, tc.nombre AS tipo_nombre
                       FROM checklists_registros cr
                       LEFT JOIN tipos_checklist tc ON tc.id=cr.tipo_id
                       WHERE cr.empresa_id=? AND cr.{$campoId}=?
                       ORDER BY cr.fecha DESC LIMIT 8", [$emp, $idActivo]);

$chkResumen = DB::one("SELECT COUNT(*) total,
                              SUM(resultado='aprobado')  aprobados,
                              SUM(resultado='observado') observados,
                              SUM(resultado='rechazado') rechazados
                       FROM checklists_registros
                       WHERE empresa_id=? AND {$campoId}=?
                         AND fecha >= DATE_SUB(?, INTERVAL 12 MONTH)", [$emp, $idActivo, $hoy]);

// ── M08 · Documentos asociados ──────────────────────────────────────────────
$documentos = DB::all("SELECT d.*, td.nombre AS tipo_nombre, ed.nombre AS estado_nombre
                       FROM documentos d
                       LEFT JOIN tipos_documento td ON td.id=d.tipo_id
                       LEFT JOIN estados_documento ed ON ed.id=d.estado_id
                       WHERE d.empresa_id=? AND d.activo=1
                         AND d.entidad_tipo=? AND d.entidad_id=?
                       ORDER BY d.fecha_vencimiento IS NULL, d.fecha_vencimiento ASC",
                      [$emp, $esEquipo ? 'equipo' : 'vehiculo', $idActivo]);

$gMeses  = array_column($serie, 'mes');
$gLitros = array_map(fn($s) => round((float)$s['litros'], 1), $serie);

$pageTitle = 'Ficha de Activo — ' . $titulo;
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/dashboard/">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/maquinaria/">Maquinaria</a></li>
    <li class="breadcrumb-item active">Ficha de activo</li>
</ol></nav>

<!-- Encabezado del activo -->
<div class="card form-card mb-4">
    <div class="card-header d-flex align-items-center gap-3 flex-wrap">
        <span class="ficha-icono"><i class="fas fa-<?= $esEquipo ? 'cogs' : 'truck' ?>"></i></span>
        <div class="flex-grow-1 min-w-0">
            <div class="fs-5 fw-bold"><?= h($titulo) ?></div>
            <div class="small opacity-75">
                <?= h($subtitulo) ?>
                <?php if (!empty($activo['categoria_nombre'])): ?>
                    · <?= h($activo['categoria_nombre']) ?>
                <?php endif; ?>
                <?php if (!empty($activo['anio'])): ?> · Año <?= (int)$activo['anio'] ?><?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($esEquipo): ?>
                <a href="<?= APP_URL ?>/modules/maquinaria/mantencion?equipo_id=<?= $idActivo ?>" class="btn btn-sm btn-warning">
                    <i class="fas fa-wrench me-1"></i>Mantención
                </a>
                <a href="<?= APP_URL ?>/modules/maquinaria/horometro?equipo_id=<?= $idActivo ?>" class="btn btn-sm btn-outline-light">
                    <i class="fas fa-tachometer-alt me-1"></i>Horómetro
                </a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/modules/combustible/carga" class="btn btn-sm btn-outline-light">
                <i class="fas fa-gas-pump me-1"></i>Nueva carga
            </a>
        </div>
    </div>
</div>

<!-- Indicadores del activo -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card kpi-card kpi-amber h-100"><div class="card-body p-3">
            <div class="kpi-icon bg-warning-subtle text-warning mb-2"><i class="fas fa-gauge-high"></i></div>
            <div class="kpi-value text-warning"><?= formatNum($indicador, 2) ?></div>
            <div class="kpi-label"><?= $indLabel ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card kpi-card kpi-slate h-100"><div class="card-body p-3">
            <div class="kpi-icon bg-secondary-subtle text-secondary mb-2"><i class="fas fa-<?= $esEquipo ? 'stopwatch' : 'road' ?>"></i></div>
            <div class="kpi-value text-secondary"><?= formatNum($usoTotal, 0) ?></div>
            <div class="kpi-label"><?= $esEquipo ? 'Horas operadas (12m)' : 'Km recorridos (12m)' ?></div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card kpi-card kpi-cyan h-100"><div class="card-body p-3">
            <div class="kpi-icon bg-info-subtle text-info mb-2"><i class="fas fa-tint"></i></div>
            <div class="kpi-value text-info"><?= formatNum((float)($rend['litros'] ?? 0), 0) ?></div>
            <div class="kpi-label">Litros consumidos</div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card kpi-card kpi-green h-100"><div class="card-body p-3">
            <div class="kpi-icon bg-success-subtle text-success mb-2"><i class="fas fa-coins"></i></div>
            <div class="kpi-value text-success" style="font-size:1.25rem"><?= formatMoney($costoUso) ?></div>
            <div class="kpi-label"><?= $costoLabel ?></div>
        </div></div>
    </div>
    <?php if ($esEquipo): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card kpi-card kpi-red h-100"><div class="card-body p-3">
            <div class="kpi-icon bg-danger-subtle text-danger mb-2"><i class="fas fa-wrench"></i></div>
            <div class="kpi-value text-danger" style="font-size:1.25rem"><?= formatMoney($costoMant) ?></div>
            <div class="kpi-label">Costo mantención (12m)</div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <?php
            $dispon = ($horasFaena['trabajo'] + $horasFaena['detencion']) > 0
                ? ($horasFaena['trabajo'] / ($horasFaena['trabajo'] + $horasFaena['detencion'])) * 100 : 100;
        ?>
        <div class="card kpi-card kpi-blue h-100"><div class="card-body p-3">
            <div class="kpi-icon bg-primary-subtle text-primary mb-2"><i class="fas fa-circle-check"></i></div>
            <div class="kpi-value text-primary"><?= formatNum($dispon, 0) ?>%</div>
            <div class="kpi-label">Disponibilidad en faena</div>
        </div></div>
    </div>
    <?php else: ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card kpi-card kpi-green h-100"><div class="card-body p-3">
            <div class="kpi-icon bg-success-subtle text-success mb-2"><i class="fas fa-route"></i></div>
            <div class="kpi-value text-success" data-count="<?= $viajesTot ?>"><?= $viajesTot ?></div>
            <div class="kpi-label">Viajes (12 meses)</div>
        </div></div>
    </div>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card kpi-card kpi-blue h-100"><div class="card-body p-3">
            <div class="kpi-icon bg-primary-subtle text-primary mb-2"><i class="fas fa-gauge"></i></div>
            <div class="kpi-value text-primary"><?= formatNum((float)$activo['odometro'], 0) ?></div>
            <div class="kpi-label">Odómetro actual</div>
        </div></div>
    </div>
    <?php endif; ?>
</div>

<!-- Consumo mensual del activo -->
<div class="card table-card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <strong><i class="fas fa-chart-area text-warning me-2"></i>Consumo mensual del activo</strong>
        <a href="<?= APP_URL ?>/modules/combustible/rendimiento" class="btn btn-sm btn-outline-primary">
            Comparar con la flota
        </a>
    </div>
    <div class="card-body">
        <div class="chart-box" style="height:250px"><canvas id="chartActivo"></canvas></div>
    </div>
</div>

<div class="row g-4">
    <!-- Últimas cargas -->
    <div class="col-12 col-xl-6">
        <div class="card table-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <strong><i class="fas fa-gas-pump text-warning me-2"></i>Últimas cargas</strong>
                <a href="<?= APP_URL ?>/modules/combustible/" class="btn btn-sm btn-outline-secondary">Ver todas</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr>
                        <th>Fecha</th><th class="text-end">Litros</th>
                        <th class="text-end"><?= $esEquipo ? 'Horómetro' : 'Odómetro' ?></th>
                        <th>Obra</th><th class="text-end">Ver</th>
                    </tr></thead>
                    <tbody>
                    <?php if (empty($cargas)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Sin cargas registradas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($cargas as $c): ?>
                    <tr>
                        <td><strong><?= formatDate($c['fecha']) ?></strong></td>
                        <td class="text-end"><?= formatNum((float)$c['litros'], 1) ?> L</td>
                        <td class="text-end text-muted">
                            <?= $esEquipo
                                ? formatNum((float)$c['horometro'], 1) . ' h'
                                : formatNum((float)$c['odometro'], 0) . ' km' ?>
                        </td>
                        <td><small><?= h($c['obra_nombre'] ?? '—') ?></small></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-info btn-action btn-view js-view"
                                    data-vista="combustible" data-id="<?= (int)$c['id'] ?>"><i class="fas fa-eye"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Mantenciones (equipo) o viajes (vehículo) -->
    <div class="col-12 col-xl-6">
        <div class="card table-card h-100">
            <?php if ($esEquipo): ?>
            <div class="card-header d-flex align-items-center justify-content-between">
                <strong><i class="fas fa-wrench text-danger me-2"></i>Historial de mantención</strong>
                <span class="badge bg-secondary"><?= formatNum($horasDetenido, 0) ?> h detenido</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th class="text-end">Costo</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php if (empty($mantenciones)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Sin mantenciones registradas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($mantenciones as $m): ?>
                    <tr>
                        <td><strong><?= formatDate($m['fecha']) ?></strong></td>
                        <td><span class="badge bg-<?= $m['tipo']==='preventiva'?'info':'danger' ?>"><?= h($m['tipo']) ?></span></td>
                        <td><small><?= h(mb_substr((string)$m['descripcion'], 0, 40)) ?></small></td>
                        <td class="text-end"><?= formatMoney((float)$m['costo_total']) ?></td>
                        <td>
                            <?php $sc = ['pendiente'=>'warning text-dark','en_proceso'=>'primary','completada'=>'success']; ?>
                            <span class="badge bg-<?= $sc[$m['estado']] ?? 'secondary' ?>"><?= h($m['estado']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-header d-flex align-items-center justify-content-between">
                <strong><i class="fas fa-truck text-success me-2"></i>Últimos viajes</strong>
                <span class="badge bg-secondary"><?= formatNum($kmViajes, 0) ?> km (12m)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Fecha</th><th>Ruta</th><th>Material</th><th class="text-end">Km</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php if (empty($viajes)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Sin viajes registrados.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($viajes as $v): ?>
                    <tr>
                        <td><strong><?= formatDate($v['fecha']) ?></strong></td>
                        <td><small><?= h(mb_substr((string)$v['origen'], 0, 18)) ?> → <?= h(mb_substr((string)$v['destino'], 0, 18)) ?></small></td>
                        <td><small><?= h($v['material_nombre'] ?? '—') ?></small></td>
                        <td class="text-end"><?= formatNum(max(0, (float)$v['km_fin'] - (float)$v['km_inicio']), 0) ?></td>
                        <td><span class="badge bg-<?= $v['estado']==='completado'?'success':($v['estado']==='cancelado'?'danger':'secondary') ?>"><?= h($v['estado']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Checklists -->
    <div class="col-12 col-xl-6">
        <div class="card table-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <strong><i class="fas fa-clipboard-check text-primary me-2"></i>Checklists de seguridad</strong>
                <span class="small">
                    <span class="badge bg-success"><?= (int)($chkResumen['aprobados'] ?? 0) ?> aprobados</span>
                    <span class="badge bg-warning text-dark"><?= (int)($chkResumen['observados'] ?? 0) ?> observados</span>
                    <span class="badge bg-danger"><?= (int)($chkResumen['rechazados'] ?? 0) ?> rechazados</span>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Fecha</th><th>Tipo</th><th>Turno</th><th>Resultado</th><th class="text-end">Ver</th></tr></thead>
                    <tbody>
                    <?php if (empty($checklists)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">Sin checklists registrados.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($checklists as $ck): ?>
                    <tr>
                        <td><strong><?= formatDate($ck['fecha']) ?></strong></td>
                        <td><small><?= h($ck['tipo_nombre'] ?? '—') ?></small></td>
                        <td><small><?= h($ck['turno']) ?></small></td>
                        <td>
                            <?php $rc = ['aprobado'=>'success','observado'=>'warning text-dark','rechazado'=>'danger']; ?>
                            <span class="badge bg-<?= $rc[$ck['resultado']] ?? 'secondary' ?>"><?= h($ck['resultado']) ?></span>
                        </td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-info btn-action btn-view js-view"
                                    data-vista="seguridad_checklists" data-id="<?= (int)$ck['id'] ?>"><i class="fas fa-eye"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Documentación -->
    <div class="col-12 col-xl-6">
        <div class="card table-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <strong><i class="fas fa-folder-open text-info me-2"></i>Documentación del activo</strong>
                <a href="<?= APP_URL ?>/modules/documentos/" class="btn btn-sm btn-outline-secondary">Gestionar</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Documento</th><th>N°</th><th>Vence</th><th>Situación</th></tr></thead>
                    <tbody>
                    <?php if (empty($documentos)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">Sin documentos asociados.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($documentos as $d):
                        $dias = $d['fecha_vencimiento']
                            ? (int)((strtotime($d['fecha_vencimiento']) - strtotime($hoy)) / 86400) : null;
                    ?>
                    <tr>
                        <td><small class="fw-semibold"><?= h($d['tipo_nombre'] ?? $d['titulo']) ?></small></td>
                        <td><small class="text-muted"><?= h($d['numero'] ?? '—') ?></small></td>
                        <td><small><?= formatDate($d['fecha_vencimiento']) ?></small></td>
                        <td>
                            <?php if ($dias === null): ?>
                                <span class="badge bg-secondary">Sin vencimiento</span>
                            <?php elseif ($dias < 0): ?>
                                <span class="badge bg-danger">Vencido hace <?= abs($dias) ?> d</span>
                            <?php elseif ($dias <= 30): ?>
                                <span class="badge bg-warning text-dark">Vence en <?= $dias ?> d</span>
                            <?php else: ?>
                                <span class="badge bg-success">Vigente</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const ctx = document.getElementById('chartActivo');
    const meses = <?= json_encode($gMeses) ?>;
    if (!ctx || !meses.length) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: meses,
            datasets: [{
                label: 'Litros', data: <?= json_encode($gLitros) ?>,
                backgroundColor: 'rgba(245,158,11,.65)', borderRadius: 6,
            }],
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, title: { display: true, text: 'Litros' } } },
        },
    });
})();
</script>

<?php include ROOT . '/includes/footer.php'; ?>
