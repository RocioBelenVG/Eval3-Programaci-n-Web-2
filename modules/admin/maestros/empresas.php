<?php
require_once dirname(__DIR__, 3) . '/bootstrap.php';
Auth::requireAdmin();
CSRF::require();

$action = get('action', 'list');
$id     = getInt('id');
$emp    = Auth::empresaId();

if (isPost()) {
    $campos = ['razon_social', 'rut', 'giro', 'direccion', 'ciudad', 'telefono', 'email'];
    $data   = [];
    $errors = [];

    $data['razon_social'] = post('razon_social');
    $data['rut']          = post('rut');
    if (!$data['razon_social']) $errors[] = 'Razón social es obligatoria.';
    if (!$data['rut'])          $errors[] = 'RUT es obligatorio.';
    foreach (['giro', 'direccion', 'ciudad', 'telefono', 'email'] as $f) {
        $data[$f] = post($f) ?: null;
    }

    if ($errors) {
        foreach ($errors as $e) Flash::error($e);
        $action = $action === 'store' ? 'create' : 'edit';
    } elseif ($action === 'store') {
        DB::run("INSERT INTO empresas (razon_social,rut,giro,direccion,ciudad,telefono,email) VALUES (?,?,?,?,?,?,?)",
            array_values($data));
        Auth::audit('crear', 'empresas', (int)DB::lastId());
        Flash::success('Empresa creada correctamente.');
        redirect('/modules/admin/maestros/empresas?action=list');
    } elseif ($action === 'update') {
        DB::run("UPDATE empresas SET razon_social=?,rut=?,giro=?,direccion=?,ciudad=?,telefono=?,email=? WHERE id=?",
            [...array_values($data), $id]);
        Auth::audit('editar', 'empresas', $id);
        Flash::success('Empresa actualizada.');
        redirect('/modules/admin/maestros/empresas?action=list');
    } elseif ($action === 'toggle_activo') {
        $row = DB::one("SELECT activo FROM empresas WHERE id = ?", [$id]);
        if ($row) {
            DB::run("UPDATE empresas SET activo = ? WHERE id = ?", [$row['activo'] ? 0 : 1, $id]);
            Flash::success('Estado actualizado.');
        }
        redirect('/modules/admin/maestros/empresas?action=list');
    }
}

$row = [];
if ($action === 'edit' && $id) {
    $row = DB::one("SELECT * FROM empresas WHERE id = ?", [$id]) ?: [];
}

$page   = getInt('page', 1);
$search = get('q');
$where  = $search ? "WHERE razon_social LIKE ? OR rut LIKE ?" : '';
$params = $search ? ["%$search%", "%$search%"] : [];
$total  = DB::count("SELECT COUNT(*) FROM empresas $where", $params);
$pag    = paginate($total, $page);
$rows   = DB::all("SELECT * FROM empresas $where ORDER BY razon_social LIMIT {$pag['limit']} OFFSET {$pag['offset']}", $params);

$pageTitle = 'Empresas';
include ROOT . '/includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/admin/">Admin</a></li>
    <li class="breadcrumb-item active">Empresas</li>
</ol></nav>

<?php if ($action === 'list'): ?>
<div class="card table-card">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <strong class="me-auto">Empresas</strong>
        <form class="d-flex gap-2" method="GET"><input type="hidden" name="action" value="list">
            <input type="text" name="q" class="form-control form-control-sm" style="width:200px" placeholder="Buscar..." value="<?= h($search) ?>">
            <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-search"></i></button>
        </form>
        <a href="?action=create" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>Nueva Empresa</a>
    </div>
    <div class="table-responsive"><table class="table table-hover mb-0">
        <thead><tr><th>#</th><th>Razón Social</th><th>RUT</th><th>Ciudad</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
        <tbody>
        <?php if (empty($rows)): ?><tr><td colspan="6" class="text-center text-muted py-4"><i class="fas fa-inbox me-2"></i>Sin registros.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
        <tr>
            <td class="text-muted small"><?= $r['id'] ?></td>
            <td><strong><?= h($r['razon_social']) ?></strong></td>
            <td><?= h($r['rut']) ?></td>
            <td><?= h($r['ciudad'] ?? '—') ?></td>
            <td><?= badgeActivo((int)$r['activo']) ?></td>
            <td class="text-end">
                <a href="?action=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action"><i class="fas fa-edit"></i></a>
                <form method="POST" class="d-inline"><?= CSRF::field() ?>
                    <input type="hidden" name="action" value="toggle_activo">
                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                    <button class="btn btn-sm <?= $r['activo'] ? 'btn-outline-warning' : 'btn-outline-success' ?> btn-action"
                            data-confirm="<?= $r['activo'] ? '¿Desactivar?' : '¿Activar?' ?>">
                        <i class="fas fa-<?= $r['activo'] ? 'ban' : 'check' ?>"></i>
                    </button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php if ($pag['pages'] > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-muted"><?= $pag['total'] ?> registros</small>
        <?= paginationHtml($pag, '?action=list&q=' . urlencode($search)) ?>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="card form-card" style="max-width:700px">
    <div class="card-header"><strong><?= $action === 'create' ? 'Nueva Empresa' : 'Editar Empresa' ?></strong></div>
    <div class="card-body">
        <form method="POST" action="?action=<?= $action === 'create' ? 'store' : 'update' ?><?= $action === 'edit' ? '&id=' . $id : '' ?>">
            <?= CSRF::field() ?><input type="hidden" name="action" value="<?= $action === 'create' ? 'store' : 'update' ?>">
            <?php if ($action === 'edit'): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">Razón Social <span class="text-danger">*</span></label>
                    <input type="text" name="razon_social" class="form-control" value="<?= h($row['razon_social'] ?? '') ?>" required maxlength="150"></div>
                <div class="col-md-4"><label class="form-label">RUT <span class="text-danger">*</span></label>
                    <input type="text" name="rut" class="form-control" value="<?= h($row['rut'] ?? '') ?>" required maxlength="20" placeholder="76.000.000-0"></div>
                <div class="col-md-12"><label class="form-label">Giro</label>
                    <input type="text" name="giro" class="form-control" value="<?= h($row['giro'] ?? '') ?>" maxlength="150"></div>
                <div class="col-md-8"><label class="form-label">Dirección</label>
                    <input type="text" name="direccion" class="form-control" value="<?= h($row['direccion'] ?? '') ?>" maxlength="200"></div>
                <div class="col-md-4"><label class="form-label">Ciudad</label>
                    <input type="text" name="ciudad" class="form-control" value="<?= h($row['ciudad'] ?? '') ?>" maxlength="100"></div>
                <div class="col-md-6"><label class="form-label">Teléfono</label>
                    <input type="tel" name="telefono" class="form-control" value="<?= h($row['telefono'] ?? '') ?>" maxlength="30"></div>
                <div class="col-md-6"><label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= h($row['email'] ?? '') ?>" maxlength="120"></div>
            </div>
            <hr class="my-3">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i><?= $action === 'create' ? 'Guardar' : 'Actualizar' ?></button>
                <a href="?action=list" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php include ROOT . '/includes/footer.php'; ?>
