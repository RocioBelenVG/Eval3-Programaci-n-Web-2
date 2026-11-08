<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requirePermiso('documentos', 'crear');
CSRF::require();

$emp  = Auth::empresaId();
$id   = getInt('id');
$edit = $id > 0;
$r    = [];

if ($edit) {
    $r = DB::one("SELECT * FROM documentos WHERE id=? AND empresa_id=?", [$id, $emp]);
    if (!$r) { Flash::error('Documento no encontrado.'); redirect('/modules/documentos/'); }
}

if (isPost()) {
    $data = [
        'tipo_id'           => postInt('tipo_id')       ?: null,
        'estado_id'         => postInt('estado_id')     ?: null,
        'obra_id'           => postInt('obra_id')       ?: null,
        'entidad_tipo'      => post('entidad_tipo', 'general'),
        'entidad_id'        => postInt('entidad_id')    ?: null,
        'titulo'            => post('titulo'),
        'numero'            => post('numero')           ?: null,
        'fecha_emision'     => post('fecha_emision')    ?: null,
        'fecha_vencimiento' => post('fecha_vencimiento')?: null,
        'observaciones'     => post('observaciones')    ?: null,
    ];

    $errors = [];
    if (!$data['titulo']) $errors[] = 'El título es obligatorio.';

    // Archivo
    $rutaArchivo = $r['ruta_archivo'] ?? null;
    $nombreArchivo = $r['nombre_archivo'] ?? null;
    if (!empty($_FILES['archivo']['name'])) {
        $allowed = ['application/pdf','image/jpeg','image/png','image/webp',
                    'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        $mime = mime_content_type($_FILES['archivo']['tmp_name']);
        if (!in_array($mime, $allowed)) {
            $errors[] = 'Tipo de archivo no permitido.';
        } elseif ($_FILES['archivo']['size'] > UPLOAD_MAX_SIZE) {
            $errors[] = 'El archivo supera el tamaño máximo (20 MB).';
        } else {
            $ext  = uploadExtFromMime($mime) ?? 'bin';
            $name = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = UPLOAD_PATH . 'documentos/' . $name;
            if (!is_dir(UPLOAD_PATH . 'documentos/')) mkdir(UPLOAD_PATH . 'documentos/', 0755, true);
            if (move_uploaded_file($_FILES['archivo']['tmp_name'], $dest)) {
                $rutaArchivo   = 'documentos/' . $name;
                $nombreArchivo = $_FILES['archivo']['name'];
            }
        }
    }

    if ($errors) {
        foreach ($errors as $e) Flash::error($e);
    } else {
        $data['ruta_archivo']   = $rutaArchivo;
        $data['nombre_archivo'] = $nombreArchivo;
        if ($edit) {
            $sets = implode(', ', array_map(fn($k) => "{$k}=?", array_keys($data)));
            DB::run("UPDATE documentos SET {$sets} WHERE id=? AND empresa_id=?", [...array_values($data), $id, $emp]);
            Auth::audit('editar', 'documentos', $id);
            Flash::success('Documento actualizado.');
        } else {
            $data['empresa_id'] = $emp;
            $data['creado_por'] = Auth::id();
            $cols = implode(', ', array_keys($data));
            $plc  = implode(', ', array_fill(0, count($data), '?'));
            DB::run("INSERT INTO documentos ({$cols}) VALUES ({$plc})", array_values($data));
            Auth::audit('crear', 'documentos', (int)DB::lastId());
            Flash::success('Documento registrado.');
        }
        redirect('/modules/documentos/');
    }
}

$tipos    = DB::all("SELECT id, nombre FROM tipos_documento ORDER BY nombre", []);
$estados  = DB::all("SELECT id, nombre FROM estados_documento ORDER BY nombre", []);
$obras    = DB::all("SELECT id, nombre FROM obras WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);

// Listas para entidad_id según tipo seleccionado
$trabajadores = DB::all("SELECT id, CONCAT(nombre,' ',apellido) AS nombre FROM trabajadores WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);
$conductores  = DB::all("SELECT c.id, CONCAT(t.nombre,' ',t.apellido) AS nombre FROM conductores c INNER JOIN trabajadores t ON t.id=c.trabajador_id WHERE t.empresa_id=? AND c.activo=1 ORDER BY t.apellido", [$emp]);
$operadores   = DB::all("SELECT o.id, CONCAT(t.nombre,' ',t.apellido) AS nombre FROM operadores o  INNER JOIN trabajadores t ON t.id=o.trabajador_id WHERE t.empresa_id=? AND o.activo=1 ORDER BY t.apellido", [$emp]);
$vehiculos    = DB::all("SELECT id, CONCAT(patente,' — ',marca) AS nombre FROM vehiculos    WHERE empresa_id=? AND activo=1 ORDER BY patente", [$emp]);
$equiposAll   = DB::all("SELECT id, CONCAT(codigo,' — ',nombre) AS nombre FROM equipos      WHERE empresa_id=? AND activo=1 ORDER BY codigo", [$emp]);

$pageTitle = $edit ? 'Editar Documento' : 'Nuevo Documento';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index">Documentos</a></li>
    <li class="breadcrumb-item active"><?= h($pageTitle) ?></li>
</ol></nav>

<div class="card form-card" style="max-width:750px">
    <div class="card-header">
        <strong><i class="fas fa-file-alt text-primary me-2"></i><?= h($pageTitle) ?></strong>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <?= CSRF::field() ?>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Título <span class="text-danger">*</span></label>
                    <input type="text" name="titulo" class="form-control" required maxlength="255"
                           value="<?= h($r['titulo'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">N° / Código</label>
                    <input type="text" name="numero" class="form-control" maxlength="100"
                           value="<?= h($r['numero'] ?? '') ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Tipo de Documento</label>
                    <select name="tipo_id" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= ($r['tipo_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= h($t['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Estado</label>
                    <select name="estado_id" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($estados as $e): ?>
                            <option value="<?= $e['id'] ?>" <?= ($r['estado_id'] ?? '') == $e['id'] ? 'selected' : '' ?>><?= h($e['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Obra asociada</label>
                    <select name="obra_id" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($obras as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= ($r['obra_id'] ?? '') == $o['id'] ? 'selected' : '' ?>><?= h($o['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Fecha Emisión</label>
                    <input type="date" name="fecha_emision" class="form-control" value="<?= h($r['fecha_emision'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha Vencimiento</label>
                    <input type="date" name="fecha_vencimiento" class="form-control" value="<?= h($r['fecha_vencimiento'] ?? '') ?>">
                    <div class="form-text">Dejar vacío si no vence.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Entidad / Asociado a</label>
                    <select name="entidad_tipo" id="selEntTipo" class="form-select">
                        <?php foreach (['general'=>'General','trabajador'=>'Trabajador','conductor'=>'Conductor','operador'=>'Operador','vehiculo'=>'Vehículo','equipo'=>'Equipo','empresa'=>'Empresa'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($r['entidad_tipo'] ?? 'general') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-12" id="entidadSelect" style="display:none">
                    <label class="form-label">Seleccionar entidad</label>
                    <select name="entidad_id" id="selEntId" class="form-select">
                        <option value="">—</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Archivo (PDF, imagen, Word, Excel)</label>
                    <input type="file" name="archivo" class="form-control"
                           accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx">
                    <?php if (!empty($r['ruta_archivo'])): ?>
                    <div class="mt-1">
                        <a href="<?= APP_URL ?>/uploads/<?= h($r['ruta_archivo']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-paperclip me-1"></i>Ver archivo actual: <?= h($r['nombre_archivo'] ?? '') ?>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="2" maxlength="1000"><?= h($r['observaciones'] ?? '') ?></textarea>
                </div>
            </div>

            <hr class="my-3">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i><?= $edit ? 'Actualizar' : 'Guardar Documento' ?></button>
                <a href="index" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
const entidades = {
    trabajador: <?= json_encode(array_map(fn($t) => ['id'=>$t['id'],'n'=>$t['nombre']], $trabajadores)) ?>,
    conductor:  <?= json_encode(array_map(fn($t) => ['id'=>$t['id'],'n'=>$t['nombre']], $conductores)) ?>,
    operador:   <?= json_encode(array_map(fn($t) => ['id'=>$t['id'],'n'=>$t['nombre']], $operadores)) ?>,
    vehiculo:   <?= json_encode(array_map(fn($t) => ['id'=>$t['id'],'n'=>$t['nombre']], $vehiculos)) ?>,
    equipo:     <?= json_encode(array_map(fn($t) => ['id'=>$t['id'],'n'=>$t['nombre']], $equiposAll)) ?>,
};
const currentEntId = <?= (int)($r['entidad_id'] ?? 0) ?>;

function updateEntSelect(tipo) {
    const wrap = document.getElementById('entidadSelect');
    const sel  = document.getElementById('selEntId');
    if (!entidades[tipo]) { wrap.style.display = 'none'; return; }
    wrap.style.display = '';
    sel.innerHTML = '<option value="">— Seleccione —</option>' +
        entidades[tipo].map(e => `<option value="${e.id}" ${e.id==currentEntId?'selected':''}>${e.n}</option>`).join('');
}
document.getElementById('selEntTipo').addEventListener('change', function() { updateEntSelect(this.value); });
updateEntSelect(document.getElementById('selEntTipo').value);
</script>

<?php include ROOT . '/includes/footer.php'; ?>
