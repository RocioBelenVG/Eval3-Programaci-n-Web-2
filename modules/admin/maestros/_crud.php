<?php
/**
 * CRUD Genérico para tablas maestras
 *
 * Variables requeridas del archivo invocador:
 *  $tabla          string  — nombre de la tabla en BD
 *  $titulo         string  — título plural (Ej: "Cargos")
 *  $singular       string  — título singular (Ej: "Cargo")
 *  $campos_form    array   — definición de campos del formulario
 *  $campos_lista   array   — columnas a mostrar en la lista [nombre_col, ...]
 *  $etiquetas_lista array  — headers de esas columnas
 *  $empresa_filtro bool    — si la tabla tiene empresa_id
 *  $backUrl        string  — URL de regreso (default: admin/)
 *
 * Estructura de $campos_form:
 *  [
 *   'nombre'    => string,
 *   'etiqueta'  => string,
 *   'tipo'      => 'text'|'textarea'|'number'|'email'|'tel'|'date'|'select'|'checkbox',
 *   'required'  => bool,
 *   'max'       => int (opcional),
 *   'opciones'  => ['val'=>'texto'] (para tipo select estático),
 *   'fuente'    => ['tabla', 'valor', 'texto', 'where'] (para select desde BD),
 *   'placeholder'=> string (opcional),
 *  ]
 */

require_once dirname(__DIR__, 3) . '/bootstrap.php';
Auth::requireAdmin();
CSRF::require();

$empresa_filtro = $empresa_filtro ?? true;
$tiene_activo   = $tiene_activo ?? true;
$ordenar_por    = $ordenar_por ?? 'id';

// URL de auto-referencia para redirects (sin query string)
$selfUrl = strtok($_SERVER['REQUEST_URI'] ?? '', '?');

$action = get('action', 'list');
$id     = getInt('id');
$emp    = Auth::empresaId();

// ---- HELPERS ----

function buildWhereEmp(bool $filtro, int $emp, string $prefix = ''): array {
    if (!$filtro) return ['', []];
    return ["{$prefix}empresa_id = ?", [$emp]];
}

function buildSelectOpciones(array $campo): array {
    if (!empty($campo['fuente'])) {
        $f      = $campo['fuente'];
        $where  = $f['where'] ?? '1=1';
        $params = [];
        if (!empty($f['empresa_filter'])) {
            $where   .= ' AND empresa_id = ?';
            $params[] = Auth::empresaId();
        }
        $rows = DB::all(
            "SELECT {$f['valor']} AS v, {$f['texto']} AS t FROM {$f['tabla']} WHERE {$where} AND activo = 1 ORDER BY {$f['texto']}",
            $params
        );
        $opts = [];
        foreach ($rows as $r) $opts[$r['v']] = $r['t'];
        return $opts;
    }
    return $campo['opciones'] ?? [];
}

// ---- HANDLE STORE / UPDATE ----
if (isPost()) {
    if ($action === 'store' || $action === 'update') {
        $data  = [];
        $errors = [];

        foreach ($campos_form as $c) {
            if ($c['tipo'] === 'checkbox') {
                $data[$c['nombre']] = isset($_POST[$c['nombre']]) ? 1 : 0;
                continue;
            }
            $val = post($c['nombre']);
            if ($c['required'] && $val === '') {
                $errors[] = h($c['etiqueta']) . ' es obligatorio.';
            }
            if (isset($c['max']) && strlen($val) > $c['max']) {
                $errors[] = h($c['etiqueta']) . ' excede el máximo permitido.';
            }
            $data[$c['nombre']] = $val === '' ? null : $val;
        }

        if ($errors) {
            foreach ($errors as $e) Flash::error($e);
            $action = ($action === 'store') ? 'create' : 'edit';
        } else {
            if ($empresa_filtro) $data['empresa_id'] = $emp;

            if ($action === 'store') {
                $cols = implode(', ', array_keys($data));
                $plc  = implode(', ', array_fill(0, count($data), '?'));
                DB::run("INSERT INTO {$tabla} ({$cols}) VALUES ({$plc})", array_values($data));
                $newId = (int)DB::lastId();
                Auth::audit('crear', $tabla, $newId, null, $data);
                Flash::success("{$singular} creado correctamente.");
                redirect("{$selfUrl}?action=list");
            } else {
                $sets = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
                DB::run("UPDATE {$tabla} SET {$sets} WHERE id = ?", [...array_values($data), $id]);
                Auth::audit('editar', $tabla, $id, null, $data);
                Flash::success("{$singular} actualizado correctamente.");
                redirect("{$selfUrl}?action=list");
            }
        }
    }

    if ($action === 'toggle_activo') {
        $row = DB::one("SELECT activo FROM {$tabla} WHERE id = ?", [$id]);
        if ($row) {
            $nuevo = $row['activo'] ? 0 : 1;
            DB::run("UPDATE {$tabla} SET activo = ? WHERE id = ?", [$nuevo, $id]);
            Auth::audit($nuevo ? 'activar' : 'desactivar', $tabla, $id);
            Flash::success("{$singular} " . ($nuevo ? 'activado' : 'desactivado') . '.');
        }
        redirect("{$selfUrl}?action=list");
    }
}

// ---- FETCH ROW FOR EDIT ----
$row = [];
if ($action === 'edit' && $id) {
    $row = DB::one("SELECT * FROM {$tabla} WHERE id = ?", [$id]);
    if (!$row) { Flash::error('Registro no encontrado.'); redirect("{$selfUrl}?action=list"); }
}

// ---- LIST ----
if ($action === 'list') {
    $search  = get('q');
    $page    = getInt('page', 1);
    $whereC  = [];
    $params  = [];

    if ($empresa_filtro) {
        $empCol   = $empresa_col ?? 't.empresa_id';
        $whereC[] = "{$empCol} = ?";
        $params[] = $emp;
    }

    if ($search && $campos_lista) {
        $searchExpr = $buscar_col ?? "t.{$campos_lista[0]}";
        $whereC[]   = "({$searchExpr} LIKE ?)";
        $params[]   = "%{$search}%";
    }

    $where = $whereC ? 'WHERE ' . implode(' AND ', $whereC) : '';
    // Incluir list_joins en COUNT cuando el WHERE referencia columnas de tablas unidas
    $joinForCount = !empty($list_joins) ? $list_joins : '';
    $total = DB::count("SELECT COUNT(*) FROM {$tabla} t {$joinForCount} {$where}", $params);
    $pag   = paginate($total, $page);

    $cols = 't.*';
    $joins = '';
    // Check for join hints in config (optional advanced feature)
    if (!empty($list_joins)) {
        $joins = $list_joins;
        $cols  = $list_select ?? 't.*';
    }

    $rows = DB::all(
        "SELECT {$cols} FROM {$tabla} t {$joins} {$where} ORDER BY t.{$ordenar_por} LIMIT {$pag['limit']} OFFSET {$pag['offset']}",
        $params
    );
}

// ---- RENDER ----
$pageTitle = $titulo;
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/dashboard/">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/admin/">Admin</a></li>
    <li class="breadcrumb-item active"><?= h($titulo) ?></li>
</ol></nav>

<?php if ($action === 'list'): ?>

<!-- ===== LISTA ===== -->
<div class="card table-card">
    <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <strong class="me-auto"><?= h($titulo) ?></strong>
        <form class="d-flex gap-2" method="GET">
            <input type="hidden" name="action" value="list">
            <input type="text" name="q" class="form-control form-control-sm" style="width:200px"
                   placeholder="Buscar..." value="<?= h($search) ?>">
            <button class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-search"></i>
            </button>
        </form>
        <a href="?action=create" class="btn btn-sm btn-primary">
            <i class="fas fa-plus me-1"></i>Nuevo <?= h($singular) ?>
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <?php foreach ($etiquetas_lista as $et): ?>
                        <th><?= h($et) ?></th>
                    <?php endforeach; ?>
                    <?php if ($tiene_activo): ?><th>Estado</th><?php endif; ?>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="99" class="text-center text-muted py-4">
                    <i class="fas fa-inbox me-2"></i>Sin registros.
                </td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="text-muted small"><?= (int)$r['id'] ?></td>
                    <?php foreach ($campos_lista as $col): ?>
                        <td><?= h((string)($r[$col] ?? '')) ?></td>
                    <?php endforeach; ?>
                    <?php if ($tiene_activo): ?>
                        <td><?= badgeActivo((int)$r['activo']) ?></td>
                    <?php endif; ?>
                    <td class="text-end">
                        <a href="?action=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php if ($tiene_activo): ?>
                        <form method="POST" class="d-inline">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="toggle_activo">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn btn-sm <?= $r['activo'] ? 'btn-outline-warning' : 'btn-outline-success' ?> btn-action"
                                    data-confirm="<?= $r['activo'] ? '¿Desactivar este registro?' : '¿Activar este registro?' ?>"
                                    title="<?= $r['activo'] ? 'Desactivar' : 'Activar' ?>">
                                <i class="fas fa-<?= $r['activo'] ? 'ban' : 'check' ?>"></i>
                            </button>
                        </form>
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
        <?= paginationHtml($pag, '?action=list&q=' . urlencode($search)) ?>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($action === 'create' || $action === 'edit'): ?>

<!-- ===== FORMULARIO ===== -->
<div class="card form-card" style="max-width:700px">
    <div class="card-header">
        <strong><?= $action === 'create' ? 'Nuevo ' . h($singular) : 'Editar ' . h($singular) ?></strong>
    </div>
    <div class="card-body">
        <form method="POST" action="?action=<?= $action === 'create' ? 'store' : 'update' ?><?= $action === 'edit' ? '&id=' . $id : '' ?>">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="<?= $action === 'create' ? 'store' : 'update' ?>">
            <?php if ($action === 'edit'): ?>
                <input type="hidden" name="id" value="<?= $id ?>">
            <?php endif; ?>

            <div class="row g-3">
            <?php foreach ($campos_form as $c):
                $val = $row[$c['nombre']] ?? post($c['nombre']);
                $req = $c['required'] ? ' required' : '';
                $ph  = $c['placeholder'] ?? '';
                $col  = $c['col'] ?? '12';
            ?>
            <div class="col-md-<?= $col ?>">
                <label for="<?= h($c['nombre']) ?>" class="form-label">
                    <?= h($c['etiqueta']) ?><?= $c['required'] ? ' <span class="text-danger">*</span>' : '' ?>
                </label>

                <?php if ($c['tipo'] === 'textarea'): ?>
                    <textarea class="form-control" id="<?= h($c['nombre']) ?>" name="<?= h($c['nombre']) ?>"
                              rows="3" <?= $req ?> placeholder="<?= h($ph) ?>"><?= h((string)$val) ?></textarea>

                <?php elseif ($c['tipo'] === 'select'): ?>
                    <?php $opts = buildSelectOpciones($c); ?>
                    <select class="form-select" id="<?= h($c['nombre']) ?>" name="<?= h($c['nombre']) ?>" <?= $req ?>
                            <?= !empty($c['parent']) ? 'data-parent="#' . $c['parent'] . '" data-url="/api/v1/options.php?tabla=' . $c['fuente']['tabla'] . '&parent_col=' . ($c['fuente']['parent_col'] ?? 'parent_id') . '"' : '' ?>>
                        <option value="">— Seleccione —</option>
                        <?php foreach ($opts as $k => $v): ?>
                            <option value="<?= h((string)$k) ?>" <?= (string)$val == (string)$k ? 'selected' : '' ?>>
                                <?= h($v) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($c['tipo'] === 'checkbox'): ?>
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" id="<?= h($c['nombre']) ?>"
                               name="<?= h($c['nombre']) ?>" value="1" <?= $val ? 'checked' : '' ?>>
                        <label class="form-check-label" for="<?= h($c['nombre']) ?>">
                            <?= h($c['etiqueta']) ?>
                        </label>
                    </div>

                <?php else: ?>
                    <input type="<?= h($c['tipo']) ?>" class="form-control"
                           id="<?= h($c['nombre']) ?>" name="<?= h($c['nombre']) ?>"
                           value="<?= h((string)$val) ?>"
                           <?= $req ?> placeholder="<?= h($ph) ?>"
                           <?= isset($c['max']) ? 'maxlength="' . (int)$c['max'] . '"' : '' ?>
                           <?= isset($c['min']) ? 'min="' . (int)$c['min'] . '"' : '' ?>>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            </div>

            <hr class="my-3">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i><?= $action === 'create' ? 'Guardar' : 'Actualizar' ?>
                </button>
                <a href="?action=list" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

<?php include ROOT . '/includes/footer.php'; ?>
