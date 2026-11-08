<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requirePermiso('transporte');

$emp  = Auth::empresaId();
$hoy  = date('Y-m-d');
$mes0 = date('Y-m-01');

// Filtros
$fDesde  = get('desde', $mes0);
$fHasta  = get('hasta', $hoy);
$obraId  = getInt('obra_id');
$buscar  = get('q');
$page    = getInt('page', 1);

$where  = "WHERE g.empresa_id=?";
$params = [$emp];
if ($fDesde)  { $where .= " AND g.fecha >= ?"; $params[] = $fDesde; }
if ($fHasta)  { $where .= " AND g.fecha <= ?"; $params[] = $fHasta; }
if ($obraId)  { $where .= " AND g.obra_id = ?"; $params[] = $obraId; }
if ($buscar)  { $where .= " AND g.numero_guia LIKE ?"; $params[] = '%' . $buscar . '%'; }

$total = DB::count("SELECT COUNT(*) FROM guias_despacho g $where", $params);
$pag   = paginate($total, $page);

$rows = DB::all(
    "SELECT g.*,
            ve.patente,
            CONCAT(tc.nombre,' ',tc.apellido) AS conductor_nombre,
            o.nombre AS obra_nombre,
            m.nombre AS material_nombre
     FROM guias_despacho g
     LEFT JOIN vehiculos  ve ON ve.id = g.vehiculo_id
     LEFT JOIN conductores c ON c.id  = g.conductor_id
     LEFT JOIN trabajadores tc ON tc.id = c.trabajador_id
     LEFT JOIN obras       o ON o.id  = g.obra_id
     LEFT JOIN materiales  m ON m.id  = g.material_id
     $where
     ORDER BY g.fecha DESC, g.id DESC
     LIMIT {$pag['limit']} OFFSET {$pag['offset']}",
    $params
);

$obras = DB::all("SELECT id, nombre FROM obras WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);

$pageTitle = 'Guías de Despacho';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index">Transporte</a></li>
    <li class="breadcrumb-item active">Guías de Despacho</li>
</ol></nav>

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
                <label class="form-label form-label-sm mb-1">N° Guía</label>
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar…" value="<?= h($buscar) ?>" style="width:110px">
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-filter me-1"></i>Filtrar</button>
                <a href="guias" class="btn btn-sm btn-outline-secondary ms-1"><i class="fas fa-times"></i></a>
            </div>
            <div class="col-auto ms-auto">
                <?= botonesExportacion('transporte_guias', [
                        'desde' => $fDesde, 'hasta' => $fHasta,
                        'obra_id' => $obraId, 'q' => $buscar,
                    ]) ?>
                <?php if (Auth::can('transporte', 'crear')): ?>
                <a href="guia" class="btn btn-sm btn-success fw-semibold">
                    <i class="fas fa-plus me-1"></i>Nueva Guía
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>N° Guía</th>
                    <th>Fecha</th>
                    <th>Vehículo</th>
                    <th>Conductor</th>
                    <th>Origen → Destino</th>
                    <th>Material</th>
                    <th>Cantidad</th>
                    <th>Receptor</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">
                    <i class="fas fa-file-invoice fa-2x d-block mb-2"></i>Sin guías en el período seleccionado.
                </td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><span class="badge bg-dark"><?= h($r['numero_guia']) ?></span></td>
                <td><?= formatDate($r['fecha']) ?></td>
                <td><small><?= h($r['patente'] ?? '—') ?></small></td>
                <td><small><?= h($r['conductor_nombre'] ?? '—') ?></small></td>
                <td>
                    <small>
                        <?= h(mb_substr($r['origen'] ?? '—', 0, 20)) ?>
                        <?php if ($r['destino']): ?><br><i class="fas fa-long-arrow-alt-right text-muted"></i> <?= h(mb_substr($r['destino'], 0, 20)) ?><?php endif; ?>
                    </small>
                </td>
                <td><small><?= h($r['material_nombre'] ?? '—') ?></small></td>
                <td><small><?= $r['cantidad'] ? formatNum((float)$r['cantidad'], 2) . ' ' . h($r['unidad'] ?? '') : '—' ?></small></td>
                <td><small><?= h($r['receptor'] ?? '—') ?></small></td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-info btn-action btn-view js-view" data-vista="transporte_guias" data-id="<?= $r['id'] ?>" title="Visualizar">
                        <i class="fas fa-eye"></i>
                    </button>
                    <?php if (Auth::can('transporte', 'editar')): ?>
                    <a href="guia?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary btn-action" title="Editar">
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
        <?= paginationHtml($pag, "?desde={$fDesde}&hasta={$fHasta}&obra_id={$obraId}&q=" . urlencode($buscar)) ?>
    </div>
    <?php endif; ?>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
