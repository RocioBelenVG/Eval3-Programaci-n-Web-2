<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireAnyPermiso('combustible');

$emp  = Auth::empresaId();
$hoy  = date('Y-m-d');
$mes0 = date('Y-m-01');

// Filtros
$fDesde  = get('desde', $mes0);
$fHasta  = get('hasta', $hoy);
$obraId  = getInt('obra_id');
$tipoU   = get('tipo_unidad');
$page    = getInt('page', 1);

// KPIs
$kpiHoyL  = DB::one("SELECT COALESCE(SUM(litros),0) AS t FROM cargas_combustible WHERE empresa_id=? AND fecha=?", [$emp, $hoy])['t'];
$kpiHoyN  = DB::count("SELECT COUNT(*) FROM cargas_combustible WHERE empresa_id=? AND fecha=?", [$emp, $hoy]);
$kpiMesL  = DB::one("SELECT COALESCE(SUM(litros),0) AS t FROM cargas_combustible WHERE empresa_id=? AND fecha BETWEEN ? AND ?", [$emp, $mes0, $hoy])['t'];
$kpiMesN  = DB::count("SELECT COUNT(*) FROM cargas_combustible WHERE empresa_id=? AND fecha BETWEEN ? AND ?", [$emp, $mes0, $hoy]);

// Costo mes
$kpiCosto = DB::one("SELECT COALESCE(SUM(litros*precio_litro),0) AS t FROM cargas_combustible WHERE empresa_id=? AND fecha BETWEEN ? AND ? AND precio_litro IS NOT NULL", [$emp, $mes0, $hoy])['t'];

// Build WHERE
$where  = "WHERE c.empresa_id = ?";
$params = [$emp];
if ($fDesde) { $where .= " AND c.fecha >= ?"; $params[] = $fDesde; }
if ($fHasta) { $where .= " AND c.fecha <= ?"; $params[] = $fHasta; }
if ($obraId) { $where .= " AND c.obra_id = ?"; $params[] = $obraId; }
if ($tipoU)  { $where .= " AND c.tipo_unidad = ?"; $params[] = $tipoU; }

$total = DB::count("SELECT COUNT(*) FROM cargas_combustible c $where", $params);
$pag   = paginate($total, $page);

$rows = DB::all(
    "SELECT c.*,
            o.nombre AS obra_nombre,
            v.patente,
            eq.codigo AS equipo_codigo, eq.nombre AS equipo_nombre,
            CONCAT(tv.nombre,' ',tv.apellido) AS conductor_nombre,
            CONCAT(to2.nombre,' ',to2.apellido) AS operador_nombre,
            f.nombre AS fuente_nombre,
            u.nombre AS creado_nombre
     FROM cargas_combustible c
     LEFT JOIN obras o       ON o.id  = c.obra_id
     LEFT JOIN vehiculos v   ON v.id  = c.vehiculo_id
     LEFT JOIN equipos eq    ON eq.id = c.equipo_id
     LEFT JOIN conductores cn ON cn.id = c.conductor_id
     LEFT JOIN trabajadores tv ON tv.id = cn.trabajador_id
     LEFT JOIN operadores op   ON op.id = c.operador_id
     LEFT JOIN trabajadores to2 ON to2.id = op.trabajador_id
     LEFT JOIN fuentes_combustible f ON f.id = c.fuente_id
     LEFT JOIN usuarios u    ON u.id  = c.creado_por
     $where
     ORDER BY c.fecha DESC, c.id DESC
     LIMIT {$pag['limit']} OFFSET {$pag['offset']}",
    $params
);

$obras = DB::all("SELECT id, nombre FROM obras WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);

$pageTitle = 'Gestión de Combustible';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/dashboard/">Dashboard</a></li>
    <li class="breadcrumb-item active">Combustible</li>
</ol></nav>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-amber">
            <div class="card-body p-3">
                <div class="kpi-icon bg-warning-subtle text-warning mb-2"><i class="fas fa-gas-pump"></i></div>
                <div class="kpi-value text-warning"><?= formatNum((float)$kpiHoyL, 0) ?> L</div>
                <div class="kpi-label">Litros Hoy</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-blue">
            <div class="card-body p-3">
                <div class="kpi-icon bg-primary-subtle text-primary mb-2"><i class="fas fa-hashtag"></i></div>
                <div class="kpi-value text-primary" data-count="<?= $kpiHoyN ?>"><?= $kpiHoyN ?></div>
                <div class="kpi-label">Cargas Hoy</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-cyan">
            <div class="card-body p-3">
                <div class="kpi-icon bg-info-subtle text-info mb-2"><i class="fas fa-calendar-alt"></i></div>
                <div class="kpi-value text-info"><?= formatNum((float)$kpiMesL, 0) ?> L</div>
                <div class="kpi-label">Litros Este Mes</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card kpi-card kpi-green">
            <div class="card-body p-3">
                <div class="kpi-icon bg-success-subtle text-success mb-2"><i class="fas fa-dollar-sign"></i></div>
                <div class="kpi-value text-success" style="font-size:1.3rem"><?= formatMoney((float)$kpiCosto) ?></div>
                <div class="kpi-label">Costo Combustible Mes</div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros + tabla -->
<div class="card table-card">
    <div class="card-header">
        <!-- Filtros -->
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
                <label class="form-label form-label-sm mb-1">Tipo</label>
                <select name="tipo_unidad" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="vehiculo" <?= $tipoU === 'vehiculo' ? 'selected' : '' ?>>Vehículo</option>
                    <option value="equipo"   <?= $tipoU === 'equipo'   ? 'selected' : '' ?>>Equipo</option>
                </select>
            </div>
            <div class="col-auto">
                <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-filter me-1"></i>Filtrar</button>
                <a href="?" class="btn btn-sm btn-outline-secondary ms-1" title="Limpiar"><i class="fas fa-times"></i></a>
            </div>
            <div class="col-auto ms-auto d-flex gap-2">
                <a href="rendimiento?desde=<?= h($fDesde) ?>&hasta=<?= h($fHasta) ?>&obra_id=<?= $obraId ?>"
                   class="btn btn-sm btn-outline-primary" title="Consumo por hora y por kilómetro de cada activo">
                    <i class="fas fa-gauge-high me-1"></i>Rendimiento
                </a>
                <?= botonesExportacion('combustible', [
                        'desde' => $fDesde, 'hasta' => $fHasta,
                        'obra_id' => $obraId, 'tipo_unidad' => $tipoU,
                    ]) ?>
                <?php if (Auth::can('combustible', 'crear')): ?>
                <a href="carga" class="btn btn-sm btn-warning fw-semibold">
                    <i class="fas fa-plus me-1"></i>Nueva Carga
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Unidad</th>
                    <th>Tipo</th>
                    <th>Obra</th>
                    <th class="text-end">Litros</th>
                    <th>Hor/Odom</th>
                    <th>Fuente</th>
                    <th>Registró</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="9" class="text-center text-muted py-4">
                    <i class="fas fa-gas-pump fa-2x d-block mb-2 text-muted"></i>Sin registros en el período seleccionado.
                </td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?= formatDate($r['fecha']) ?></strong></td>
                <td>
                    <?php if ($r['tipo_unidad'] === 'vehiculo'): ?>
                        <i class="fas fa-truck text-primary me-1"></i><?= h($r['patente'] ?? '—') ?>
                    <?php else: ?>
                        <i class="fas fa-cogs text-warning me-1"></i><?= h($r['equipo_codigo'] ?? '—') ?>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge bg-<?= $r['tipo_unidad'] === 'vehiculo' ? 'primary' : 'warning text-dark' ?>">
                        <?= $r['tipo_unidad'] === 'vehiculo' ? 'Vehículo' : 'Equipo' ?>
                    </span>
                </td>
                <td><small><?= h($r['obra_nombre'] ?? '—') ?></small></td>
                <td class="text-end"><strong><?= formatNum((float)$r['litros'], 1) ?> L</strong></td>
                <td class="text-muted small">
                    <?php if ($r['tipo_unidad'] === 'equipo' && $r['horometro']): ?>
                        <?= formatNum((float)$r['horometro'], 1) ?> hrs
                    <?php elseif ($r['odometro']): ?>
                        <?= formatNum((float)$r['odometro'], 0) ?> km
                    <?php else: ?>—<?php endif; ?>
                </td>
                <td><small><?= h($r['fuente_nombre'] ?? '—') ?></small></td>
                <td><small class="text-muted"><?= h($r['creado_nombre'] ?? '—') ?></small></td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-info btn-action btn-view js-view" data-vista="combustible" data-id="<?= $r['id'] ?>" title="Visualizar">
                        <i class="fas fa-eye"></i>
                    </button>
                    <a href="carga?id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action" title="Editar">
                        <i class="fas fa-pen"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <?php if (!empty($rows)): ?>
            <tfoot class="table-light">
                <tr>
                    <td colspan="4" class="text-end fw-semibold">Total período:</td>
                    <td class="text-end fw-bold"><?= formatNum(array_sum(array_column($rows, 'litros')), 1) ?> L</td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>
    </div>

    <?php if ($pag['pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><?= $pag['total'] ?> registros</small>
        <?= paginationHtml($pag, "?desde={$fDesde}&hasta={$fHasta}&obra_id={$obraId}&tipo_unidad={$tipoU}") ?>
    </div>
    <?php endif; ?>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
