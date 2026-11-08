<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireAnyPermiso('maquinaria');

$emp = Auth::empresaId();
$hoy = date('Y-m-d');

// KPIs flota
$totalEquipos = DB::count("SELECT COUNT(*) FROM equipos WHERE empresa_id=? AND activo=1", [$emp]);
$enMantencion = DB::count(
    "SELECT COUNT(*) FROM mantenciones m INNER JOIN equipos e ON e.id=m.equipo_id
     WHERE e.empresa_id=? AND m.estado IN ('pendiente','en_proceso')", [$emp]);
$disponibles  = $totalEquipos - $enMantencion;

$mantsActivas = DB::all(
    "SELECT m.*, e.codigo, e.nombre AS equipo_nombre
     FROM mantenciones m INNER JOIN equipos e ON e.id=m.equipo_id
     WHERE e.empresa_id=? AND m.estado IN ('pendiente','en_proceso')
     ORDER BY m.fecha DESC LIMIT 20", [$emp]);

$flota = DB::all(
    "SELECT eq.id, eq.codigo, eq.nombre, eq.horometro,
            m.nombre AS marca_nombre, c.nombre AS categoria_nombre,
            (SELECT estado FROM mantenciones WHERE equipo_id=eq.id AND estado IN ('pendiente','en_proceso') LIMIT 1) AS mant_activa
     FROM equipos eq
     LEFT JOIN marcas_equipo m ON m.id=eq.marca_id
     LEFT JOIN categorias_equipo c ON c.id=eq.categoria_id
     WHERE eq.empresa_id=? AND eq.activo=1
     ORDER BY eq.codigo", [$emp]);

$pageTitle = 'Gestión de Maquinaria';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/dashboard/">Dashboard</a></li>
    <li class="breadcrumb-item active">Maquinaria</li>
</ol></nav>

<!-- KPIs -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card kpi-card kpi-blue">
            <div class="card-body p-3">
                <div class="kpi-icon bg-primary-subtle text-primary mb-2"><i class="fas fa-cogs"></i></div>
                <div class="kpi-value text-primary" data-count="<?= $totalEquipos ?>"><?= $totalEquipos ?></div>
                <div class="kpi-label">Total Equipos</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card kpi-card kpi-green">
            <div class="card-body p-3">
                <div class="kpi-icon bg-success-subtle text-success mb-2"><i class="fas fa-check-circle"></i></div>
                <div class="kpi-value text-success" data-count="<?= $disponibles ?>"><?= $disponibles ?></div>
                <div class="kpi-label">Disponibles</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card kpi-card kpi-amber">
            <div class="card-body p-3">
                <div class="kpi-icon bg-warning-subtle text-warning mb-2"><i class="fas fa-wrench"></i></div>
                <div class="kpi-value text-warning" data-count="<?= $enMantencion ?>"><?= $enMantencion ?></div>
                <div class="kpi-label">En Mantención</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Flota -->
    <div class="col-lg-7">
        <div class="card table-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <strong><i class="fas fa-list text-primary me-2"></i>Flota de Equipos</strong>
                <div class="d-flex gap-2">
                    <?= botonesExportacion('maquinaria_flota') ?>
                    <?php if (Auth::can('maquinaria', 'crear')): ?>
                    <a href="mantencion" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i>Nueva Mantención
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Código</th><th>Equipo</th><th>Horómetro</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    <?php if (empty($flota)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-3">
                            Sin equipos registrados. <a href="<?= APP_URL ?>/modules/admin/maestros/equipos">Agregar equipos</a>
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($flota as $e): ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?= h($e['codigo']) ?></span></td>
                        <td>
                            <div class="fw-semibold small"><?= h($e['nombre']) ?></div>
                            <div class="text-muted" style="font-size:.75rem"><?= h($e['marca_nombre'] ?? '') ?> · <?= h($e['categoria_nombre'] ?? '') ?></div>
                        </td>
                        <td><small><?= formatNum((float)$e['horometro'], 1) ?> hrs</small></td>
                        <td>
                            <?php if ($e['mant_activa']): ?>
                                <span class="badge bg-warning text-dark">En Mantención</span>
                            <?php else: ?>
                                <span class="badge bg-success">Disponible</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <a href="ficha?equipo_id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-dark btn-action" title="Ficha completa del activo">
                                <i class="fas fa-id-card"></i>
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-info btn-action btn-view js-view" data-vista="maquinaria_flota" data-id="<?= $e['id'] ?>" title="Visualizar">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if (Auth::can('maquinaria', 'crear')): ?>
                            <a href="mantencion?equipo_id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-warning btn-action" title="Nueva Mantención">
                                <i class="fas fa-wrench"></i>
                            </a>
                            <?php endif; ?>
                            <?php if (Auth::can('maquinaria', 'editar')): ?>
                            <a href="horometro?equipo_id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary btn-action" title="Actualizar horómetro">
                                <i class="fas fa-tachometer-alt"></i>
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Mantenciones activas -->
    <div class="col-lg-5">
        <div class="card table-card">
            <div class="card-header">
                <strong><i class="fas fa-tools text-warning me-2"></i>Mantenciones Activas</strong>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Equipo</th><th>Tipo</th><th>Fecha</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php if (empty($mantsActivas)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">
                            <i class="fas fa-check-circle text-success me-1"></i>Sin mantenciones activas.
                        </td></tr>
                    <?php endif; ?>
                    <?php foreach ($mantsActivas as $m): ?>
                    <tr>
                        <td><small><span class="badge bg-secondary me-1"><?= h($m['codigo']) ?></span><?= h(mb_substr($m['equipo_nombre'], 0, 18)) ?></small></td>
                        <td><span class="badge bg-<?= $m['tipo'] === 'preventiva' ? 'info' : 'danger' ?> text-<?= $m['tipo'] === 'preventiva' ? 'white' : 'white' ?>"><?= h($m['tipo']) ?></span></td>
                        <td><small><?= formatDate($m['fecha']) ?></small></td>
                        <td>
                            <?php $sc = ['pendiente' => 'warning text-dark', 'en_proceso' => 'primary', 'completada' => 'success']; ?>
                            <span class="badge bg-<?= $sc[$m['estado']] ?? 'secondary' ?>"><?= h($m['estado']) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
