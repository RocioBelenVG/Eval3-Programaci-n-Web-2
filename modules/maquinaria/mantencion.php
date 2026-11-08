<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requirePermiso('maquinaria', 'crear');
CSRF::require();

$emp      = Auth::empresaId();
$id       = getInt('id');
$eqId     = getInt('equipo_id');
$edit     = $id > 0;
$equipos  = DB::all("SELECT id, codigo, nombre, horometro FROM equipos WHERE empresa_id=? AND activo=1 ORDER BY codigo", [$emp]);

$r = [];
if ($edit) {
    $r = DB::one("SELECT m.*, e.empresa_id FROM mantenciones m INNER JOIN equipos e ON e.id=m.equipo_id WHERE m.id=? AND e.empresa_id=?", [$id, $emp]);
    if (!$r) { Flash::error('No encontrado.'); redirect('/modules/maquinaria/'); }
}

if (isPost()) {
    $data = [
        'equipo_id'    => postInt('equipo_id'),
        'tipo'         => post('tipo', 'preventiva'),
        'fecha'        => post('fecha', date('Y-m-d')),
        'horometro'    => postFloat('horometro') ?: null,
        'descripcion'  => post('descripcion') ?: null,
        'tecnico'      => post('tecnico') ?: null,
        'costo_total'  => postFloat('costo_total'),
        'estado'       => post('estado', 'pendiente'),
        'observaciones'=> post('observaciones') ?: null,
        // Constancia del técnico y lugar de la intervención
        'lat'          => post('lat')       !== '' ? postFloat('lat')  : null,
        'lng'          => post('lng')       !== '' ? postFloat('lng')  : null,
        'firma_b64'    => post('firma_b64') !== '' ? post('firma_b64') : null,
        'creado_por'   => Auth::id(),
    ];
    $errors = [];
    if (!$data['equipo_id']) $errors[] = 'Seleccione un equipo.';
    if (!$data['fecha'])     $errors[] = 'La fecha es obligatoria.';
    if (!$data['descripcion']) $errors[] = 'Descripción es obligatoria.';

    // El equipo llega por POST: hay que confirmar que es de la empresa activa
    // antes de registrar la mantención o tocar su horómetro.
    if ($data['equipo_id'] && !DB::one(
            "SELECT id FROM equipos WHERE id=? AND empresa_id=?", [$data['equipo_id'], $emp])) {
        $errors[] = 'El equipo seleccionado no pertenece a la empresa activa.';
    }

    if ($errors) {
        foreach ($errors as $e) Flash::error($e);
    } else {
        $empresa_id = $emp;
        if ($edit) {
            unset($data['creado_por']);
            $sets = implode(', ', array_map(fn($k) => "{$k}=?", array_keys($data)));
            DB::run("/* tenant-ok: la mantención se validó por empresa al cargarla */
                     UPDATE mantenciones SET {$sets} WHERE id=?", [...array_values($data), $id]);
            // Actualizar horómetro del equipo si se ingresó
            if ($data['horometro'] && $data['horometro'] > 0) {
                DB::run("UPDATE equipos SET horometro=? WHERE id=? AND empresa_id=? AND horometro < ?",
                    [$data['horometro'], $data['equipo_id'], $emp, $data['horometro']]);
            }
            Flash::success('Mantención actualizada.');
        } else {
            $data['empresa_id'] = $emp;
            $cols = implode(', ', array_keys($data));
            $plc  = implode(', ', array_fill(0, count($data), '?'));
            DB::run("INSERT INTO mantenciones ({$cols}) VALUES ({$plc})", array_values($data));
            if ($data['horometro'] && $data['horometro'] > 0) {
                DB::run("UPDATE equipos SET horometro=? WHERE id=? AND empresa_id=? AND horometro < ?",
                    [$data['horometro'], $data['equipo_id'], $emp, $data['horometro']]);
            }
            Auth::audit('crear', 'maquinaria', (int)DB::lastId());
            Flash::success('Mantención registrada.');
        }
        redirect('/modules/maquinaria/');
    }
}

$pageTitle = $edit ? 'Editar Mantención' : 'Nueva Mantención';
include ROOT . '/includes/header.php';
?>
<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index">Maquinaria</a></li>
    <li class="breadcrumb-item active"><?= h($pageTitle) ?></li>
</ol></nav>

<div class="card form-card" style="max-width:700px">
    <div class="card-header">
        <strong><i class="fas fa-wrench text-warning me-2"></i><?= h($pageTitle) ?></strong>
    </div>
    <div class="card-body">
        <form method="POST">
            <?= CSRF::field() ?>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Equipo <span class="text-danger">*</span></label>
                    <select name="equipo_id" class="form-select" <?= empty($equipos) ? '' : 'required' ?>>
                        <?php if (empty($equipos)): ?>
                            <option value="" disabled selected>Sin equipos — Agregue en Admin → Flota</option>
                        <?php else: ?>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($equipos as $e): ?>
                                <option value="<?= $e['id'] ?>" <?= ($r['equipo_id'] ?? $eqId) == $e['id'] ? 'selected' : '' ?>>
                                    <?= h($e['codigo']) ?> — <?= h($e['nombre']) ?> (<?= formatNum((float)$e['horometro'], 1) ?> hrs)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($equipos)): ?>
                    <div class="form-text text-danger"><i class="fas fa-exclamation-triangle me-1"></i>
                        <a href="<?= APP_URL ?>/modules/admin/maestros/equipos?action=create" target="_blank">Agregar equipo</a> antes de registrar mantenciones.
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo <span class="text-danger">*</span></label>
                    <select name="tipo" class="form-select" required>
                        <option value="preventiva" <?= ($r['tipo'] ?? '') === 'preventiva' ? 'selected' : '' ?>>Preventiva</option>
                        <option value="correctiva" <?= ($r['tipo'] ?? '') === 'correctiva' ? 'selected' : '' ?>>Correctiva</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <?php foreach (['pendiente' => 'Pendiente', 'en_proceso' => 'En Proceso', 'completada' => 'Completada'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($r['estado'] ?? 'pendiente') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha <span class="text-danger">*</span></label>
                    <input type="date" name="fecha" class="form-control" required value="<?= h($r['fecha'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Horómetro (hrs)</label>
                    <input type="number" name="horometro" class="form-control" min="0" step="0.1" value="<?= h($r['horometro'] ?? '') ?>" placeholder="Actualiza el horómetro">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Costo Total</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="costo_total" class="form-control" min="0" step="1" value="<?= h($r['costo_total'] ?? '0') ?>">
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Descripción del Trabajo <span class="text-danger">*</span></label>
                    <textarea name="descripcion" class="form-control" rows="3" required placeholder="Detalle del trabajo realizado..."><?= h($r['descripcion'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Técnico / Mecánico</label>
                    <input type="text" name="tecnico" class="form-control" value="<?= h($r['tecnico'] ?? '') ?>" maxlength="100">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Observaciones</label>
                    <input type="text" name="observaciones" class="form-control" value="<?= h($r['observaciones'] ?? '') ?>" maxlength="255">
                </div>
            </div>
            <hr class="my-3">
            <?php
                $firmaVal   = $r['firma_b64'] ?? '';
                $latVal     = $r['lat'] ?? '';
                $lngVal     = $r['lng'] ?? '';
                $firmaLabel = 'Firma del técnico';
                $firmaAyuda = 'Constancia de la intervención realizada.';
                include ROOT . '/includes/firma_gps.php';
            ?>

            <hr class="my-3">
            <div class="d-flex gap-2 form-acciones">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i><?= $edit ? 'Actualizar' : 'Registrar Mantención' ?>
                </button>
                <a href="index" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>
<?php include ROOT . '/includes/footer.php'; ?>
