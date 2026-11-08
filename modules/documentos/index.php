<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireAnyPermiso('documentos');

$emp  = Auth::empresaId();
$hoy  = date('Y-m-d');

// KPIs alertas
$kpiTotal    = DB::count("SELECT COUNT(*) FROM documentos WHERE empresa_id=? AND activo=1", [$emp]);
$kpiVencidos = DB::count("SELECT COUNT(*) FROM documentos WHERE empresa_id=? AND activo=1 AND fecha_vencimiento < ?", [$emp, $hoy]);
$kpiProx15   = DB::count("SELECT COUNT(*) FROM documentos WHERE empresa_id=? AND activo=1 AND fecha_vencimiento BETWEEN ? AND DATE_ADD(?,INTERVAL 15 DAY)", [$emp, $hoy, $hoy]);
$kpiProx30   = DB::count("SELECT COUNT(*) FROM documentos WHERE empresa_id=? AND activo=1 AND fecha_vencimiento BETWEEN DATE_ADD(?,INTERVAL 15 DAY) AND DATE_ADD(?,INTERVAL 30 DAY)", [$emp, $hoy, $hoy]);

// Filtros
$buscar     = get('q');
$tipoId     = getInt('tipo_id');
$entTipo    = get('entidad_tipo');
$alerta     = get('alerta');
$obraId     = getInt('obra_id');
$page       = getInt('page', 1);

$where  = "WHERE d.empresa_id=? AND d.activo=1";
$params = [$emp];
if ($buscar)  { $where .= " AND (d.titulo LIKE ? OR d.numero LIKE ?)"; $params[] = "%{$buscar}%"; $params[] = "%{$buscar}%"; }
if ($tipoId)  { $where .= " AND d.tipo_id=?";       $params[] = $tipoId; }
if ($entTipo) { $where .= " AND d.entidad_tipo=?";  $params[] = $entTipo; }
if ($obraId)  { $where .= " AND d.obra_id=?";       $params[] = $obraId; }
if ($alerta === 'vencido') { $where .= " AND d.fecha_vencimiento < ?"; $params[] = $hoy; }
elseif ($alerta === 'prox15') { $where .= " AND d.fecha_vencimiento BETWEEN ? AND DATE_ADD(?,INTERVAL 15 DAY)"; $params[] = $hoy; $params[] = $hoy; }
elseif ($alerta === 'prox30') { $where .= " AND d.fecha_vencimiento BETWEEN ? AND DATE_ADD(?,INTERVAL 30 DAY)"; $params[] = $hoy; $params[] = $hoy; }

$total = DB::count("SELECT COUNT(*) FROM documentos d $where", $params);
$pag   = paginate($total, $page);

$rows = DB::all(
    "SELECT d.*, td.nombre AS tipo_nombre, ed.nombre AS estado_nombre, o.nombre AS obra_nombre
     FROM documentos d
     LEFT JOIN tipos_documento td ON td.id=d.tipo_id
     LEFT JOIN estados_documento ed ON ed.id=d.estado_id
     LEFT JOIN obras o ON o.id=d.obra_id
     $where ORDER BY
       CASE WHEN d.fecha_vencimiento IS NULL THEN 1 ELSE 0 END,
       d.fecha_vencimiento ASC
     LIMIT {$pag['limit']} OFFSET {$pag['offset']}",
    $params
);

$tipos = DB::all("SELECT id, nombre FROM tipos_documento ORDER BY nombre", []);
$obras = DB::all("SELECT id, nombre FROM obras WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);

$pageTitle = 'Control Documental';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/dashboard/">Dashboard</a></li>
    <li class="breadcrumb-item active">Documentos</li>
</ol></nav>

<!-- KPIs alertas -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <a href="?" class="text-decoration-none">
        <div class="card kpi-card kpi-blue">
            <div class="card-body p-3">
                <div class="kpi-icon bg-primary-subtle text-primary mb-2"><i class="fas fa-folder-open"></i></div>
                <div class="kpi-value text-primary" data-count="<?= $kpiTotal ?>"><?= $kpiTotal ?></div>
                <div class="kpi-label">Total Documentos</div>
            </div>
        </div></a>
    </div>
    <div class="col-6 col-md-3">
        <a href="?alerta=vencido" class="text-decoration-none">
        <div class="card kpi-card kpi-red">
            <div class="card-body p-3">
                <div class="kpi-icon bg-danger-subtle text-danger mb-2"><i class="fas fa-times-circle"></i></div>
                <div class="kpi-value text-danger" data-count="<?= $kpiVencidos ?>"><?= $kpiVencidos ?></div>
                <div class="kpi-label">Vencidos</div>
            </div>
        </div></a>
    </div>
    <div class="col-6 col-md-3">
        <a href="?alerta=prox15" class="text-decoration-none">
        <div class="card kpi-card kpi-amber">
            <div class="card-body p-3">
                <div class="kpi-icon bg-warning-subtle text-warning mb-2"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="kpi-value text-warning" data-count="<?= $kpiProx15 ?>"><?= $kpiProx15 ?></div>
                <div class="kpi-label">Vencen en 15 días</div>
            </div>
        </div></a>
    </div>
    <div class="col-6 col-md-3">
        <a href="?alerta=prox30" class="text-decoration-none">
        <div class="card kpi-card kpi-cyan">
            <div class="card-body p-3">
                <div class="kpi-icon bg-info-subtle text-info mb-2"><i class="fas fa-clock"></i></div>
                <div class="kpi-value text-info" data-count="<?= $kpiProx30 ?>"><?= $kpiProx30 ?></div>
                <div class="kpi-label">Vencen en 30 días</div>
            </div>
        </div></a>
    </div>
</div>

<div class="card table-card">
    <div class="card-header">
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-auto">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Título o N°…"
                       value="<?= h($buscar) ?>" style="width:160px">
            </div>
            <div class="col-auto">
                <select name="tipo_id" class="form-select form-select-sm" style="min-width:120px">
                    <option value="">Todos los tipos</option>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $tipoId == $t['id'] ? 'selected' : '' ?>><?= h($t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="entidad_tipo" class="form-select form-select-sm">
                    <option value="">Todas entidades</option>
                    <?php foreach (['trabajador'=>'Trabajador','conductor'=>'Conductor','operador'=>'Operador','vehiculo'=>'Vehículo','equipo'=>'Equipo','empresa'=>'Empresa','general'=>'General'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= $entTipo === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="obra_id" class="form-select form-select-sm" style="min-width:120px">
                    <option value="">Todas obras</option>
                    <?php foreach ($obras as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= $obraId == $o['id'] ? 'selected' : '' ?>><?= h($o['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="alerta" value="<?= h($alerta) ?>">
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-filter me-1"></i>Filtrar</button>
                <a href="?" class="btn btn-sm btn-outline-secondary ms-1"><i class="fas fa-times"></i></a>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <?= botonesExportacion('documentos', [
                        'q' => $buscar, 'tipo_id' => $tipoId, 'entidad_tipo' => $entTipo,
                        'obra_id' => $obraId, 'alerta' => $alerta,
                    ]) ?>
                <?php if (Auth::can('documentos', 'crear')): ?>
                <a href="documento" class="btn btn-sm btn-primary fw-semibold">
                    <i class="fas fa-plus me-1"></i>Nuevo Documento
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if (Auth::can('documentos', 'ver')): ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Título</th>
                    <th>Tipo</th>
                    <th>Entidad</th>
                    <th>Obra</th>
                    <th>N° / Emisión</th>
                    <th>Vencimiento</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">
                    <i class="fas fa-folder-open fa-2x d-block mb-2"></i>Sin documentos registrados.
                </td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <?php
                $vencAlert = '';
                if ($r['fecha_vencimiento']) {
                    $diff = (strtotime($r['fecha_vencimiento']) - strtotime($hoy)) / 86400;
                    if ($diff < 0)       $vencAlert = 'table-danger';
                    elseif ($diff <= 15) $vencAlert = 'table-warning';
                }
            ?>
            <tr class="<?= $vencAlert ?>">
                <td>
                    <div class="fw-semibold small"><?= h(mb_substr($r['titulo'], 0, 50)) ?></div>
                    <?php if ($r['ruta_archivo']): ?>
                    <a href="<?= APP_URL ?>/uploads/<?= h($r['ruta_archivo']) ?>" target="_blank" class="small text-primary">
                        <i class="fas fa-paperclip me-1"></i>Ver archivo
                    </a>
                    <?php endif; ?>
                </td>
                <td><small><?= h($r['tipo_nombre'] ?? '—') ?></small></td>
                <td><small><?= h(ucfirst($r['entidad_tipo'])) ?></small></td>
                <td><small><?= h($r['obra_nombre'] ?? '—') ?></small></td>
                <td>
                    <small><?= h($r['numero'] ?? '—') ?></small>
                    <?php if ($r['fecha_emision']): ?><br><small class="text-muted"><?= formatDate($r['fecha_emision']) ?></small><?php endif; ?>
                </td>
                <td>
                    <?php if ($r['fecha_vencimiento']): ?>
                    <?php
                        $diff2 = (strtotime($r['fecha_vencimiento']) - strtotime($hoy)) / 86400;
                        if ($diff2 < 0) echo '<span class="badge bg-danger"><i class="fas fa-times me-1"></i>Vencido</span><br>';
                        elseif ($diff2 <= 15) echo '<span class="badge bg-warning text-dark"><i class="fas fa-exclamation me-1"></i>' . (int)$diff2 . ' días</span><br>';
                        elseif ($diff2 <= 30) echo '<span class="badge bg-info">' . (int)$diff2 . ' días</span><br>';
                    ?>
                    <small><?= formatDate($r['fecha_vencimiento']) ?></small>
                    <?php else: ?>
                    <small class="text-muted">Sin vencimiento</small>
                    <?php endif; ?>
                </td>
                <td><small><?= h($r['estado_nombre'] ?? '—') ?></small></td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-info btn-action btn-view js-view" data-vista="documentos" data-id="<?= $r['id'] ?>" title="Visualizar"><i class="fas fa-eye"></i></button>
                    <?php if (Auth::can('documentos', 'editar')): ?>
                    <a href="documento?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-primary btn-action" title="Editar"><i class="fas fa-pen"></i></a>
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
        <?= paginationHtml($pag, "?q=" . urlencode($buscar) . "&tipo_id={$tipoId}&entidad_tipo={$entTipo}&obra_id={$obraId}&alerta={$alerta}") ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
