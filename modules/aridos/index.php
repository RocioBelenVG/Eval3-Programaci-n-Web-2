<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireAnyPermiso('aridos');

$emp  = Auth::empresaId();
$hoy  = date('Y-m-d');
$mes0 = date('Y-m-01');

// KPIs
$kpiProdHoy   = DB::one("SELECT COALESCE(SUM(cantidad),0) AS t FROM produccion_aridos WHERE empresa_id=? AND fecha=?", [$emp, $hoy]);
$kpiProdMes   = DB::one("SELECT COALESCE(SUM(cantidad),0) AS t FROM produccion_aridos WHERE empresa_id=? AND fecha BETWEEN ? AND ?", [$emp, $mes0, $hoy]);
$kpiDespHoy   = DB::count("SELECT COUNT(*) FROM despachos_aridos WHERE empresa_id=? AND fecha=?", [$emp, $hoy]);
$kpiDespMes   = DB::one("SELECT COALESCE(SUM(cantidad),0) AS t FROM despachos_aridos WHERE empresa_id=? AND fecha BETWEEN ? AND ?", [$emp, $mes0, $hoy]);

// Stock actual por material
$stock = DB::all(
    "SELECT s.cantidad, s.unidad, m.nombre AS material, c.nombre AS clasificacion, o.nombre AS obra_nombre
     FROM stock_aridos s
     LEFT JOIN materiales m ON m.id=s.material_id
     LEFT JOIN clasificaciones_arido c ON c.id=s.clasificacion_id
     LEFT JOIN obras o ON o.id=s.obra_id
     WHERE s.empresa_id=? AND s.cantidad > 0
     ORDER BY m.nombre, c.nombre", [$emp]);

// Filtros producción
$fDesde = get('desde', $mes0);
$fHasta = get('hasta', $hoy);
$obraId = getInt('obra_id');
$matId  = getInt('material_id');
$page   = getInt('page', 1);
$tab    = get('tab', 'produccion');

if ($tab === 'despachos') {
    $where  = "WHERE d.empresa_id=?";
    $params = [$emp];
    if ($fDesde) { $where .= " AND d.fecha >= ?"; $params[] = $fDesde; }
    if ($fHasta) { $where .= " AND d.fecha <= ?"; $params[] = $fHasta; }
    if ($obraId) { $where .= " AND d.obra_id = ?"; $params[] = $obraId; }
    if ($matId)  { $where .= " AND d.material_id = ?"; $params[] = $matId; }
    $total = DB::count("SELECT COUNT(*) FROM despachos_aridos d $where", $params);
    $pag   = paginate($total, $page);
    $rows  = DB::all(
        "SELECT d.*, m.nombre AS mat_nombre, cl.nombre AS clas_nombre,
                o.nombre AS obra_nombre, cli.razon_social AS cliente_nombre,
                ve.patente, CONCAT(tc.nombre,' ',tc.apellido) AS conductor_nombre
         FROM despachos_aridos d
         LEFT JOIN materiales m ON m.id=d.material_id
         LEFT JOIN clasificaciones_arido cl ON cl.id=d.clasificacion_id
         LEFT JOIN obras o ON o.id=d.obra_id
         LEFT JOIN clientes cli ON cli.id=d.cliente_id
         LEFT JOIN vehiculos ve ON ve.id=d.vehiculo_id
         LEFT JOIN conductores c ON c.id=d.conductor_id
         LEFT JOIN trabajadores tc ON tc.id=c.trabajador_id
         $where ORDER BY d.fecha DESC, d.id DESC
         LIMIT {$pag['limit']} OFFSET {$pag['offset']}",
        $params
    );
} else {
    $where  = "WHERE p.empresa_id=?";
    $params = [$emp];
    if ($fDesde) { $where .= " AND p.fecha >= ?"; $params[] = $fDesde; }
    if ($fHasta) { $where .= " AND p.fecha <= ?"; $params[] = $fHasta; }
    if ($obraId) { $where .= " AND p.obra_id = ?"; $params[] = $obraId; }
    if ($matId)  { $where .= " AND p.material_id = ?"; $params[] = $matId; }
    $total = DB::count("SELECT COUNT(*) FROM produccion_aridos p $where", $params);
    $pag   = paginate($total, $page);
    $rows  = DB::all(
        "SELECT p.*, m.nombre AS mat_nombre, cl.nombre AS clas_nombre,
                o.nombre AS obra_nombre, e.codigo AS equipo_codigo,
                CONCAT(top2.nombre,' ',top2.apellido) AS operador_nombre
         FROM produccion_aridos p
         LEFT JOIN materiales m ON m.id=p.material_id
         LEFT JOIN clasificaciones_arido cl ON cl.id=p.clasificacion_id
         LEFT JOIN obras o ON o.id=p.obra_id
         LEFT JOIN equipos e ON e.id=p.equipo_id
         LEFT JOIN operadores op ON op.id=p.operador_id
         LEFT JOIN trabajadores top2 ON top2.id=op.trabajador_id
         $where ORDER BY p.fecha DESC, p.id DESC
         LIMIT {$pag['limit']} OFFSET {$pag['offset']}",
        $params
    );
}

$obras      = DB::all("SELECT id, nombre FROM obras WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);
$materiales = DB::all("SELECT id, nombre FROM materiales WHERE activo=1 ORDER BY nombre", []);

$pageTitle = 'Gestión de Áridos';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/dashboard/">Dashboard</a></li>
    <li class="breadcrumb-item active">Áridos</li>
</ol></nav>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-amber">
            <div class="card-body p-3">
                <div class="kpi-icon bg-warning-subtle text-warning mb-2"><i class="fas fa-industry"></i></div>
                <div class="kpi-value text-warning"><?= formatNum((float)$kpiProdHoy['t'], 1) ?></div>
                <div class="kpi-label">Producción Hoy (ton)</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-blue">
            <div class="card-body p-3">
                <div class="kpi-icon bg-primary-subtle text-primary mb-2"><i class="fas fa-calendar-alt"></i></div>
                <div class="kpi-value text-primary"><?= formatNum((float)$kpiProdMes['t'], 0) ?></div>
                <div class="kpi-label">Producción Mes (ton)</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-green">
            <div class="card-body p-3">
                <div class="kpi-icon bg-success-subtle text-success mb-2"><i class="fas fa-truck-loading"></i></div>
                <div class="kpi-value text-success" data-count="<?= $kpiDespHoy ?>"><?= $kpiDespHoy ?></div>
                <div class="kpi-label">Despachos Hoy</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-cyan">
            <div class="card-body p-3">
                <div class="kpi-icon bg-info-subtle text-info mb-2"><i class="fas fa-weight-hanging"></i></div>
                <div class="kpi-value text-info"><?= formatNum((float)$kpiDespMes['t'], 0) ?></div>
                <div class="kpi-label">Despachado Mes (ton)</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Stock actual -->
    <div class="col-lg-4">
        <div class="card table-card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <strong><i class="fas fa-cubes text-warning me-2"></i>Stock Actual</strong>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Material</th><th>Obra</th><th class="text-end">Cantidad</th></tr></thead>
                    <tbody>
                    <?php if (empty($stock)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-3">Sin stock registrado.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($stock as $s): ?>
                    <tr>
                        <td>
                            <small class="fw-semibold"><?= h($s['material']) ?></small>
                            <?php if ($s['clasificacion']): ?>
                            <br><span class="badge bg-secondary" style="font-size:.65rem"><?= h($s['clasificacion']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><small class="text-muted"><?= h($s['obra_nombre'] ?? 'General') ?></small></td>
                        <td class="text-end"><strong><?= formatNum((float)$s['cantidad'], 1) ?></strong> <small><?= h($s['unidad']) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Registros con tabs -->
    <div class="col-lg-8">
        <div class="card table-card">
            <div class="card-header">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <ul class="nav nav-tabs card-header-tabs mb-0">
                        <li class="nav-item">
                            <a class="nav-link <?= $tab === 'produccion' ? 'active' : '' ?>"
                               href="?tab=produccion&desde=<?= h($fDesde) ?>&hasta=<?= h($fHasta) ?>&obra_id=<?= $obraId ?>">
                               <i class="fas fa-industry me-1"></i>Producción
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $tab === 'despachos' ? 'active' : '' ?>"
                               href="?tab=despachos&desde=<?= h($fDesde) ?>&hasta=<?= h($fHasta) ?>&obra_id=<?= $obraId ?>">
                               <i class="fas fa-truck-loading me-1"></i>Despachos
                            </a>
                        </li>
                    </ul>
                    <div class="d-flex gap-2">
                        <?= botonesExportacion(
                                $tab === 'despachos' ? 'aridos_despachos' : 'aridos_produccion',
                                ['desde' => $fDesde, 'hasta' => $fHasta,
                                 'obra_id' => $obraId, 'material_id' => $matId]
                            ) ?>
                        <?php if (Auth::can('aridos', 'crear')): ?>
                        <?php if ($tab === 'despachos'): ?>
                        <a href="despacho" class="btn btn-sm btn-success fw-semibold"><i class="fas fa-plus me-1"></i>Nuevo Despacho</a>
                        <?php else: ?>
                        <a href="produccion" class="btn btn-sm btn-warning fw-semibold text-dark"><i class="fas fa-plus me-1"></i>Registrar Producción</a>
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
                        <select name="material_id" class="form-select form-select-sm" style="min-width:110px">
                            <option value="">Todos</option>
                            <?php foreach ($materiales as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= $matId == $m['id'] ? 'selected' : '' ?>><?= h($m['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-filter me-1"></i>Filtrar</button>
                        <a href="?tab=<?= h($tab) ?>" class="btn btn-sm btn-outline-secondary ms-1"><i class="fas fa-times"></i></a>
                    </div>
                </form>
            </div>

            <div class="table-responsive">
            <?php if ($tab === 'produccion'): ?>
                <table class="table table-hover mb-0">
                    <thead><tr><th>Fecha</th><th>Material</th><th>Obra</th><th>Turno</th><th>Equipo</th><th class="text-end">Cantidad</th><th class="text-end">Acciones</th></tr></thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-industry fa-2x d-block mb-2"></i>Sin producción registrada.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= formatDate($r['fecha']) ?></td>
                        <td>
                            <small class="fw-semibold"><?= h($r['mat_nombre']) ?></small>
                            <?php if ($r['clas_nombre']): ?><br><span class="badge bg-secondary" style="font-size:.65rem"><?= h($r['clas_nombre']) ?></span><?php endif; ?>
                        </td>
                        <td><small><?= h($r['obra_nombre'] ?? '—') ?></small></td>
                        <td><small><?= h(ucfirst($r['turno'] ?? '—')) ?></small></td>
                        <td><small><?= h($r['equipo_codigo'] ?? '—') ?></small></td>
                        <td class="text-end"><strong><?= formatNum((float)$r['cantidad'], 2) ?></strong> <small><?= h($r['unidad']) ?></small></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-info btn-action btn-view js-view" data-vista="aridos_produccion" data-id="<?= $r['id'] ?>" title="Visualizar"><i class="fas fa-eye"></i></button>
                            <?php if (Auth::can('aridos', 'editar')): ?>
                            <a href="produccion?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary btn-action" title="Editar"><i class="fas fa-pen"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <table class="table table-hover mb-0">
                    <thead><tr><th>Fecha</th><th>N° Guía</th><th>Material</th><th>Destino / Cliente</th><th>Vehículo</th><th class="text-end">Cantidad</th><th class="text-end">Acciones</th></tr></thead>
                    <tbody>
                    <?php if (empty($rows)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4"><i class="fas fa-truck-loading fa-2x d-block mb-2"></i>Sin despachos registrados.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= formatDate($r['fecha']) ?></td>
                        <td><span class="badge bg-dark"><?= h($r['guia_numero'] ?? '—') ?></span></td>
                        <td>
                            <small class="fw-semibold"><?= h($r['mat_nombre']) ?></small>
                            <?php if ($r['clas_nombre']): ?><br><span class="badge bg-secondary" style="font-size:.65rem"><?= h($r['clas_nombre']) ?></span><?php endif; ?>
                        </td>
                        <td>
                            <small><?= h($r['cliente_nombre'] ?? $r['destino'] ?? '—') ?></small>
                            <?php if ($r['receptor']): ?><br><small class="text-muted"><?= h($r['receptor']) ?></small><?php endif; ?>
                        </td>
                        <td><small><?= h($r['patente'] ?? '—') ?></small></td>
                        <td class="text-end"><strong><?= formatNum((float)$r['cantidad'], 2) ?></strong> <small><?= h($r['unidad']) ?></small></td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-info btn-action btn-view js-view" data-vista="aridos_despachos" data-id="<?= $r['id'] ?>" title="Visualizar"><i class="fas fa-eye"></i></button>
                            <?php if (Auth::can('aridos', 'editar')): ?>
                            <a href="despacho?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary btn-action" title="Editar"><i class="fas fa-pen"></i></a>
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
                <?= paginationHtml($pag, "?tab={$tab}&desde={$fDesde}&hasta={$fHasta}&obra_id={$obraId}&material_id={$matId}") ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
