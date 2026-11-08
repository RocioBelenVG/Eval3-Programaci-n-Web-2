<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requirePermiso('contratos');
CSRF::require();

$emp        = Auth::empresaId();
$contratoId = getInt('contrato_id');
$contrato   = null;

if ($contratoId) {
    $contrato = DB::one("SELECT * FROM contratos WHERE id=? AND empresa_id=?", [$contratoId, $emp]);
}

if (isPost()) {
    if (!Auth::can('contratos', 'editar')) {
        Flash::error('No tiene permiso para modificar ítems de contrato.');
        redirect('/modules/contratos/');
    }
    $cid    = postInt('contrato_id');
    $cont   = DB::one("SELECT id FROM contratos WHERE id=? AND empresa_id=?", [$cid, $emp]);
    if (!$cont) { Flash::error('Contrato no válido.'); redirect('/modules/contratos/'); }

    $codigos   = $_POST['codigo']    ?? [];
    $descs     = $_POST['descripcion'] ?? [];
    $unidades  = $_POST['unidad']    ?? [];
    $cantidades= $_POST['cantidad_total'] ?? [];
    $precios   = $_POST['precio_unitario'] ?? [];

    DB::run("DELETE FROM items_contrato WHERE contrato_id=?", [$cid]);
    foreach ($descs as $i => $desc) {
        $desc = trim($desc);
        if ($desc === '') continue;
        DB::run(
            "INSERT INTO items_contrato (contrato_id,codigo,descripcion,unidad,cantidad_total,precio_unitario) VALUES (?,?,?,?,?,?)",
            [$cid, $codigos[$i] ?: null, $desc, $unidades[$i] ?: null, (float)($cantidades[$i] ?? 0), (float)($precios[$i] ?? 0)]
        );
    }
    Auth::audit('editar', 'contratos', $cid);
    Flash::success('Ítems guardados correctamente.');
    redirect("/modules/contratos/items?contrato_id={$cid}");
}

$contratos = DB::all("SELECT id, CONCAT(COALESCE(numero,'S/N'),' — ',COALESCE(descripcion,'')) AS label FROM contratos WHERE empresa_id=? AND activo=1 ORDER BY numero", [$emp]);
$items     = $contratoId ? DB::all("SELECT * FROM items_contrato WHERE contrato_id=? ORDER BY id", [$contratoId]) : [];

$pageTitle = 'Ítems de Contrato';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index">Contratos</a></li>
    <li class="breadcrumb-item active">Ítems</li>
</ol></nav>

<div class="card table-card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <strong><i class="fas fa-list text-primary me-2"></i>Ítems / Partidas del Contrato</strong>
        <div>
            <form class="d-flex gap-2 align-items-center" method="GET">
                <select name="contrato_id" class="form-select form-select-sm" style="min-width:220px" onchange="this.form.submit()">
                    <option value="">— Seleccione contrato —</option>
                    <?php foreach ($contratos as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $contratoId == $c['id'] ? 'selected' : '' ?>><?= h($c['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </div>

    <?php if ($contratoId && $contrato): ?>
    <form method="POST">
        <?= CSRF::field() ?>
        <input type="hidden" name="contrato_id" value="<?= $contratoId ?>">
        <div class="table-responsive">
            <table class="table table-sm mb-0" id="tblItems">
                <thead>
                    <tr>
                        <th style="width:8%">Código</th>
                        <th style="width:35%">Descripción <span class="text-danger">*</span></th>
                        <th style="width:10%">Unidad</th>
                        <th style="width:12%">Cantidad</th>
                        <th style="width:15%">Precio Unit.</th>
                        <th style="width:15%" class="text-end">Total</th>
                        <th style="width:5%"></th>
                    </tr>
                </thead>
                <tbody id="bodyItems">
                <?php $rows = empty($items) ? [[]] : $items; ?>
                <?php foreach ($rows as $it): ?>
                <tr class="item-row">
                    <td><input type="text" name="codigo[]" class="form-control form-control-sm" maxlength="30" value="<?= h($it['codigo'] ?? '') ?>"></td>
                    <td><input type="text" name="descripcion[]" class="form-control form-control-sm" maxlength="255" value="<?= h($it['descripcion'] ?? '') ?>"></td>
                    <td><input type="text" name="unidad[]" class="form-control form-control-sm" maxlength="30" value="<?= h($it['unidad'] ?? '') ?>"></td>
                    <td><input type="number" name="cantidad_total[]" class="form-control form-control-sm cant-input" min="0" step="0.01" value="<?= h($it['cantidad_total'] ?? '0') ?>"></td>
                    <td><input type="number" name="precio_unitario[]" class="form-control form-control-sm precio-input" min="0" step="1" value="<?= h($it['precio_unitario'] ?? '0') ?>"></td>
                    <td class="text-end align-middle total-cell"><small><?= isset($it['monto_total']) ? formatMoney((float)$it['monto_total']) : '—' ?></small></td>
                    <td><button type="button" class="btn btn-sm btn-link text-danger remove-row"><i class="fas fa-trash"></i></button></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <td colspan="5" class="text-end fw-bold small">Total Contrato:</td>
                        <td class="text-end fw-bold small" id="totalGeneral">
                            <?= formatMoney(array_sum(array_column($items, 'monto_total'))) ?>
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="card-footer d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAddItem">
                <i class="fas fa-plus me-1"></i>Agregar Ítem
            </button>
            <button type="submit" class="btn btn-sm btn-primary ms-auto">
                <i class="fas fa-save me-2"></i>Guardar Ítems
            </button>
        </div>
    </form>
    <?php else: ?>
    <div class="card-body text-center text-muted py-4">
        <i class="fas fa-arrow-up d-block mb-2"></i>Seleccione un contrato para gestionar sus ítems.
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('click', e => {
    if (e.target.closest('.remove-row')) {
        const tbody = e.target.closest('tbody');
        if (tbody.querySelectorAll('tr').length > 1)
            e.target.closest('tr').remove();
        else
            e.target.closest('tr').querySelectorAll('input').forEach(el => el.value = '');
        calcTotal();
    }
});
document.getElementById('btnAddItem')?.addEventListener('click', () => {
    document.getElementById('bodyItems').insertAdjacentHTML('beforeend', `
    <tr class="item-row">
        <td><input type="text" name="codigo[]" class="form-control form-control-sm" maxlength="30"></td>
        <td><input type="text" name="descripcion[]" class="form-control form-control-sm" maxlength="255"></td>
        <td><input type="text" name="unidad[]" class="form-control form-control-sm" maxlength="30"></td>
        <td><input type="number" name="cantidad_total[]" class="form-control form-control-sm cant-input" min="0" step="0.01" value="0"></td>
        <td><input type="number" name="precio_unitario[]" class="form-control form-control-sm precio-input" min="0" step="1" value="0"></td>
        <td class="text-end align-middle total-cell"><small>—</small></td>
        <td><button type="button" class="btn btn-sm btn-link text-danger remove-row"><i class="fas fa-trash"></i></button></td>
    </tr>`);
});
document.addEventListener('input', e => {
    if (e.target.matches('.cant-input,.precio-input')) calcTotal();
});
function calcTotal() {
    let total = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const c = parseFloat(row.querySelector('.cant-input')?.value || 0);
        const p = parseFloat(row.querySelector('.precio-input')?.value || 0);
        const sub = c * p;
        row.querySelector('.total-cell').innerHTML = '<small>$' + sub.toLocaleString('es-CL') + '</small>';
        total += sub;
    });
    const el = document.getElementById('totalGeneral');
    if (el) el.textContent = '$' + total.toLocaleString('es-CL');
}
</script>

<?php include ROOT . '/includes/footer.php'; ?>
