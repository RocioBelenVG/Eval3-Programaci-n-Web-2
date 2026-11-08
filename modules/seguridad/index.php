<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireAnyPermiso('seguridad');

$emp  = Auth::empresaId();
$hoy  = date('Y-m-d');
$mes0 = date('Y-m-01');

// KPIs
$kpiChecklistsHoy   = DB::count("SELECT COUNT(*) FROM checklists_registros WHERE empresa_id=? AND fecha=?", [$emp, $hoy]);
$kpiChecklistsMes   = DB::count("SELECT COUNT(*) FROM checklists_registros WHERE empresa_id=? AND fecha BETWEEN ? AND ?", [$emp, $mes0, $hoy]);
$kpiIncidentesMes   = DB::count("SELECT COUNT(*) FROM incidentes WHERE empresa_id=? AND fecha BETWEEN ? AND ?", [$emp, $mes0, $hoy]);
$kpiIncAbiertos     = DB::count("SELECT COUNT(*) FROM incidentes WHERE empresa_id=? AND estado IN ('reportado','investigando')", [$emp]);

// Filtros
$tab    = get('tab', 'checklists');
$fDesde = get('desde', $mes0);
$fHasta = get('hasta', $hoy);
$obraId = getInt('obra_id');
$page   = getInt('page', 1);

if ($tab === 'incidentes') {
    $where  = "WHERE i.empresa_id=?";
    $params = [$emp];
    if ($fDesde) { $where .= " AND i.fecha >= ?"; $params[] = $fDesde; }
    if ($fHasta) { $where .= " AND i.fecha <= ?"; $params[] = $fHasta; }
    if ($obraId) { $where .= " AND i.obra_id = ?"; $params[] = $obraId; }
    $total = DB::count("SELECT COUNT(*) FROM incidentes i $where", $params);
    $pag   = paginate($total, $page);
    $rows  = DB::all(
        "SELECT i.*, o.nombre AS obra_nombre,
                CONCAT(t.nombre,' ',t.apellido) AS trab_nombre
         FROM incidentes i
         LEFT JOIN obras o ON o.id=i.obra_id
         LEFT JOIN trabajadores t ON t.id=i.trabajador_id
         $where ORDER BY i.fecha DESC, i.id DESC
         LIMIT {$pag['limit']} OFFSET {$pag['offset']}",
        $params
    );
} else {
    $where  = "WHERE cr.empresa_id=?";
    $params = [$emp];
    if ($fDesde) { $where .= " AND cr.fecha >= ?"; $params[] = $fDesde; }
    if ($fHasta) { $where .= " AND cr.fecha <= ?"; $params[] = $fHasta; }
    if ($obraId) { $where .= " AND cr.obra_id = ?"; $params[] = $obraId; }
    $total = DB::count("SELECT COUNT(*) FROM checklists_registros cr $where", $params);
    $pag   = paginate($total, $page);
    $rows  = DB::all(
        "SELECT cr.*, tc.nombre AS tipo_nombre, o.nombre AS obra_nombre,
                e.codigo AS equipo_codigo, ve.patente,
                u.nombre AS creado_nombre
         FROM checklists_registros cr
         LEFT JOIN tipos_checklist tc ON tc.id=cr.tipo_id
         LEFT JOIN obras o ON o.id=cr.obra_id
         LEFT JOIN equipos e ON e.id=cr.equipo_id
         LEFT JOIN vehiculos ve ON ve.id=cr.vehiculo_id
         LEFT JOIN usuarios u ON u.id=cr.creado_por
         $where ORDER BY cr.fecha DESC, cr.id DESC
         LIMIT {$pag['limit']} OFFSET {$pag['offset']}",
        $params
    );
}

$obras = DB::all("SELECT id, nombre FROM obras WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);

$resColors = ['aprobado'=>'success','rechazado'=>'danger','observado'=>'warning'];
$gravColors = ['leve'=>'info','moderado'=>'warning','grave'=>'danger','fatal'=>'dark'];
$estColors  = ['reportado'=>'warning','investigando'=>'primary','cerrado'=>'success'];

$pageTitle = 'Seguridad y Prevención';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/dashboard/">Dashboard</a></li>
    <li class="breadcrumb-item active">Seguridad</li>
</ol></nav>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-green">
            <div class="card-body p-3">
                <div class="kpi-icon bg-success-subtle text-success mb-2"><i class="fas fa-clipboard-check"></i></div>
                <div class="kpi-value text-success" data-count="<?= $kpiChecklistsHoy ?>"><?= $kpiChecklistsHoy ?></div>
                <div class="kpi-label">Checklists Hoy</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-cyan">
            <div class="card-body p-3">
                <div class="kpi-icon bg-info-subtle text-info mb-2"><i class="fas fa-calendar-check"></i></div>
                <div class="kpi-value text-info" data-count="<?= $kpiChecklistsMes ?>"><?= $kpiChecklistsMes ?></div>
                <div class="kpi-label">Checklists Mes</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-amber">
            <div class="card-body p-3">
                <div class="kpi-icon bg-warning-subtle text-warning mb-2"><i class="fas fa-exclamation-circle"></i></div>
                <div class="kpi-value text-warning" data-count="<?= $kpiIncidentesMes ?>"><?= $kpiIncidentesMes ?></div>
                <div class="kpi-label">Incidentes Mes</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-red">
            <div class="card-body p-3">
                <div class="kpi-icon bg-danger-subtle text-danger mb-2"><i class="fas fa-radiation-alt"></i></div>
                <div class="kpi-value text-danger" data-count="<?= $kpiIncAbiertos ?>"><?= $kpiIncAbiertos ?></div>
                <div class="kpi-label">Incidentes Abiertos</div>
            </div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <ul class="nav nav-tabs card-header-tabs mb-0">
                <li class="nav-item">
                    <a class="nav-link <?= $tab === 'checklists' ? 'active' : '' ?>"
                       href="?tab=checklists&desde=<?= h($fDesde) ?>&hasta=<?= h($fHasta) ?>&obra_id=<?= $obraId ?>">
                       <i class="fas fa-clipboard-check me-1"></i>Checklists
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $tab === 'incidentes' ? 'active' : '' ?>"
                       href="?tab=incidentes&desde=<?= h($fDesde) ?>&hasta=<?= h($fHasta) ?>&obra_id=<?= $obraId ?>">
                       <i class="fas fa-exclamation-circle me-1"></i>Incidentes
                       <?php if ($kpiIncAbiertos > 0): ?>
                       <span class="badge bg-danger ms-1"><?= $kpiIncAbiertos ?></span>
                       <?php endif; ?>
                    </a>
                </li>
            </ul>
            <div class="d-flex gap-2">
                <?= botonesExportacion(
                        $tab === 'incidentes' ? 'seguridad_incidentes' : 'seguridad_checklists',
                        ['desde' => $fDesde, 'hasta' => $fHasta, 'obra_id' => $obraId]
                    ) ?>
                <?php if (Auth::can('seguridad', 'crear')): ?>
                <?php if ($tab === 'incidentes'): ?>
                <a href="incidente" class="btn btn-sm btn-danger fw-semibold"><i class="fas fa-plus me-1"></i>Reportar Incidente</a>
                <?php else: ?>
                <a href="checklist" class="btn btn-sm btn-success fw-semibold"><i class="fas fa-plus me-1"></i>Nuevo Checklist</a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <form class="row g-2 align-items-end mt-2" method="GET">
            <input type="hidden" name="tab" value="<?= h($tab) ?>">
            <div class="col-auto">
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= h($fDesde) ?>">
            </div>
            <div class="col-auto">
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= h($fHasta) ?>">
            </div>
            <div class="col-auto">
                <select name="obra_id" class="form-select form-select-sm" style="min-width:120px">
                    <option value="">Todas las obras</option>
                    <?php foreach ($obras as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= $obraId == $o['id'] ? 'selected' : '' ?>><?= h($o['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-filter me-1"></i>Filtrar</button>
                <a href="?tab=<?= h($tab) ?>" class="btn btn-sm btn-outline-secondary ms-1"><i class="fas fa-times"></i></a>
            </div>
        </form>
    </div>

    <?php if (Auth::can('seguridad', 'ver')): ?>
    <div class="table-responsive">
    <?php if ($tab === 'checklists'): ?>
        <table class="table table-hover mb-0">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Obra</th><th>Turno</th><th>Equipo / Vehículo</th><th>Resultado</th><th>Registró</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-clipboard fa-2x d-block mb-2"></i>Sin checklists registrados.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= formatDate($r['fecha']) ?></td>
                <td><small><?= h($r['tipo_nombre'] ?? '—') ?></small></td>
                <td><small><?= h($r['obra_nombre'] ?? '—') ?></small></td>
                <td><small><?= h(ucfirst($r['turno'] ?? '—')) ?></small></td>
                <td><small><?= h($r['equipo_codigo'] ?? $r['patente'] ?? '—') ?></small></td>
                <td><span class="badge bg-<?= $resColors[$r['resultado']] ?? 'secondary' ?>"><?= h(ucfirst($r['resultado'])) ?></span></td>
                <td><small><?= h($r['creado_nombre'] ?? '—') ?></small></td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-info btn-action btn-view js-view" data-vista="seguridad_checklists" data-id="<?= $r['id'] ?>" title="Visualizar"><i class="fas fa-eye"></i></button>
                    <?php if (Auth::can('seguridad', 'editar')): ?>
                    <a href="checklist?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary btn-action" title="Editar"><i class="fas fa-pen"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <table class="table table-hover mb-0">
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Gravedad</th><th>Descripción</th><th>Obra</th><th>Trabajador</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4"><i class="fas fa-shield-alt fa-2x d-block mb-2"></i>Sin incidentes registrados.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= formatDate($r['fecha']) ?></td>
                <td><small><?= h(ucfirst(str_replace('_', ' ', $r['tipo']))) ?></small></td>
                <td><span class="badge bg-<?= $gravColors[$r['gravedad']] ?? 'secondary' ?>"><?= h(ucfirst($r['gravedad'])) ?></span></td>
                <td><small><?= h(mb_substr($r['descripcion'], 0, 50)) ?></small></td>
                <td><small><?= h($r['obra_nombre'] ?? '—') ?></small></td>
                <td><small><?= h($r['trab_nombre'] ?? '—') ?></small></td>
                <td><span class="badge bg-<?= $estColors[$r['estado']] ?? 'secondary' ?>"><?= h(ucfirst($r['estado'])) ?></span></td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-info btn-action btn-view js-view" data-vista="seguridad_incidentes" data-id="<?= $r['id'] ?>" title="Visualizar"><i class="fas fa-eye"></i></button>
                    <?php if (Auth::can('seguridad', 'editar')): ?>
                    <a href="incidente?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary btn-action" title="Editar"><i class="fas fa-pen"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
    <?php if ($pag['pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><?= $pag['total'] ?> registros</small>
        <?= paginationHtml($pag, "?tab={$tab}&desde={$fDesde}&hasta={$fHasta}&obra_id={$obraId}") ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
