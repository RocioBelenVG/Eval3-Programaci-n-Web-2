<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireAnyPermiso('transporte');

$emp  = Auth::empresaId();
$hoy  = date('Y-m-d');
$mes0 = date('Y-m-01');

// KPIs
$kpiHoy      = DB::count("SELECT COUNT(*) FROM viajes WHERE empresa_id=? AND fecha=?", [$emp, $hoy]);
$kpiMes      = DB::count("SELECT COUNT(*) FROM viajes WHERE empresa_id=? AND fecha BETWEEN ? AND ?", [$emp, $mes0, $hoy]);
$kpiEnRuta   = DB::count("SELECT COUNT(*) FROM viajes WHERE empresa_id=? AND estado='en_ruta'", [$emp]);
$kpiGuiasMes = DB::count("SELECT COUNT(*) FROM guias_despacho WHERE empresa_id=? AND fecha BETWEEN ? AND ?", [$emp, $mes0, $hoy]);

// Filtros
$fDesde = get('desde', $mes0);
$fHasta = get('hasta', $hoy);
$obraId = getInt('obra_id');
$estado = get('estado');
$page   = getInt('page', 1);

$where  = "WHERE v.empresa_id=?";
$params = [$emp];
if ($fDesde) { $where .= " AND v.fecha >= ?"; $params[] = $fDesde; }
if ($fHasta) { $where .= " AND v.fecha <= ?"; $params[] = $fHasta; }
if ($obraId) { $where .= " AND v.obra_id = ?"; $params[] = $obraId; }
if ($estado) { $where .= " AND v.estado = ?"; $params[] = $estado; }

$total = DB::count("SELECT COUNT(*) FROM viajes v $where", $params);
$pag   = paginate($total, $page);

$rows = DB::all(
    "SELECT v.*,
            ve.patente, ve.marca AS veh_marca,
            CONCAT(tc.nombre,' ',tc.apellido) AS conductor_nombre,
            o.nombre AS obra_nombre,
            m.nombre AS material_nombre
     FROM viajes v
     LEFT JOIN vehiculos  ve ON ve.id = v.vehiculo_id
     LEFT JOIN conductores c ON c.id  = v.conductor_id
     LEFT JOIN trabajadores tc ON tc.id = c.trabajador_id
     LEFT JOIN obras       o ON o.id  = v.obra_id
     LEFT JOIN materiales  m ON m.id  = v.material_id
     $where
     ORDER BY v.fecha DESC, v.id DESC
     LIMIT {$pag['limit']} OFFSET {$pag['offset']}",
    $params
);

$obras = DB::all("SELECT id, nombre FROM obras WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);

$estadoColores = [
    'programado' => 'secondary',
    'en_ruta'    => 'primary',
    'completado' => 'success',
    'cancelado'  => 'danger',
];

$pageTitle = 'Gestión de Transporte';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/dashboard/">Dashboard</a></li>
    <li class="breadcrumb-item active">Transporte</li>
</ol></nav>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-blue">
            <div class="card-body p-3">
                <div class="kpi-icon bg-primary-subtle text-primary mb-2"><i class="fas fa-truck"></i></div>
                <div class="kpi-value text-primary" data-count="<?= $kpiHoy ?>"><?= $kpiHoy ?></div>
                <div class="kpi-label">Viajes Hoy</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-cyan">
            <div class="card-body p-3">
                <div class="kpi-icon bg-info-subtle text-info mb-2"><i class="fas fa-calendar-alt"></i></div>
                <div class="kpi-value text-info" data-count="<?= $kpiMes ?>"><?= $kpiMes ?></div>
                <div class="kpi-label">Viajes Este Mes</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-amber">
            <div class="card-body p-3">
                <div class="kpi-icon bg-warning-subtle text-warning mb-2"><i class="fas fa-route"></i></div>
                <div class="kpi-value text-warning" data-count="<?= $kpiEnRuta ?>"><?= $kpiEnRuta ?></div>
                <div class="kpi-label">En Ruta Ahora</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-green">
            <div class="card-body p-3">
                <div class="kpi-icon bg-success-subtle text-success mb-2"><i class="fas fa-file-invoice"></i></div>
                <div class="kpi-value text-success" data-count="<?= $kpiGuiasMes ?>"><?= $kpiGuiasMes ?></div>
                <div class="kpi-label">Guías Este Mes</div>
            </div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-auto">
                <label class="form-label form-label-sm mb-1">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= h($fDesde) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= h($fHasta) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm mb-1">Obra</label>
                <select name="obra_id" class="form-select form-select-sm" style="min-width:130px">
                    <option value="">Todas</option>
                    <?php foreach ($obras as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= $obraId == $o['id'] ? 'selected' : '' ?>><?= h($o['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm mb-1">Estado</label>
                <select name="estado" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <?php foreach (['programado'=>'Programado','en_ruta'=>'En Ruta','completado'=>'Completado','cancelado'=>'Cancelado'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= $estado === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-filter me-1"></i>Filtrar</button>
                <a href="?" class="btn btn-sm btn-outline-secondary ms-1"><i class="fas fa-times"></i></a>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <?= botonesExportacion('transporte_viajes', [
                        'desde' => $fDesde, 'hasta' => $fHasta,
                        'obra_id' => $obraId, 'estado' => $estado,
                    ]) ?>
                <?php if (Auth::can('transporte')): ?>
                <a href="guias" class="btn btn-sm btn-outline-success fw-semibold">
                    <i class="fas fa-file-invoice me-1"></i>Guías de Despacho
                </a>
                <?php endif; ?>
                <?php if (Auth::can('transporte', 'crear')): ?>
                <a href="viaje" class="btn btn-sm btn-primary fw-semibold">
                    <i class="fas fa-plus me-1"></i>Nuevo Viaje
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if (Auth::can('transporte', 'ver')): ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Vehículo</th>
                    <th>Conductor</th>
                    <th>Origen → Destino</th>
                    <th>Obra</th>
                    <th>Carga</th>
                    <th>km</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">
                    <i class="fas fa-truck fa-2x d-block mb-2"></i>Sin viajes en el período seleccionado.
                </td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?= formatDate($r['fecha']) ?></strong></td>
                <td>
                    <span class="badge bg-secondary"><?= h($r['patente'] ?? '—') ?></span>
                    <small class="d-block text-muted"><?= h($r['veh_marca'] ?? '') ?></small>
                </td>
                <td><small><?= h($r['conductor_nombre'] ?? '—') ?></small></td>
                <td>
                    <small>
                        <?= h(mb_substr($r['origen'] ?? '—', 0, 22)) ?>
                        <?php if ($r['destino']): ?>
                        <br><i class="fas fa-long-arrow-alt-right text-muted"></i> <?= h(mb_substr($r['destino'], 0, 22)) ?>
                        <?php endif; ?>
                    </small>
                </td>
                <td><small><?= h($r['obra_nombre'] ?? '—') ?></small></td>
                <td>
                    <small>
                        <?= h($r['material_nombre'] ?? '—') ?>
                        <?php if ($r['cantidad']): ?>
                        <br><?= formatNum((float)$r['cantidad'], 2) ?> <?= h($r['unidad'] ?? '') ?>
                        <?php endif; ?>
                    </small>
                </td>
                <td><small>
                    <?php
                    $km = (int)$r['km_fin'] - (int)$r['km_inicio'];
                    echo $km > 0 ? formatNum($km, 0) . ' km' : '—';
                    ?>
                </small></td>
                <td>
                    <span class="badge bg-<?= $estadoColores[$r['estado']] ?? 'secondary' ?>">
                        <?= h(ucfirst(str_replace('_', ' ', $r['estado']))) ?>
                    </span>
                </td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-info btn-action btn-view js-view" data-vista="transporte_viajes" data-id="<?= $r['id'] ?>" title="Visualizar">
                        <i class="fas fa-eye"></i>
                    </button>
                    <?php if (Auth::can('transporte', 'editar') && $r['estado'] !== 'cancelado'): ?>
                    <a href="viaje?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary btn-action" title="Editar">
                        <i class="fas fa-pen"></i>
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><?= $pag['total'] ?> registros</small>
        <?= paginationHtml($pag, "?desde={$fDesde}&hasta={$fHasta}&obra_id={$obraId}&estado={$estado}") ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
