<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requirePermiso('faena', 'crear');

$emp  = Auth::empresaId();
$id   = getInt('id');
$edit = $id > 0;

$r = $actividades = $personal = $equipos_parte = [];

if ($edit) {
    $r = DB::one("SELECT * FROM partes_diarios WHERE id=? AND empresa_id=?", [$id, $emp]);
    if (!$r) { Flash::error('Parte no encontrado.'); redirect('/modules/faena/'); }
    // El parte ya fue validado contra la empresa activa en la consulta anterior,
    // por eso sus detalles se leen sólo por parte_id.
    $actividades   = DB::all("/* tenant-ok: parte validado arriba */
        SELECT * FROM parte_actividades WHERE parte_id=? ORDER BY id", [$id]);
    $personal      = DB::all(
        "/* tenant-ok: parte validado arriba */
         SELECT pp.*, CONCAT(t.nombre,' ',t.apellido) AS trab_nombre
         FROM parte_personal pp LEFT JOIN trabajadores t ON t.id=pp.trabajador_id
         WHERE pp.parte_id=? ORDER BY pp.id", [$id]);
    $equipos_parte = DB::all(
        "/* tenant-ok: parte validado arriba */
         SELECT pe.*, e.codigo, e.nombre AS eq_nombre
         FROM parte_equipos pe LEFT JOIN equipos e ON e.id=pe.equipo_id
         WHERE pe.parte_id=? ORDER BY pe.id", [$id]);
}

$readonly = $edit && $r['estado'] === 'cerrado' && !Auth::isAdmin();

if (isPost() && !$readonly) {
    CSRF::require();

    $data = [
        'obra_id'       => postInt('obra_id') ?: null,
        'contrato_id'   => postInt('contrato_id') ?: null,
        'fecha'         => post('fecha', date('Y-m-d')),
        'supervisor_id' => postInt('supervisor_id') ?: null,
        'clima'         => post('clima') ?: null,
        'estado'        => post('estado', 'borrador'),
        'observaciones' => post('observaciones') ?: null,
        'firma_b64'     => post('firma_b64') ?: null,
        'lat'           => postFloat('lat') ?: null,
        'lng'           => postFloat('lng') ?: null,
    ];

    $actDesc   = $_POST['act_desc']   ?? [];
    $actUnidad = $_POST['act_unidad'] ?? [];
    $actCant   = $_POST['act_cant']   ?? [];
    $persId    = $_POST['pers_id']    ?? [];
    $persHrs   = $_POST['pers_horas'] ?? [];
    $eqId      = $_POST['eq_id']      ?? [];
    $eqTrab    = $_POST['eq_trab']    ?? [];
    $eqDet     = $_POST['eq_det']     ?? [];

    if (!$data['fecha']) {
        Flash::error('La fecha es obligatoria.');
    } else {
        $pdo = DB::conn();
        $pdo->beginTransaction();
        try {
            if ($edit) {
                $sets = implode(', ', array_map(fn($k) => "{$k}=?", array_keys($data)));
                DB::run("UPDATE partes_diarios SET {$sets} WHERE id=? AND empresa_id=?",
                    [...array_values($data), $id, $emp]);
                $parteId = $id;
            } else {
                $data['empresa_id'] = $emp;
                $data['creado_por'] = Auth::id();
                $cols = implode(', ', array_keys($data));
                $plc  = implode(', ', array_fill(0, count($data), '?'));
                DB::run("INSERT INTO partes_diarios ({$cols}) VALUES ({$plc})", array_values($data));
                $parteId = (int)DB::lastId();
            }

            DB::run("DELETE FROM parte_actividades WHERE parte_id=?", [$parteId]);
            DB::run("DELETE FROM parte_personal WHERE parte_id=?",    [$parteId]);
            DB::run("DELETE FROM parte_equipos WHERE parte_id=?",     [$parteId]);

            foreach ($actDesc as $i => $desc) {
                $desc = trim($desc);
                if ($desc === '') continue;
                DB::run("INSERT INTO parte_actividades (parte_id, descripcion, unidad, cantidad) VALUES (?,?,?,?)",
                    [$parteId, $desc, $actUnidad[$i] ?? null,
                     ($actCant[$i] ?? '') !== '' ? (float)$actCant[$i] : null]);
            }
            foreach ($persId as $i => $tid) {
                $tid = (int)$tid;
                if (!$tid) continue;
                DB::run("INSERT INTO parte_personal (parte_id, trabajador_id, horas) VALUES (?,?,?)",
                    [$parteId, $tid, ($persHrs[$i] ?? '') !== '' ? (float)$persHrs[$i] : null]);
            }
            foreach ($eqId as $i => $eid) {
                $eid = (int)$eid;
                if (!$eid) continue;
                DB::run("INSERT INTO parte_equipos (parte_id, equipo_id, horas_trabajo, horas_detenciones) VALUES (?,?,?,?)",
                    [$parteId, $eid, (float)($eqTrab[$i] ?? 0), (float)($eqDet[$i] ?? 0)]);
            }

            $pdo->commit();
            Auth::audit($edit ? 'editar' : 'crear', 'faena', $parteId);
            Flash::success($edit ? 'Parte actualizado.' : 'Parte registrado correctamente.');
            redirect('/modules/faena/');
        } catch (Exception $ex) {
            $pdo->rollBack();
            Flash::error('Error al guardar el parte.');
        }
    }
}

$obras      = DB::all("SELECT id, nombre FROM obras WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);
$contratos  = DB::all("SELECT id, numero AS codigo, COALESCE(descripcion, numero, 'Sin descripción') AS nombre FROM contratos WHERE empresa_id=? AND activo=1 ORDER BY numero", [$emp]);
$supervisores = DB::all(
    "SELECT id, CONCAT(nombre,' ',apellido) AS nombre FROM trabajadores WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);
$trabajadores = $supervisores;
$equiposAll = DB::all("SELECT id, codigo, nombre FROM equipos WHERE empresa_id=? AND activo=1 ORDER BY codigo", [$emp]);

$pageTitle = $edit ? 'Editar Parte de Faena' : 'Nuevo Parte de Faena';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index">Partes de Faena</a></li>
    <li class="breadcrumb-item active"><?= h($pageTitle) ?></li>
</ol></nav>

<?php if ($readonly): ?>
<div class="alert alert-info py-2 mb-3">
    <i class="fas fa-lock me-2"></i>Este parte está <strong>cerrado</strong>. Solo lectura.
</div>
<?php endif; ?>

<form method="POST" id="formParte" data-offline="1">
<?= CSRF::field() ?>
<input type="hidden" name="lat" id="fLat" value="<?= h($r['lat'] ?? '') ?>">
<input type="hidden" name="lng" id="fLng" value="<?= h($r['lng'] ?? '') ?>">
<input type="hidden" name="firma_b64" id="fFirmaB64" value="">

<!-- ─── ENCABEZADO ─── -->
<div class="card table-card mb-3">
    <div class="card-header"><strong><i class="fas fa-clipboard-list text-primary me-2"></i>Encabezado del Parte</strong></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Fecha <span class="text-danger">*</span></label>
                <input type="date" name="fecha" class="form-control" required
                       value="<?= h($r['fecha'] ?? date('Y-m-d')) ?>" <?= $readonly ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-5">
                <label class="form-label">Obra</label>
                <select name="obra_id" id="selObra" class="form-select" <?= $readonly ? 'disabled' : '' ?>>
                    <?php if (empty($obras)): ?>
                        <option value="">Sin obras — Agregue en Admin → Proyectos</option>
                    <?php else: ?>
                        <option value="">— Sin obra —</option>
                        <?php foreach ($obras as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= ($r['obra_id'] ?? '') == $o['id'] ? 'selected' : '' ?>>
                                <?= h($o['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Contrato</label>
                <select name="contrato_id" class="form-select" <?= $readonly ? 'disabled' : '' ?>>
                    <?php if (empty($contratos)): ?>
                        <option value="">Sin contratos registrados</option>
                    <?php else: ?>
                        <option value="">— Sin contrato —</option>
                        <?php foreach ($contratos as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($r['contrato_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                <?= h(($c['codigo'] ?? '') . ($c['nombre'] ? ' — ' . $c['nombre'] : '')) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Supervisor</label>
                <select name="supervisor_id" class="form-select" <?= $readonly ? 'disabled' : '' ?>>
                    <?php if (empty($supervisores)): ?>
                        <option value="">Sin trabajadores — Agregue en Admin → Personal</option>
                    <?php else: ?>
                        <option value="">— Sin supervisor —</option>
                        <?php foreach ($supervisores as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($r['supervisor_id'] ?? '') == $s['id'] ? 'selected' : '' ?>>
                                <?= h($s['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Clima</label>
                <select name="clima" class="form-select" <?= $readonly ? 'disabled' : '' ?>>
                    <?php foreach ([''=>'—','Despejado'=>'Despejado','Nublado'=>'Nublado','Lluvia'=>'Lluvia','Viento'=>'Viento','Nieve'=>'Nieve'] as $v => $l): ?>
                        <option value="<?= $v ?>" <?= ($r['clima'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Coordenadas GPS</label>
                <div class="input-group">
                    <input type="text" id="gpsDisplay" class="form-control form-control-sm" readonly
                           value="<?= ($r['lat'] ?? '') ? h($r['lat'] . ', ' . $r['lng']) : '' ?>"
                           placeholder="Sin capturar">
                    <?php if (!$readonly): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnGps">
                        <i class="fas fa-map-marker-alt"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Observaciones</label>
                <textarea name="observaciones" class="form-control" rows="2" maxlength="1000"
                          placeholder="Novedades del día, incidentes, condiciones especiales..." <?= $readonly ? 'disabled' : '' ?>
                          ><?= h($r['observaciones'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- ─── ACTIVIDADES ─── -->
<div class="card table-card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-tasks text-success me-2"></i>Actividades Realizadas</strong>
        <?php if (!$readonly): ?>
        <button type="button" class="btn btn-sm btn-outline-success" id="btnAddAct">
            <i class="fas fa-plus me-1"></i>Agregar Fila
        </button>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0" id="tblAct">
            <thead><tr>
                <th style="width:55%">Descripción <span class="text-danger">*</span></th>
                <th style="width:15%">Unidad</th>
                <th style="width:15%">Cantidad</th>
                <?php if (!$readonly): ?><th style="width:5%"></th><?php endif; ?>
            </tr></thead>
            <tbody id="bodyAct">
            <?php if (empty($actividades)): ?>
                <tr class="act-row">
                    <td><input type="text" name="act_desc[]" class="form-control form-control-sm" placeholder="Descripción de la actividad" <?= $readonly ? 'disabled' : '' ?>></td>
                    <td><input type="text" name="act_unidad[]" class="form-control form-control-sm" placeholder="m³, ton, m…" maxlength="30" <?= $readonly ? 'disabled' : '' ?>></td>
                    <td><input type="number" name="act_cant[]" class="form-control form-control-sm" min="0" step="0.01" <?= $readonly ? 'disabled' : '' ?>></td>
                    <?php if (!$readonly): ?><td><button type="button" class="btn btn-sm btn-link text-danger remove-row"><i class="fas fa-trash"></i></button></td><?php endif; ?>
                </tr>
            <?php else: ?>
                <?php foreach ($actividades as $a): ?>
                <tr class="act-row">
                    <td><input type="text" name="act_desc[]" class="form-control form-control-sm" value="<?= h($a['descripcion']) ?>" <?= $readonly ? 'disabled' : '' ?>></td>
                    <td><input type="text" name="act_unidad[]" class="form-control form-control-sm" value="<?= h($a['unidad'] ?? '') ?>" maxlength="30" <?= $readonly ? 'disabled' : '' ?>></td>
                    <td><input type="number" name="act_cant[]" class="form-control form-control-sm" value="<?= h($a['cantidad'] ?? '') ?>" min="0" step="0.01" <?= $readonly ? 'disabled' : '' ?>></td>
                    <?php if (!$readonly): ?><td><button type="button" class="btn btn-sm btn-link text-danger remove-row"><i class="fas fa-trash"></i></button></td><?php endif; ?>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ─── PERSONAL ─── -->
<div class="card table-card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-users text-info me-2"></i>Personal en Faena</strong>
        <?php if (!$readonly): ?>
        <button type="button" class="btn btn-sm btn-outline-info" id="btnAddPers">
            <i class="fas fa-plus me-1"></i>Agregar Personal
        </button>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr>
                <th style="width:70%">Trabajador</th>
                <th style="width:20%">Horas trabajadas</th>
                <?php if (!$readonly): ?><th style="width:5%"></th><?php endif; ?>
            </tr></thead>
            <tbody id="bodyPers">
            <?php
            $persRows = empty($personal) ? [[]] : $personal;
            foreach ($persRows as $p):
            ?>
            <tr class="pers-row">
                <td>
                    <select name="pers_id[]" class="form-select form-select-sm" <?= $readonly ? 'disabled' : '' ?>>
                        <option value="">— Seleccione trabajador —</option>
                        <?php foreach ($trabajadores as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= ($p['trabajador_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                                <?= h($t['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" name="pers_horas[]" class="form-control form-control-sm" min="0" max="24" step="0.5"
                           value="<?= h($p['horas'] ?? '') ?>" placeholder="8.0" <?= $readonly ? 'disabled' : '' ?>></td>
                <?php if (!$readonly): ?><td><button type="button" class="btn btn-sm btn-link text-danger remove-row"><i class="fas fa-trash"></i></button></td><?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ─── EQUIPOS ─── -->
<div class="card table-card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-cogs text-warning me-2"></i>Equipos en Faena</strong>
        <?php if (!$readonly): ?>
        <button type="button" class="btn btn-sm btn-outline-warning" id="btnAddEq">
            <i class="fas fa-plus me-1"></i>Agregar Equipo
        </button>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead><tr>
                <th style="width:50%">Equipo</th>
                <th style="width:20%">Horas Trabajo</th>
                <th style="width:20%">Horas Detención</th>
                <?php if (!$readonly): ?><th style="width:5%"></th><?php endif; ?>
            </tr></thead>
            <tbody id="bodyEq">
            <?php
            $eqRows = empty($equipos_parte) ? [[]] : $equipos_parte;
            foreach ($eqRows as $e):
            ?>
            <tr class="eq-row">
                <td>
                    <select name="eq_id[]" class="form-select form-select-sm" <?= $readonly ? 'disabled' : '' ?>>
                        <option value="">— Seleccione equipo —</option>
                        <?php foreach ($equiposAll as $eq): ?>
                            <option value="<?= $eq['id'] ?>" <?= ($e['equipo_id'] ?? '') == $eq['id'] ? 'selected' : '' ?>>
                                <?= h($eq['codigo'] . ' — ' . $eq['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td><input type="number" name="eq_trab[]" class="form-control form-control-sm" min="0" max="24" step="0.5"
                           value="<?= h($e['horas_trabajo'] ?? '0') ?>" <?= $readonly ? 'disabled' : '' ?>></td>
                <td><input type="number" name="eq_det[]" class="form-control form-control-sm" min="0" max="24" step="0.5"
                           value="<?= h($e['horas_detenciones'] ?? '0') ?>" <?= $readonly ? 'disabled' : '' ?>></td>
                <?php if (!$readonly): ?><td><button type="button" class="btn btn-sm btn-link text-danger remove-row"><i class="fas fa-trash"></i></button></td><?php endif; ?>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ─── FIRMA ─── -->
<?php if (!$readonly): ?>
<div class="card table-card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <strong><i class="fas fa-signature text-secondary me-2"></i>Firma del Supervisor</strong>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnClearFirma">
            <i class="fas fa-eraser me-1"></i>Limpiar
        </button>
    </div>
    <div class="card-body py-2">
        <?php if (!empty($r['firma_b64'])): ?>
        <div class="mb-2"><small class="text-muted">Firma guardada:</small><br>
            <img src="<?= h($r['firma_b64']) ?>" style="border:1px solid #ccc;height:80px;">
        </div>
        <?php endif; ?>
        <canvas id="sigCanvas" width="600" height="130"
                style="border:1px solid #dee2e6;border-radius:6px;background:#fafafa;touch-action:none;max-width:100%;cursor:crosshair;"></canvas>
        <div><small class="text-muted">Firma del supervisor responsable del parte.</small></div>
    </div>
</div>
<?php endif; ?>

<!-- ─── BOTONES ─── -->
<?php if (!$readonly): ?>
<div class="d-flex gap-2 flex-wrap mb-4">
    <button type="submit" name="estado" value="borrador" class="btn btn-outline-warning fw-semibold">
        <i class="fas fa-save me-2"></i>Guardar Borrador
    </button>
    <button type="submit" name="estado" value="cerrado" class="btn btn-success fw-semibold"
            onclick="return confirm('¿Cerrar el parte? No podrá editarse sin permisos de administrador.')">
        <i class="fas fa-lock me-2"></i>Cerrar y Firmar Parte
    </button>
    <a href="index" class="btn btn-outline-secondary">Cancelar</a>
</div>
<?php else: ?>
<div class="mb-4">
    <a href="index" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Volver</a>
</div>
<?php endif; ?>

</form>

<?php if (!$readonly): ?>
<script>
// ── Dyn rows ──────────────────────────────────────────────────────────────────
function addRow(tbodyId, rowClass, templateFn) {
    document.getElementById(tbodyId).insertAdjacentHTML('beforeend', templateFn());
}
function removeRow(btn) {
    const tbody = btn.closest('tbody');
    if (tbody.querySelectorAll('tr').length <= 1) {
        btn.closest('tr').querySelectorAll('input,select').forEach(el => { el.value = ''; });
    } else {
        btn.closest('tr').remove();
    }
}
document.addEventListener('click', e => {
    if (e.target.closest('.remove-row')) removeRow(e.target.closest('.remove-row'));
});

document.getElementById('btnAddAct').addEventListener('click', () => {
    addRow('bodyAct', 'act-row', () => `
    <tr class="act-row">
        <td><input type="text" name="act_desc[]" class="form-control form-control-sm" placeholder="Descripción de la actividad"></td>
        <td><input type="text" name="act_unidad[]" class="form-control form-control-sm" placeholder="m³, ton…" maxlength="30"></td>
        <td><input type="number" name="act_cant[]" class="form-control form-control-sm" min="0" step="0.01"></td>
        <td><button type="button" class="btn btn-sm btn-link text-danger remove-row"><i class="fas fa-trash"></i></button></td>
    </tr>`);
});

document.getElementById('btnAddPers').addEventListener('click', () => {
    const opts = <?= json_encode(array_map(fn($t) => ['id'=>$t['id'],'n'=>$t['nombre']], $trabajadores)) ?>;
    let optsHtml = '<option value="">— Seleccione trabajador —</option>' +
        opts.map(t => `<option value="${t.id}">${t.n}</option>`).join('');
    document.getElementById('bodyPers').insertAdjacentHTML('beforeend', `
    <tr class="pers-row">
        <td><select name="pers_id[]" class="form-select form-select-sm">${optsHtml}</select></td>
        <td><input type="number" name="pers_horas[]" class="form-control form-control-sm" min="0" max="24" step="0.5" placeholder="8.0"></td>
        <td><button type="button" class="btn btn-sm btn-link text-danger remove-row"><i class="fas fa-trash"></i></button></td>
    </tr>`);
});

document.getElementById('btnAddEq').addEventListener('click', () => {
    const opts = <?= json_encode(array_map(fn($e) => ['id'=>$e['id'],'n'=>$e['codigo'].' — '.$e['nombre']], $equiposAll)) ?>;
    let optsHtml = '<option value="">— Seleccione equipo —</option>' +
        opts.map(e => `<option value="${e.id}">${e.n}</option>`).join('');
    document.getElementById('bodyEq').insertAdjacentHTML('beforeend', `
    <tr class="eq-row">
        <td><select name="eq_id[]" class="form-select form-select-sm">${optsHtml}</select></td>
        <td><input type="number" name="eq_trab[]" class="form-control form-control-sm" min="0" max="24" step="0.5" value="0"></td>
        <td><input type="number" name="eq_det[]" class="form-control form-control-sm" min="0" max="24" step="0.5" value="0"></td>
        <td><button type="button" class="btn btn-sm btn-link text-danger remove-row"><i class="fas fa-trash"></i></button></td>
    </tr>`);
});

// ── GPS ───────────────────────────────────────────────────────────────────────
document.getElementById('btnGps').addEventListener('click', function() {
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    navigator.geolocation.getCurrentPosition(
        pos => {
            const lat = pos.coords.latitude.toFixed(7);
            const lng = pos.coords.longitude.toFixed(7);
            document.getElementById('fLat').value = lat;
            document.getElementById('fLng').value = lng;
            document.getElementById('gpsDisplay').value = lat + ', ' + lng;
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-check text-success"></i>';
        },
        err => {
            alert('No se pudo obtener ubicación: ' + err.message);
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-map-marker-alt"></i>';
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
});

// ── Signature pad ─────────────────────────────────────────────────────────────
(function() {
    const canvas = document.getElementById('sigCanvas');
    const ctx = canvas.getContext('2d');
    let drawing = false;

    function getPos(e) {
        const r = canvas.getBoundingClientRect();
        const src = e.touches ? e.touches[0] : e;
        return { x: (src.clientX - r.left) * (canvas.width / r.width), y: (src.clientY - r.top) * (canvas.height / r.height) };
    }
    canvas.addEventListener('mousedown',  e => { drawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); });
    canvas.addEventListener('touchstart', e => { e.preventDefault(); drawing = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }, { passive: false });
    canvas.addEventListener('mousemove',  e => { if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.strokeStyle = '#1a2537'; ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.stroke(); });
    canvas.addEventListener('touchmove',  e => { e.preventDefault(); if (!drawing) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.strokeStyle = '#1a2537'; ctx.lineWidth = 2; ctx.lineCap = 'round'; ctx.stroke(); }, { passive: false });
    canvas.addEventListener('mouseup',    () => drawing = false);
    canvas.addEventListener('touchend',   () => drawing = false);

    document.getElementById('btnClearFirma').addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        document.getElementById('fFirmaB64').value = '';
    });

    document.getElementById('formParte').addEventListener('submit', () => {
        const blank = document.createElement('canvas');
        blank.width = canvas.width; blank.height = canvas.height;
        if (canvas.toDataURL() !== blank.toDataURL()) {
            document.getElementById('fFirmaB64').value = canvas.toDataURL('image/png');
        }
    });
})();
</script>
<?php endif; ?>

<?php include ROOT . '/includes/footer.php'; ?>
