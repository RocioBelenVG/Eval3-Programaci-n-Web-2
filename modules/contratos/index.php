<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireAnyPermiso('contratos');

$emp  = Auth::empresaId();
$hoy  = date('Y-m-d');

// KPIs
$kpiActivos   = DB::count("SELECT COUNT(*) FROM contratos WHERE empresa_id=? AND activo=1", [$emp]);
$kpiMonto     = DB::one("SELECT COALESCE(SUM(monto_contrato),0) AS t FROM contratos WHERE empresa_id=? AND activo=1", [$emp]);
$kpiAvanzado  = DB::one("SELECT COALESCE(SUM(monto),0) AS t FROM avances_contrato av INNER JOIN contratos c ON c.id=av.contrato_id WHERE c.empresa_id=?", [$emp]);
$kpiVencen30  = DB::count("SELECT COUNT(*) FROM contratos WHERE empresa_id=? AND activo=1 AND fecha_termino BETWEEN ? AND DATE_ADD(?,INTERVAL 30 DAY)", [$emp, $hoy, $hoy]);

// Filtros
$buscar  = get('q');
$estado  = getInt('estado_id');
$page    = getInt('page', 1);

$where  = "WHERE c.empresa_id=? AND c.activo=1";
$params = [$emp];
if ($buscar) { $where .= " AND (c.numero LIKE ? OR c.descripcion LIKE ?)"; $params[] = "%{$buscar}%"; $params[] = "%{$buscar}%"; }
if ($estado) { $where .= " AND c.estado_id=?"; $params[] = $estado; }

$total = DB::count("SELECT COUNT(*) FROM contratos c $where", $params);
$pag   = paginate($total, $page);

$rows = DB::all(
    "SELECT c.*,
            o.nombre AS obra_nombre,
            cl.razon_social AS cliente_nombre,
            ec.nombre AS estado_nombre,
            ec.color AS estado_color,
            COALESCE((SELECT SUM(monto) FROM avances_contrato WHERE contrato_id=c.id),0) AS monto_avanzado
     FROM contratos c
     LEFT JOIN obras o           ON o.id=c.obra_id
     LEFT JOIN clientes cl       ON cl.id=c.cliente_id
     LEFT JOIN estados_contrato ec ON ec.id=c.estado_id
     $where
     ORDER BY c.fecha_inicio DESC, c.id DESC
     LIMIT {$pag['limit']} OFFSET {$pag['offset']}",
    $params
);

$estados = DB::all("SELECT id, nombre FROM estados_contrato ORDER BY nombre", []);

$pageTitle = 'Gestión de Contratos';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/dashboard/">Dashboard</a></li>
    <li class="breadcrumb-item active">Contratos</li>
</ol></nav>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-blue">
            <div class="card-body p-3">
                <div class="kpi-icon bg-primary-subtle text-primary mb-2"><i class="fas fa-file-contract"></i></div>
                <div class="kpi-value text-primary" data-count="<?= $kpiActivos ?>"><?= $kpiActivos ?></div>
                <div class="kpi-label">Contratos Activos</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-green">
            <div class="card-body p-3">
                <div class="kpi-icon bg-success-subtle text-success mb-2"><i class="fas fa-dollar-sign"></i></div>
                <div class="kpi-value text-success" style="font-size:1.2rem"><?= formatMoney((float)$kpiMonto['t']) ?></div>
                <div class="kpi-label">Monto Total</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-cyan">
            <div class="card-body p-3">
                <div class="kpi-icon bg-info-subtle text-info mb-2"><i class="fas fa-tasks"></i></div>
                <div class="kpi-value text-info" style="font-size:1.2rem"><?= formatMoney((float)$kpiAvanzado['t']) ?></div>
                <div class="kpi-label">Monto Avanzado</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-amber">
            <div class="card-body p-3">
                <div class="kpi-icon bg-warning-subtle text-warning mb-2"><i class="fas fa-clock"></i></div>
                <div class="kpi-value text-warning" data-count="<?= $kpiVencen30 ?>"><?= $kpiVencen30 ?></div>
                <div class="kpi-label">Vencen en 30 días</div>
            </div>
        </div>
    </div>
</div>

<div class="card table-card">
    <div class="card-header">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-auto">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar contrato…"
                       value="<?= h($buscar) ?>" style="width:180px">
            </div>
            <div class="col-auto">
                <select name="estado_id" class="form-select form-select-sm" style="min-width:130px">
                    <option value="">Todos los estados</option>
                    <?php foreach ($estados as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $estado == $e['id'] ? 'selected' : '' ?>><?= h($e['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-filter me-1"></i>Filtrar</button>
                <a href="?" class="btn btn-sm btn-outline-secondary ms-1"><i class="fas fa-times"></i></a>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <?= botonesExportacion('contratos', ['q' => $buscar, 'estado_id' => $estado]) ?>
                <?php if (Auth::can('contratos', 'crear')): ?>
                <a href="avance" class="btn btn-sm btn-primary fw-semibold">
                    <i class="fas fa-plus me-1"></i>Registrar Avance
                </a>
                <a href="items" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-list me-1"></i>Ítems
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if (Auth::can('contratos', 'ver')): ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>N° Contrato</th>
                    <th>Descripción</th>
                    <th>Obra / Cliente</th>
                    <th>Monto</th>
                    <th>Avance</th>
                    <th>Vigencia</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">
                    <i class="fas fa-file-contract fa-2x d-block mb-2"></i>Sin contratos registrados.
                    <a href="<?= APP_URL ?>/modules/admin/maestros/contratos">Agregar contratos</a>
                </td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <?php
                $pct = $r['monto_contrato'] > 0 ? min(100, round(($r['monto_avanzado'] / $r['monto_contrato']) * 100)) : 0;
                $pctColor = $pct >= 100 ? 'success' : ($pct >= 70 ? 'primary' : ($pct >= 40 ? 'info' : 'warning'));
                $venceProx = $r['fecha_termino'] && $r['fecha_termino'] <= date('Y-m-d', strtotime('+30 days'));
            ?>
            <tr>
                <td><span class="badge bg-dark"><?= h($r['numero'] ?? 'S/N') ?></span></td>
                <td>
                    <div class="fw-semibold small"><?= h(mb_substr($r['descripcion'] ?? '', 0, 45)) ?></div>
                    <small class="text-muted"><?= h($r['tipo'] ?? '') ?></small>
                </td>
                <td>
                    <small><?= h($r['obra_nombre'] ?? '—') ?></small>
                    <?php if ($r['cliente_nombre']): ?><br><small class="text-muted"><?= h($r['cliente_nombre']) ?></small><?php endif; ?>
                </td>
                <td><small><?= formatMoney((float)$r['monto_contrato']) ?></small></td>
                <td style="min-width:120px">
                    <div class="d-flex align-items-center gap-1">
                        <div class="progress flex-grow-1" style="height:8px">
                            <div class="progress-bar bg-<?= $pctColor ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                        <small class="text-nowrap"><?= $pct ?>%</small>
                    </div>
                    <small class="text-muted"><?= formatMoney((float)$r['monto_avanzado']) ?></small>
                </td>
                <td>
                    <small><?= $r['fecha_inicio'] ? formatDate($r['fecha_inicio']) : '—' ?></small>
                    <?php if ($r['fecha_termino']): ?>
                    <br><small class="<?= $venceProx ? 'text-danger fw-bold' : 'text-muted' ?>">
                        <?= $venceProx ? '<i class="fas fa-exclamation-triangle me-1"></i>' : '' ?>
                        <?= formatDate($r['fecha_termino']) ?>
                    </small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($r['estado_nombre']): ?>
                    <span class="badge" style="background:<?= h($r['estado_color'] ?? '#6c757d') ?>"><?= h($r['estado_nombre']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-info btn-action btn-view js-view" data-vista="contratos" data-id="<?= $r['id'] ?>" title="Visualizar">
                        <i class="fas fa-eye"></i>
                    </button>
                    <?php if (Auth::can('contratos', 'crear')): ?>
                    <a href="avance?contrato_id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary btn-action" title="Registrar avance">
                        <i class="fas fa-chart-line"></i>
                    </a>
                    <?php endif; ?>
                    <a href="items?contrato_id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action" title="Ver ítems">
                        <i class="fas fa-list"></i>
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
        <?= paginationHtml($pag, "?q=" . urlencode($buscar) . "&estado_id={$estado}") ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
