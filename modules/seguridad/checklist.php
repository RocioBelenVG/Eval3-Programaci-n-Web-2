<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requirePermiso('seguridad', 'crear');
CSRF::require();

$emp  = Auth::empresaId();
$id   = getInt('id');
$edit = $id > 0;
$r    = [];
$items_resp = [];

if ($edit) {
    $r = DB::one("SELECT * FROM checklists_registros WHERE id=? AND empresa_id=?", [$id, $emp]);
    if (!$r) { Flash::error('Checklist no encontrado.'); redirect('/modules/seguridad/'); }
    $items_resp = DB::all("SELECT * FROM checklist_items_resp WHERE registro_id=? ORDER BY id", [$id]);
}

// Ítems predefinidos según el tipo (hardcoded como punto de partida)
$plantillas = [
    'default' => [
        'EPP completo (casco, chaleco, guantes, zapatos)',
        'Área de trabajo delimitada y señalizada',
        'Herramientas en buen estado',
        'Botiquín disponible y completo',
        'Extintor accesible y vigente',
        'Personal informado de riesgos',
        'Superficies libres de obstáculos',
        'Iluminación adecuada',
    ],
];

if (isPost()) {
    $data = [
        'tipo_id'       => postInt('tipo_id')      ?: null,
        'obra_id'       => postInt('obra_id')      ?: null,
        'equipo_id'     => postInt('equipo_id')    ?: null,
        'vehiculo_id'   => postInt('vehiculo_id')  ?: null,
        'trabajador_id' => postInt('trabajador_id')?: null,
        'fecha'         => post('fecha', date('Y-m-d')),
        'turno'         => post('turno', 'mañana'),
        'resultado'     => post('resultado', 'aprobado'),
        'observaciones' => post('observaciones')   ?: null,
        'firma_b64'     => post('firma_b64')       ?: null,
        // Lugar donde se realizó la inspección
        'lat'           => post('lat') !== '' ? postFloat('lat') : null,
        'lng'           => post('lng') !== '' ? postFloat('lng') : null,
        'creado_por'    => Auth::id(),
    ];

    $itemsTexto = $_POST['item_texto'] ?? [];
    $itemsCumple= $_POST['item_cumple'] ?? [];
    $itemsObs   = $_POST['item_obs']   ?? [];

    if (!$data['fecha']) {
        Flash::error('La fecha es obligatoria.');
    } else {
        $pdo = DB::conn();
        $pdo->beginTransaction();
        try {
            if ($edit) {
                unset($data['creado_por']);
                $sets = implode(', ', array_map(fn($k) => "{$k}=?", array_keys($data)));
                DB::run("UPDATE checklists_registros SET {$sets} WHERE id=? AND empresa_id=?",
                    [...array_values($data), $id, $emp]);
                DB::run("DELETE FROM checklist_items_resp WHERE registro_id=?", [$id]);
                $regId = $id;
            } else {
                $data['empresa_id'] = $emp;
                $cols = implode(', ', array_keys($data));
                $plc  = implode(', ', array_fill(0, count($data), '?'));
                DB::run("INSERT INTO checklists_registros ({$cols}) VALUES ({$plc})", array_values($data));
                $regId = (int)DB::lastId();
            }
            foreach ($itemsTexto as $i => $texto) {
                $texto = trim($texto);
                if ($texto === '') continue;
                DB::run("INSERT INTO checklist_items_resp (registro_id, item, cumple, observacion) VALUES (?,?,?,?)",
                    [$regId, $texto, isset($itemsCumple[$i]) ? 1 : 0, $itemsObs[$i] ?? null]);
            }
            $pdo->commit();
            Auth::audit($edit ? 'editar' : 'crear', 'seguridad', $regId);
            Flash::success($edit ? 'Checklist actualizado.' : 'Checklist registrado.');
        } catch (Exception $ex) {
            $pdo->rollBack();
            Flash::error('Error al guardar.');
        }
        redirect('/modules/seguridad/');
    }
}

$tipos        = DB::all("SELECT id, nombre FROM tipos_checklist WHERE activo=1 ORDER BY nombre", []);
$obras        = DB::all("SELECT id, nombre FROM obras WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);
$equipos      = DB::all("SELECT id, codigo, nombre FROM equipos WHERE empresa_id=? AND activo=1 ORDER BY codigo", [$emp]);
$vehiculos    = DB::all("SELECT id, patente, marca FROM vehiculos WHERE empresa_id=? AND activo=1 ORDER BY patente", [$emp]);
$trabajadores = DB::all("SELECT id, CONCAT(nombre,' ',apellido) AS nombre FROM trabajadores WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);

$readonly = $edit;
$pageTitle = $edit ? 'Ver Checklist' : 'Nuevo Checklist';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index">Seguridad</a></li>
    <li class="breadcrumb-item active"><?= h($pageTitle) ?></li>
</ol></nav>

<form method="POST" id="formChk" data-offline="1">
<?= CSRF::field() ?>
<input type="hidden" name="firma_b64" id="fFirmaB64">

<div class="row g-3 mb-3">
    <div class="col-lg-6">
        <div class="card form-card">
            <div class="card-header"><strong><i class="fas fa-clipboard-check text-success me-2"></i>Encabezado</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" required value="<?= h($r['fecha'] ?? date('Y-m-d')) ?>" <?= $readonly ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Turno</label>
                        <select name="turno" class="form-select" <?= $readonly ? 'disabled' : '' ?>>
                            <?php foreach (['mañana'=>'Mañana','tarde'=>'Tarde','noche'=>'Noche'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= ($r['turno'] ?? 'mañana') === $v ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Resultado</label>
                        <select name="resultado" class="form-select" <?= $readonly ? 'disabled' : '' ?>>
                            <?php foreach (['aprobado'=>'Aprobado','observado'=>'Observado','rechazado'=>'Rechazado'] as $v => $l): ?>
                                <option value="<?= $v ?>" <?= ($r['resultado'] ?? 'aprobado') === $v ? 'selected' : '' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Tipo de Checklist</label>
                        <select name="tipo_id" class="form-select" <?= $readonly ? 'disabled' : '' ?>>
                            <option value="">—</option>
                            <?php foreach ($tipos as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($r['tipo_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= h($t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Obra</label>
                        <select name="obra_id" class="form-select" <?= $readonly ? 'disabled' : '' ?>>
                            <option value="">—</option>
                            <?php foreach ($obras as $o): ?>
                                <option value="<?= $o['id'] ?>" <?= ($r['obra_id'] ?? '') == $o['id'] ? 'selected' : '' ?>><?= h($o['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Equipo inspeccionado</label>
                        <select name="equipo_id" class="form-select" <?= $readonly ? 'disabled' : '' ?>>
                            <option value="">—</option>
                            <?php foreach ($equipos as $e): ?>
                                <option value="<?= $e['id'] ?>" <?= ($r['equipo_id'] ?? '') == $e['id'] ? 'selected' : '' ?>><?= h($e['codigo'] . ' ' . $e['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Vehículo inspeccionado</label>
                        <select name="vehiculo_id" class="form-select" <?= $readonly ? 'disabled' : '' ?>>
                            <option value="">—</option>
                            <?php foreach ($vehiculos as $v): ?>
                                <option value="<?= $v['id'] ?>" <?= ($r['vehiculo_id'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= h($v['patente'] . ' — ' . $v['marca']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observaciones generales</label>
                        <textarea name="observaciones" class="form-control" rows="2" <?= $readonly ? 'disabled' : '' ?>><?= h($r['observaciones'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$readonly): ?>
    <div class="col-lg-6">
        <div class="card form-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <strong><i class="fas fa-signature text-secondary me-2"></i>Firma</strong>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearFirma"><i class="fas fa-eraser me-1"></i>Limpiar</button>
            </div>
            <div class="card-body py-2">
                <canvas id="sigCanvas" width="480" height="130"
                        style="border:1px solid #dee2e6;border-radius:6px;background:#fafafa;touch-action:none;max-width:100%;cursor:crosshair;"></canvas>
                <div><small class="text-muted">Firma del responsable del checklist.</small></div>
            </div>
        </div>
    </div>
    <?php elseif (!empty($r['firma_b64'])): ?>
    <div class="col-lg-6">
        <div class="card form-card">
            <div class="card-header"><strong>Firma</strong></div>
            <div class="card-body"><img src="<?= h($r['firma_b64']) ?>" style="max-height:100px;border:1px solid #dee2e6;border-radius:4px;"></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Ítems del checklist -->
<div class="card table-card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-list-check text-success me-2"></i>Ítems a Verificar</strong>
        <?php if (!$readonly): ?>
        <button type="button" class="btn btn-sm btn-outline-success" id="btnAddItem"><i class="fas fa-plus me-1"></i>Agregar Ítem</button>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr>
                <th style="width:5%">Cumple</th>
                <th>Ítem a verificar</th>
                <th style="width:30%">Observación</th>
                <?php if (!$readonly): ?><th style="width:5%"></th><?php endif; ?>
            </tr></thead>
            <tbody id="bodyItems">
            <?php
            $displayItems = !empty($items_resp) ? $items_resp : array_map(fn($t) => ['item'=>$t,'cumple'=>1,'observacion'=>''], $plantillas['default']);
            $loop = 0;
            foreach ($displayItems as $it):
                $idx = $loop++;
            ?>
            <tr class="item-row">
                <td class="text-center">
                    <input type="checkbox" name="item_cumple[<?= $idx ?>]" value="1"
                           class="form-check-input item-cb" <?= ($it['cumple'] ?? 1) ? 'checked' : '' ?> <?= $readonly ? 'disabled' : '' ?>>
                </td>
                <td>
                    <input type="text" name="item_texto[<?= $idx ?>]" class="form-control form-control-sm"
                           value="<?= h($it['item'] ?? '') ?>" <?= $readonly ? 'disabled' : '' ?>>
                </td>
                <td>
                    <input type="text" name="item_obs[<?= $idx ?>]" class="form-control form-control-sm"
                           value="<?= h($it['observacion'] ?? '') ?>" placeholder="Observación…" <?= $readonly ? 'disabled' : '' ?>>
                </td>
                <?php if (!$readonly): ?><td><button type="button" class="btn btn-sm btn-link text-danger remove-row"><i class="fas fa-trash"></i></button></td><?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!$readonly): ?>
<div class="card form-card mb-4">
    <div class="card-body">
        <?php
            // La firma ya se captura más arriba en este formulario; aquí sólo la ubicación.
            $latVal   = $r['lat'] ?? '';
            $lngVal   = $r['lng'] ?? '';
            $conFirma = false;
            include ROOT . '/includes/firma_gps.php';
        ?>
    </div>
</div>

<div class="d-flex gap-2 mb-4 form-acciones">
    <button type="submit" class="btn btn-success fw-semibold"><i class="fas fa-save me-2"></i>Guardar Checklist</button>
    <a href="index" class="btn btn-outline-secondary">Cancelar</a>
</div>
<?php else: ?>
<div class="mb-4"><a href="index" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Volver</a></div>
<?php endif; ?>

</form>

<?php if (!$readonly): ?>
<script>
let itemIdx = <?= count($displayItems) ?>;
document.addEventListener('click', e => {
    if (e.target.closest('.remove-row')) {
        const tbody = e.target.closest('tbody');
        if (tbody.querySelectorAll('tr').length > 1) e.target.closest('tr').remove();
    }
});
document.getElementById('btnAddItem').addEventListener('click', () => {
    const idx = itemIdx++;
    document.getElementById('bodyItems').insertAdjacentHTML('beforeend', `
    <tr class="item-row">
        <td class="text-center"><input type="checkbox" name="item_cumple[${idx}]" value="1" class="form-check-input item-cb" checked></td>
        <td><input type="text" name="item_texto[${idx}]" class="form-control form-control-sm" placeholder="Descripción del ítem…"></td>
        <td><input type="text" name="item_obs[${idx}]" class="form-control form-control-sm" placeholder="Observación…"></td>
        <td><button type="button" class="btn btn-sm btn-link text-danger remove-row"><i class="fas fa-trash"></i></button></td>
    </tr>`);
});
// Signature pad
(function() {
    const canvas = document.getElementById('sigCanvas');
    const ctx = canvas.getContext('2d');
    let drawing = false;
    function getPos(e) {
        const r = canvas.getBoundingClientRect();
        const s = e.touches ? e.touches[0] : e;
        return { x: (s.clientX - r.left) * (canvas.width / r.width), y: (s.clientY - r.top) * (canvas.height / r.height) };
    }
    canvas.addEventListener('mousedown',  e => { drawing=true; const p=getPos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); });
    canvas.addEventListener('touchstart', e => { e.preventDefault(); drawing=true; const p=getPos(e); ctx.beginPath(); ctx.moveTo(p.x,p.y); }, {passive:false});
    canvas.addEventListener('mousemove',  e => { if(!drawing)return; const p=getPos(e); ctx.lineTo(p.x,p.y); ctx.strokeStyle='#1a2537'; ctx.lineWidth=2; ctx.lineCap='round'; ctx.stroke(); });
    canvas.addEventListener('touchmove',  e => { e.preventDefault(); if(!drawing)return; const p=getPos(e); ctx.lineTo(p.x,p.y); ctx.strokeStyle='#1a2537'; ctx.lineWidth=2; ctx.lineCap='round'; ctx.stroke(); }, {passive:false});
    canvas.addEventListener('mouseup',  () => drawing=false);
    canvas.addEventListener('touchend', () => drawing=false);
    document.getElementById('btnClearFirma').addEventListener('click', () => { ctx.clearRect(0,0,canvas.width,canvas.height); document.getElementById('fFirmaB64').value=''; });
    document.getElementById('formChk').addEventListener('submit', () => {
        const blank = document.createElement('canvas'); blank.width=canvas.width; blank.height=canvas.height;
        if (canvas.toDataURL() !== blank.toDataURL()) document.getElementById('fFirmaB64').value = canvas.toDataURL('image/png');
    });
})();
</script>
<?php endif; ?>

<?php include ROOT . '/includes/footer.php'; ?>
