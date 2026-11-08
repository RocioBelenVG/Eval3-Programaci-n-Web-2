<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireAdmin();
CSRF::require();

$action = get('action', 'list');
$id     = getInt('id');
$emp    = Auth::empresaId();

if (isPost()) {
    if ($action === 'store' || $action === 'update') {
        $nombre  = post('nombre');
        $email   = strtolower(trim(post('email')));
        $rol_id  = postInt('rol_id');
        $activo  = postInt('activo', 1);
        $pwd     = post('password');
        $errors  = [];

        if (!$nombre) $errors[] = 'Nombre es obligatorio.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email inválido.';
        if (!$rol_id) $errors[] = 'Seleccione un rol.';
        if ($action === 'store' && !passwordEsFuerte($pwd)) $errors[] = 'La contraseña debe tener mínimo 8 caracteres e incluir letras y números.';
        if ($action === 'store' && $pwd !== post('password_confirm')) $errors[] = 'Las contraseñas no coinciden.';

        if ($errors) {
            foreach ($errors as $e) Flash::error($e);
            $action = $action === 'store' ? 'create' : 'edit';
        } elseif ($action === 'store') {
            DB::run(
                "INSERT INTO usuarios (empresa_id, email, nombre, password_hash, rol_id, activo) VALUES (?,?,?,?,?,?)",
                [$emp, $email, $nombre, password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]), $rol_id, $activo]
            );
            Auth::audit('crear', 'usuarios', (int)DB::lastId());
            Flash::success('Usuario creado correctamente.');
            redirect('/modules/admin/usuarios');
        } else {
            $update = "UPDATE usuarios SET nombre=?, email=?, rol_id=?, activo=? WHERE id=? AND empresa_id=?";
            $params = [$nombre, $email, $rol_id, $activo, $id, $emp];
            if ($pwd) {
                if (!passwordEsFuerte($pwd)) { Flash::error('La contraseña debe tener mínimo 8 caracteres e incluir letras y números.'); $action = 'edit'; }
                else {
                    $update = "UPDATE usuarios SET nombre=?, email=?, rol_id=?, activo=?, password_hash=? WHERE id=? AND empresa_id=?";
                    $params = [$nombre, $email, $rol_id, $activo, password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]), $id, $emp];
                }
            }
            if ($action === 'update') {
                DB::run($update, $params);
                Auth::audit('editar', 'usuarios', $id);
                Flash::success('Usuario actualizado.');
                redirect('/modules/admin/usuarios');
            }
        }
    }
}

$row = [];
if ($action === 'edit' && $id) {
    $row = DB::one("SELECT * FROM usuarios WHERE id = ? AND empresa_id = ?", [$id, $emp]) ?: [];
}

$roles  = DB::all("SELECT id, nombre FROM roles WHERE activo = 1 ORDER BY nombre");
$search = get('q');
$page   = getInt('page', 1);
$where  = "WHERE u.empresa_id = ?";
$params = [$emp];
if ($search) { $where .= " AND (u.nombre LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
$total  = DB::count("SELECT COUNT(*) FROM usuarios u $where", $params);
$pag    = paginate($total, $page);
$rows   = DB::all(
    "SELECT u.*, r.nombre AS rol_nombre FROM usuarios u LEFT JOIN roles r ON r.id = u.rol_id $where ORDER BY u.nombre LIMIT {$pag['limit']} OFFSET {$pag['offset']}",
    $params
);

$pageTitle = 'Usuarios';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/admin/">Admin</a></li>
    <li class="breadcrumb-item active">Usuarios</li>
</ol></nav>

<?php if ($action === 'list'): ?>
<div class="card table-card">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <strong class="me-auto">Usuarios del Sistema</strong>
        <form class="d-flex gap-2" method="GET"><input type="hidden" name="action" value="list">
            <input type="text" name="q" class="form-control form-control-sm" style="width:200px" placeholder="Buscar..." value="<?= h($search) ?>">
            <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
        </form>
        <a href="?action=create" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>Nuevo Usuario</a>
    </div>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>Nombre</th><th>Email</th><th>Rol</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>
        <?php if (empty($rows)): ?><tr><td colspan="5" class="text-center text-muted py-4">Sin usuarios.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= h($r['nombre']) ?></td>
            <td><small><?= h($r['email']) ?></small></td>
            <td><span class="badge bg-primary-subtle text-primary"><?= h($r['rol_nombre']) ?></span></td>
            <td><?= badgeActivo((int)$r['activo']) ?></td>
            <td class="text-end">
                <a href="?action=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action">
                    <i class="fas fa-edit"></i>
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php else: ?>
<div class="card form-card" style="max-width:600px">
    <div class="card-header"><strong><?= $action === 'create' ? 'Nuevo Usuario' : 'Editar Usuario' ?></strong></div>
    <div class="card-body">
        <form method="POST" action="?action=<?= $action === 'create' ? 'store' : 'update' ?><?= $action === 'edit' ? '&id=' . $id : '' ?>">
            <?= CSRF::field() ?><input type="hidden" name="action" value="<?= $action === 'create' ? 'store' : 'update' ?>">
            <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
            <div class="row g-3">
                <div class="col-12"><label class="form-label">Nombre Completo <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" class="form-control" value="<?= h($row['nombre'] ?? '') ?>" required maxlength="120"></div>
                <div class="col-md-8"><label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" value="<?= h($row['email'] ?? '') ?>" required maxlength="120"></div>
                <div class="col-md-4"><label class="form-label">Rol <span class="text-danger">*</span></label>
                    <select name="rol_id" class="form-select" required>
                        <option value="">— Rol —</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= ($row['rol_id'] ?? 0) == $r['id'] ? 'selected' : '' ?>><?= h($r['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Contraseña <?= $action === 'create' ? '<span class="text-danger">*</span>' : '<small class="text-muted">(dejar en blanco para no cambiar)</small>' ?></label>
                    <input type="password" name="password" class="form-control" <?= $action === 'create' ? 'required' : '' ?> minlength="8" placeholder="Mínimo 8 caracteres" autocomplete="new-password"></div>
                <?php if ($action === 'create'): ?>
                <div class="col-md-6"><label class="form-label">Confirmar Contraseña <span class="text-danger">*</span></label>
                    <input type="password" name="password_confirm" class="form-control" required placeholder="Repetir contraseña" autocomplete="new-password"></div>
                <?php endif; ?>
                <div class="col-12"><div class="form-check">
                    <input type="checkbox" name="activo" value="1" class="form-check-input" id="activo" <?= ($row['activo'] ?? 1) ? 'checked' : '' ?>>
                    <label for="activo" class="form-check-label">Usuario activo</label>
                </div></div>
            </div>
            <hr class="my-3">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i><?= $action === 'create' ? 'Crear Usuario' : 'Actualizar' ?></button>
                <a href="?action=list" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php include ROOT . '/includes/footer.php'; ?>
