<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requirePermiso('maquinaria', 'editar');
CSRF::require();

$emp   = Auth::empresaId();
$eqId  = getInt('equipo_id');
$eq    = $eqId ? DB::one("SELECT * FROM equipos WHERE id=? AND empresa_id=?", [$eqId, $emp]) : null;
if (!$eq) { Flash::error('Equipo no encontrado.'); redirect('/modules/maquinaria/'); }

if (isPost()) {
    $nuevo = postFloat('horometro');
    if ($nuevo <= 0) { Flash::error('El horómetro debe ser mayor a 0.'); }
    elseif ($nuevo < (float)$eq['horometro']) { Flash::error('El horómetro no puede ser menor al actual (' . formatNum((float)$eq['horometro'], 1) . ' hrs).'); }
    else {
        DB::run("UPDATE equipos SET horometro=? WHERE id=? AND empresa_id=?", [$nuevo, $eqId, $emp]);
        Auth::audit('editar', 'equipos', $eqId);
        Flash::success('Horómetro actualizado a ' . formatNum($nuevo, 1) . ' hrs.');
        redirect('/modules/maquinaria/');
    }
}

$pageTitle = 'Actualizar Horómetro';
include ROOT . '/includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index">Maquinaria</a></li>
    <li class="breadcrumb-item active">Horómetro</li>
</ol></nav>

<div class="card form-card" style="max-width:400px">
    <div class="card-header">
        <strong><i class="fas fa-tachometer-alt text-primary me-2"></i>Actualizar Horómetro</strong>
    </div>
    <div class="card-body">
        <div class="alert alert-info py-2 mb-3">
            <strong><?= h($eq['codigo']) ?></strong> — <?= h($eq['nombre']) ?><br>
            <small>Horómetro actual: <strong><?= formatNum((float)$eq['horometro'], 1) ?> hrs</strong></small>
        </div>
        <form method="POST">
            <?= CSRF::field() ?>
            <div class="mb-3">
                <label class="form-label">Nuevo Horómetro (hrs) <span class="text-danger">*</span></label>
                <input type="number" name="horometro" class="form-control form-control-lg" required
                       min="<?= (float)$eq['horometro'] ?>" step="0.1"
                       placeholder="Ej: <?= (float)$eq['horometro'] + 8 ?>">
                <div class="form-text">Debe ser mayor o igual al horómetro actual.</div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Actualizar</button>
                <a href="index" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php include ROOT . '/includes/footer.php'; ?>
