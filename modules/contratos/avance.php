<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requirePermiso('contratos', 'crear');
CSRF::require();

$emp        = Auth::empresaId();
$id         = getInt('id');
$contratoId = getInt('contrato_id');
$edit       = $id > 0;
$r          = [];

if ($edit) {
    $r = DB::one("SELECT av.* FROM avances_contrato av INNER JOIN contratos c ON c.id=av.contrato_id WHERE av.id=? AND c.empresa_id=?", [$id, $emp]);
    if (!$r) { Flash::error('Avance no encontrado.'); redirect('/modules/contratos/'); }
    $contratoId = (int)$r['contrato_id'];
}

$contrato = $contratoId ? DB::one("SELECT * FROM contratos WHERE id=? AND empresa_id=?", [$contratoId, $emp]) : null;

// Si el contrato pedido no es de la empresa activa, se descarta el id: de lo
// contrario el historial y los ítems de más abajo mostrarían datos ajenos.
if ($contratoId && !$contrato) {
    Flash::error('El contrato solicitado no pertenece a la empresa activa.');
    redirect('/modules/contratos/');
}

if (isPost()) {
    $data = [
        'contrato_id' => postInt('contrato_id'),
        'item_id'     => postInt('item_id') ?: null,
        'fecha'       => post('fecha', date('Y-m-d')),
        'cantidad'    => postFloat('cantidad'),
        'monto'       => postFloat('monto'),
        'observacion' => post('observacion') ?: null,
        'creado_por'  => Auth::id(),
    ];

    $errors = [];
    if (!$data['contrato_id']) $errors[] = 'Seleccione un contrato.';
    if (!$data['fecha'])       $errors[] = 'La fecha es obligatoria.';
    if ($data['monto'] <= 0 && $data['cantidad'] <= 0) $errors[] = 'Ingrese monto o cantidad.';

    // El contrato recibido debe ser de la empresa activa
    if ($data['contrato_id'] && !DB::one(
            "SELECT id FROM contratos WHERE id=? AND empresa_id=?", [$data['contrato_id'], $emp])) {
        $errors[] = 'El contrato seleccionado no pertenece a la empresa activa.';
    }
    // El ítem, si viene, debe pertenecer a ese mismo contrato
    if ($data['item_id'] && !DB::one(
            "SELECT id FROM items_contrato WHERE id=? AND contrato_id=?", [$data['item_id'], $data['contrato_id']])) {
        $errors[] = 'La partida seleccionada no corresponde al contrato.';
    }

    if ($errors) {
        foreach ($errors as $e) Flash::error($e);
    } else {
        if ($edit) {
            unset($data['creado_por']);
            $sets = implode(', ', array_map(fn($k) => "{$k}=?", array_keys($data)));
            DB::run("UPDATE avances_contrato SET {$sets} WHERE id=?", [...array_values($data), $id]);
            Auth::audit('editar', 'contratos', $id);
            Flash::success('Avance actualizado.');
        } else {
            $cols = implode(', ', array_keys($data));
            $plc  = implode(', ', array_fill(0, count($data), '?'));
            DB::run("INSERT INTO avances_contrato ({$cols}) VALUES ({$plc})", array_values($data));
            Auth::audit('crear', 'contratos', (int)DB::lastId());
            Flash::success('Avance registrado.');
        }
        redirect('/modules/contratos/');
    }
}

$contratos = DB::all(
    "SELECT c.id, CONCAT(COALESCE(c.numero,'S/N'),' — ',COALESCE(c.descripcion,'')) AS label
     FROM contratos c WHERE c.empresa_id=? AND c.activo=1 ORDER BY c.numero", [$emp]);

$items = $contratoId ? DB::all("SELECT id, CONCAT(COALESCE(codigo,''),' ',descripcion) AS label FROM items_contrato WHERE contrato_id=? AND activo=1 ORDER BY id", [$contratoId]) : [];

// Historial de avances del contrato
$historial = $contratoId ? DB::all(
    "/* tenant-ok: el contrato fue validado contra la empresa activa */
     SELECT av.*, ic.descripcion AS item_desc, u.nombre AS creado_nombre
     FROM avances_contrato av
     LEFT JOIN items_contrato ic ON ic.id=av.item_id
     LEFT JOIN usuarios u ON u.id=av.creado_por
     WHERE av.contrato_id=? ORDER BY av.fecha DESC LIMIT 30", [$contratoId]) : [];

$pageTitle = $edit ? 'Editar Avance' : 'Registrar Avance';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index">Contratos</a></li>
    <li class="breadcrumb-item active"><?= h($pageTitle) ?></li>
</ol></nav>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card form-card">
            <div class="card-header">
                <strong><i class="fas fa-chart-line text-primary me-2"></i><?= h($pageTitle) ?></strong>
            </div>
            <div class="card-body">
                <form method="POST" id="formAvance">
                    <?= CSRF::field() ?>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Contrato <span class="text-danger">*</span></label>
                            <select name="contrato_id" id="selContrato" class="form-select" required>
                                <option value="">— Seleccione —</option>
                                <?php foreach ($contratos as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($r['contrato_id'] ?? $contratoId) == $c['id'] ? 'selected' : '' ?>>
                                        <?= h($c['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Ítem / Partida</label>
                            <select name="item_id" id="selItem" class="form-select">
                                <option value="">— Sin ítem específico —</option>
                                <?php foreach ($items as $it): ?>
                                    <option value="<?= $it['id'] ?>" <?= ($r['item_id'] ?? '') == $it['id'] ? 'selected' : '' ?>><?= h($it['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha <span class="text-danger">*</span></label>
                            <input type="date" name="fecha" class="form-control" required value="<?= h($r['fecha'] ?? date('Y-m-d')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cantidad</label>
                            <input type="number" name="cantidad" class="form-control" min="0" step="0.01"
                                   value="<?= h($r['cantidad'] ?? '0') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Monto avanzado ($)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="monto" class="form-control" min="0" step="1"
                                       value="<?= h($r['monto'] ?? '0') ?>">
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observación</label>
                            <input type="text" name="observacion" class="form-control" maxlength="255"
                                   value="<?= h($r['observacion'] ?? '') ?>">
                        </div>
                    </div>
                    <hr class="my-3">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Guardar</button>
                        <a href="index" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <?php if ($contrato): ?>
        <div class="card table-card mb-3">
            <div class="card-header"><strong><i class="fas fa-info-circle text-info me-2"></i>Contrato Seleccionado</strong></div>
            <div class="card-body py-2">
                <?php
                $totalAvanzado = array_sum(array_column($historial, 'monto'));
                $pct = $contrato['monto_contrato'] > 0 ? min(100, round(($totalAvanzado / $contrato['monto_contrato']) * 100)) : 0;
                ?>
                <div class="row g-2 small">
                    <div class="col-6"><strong>N°:</strong> <?= h($contrato['numero'] ?? 'S/N') ?></div>
                    <div class="col-6"><strong>Monto:</strong> <?= formatMoney((float)$contrato['monto_contrato']) ?></div>
                    <div class="col-6"><strong>Avanzado:</strong> <?= formatMoney($totalAvanzado) ?></div>
                    <div class="col-6"><strong>Saldo:</strong> <?= formatMoney((float)$contrato['monto_contrato'] - $totalAvanzado) ?></div>
                    <div class="col-12">
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height:12px">
                                <div class="progress-bar bg-primary" style="width:<?= $pct ?>%" role="progressbar"><?= $pct ?>%</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($historial): ?>
        <div class="card table-card">
            <div class="card-header"><strong><i class="fas fa-history text-secondary me-2"></i>Últimos Avances</strong></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Fecha</th><th>Ítem</th><th>Cantidad</th><th class="text-end">Monto</th><th>Por</th></tr></thead>
                    <tbody>
                    <?php foreach ($historial as $h): ?>
                    <tr>
                        <td><?= formatDate($h['fecha']) ?></td>
                        <td><small><?= h(mb_substr($h['item_desc'] ?? 'General', 0, 30)) ?></small></td>
                        <td><small><?= formatNum((float)$h['cantidad'], 2) ?></small></td>
                        <td class="text-end"><small><?= formatMoney((float)$h['monto']) ?></small></td>
                        <td><small><?= h($h['creado_nombre'] ?? '—') ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.getElementById('selContrato').addEventListener('change', function() {
    const cid = this.value;
    if (!cid) return;
    window.location.href = 'avance?contrato_id=' + cid;
});
</script>

<?php include ROOT . '/includes/footer.php'; ?>
