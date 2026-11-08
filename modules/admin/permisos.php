<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireAdmin();
CSRF::require();

$modulos = [
    'dashboard'   => 'Dashboard',
    'faena'       => 'Partes de Faena',
    'combustible' => 'Combustible',
    'maquinaria'  => 'Maquinaria',
    'transporte'  => 'Transporte',
    'aridos'      => 'Áridos',
    'contratos'   => 'Contratos',
    'documentos'  => 'Documentos',
    'seguridad'   => 'Seguridad',
    'admin'       => 'Administración',
];
$acciones = ['ver', 'crear', 'editar', 'eliminar'];

$roles = DB::all("SELECT id, nombre FROM roles ORDER BY id");

if (isPost() && get('action') === 'save') {
    $rol_id = postInt('rol_id');
    if (!$rol_id) { Flash::error('Seleccione un rol.'); redirect('/modules/admin/permisos'); }

    // Eliminar permisos actuales del rol
    DB::run("DELETE FROM permisos WHERE rol_id = ?", [$rol_id]);

    // Insertar nuevos permisos
    foreach ($modulos as $mod => $label) {
        foreach ($acciones as $acc) {
            $val = isset($_POST["perm_{$mod}_{$acc}"]) ? 1 : 0;
            DB::run("INSERT INTO permisos (rol_id, modulo, accion, permitido) VALUES (?,?,?,?)",
                [$rol_id, $mod, $acc, $val]);
        }
    }
    Auth::audit('editar', 'permisos', $rol_id);
    Flash::success('Permisos actualizados para el rol.');
    redirect('/modules/admin/permisos?rol_id=' . $rol_id);
}

$rolActivo = getInt('rol_id');
$permsActuales = [];
if ($rolActivo) {
    $rows = DB::all("SELECT modulo, accion, permitido FROM permisos WHERE rol_id = ?", [$rolActivo]);
    foreach ($rows as $r) {
        $permsActuales[$r['modulo'] . ':' . $r['accion']] = (bool)$r['permitido'];
    }
}

$pageTitle = 'Gestión de Permisos';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/admin/">Admin</a></li>
    <li class="breadcrumb-item active">Permisos</li>
</ol></nav>

<div class="card table-card">
    <div class="card-header">
        <strong><i class="fas fa-lock text-primary me-2"></i>Matriz de Permisos</strong>
    </div>
    <div class="card-body">
        <!-- Selector de rol -->
        <div class="row mb-4">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Seleccionar Rol</label>
                <div class="d-flex gap-2">
                    <select id="rolSelector" class="form-select" onchange="window.location='?rol_id='+this.value">
                        <option value="">— Seleccione un rol —</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $rolActivo == $r['id'] ? 'selected' : '' ?>>
                                <?= h($r['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <?php if ($rolActivo): ?>
        <?php $rolNombre = DB::one("SELECT nombre FROM roles WHERE id = ?", [$rolActivo])['nombre'] ?? ''; ?>

        <?php if ($rolNombre === 'Administrador General'): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                El Administrador General tiene acceso total al sistema. No requiere configuración de permisos.
            </div>
        <?php else: ?>
        <form method="POST" action="?action=save">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="rol_id" value="<?= $rolActivo ?>">

            <div class="alert alert-secondary py-2 mb-3">
                <i class="fas fa-user-tag me-2"></i>Configurando permisos para: <strong><?= h($rolNombre) ?></strong>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-sm">
                    <thead class="table-dark">
                        <tr>
                            <th style="min-width:160px">Módulo</th>
                            <?php foreach ($acciones as $a): ?>
                                <th class="text-center text-capitalize"><?= h($a) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($modulos as $mod => $label): ?>
                    <tr>
                        <td class="fw-semibold"><?= h($label) ?></td>
                        <?php foreach ($acciones as $acc): ?>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input"
                                   name="perm_<?= $mod ?>_<?= $acc ?>"
                                   value="1"
                                   <?= ($permsActuales["{$mod}:{$acc}"] ?? false) ? 'checked' : '' ?>>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Guardar Permisos
                </button>
                <button type="button" class="btn btn-outline-secondary"
                        onclick="document.querySelectorAll('input[type=checkbox]').forEach(c=>c.checked=true)">
                    Marcar todo
                </button>
                <button type="button" class="btn btn-outline-secondary"
                        onclick="document.querySelectorAll('input[type=checkbox]').forEach(c=>c.checked=false)">
                    Desmarcar todo
                </button>
            </div>
        </form>
        <?php endif; ?>
        <?php else: ?>
        <div class="text-center text-muted py-5">
            <i class="fas fa-lock fa-3x mb-3 d-block text-muted"></i>
            Seleccione un rol para ver y editar sus permisos.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
