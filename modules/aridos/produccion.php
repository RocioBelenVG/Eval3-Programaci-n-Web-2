<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requirePermiso('aridos', 'crear');
CSRF::require();

$emp  = Auth::empresaId();
$id   = getInt('id');
$edit = $id > 0;
$r    = [];

if ($edit) {
    $r = DB::one("SELECT * FROM produccion_aridos WHERE id=? AND empresa_id=?", [$id, $emp]);
    if (!$r) { Flash::error('Registro no encontrado.'); redirect('/modules/aridos/'); }
}

if (isPost()) {
    $data = [
        'material_id'      => postInt('material_id'),
        'clasificacion_id' => postInt('clasificacion_id') ?: null,
        'obra_id'          => postInt('obra_id')          ?: null,
        'fecha'            => post('fecha', date('Y-m-d')),
        'cantidad'         => postFloat('cantidad'),
        'unidad'           => post('unidad', 'ton'),
        'equipo_id'        => postInt('equipo_id')        ?: null,
        'operador_id'      => postInt('operador_id')      ?: null,
        'turno'            => post('turno', 'mañana'),
        'observaciones'    => post('observaciones')       ?: null,
    ];

    $errors = [];
    if (!$data['material_id']) $errors[] = 'Seleccione un material.';
    if ($data['cantidad'] <= 0) $errors[] = 'La cantidad debe ser mayor a 0.';
    if (!$data['fecha'])        $errors[] = 'La fecha es obligatoria.';

    if ($errors) {
        foreach ($errors as $e) Flash::error($e);
    } else {
        $pdo = DB::conn();
        $pdo->beginTransaction();
        try {
            if ($edit) {
                $oldCant = (float)$r['cantidad'];
                $oldMat  = (int)$r['material_id'];
                $oldClas = (int)($r['clasificacion_id'] ?? 0);
                $oldObra = (int)($r['obra_id'] ?? 0);

                $sets = implode(', ', array_map(fn($k) => "{$k}=?", array_keys($data)));
                DB::run("UPDATE produccion_aridos SET {$sets} WHERE id=? AND empresa_id=?", [...array_values($data), $id, $emp]);

                // Revertir stock anterior y sumar nuevo
                updateStock($emp, $oldObra ?: null, $oldMat, $oldClas ?: null, $r['unidad'], -$oldCant);
                updateStock($emp, $data['obra_id'], $data['material_id'], $data['clasificacion_id'], $data['unidad'], $data['cantidad']);
                Auth::audit('editar', 'aridos', $id);
                Flash::success('Producción actualizada.');
            } else {
                $data['empresa_id'] = $emp;
                $data['creado_por'] = Auth::id();
                $cols = implode(', ', array_keys($data));
                $plc  = implode(', ', array_fill(0, count($data), '?'));
                DB::run("INSERT INTO produccion_aridos ({$cols}) VALUES ({$plc})", array_values($data));
                $newId = (int)DB::lastId();
                updateStock($emp, $data['obra_id'], $data['material_id'], $data['clasificacion_id'], $data['unidad'], $data['cantidad']);
                Auth::audit('crear', 'aridos', $newId);
                Flash::success('Producción registrada.');
            }
            $pdo->commit();
        } catch (Exception $ex) {
            $pdo->rollBack();
            Flash::error('Error al guardar.');
        }
        redirect('/modules/aridos/');
    }
}

function updateStock(int $emp, ?int $obraId, int $matId, ?int $clasId, string $unidad, float $delta): void {
    $existing = DB::one(
        "SELECT id, cantidad FROM stock_aridos WHERE empresa_id=? AND material_id=? AND " .
        ($obraId   ? "obra_id=?"          : "obra_id IS NULL") . " AND " .
        ($clasId   ? "clasificacion_id=?" : "clasificacion_id IS NULL"),
        array_filter([$emp, $matId, $obraId, $clasId], fn($v) => $v !== null)
    );
    if ($existing) {
        $nueva = max(0, (float)$existing['cantidad'] + $delta);
        DB::run("UPDATE stock_aridos SET cantidad=? WHERE id=? AND empresa_id=?", [$nueva, $existing['id'], $emp]);
    } elseif ($delta > 0) {
        DB::run(
            "INSERT INTO stock_aridos (empresa_id, obra_id, material_id, clasificacion_id, cantidad, unidad) VALUES (?,?,?,?,?,?)",
            [$emp, $obraId, $matId, $clasId, $delta, $unidad]
        );
    }
}

$materiales      = DB::all("SELECT id, nombre FROM materiales WHERE activo=1 ORDER BY nombre", []);
$clasificaciones = DB::all("SELECT id, nombre FROM clasificaciones_arido WHERE activo=1 ORDER BY nombre", []);
$obras           = DB::all("SELECT id, nombre FROM obras WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);
$equipos         = DB::all("SELECT id, codigo, nombre FROM equipos WHERE empresa_id=? AND activo=1 ORDER BY codigo", [$emp]);
$operadores      = DB::all("SELECT o.id, CONCAT(t.nombre,' ',t.apellido) AS nombre FROM operadores o INNER JOIN trabajadores t ON t.id=o.trabajador_id WHERE t.empresa_id=? AND o.activo=1 ORDER BY t.apellido", [$emp]);

$pageTitle = $edit ? 'Editar Producción' : 'Registrar Producción';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index">Áridos</a></li>
    <li class="breadcrumb-item active"><?= h($pageTitle) ?></li>
</ol></nav>

<div class="card form-card" style="max-width:650px">
    <div class="card-header">
        <strong><i class="fas fa-industry text-warning me-2"></i><?= h($pageTitle) ?></strong>
    </div>
    <div class="card-body">
        <form method="POST" data-offline="1">
            <?= CSRF::field() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Fecha <span class="text-danger">*</span></label>
                    <input type="date" name="fecha" class="form-control" required value="<?= h($r['fecha'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Turno</label>
                    <select name="turno" class="form-select">
                        <?php foreach (['mañana'=>'Mañana','tarde'=>'Tarde','noche'=>'Noche'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($r['turno'] ?? 'mañana') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Obra</label>
                    <select name="obra_id" class="form-select">
                        <option value="">— General —</option>
                        <?php foreach ($obras as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= ($r['obra_id'] ?? '') == $o['id'] ? 'selected' : '' ?>><?= h($o['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Material <span class="text-danger">*</span></label>
                    <select name="material_id" class="form-select" required>
                        <?php if (empty($materiales)): ?>
                            <option value="" disabled selected>Sin materiales — Agregue en Admin → Áridos</option>
                        <?php else: ?>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($materiales as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= ($r['material_id'] ?? '') == $m['id'] ? 'selected' : '' ?>><?= h($m['nombre']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Clasificación</label>
                    <select name="clasificacion_id" class="form-select">
                        <option value="">— Sin clasificación —</option>
                        <?php foreach ($clasificaciones as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($r['clasificacion_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= h($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-5">
                    <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                    <input type="number" name="cantidad" class="form-control" required min="0.01" step="0.01"
                           value="<?= h($r['cantidad'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Unidad</label>
                    <select name="unidad" class="form-select">
                        <?php foreach (['ton'=>'Toneladas','m3'=>'m³','unidad'=>'Unidades'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($r['unidad'] ?? 'ton') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Equipo</label>
                    <select name="equipo_id" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($equipos as $e): ?>
                            <option value="<?= $e['id'] ?>" <?= ($r['equipo_id'] ?? '') == $e['id'] ? 'selected' : '' ?>><?= h($e['codigo'] . ' ' . $e['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Operador</label>
                    <select name="operador_id" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($operadores as $op): ?>
                            <option value="<?= $op['id'] ?>" <?= ($r['operador_id'] ?? '') == $op['id'] ? 'selected' : '' ?>><?= h($op['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Observaciones</label>
                    <input type="text" name="observaciones" class="form-control" maxlength="255" value="<?= h($r['observaciones'] ?? '') ?>">
                </div>
            </div>

            <hr class="my-3">
            <div class="alert alert-info py-2 small">
                <i class="fas fa-info-circle me-1"></i>Al guardar, el stock del material se actualizará automáticamente.
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-warning text-dark fw-semibold">
                    <i class="fas fa-save me-2"></i><?= $edit ? 'Actualizar' : 'Registrar Producción' ?>
                </button>
                <a href="index" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
