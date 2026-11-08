<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireAnyPermiso('faena');

$emp  = Auth::empresaId();
$hoy  = date('Y-m-d');
$mes0 = date('Y-m-01');

// Filtros
$fDesde = get('desde', $mes0);
$fHasta = get('hasta', $hoy);
$obraId = getInt('obra_id');
$page   = getInt('page', 1);

// KPIs
$kpiHoy = DB::count("SELECT COUNT(*) FROM partes_diarios WHERE empresa_id=? AND fecha=?", [$emp, $hoy]);
$kpiMes = DB::count("SELECT COUNT(*) FROM partes_diarios WHERE empresa_id=? AND fecha BETWEEN ? AND ?", [$emp, $mes0, $hoy]);
$kpiBorradores = DB::count("SELECT COUNT(*) FROM partes_diarios WHERE empresa_id=? AND estado='borrador'", [$emp]);

// Build query
$where  = "WHERE p.empresa_id=?";
$params = [$emp];
if ($fDesde) { $where .= " AND p.fecha >= ?"; $params[] = $fDesde; }
if ($fHasta) { $where .= " AND p.fecha <= ?"; $params[] = $fHasta; }
if ($obraId) { $where .= " AND p.obra_id = ?"; $params[] = $obraId; }

$total = DB::count("SELECT COUNT(*) FROM partes_diarios p $where", $params);
$pag   = paginate($total, $page);

$rows = DB::all(
    "SELECT p.*, o.nombre AS obra_nombre,
            CONCAT(t.nombre,' ',t.apellido) AS supervisor_nombre,
            u.nombre AS creado_nombre
     FROM partes_diarios p
     LEFT JOIN obras o ON o.id = p.obra_id
     LEFT JOIN trabajadores t ON t.id = p.supervisor_id
     LEFT JOIN usuarios u ON u.id = p.creado_por
     $where ORDER BY p.fecha DESC, p.id DESC
     LIMIT {$pag['limit']} OFFSET {$pag['offset']}",
    $params
);

$obras = DB::all("SELECT id, nombre FROM obras WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);

$pageTitle = 'Partes de Faena';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/dashboard/">Dashboard</a></li>
    <li class="breadcrumb-item active">Partes de Faena</li>
</ol></nav>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card kpi-card kpi-blue">
            <div class="card-body p-3">
                <div class="kpi-icon bg-primary-subtle text-primary mb-2"><i class="fas fa-hard-hat"></i></div>
                <div class="kpi-value text-primary" data-count="<?= $kpiHoy ?>"><?= $kpiHoy ?></div>
                <div class="kpi-label">Partes Hoy</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card kpi-card kpi-cyan">
            <div class="card-body p-3">
                <div class="kpi-icon bg-info-subtle text-info mb-2"><i class="fas fa-calendar-alt"></i></div>
                <div class="kpi-value text-info" data-count="<?= $kpiMes ?>"><?= $kpiMes ?></div>
                <div class="kpi-label">Partes Este Mes</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card kpi-card kpi-amber">
            <div class="card-body p-3">
                <div class="kpi-icon bg-warning-subtle text-warning mb-2"><i class="fas fa-edit"></i></div>
                <div class="kpi-value text-warning" data-count="<?= $kpiBorradores ?>"><?= $kpiBorradores ?></div>
                <div class="kpi-label">Borradores</div>
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
                <select name="obra_id" class="form-select form-select-sm" style="min-width:140px">
                    <option value="">Todas</option>
                    <?php foreach ($obras as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= $obraId == $o['id'] ? 'selected' : '' ?>><?= h($o['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-filter me-1"></i>Filtrar</button>
                <a href="?" class="btn btn-sm btn-outline-secondary ms-1"><i class="fas fa-times"></i></a>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <?= botonesExportacion('faena', [
                        'desde' => $fDesde, 'hasta' => $fHasta, 'obra_id' => $obraId,
                    ]) ?>
                <?php if (Auth::can('faena', 'crear')): ?>
                <a href="parte" class="btn btn-sm btn-primary fw-semibold">
                    <i class="fas fa-plus me-1"></i>Nuevo Parte
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if (Auth::can('faena', 'ver')): ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr><th>Fecha</th><th>Obra</th><th>Supervisor</th><th>Clima</th><th>Estado</th><th>Registró</th><th class="text-end">Acciones</th></tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">
                    <i class="fas fa-hard-hat fa-2x d-block mb-2"></i>Sin partes en el período seleccionado.
                </td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?= formatDate($r['fecha']) ?></strong></td>
                <td><?= h($r['obra_nombre'] ?? '—') ?></td>
                <td><small><?= h($r['supervisor_nombre'] ?? '—') ?></small></td>
                <td><small><?= h($r['clima'] ?? '—') ?></small></td>
                <td>
                    <?php if ($r['estado'] === 'cerrado'): ?>
                        <span class="badge bg-success"><i class="fas fa-lock me-1"></i>Cerrado</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark"><i class="fas fa-edit me-1"></i>Borrador</span>
                    <?php endif; ?>
                </td>
                <td><small class="text-muted"><?= h($r['creado_nombre'] ?? '—') ?></small></td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-info btn-action btn-view js-view" data-vista="faena" data-id="<?= $r['id'] ?>" title="Visualizar">
                        <i class="fas fa-eye"></i>
                    </button>
                    <a href="parte?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary btn-action" title="Editar">
                        <i class="fas fa-pen"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pag['pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><?= $pag['total'] ?> registros</small>
        <?= paginationHtml($pag, "?desde={$fDesde}&hasta={$fHasta}&obra_id={$obraId}") ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
