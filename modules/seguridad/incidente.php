<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requirePermiso('seguridad', 'crear');
CSRF::require();

$emp  = Auth::empresaId();
$id   = getInt('id');
$edit = $id > 0;
$r    = [];

if ($edit) {
    $r = DB::one("SELECT * FROM incidentes WHERE id=? AND empresa_id=?", [$id, $emp]);
    if (!$r) { Flash::error('Incidente no encontrado.'); redirect('/modules/seguridad/'); }
}

if (isPost()) {
    $data = [
        'obra_id'       => postInt('obra_id')      ?: null,
        'fecha'         => post('fecha', date('Y-m-d')),
        'hora'          => post('hora')            ?: null,
        'tipo'          => post('tipo', 'incidente'),
        'gravedad'      => post('gravedad', 'leve'),
        'descripcion'   => post('descripcion'),
        'lugar'         => post('lugar')           ?: null,
        'trabajador_id' => postInt('trabajador_id')?: null,
        'dias_perdidos' => postInt('dias_perdidos'),
        'acciones'      => post('acciones')        ?: null,
        'estado'        => post('estado', 'reportado'),
        // Lugar exacto del evento y firma de quien reporta
        'lat'           => post('lat')       !== '' ? postFloat('lat')  : null,
        'lng'           => post('lng')       !== '' ? postFloat('lng')  : null,
        'firma_b64'     => post('firma_b64') !== '' ? post('firma_b64') : null,
    ];

    $errors = [];
    if (!$data['descripcion']) $errors[] = 'La descripción es obligatoria.';
    if (!$data['fecha'])       $errors[] = 'La fecha es obligatoria.';

    // Foto
    $fotoRuta = $r['foto_ruta'] ?? null;
    if (!empty($_FILES['foto']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $mime    = mime_content_type($_FILES['foto']['tmp_name']);
        if (!in_array($mime, $allowed)) {
            $errors[] = 'Solo se aceptan imágenes JPG, PNG o WEBP.';
        } else {
            $ext  = uploadExtFromMime($mime) ?? 'bin';
            $name = 'inc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = UPLOAD_PATH . 'seguridad/' . $name;
            if (!is_dir(UPLOAD_PATH . 'seguridad/')) mkdir(UPLOAD_PATH . 'seguridad/', 0755, true);
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $dest)) $fotoRuta = 'seguridad/' . $name;
        }
    }

    if ($errors) {
        foreach ($errors as $e) Flash::error($e);
    } else {
        $data['foto_ruta'] = $fotoRuta;
        if ($edit) {
            $sets = implode(', ', array_map(fn($k) => "{$k}=?", array_keys($data)));
            DB::run("UPDATE incidentes SET {$sets} WHERE id=? AND empresa_id=?", [...array_values($data), $id, $emp]);
            Auth::audit('editar', 'seguridad', $id);
            Flash::success('Incidente actualizado.');
        } else {
            $data['empresa_id'] = $emp;
            $data['creado_por'] = Auth::id();
            $cols = implode(', ', array_keys($data));
            $plc  = implode(', ', array_fill(0, count($data), '?'));
            DB::run("INSERT INTO incidentes ({$cols}) VALUES ({$plc})", array_values($data));
            Auth::audit('crear', 'seguridad', (int)DB::lastId());
            Flash::success('Incidente reportado. Notifique a su supervisor.');
        }
        redirect('/modules/seguridad/index?tab=incidentes');
    }
}

$obras        = DB::all("SELECT id, nombre FROM obras WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);
$trabajadores = DB::all("SELECT id, CONCAT(nombre,' ',apellido) AS nombre FROM trabajadores WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);

$pageTitle = $edit ? 'Editar Incidente' : 'Reportar Incidente';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index">Seguridad</a></li>
    <li class="breadcrumb-item active"><?= h($pageTitle) ?></li>
</ol></nav>

<?php if (!$edit): ?>
<div class="alert alert-danger d-flex align-items-center py-2 mb-3">
    <i class="fas fa-exclamation-triangle fa-lg me-3"></i>
    <div><strong>Reporte de Incidente / Accidente.</strong> Complete con la mayor precisión posible. En caso de emergencia médica llame al <strong>131</strong>.</div>
</div>
<?php endif; ?>

<div class="card form-card" style="max-width:750px">
    <div class="card-header">
        <strong><i class="fas fa-exclamation-circle text-danger me-2"></i><?= h($pageTitle) ?></strong>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" data-offline="1">
            <?= CSRF::field() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Fecha <span class="text-danger">*</span></label>
                    <input type="date" name="fecha" class="form-control" required value="<?= h($r['fecha'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hora</label>
                    <input type="time" name="hora" class="form-control" value="<?= h($r['hora'] ?? date('H:i')) ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Obra</label>
                    <select name="obra_id" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($obras as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= ($r['obra_id'] ?? '') == $o['id'] ? 'selected' : '' ?>><?= h($o['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Tipo <span class="text-danger">*</span></label>
                    <select name="tipo" class="form-select">
                        <?php foreach (['accidente'=>'Accidente','incidente'=>'Incidente','cuasi_accidente'=>'Cuasi-accidente','enfermedad'=>'Enfermedad profesional','otro'=>'Otro'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($r['tipo'] ?? 'incidente') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Gravedad <span class="text-danger">*</span></label>
                    <select name="gravedad" class="form-select">
                        <?php foreach (['leve'=>'Leve','moderado'=>'Moderado','grave'=>'Grave','fatal'=>'Fatal'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($r['gravedad'] ?? 'leve') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <?php foreach (['reportado'=>'Reportado','investigando'=>'Investigando','cerrado'=>'Cerrado'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($r['estado'] ?? 'reportado') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Trabajador involucrado</label>
                    <select name="trabajador_id" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($trabajadores as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= ($r['trabajador_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= h($t['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Días perdidos</label>
                    <input type="number" name="dias_perdidos" class="form-control" min="0" value="<?= h($r['dias_perdidos'] ?? '0') ?>">
                </div>

                <div class="col-12">
                    <label class="form-label">Lugar exacto</label>
                    <input type="text" name="lugar" class="form-control" maxlength="200" value="<?= h($r['lugar'] ?? '') ?>" placeholder="Ej: Cantera norte, sector pala #3">
                </div>

                <div class="col-12">
                    <label class="form-label">Descripción del hecho <span class="text-danger">*</span></label>
                    <textarea name="descripcion" class="form-control" rows="4" required maxlength="2000"
                              placeholder="Describa detalladamente qué ocurrió, cómo, y las condiciones del entorno..."><?= h($r['descripcion'] ?? '') ?></textarea>
                </div>

                <div class="col-12">
                    <label class="form-label">Acciones correctivas / Medidas tomadas</label>
                    <textarea name="acciones" class="form-control" rows="3" maxlength="2000"
                              placeholder="Medidas inmediatas, acciones de corrección, seguimiento planificado..."><?= h($r['acciones'] ?? '') ?></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Foto del lugar</label>
                    <input type="file" name="foto" class="form-control" accept="image/*" capture="environment">
                    <?php if (!empty($r['foto_ruta'])): ?>
                    <div class="mt-1">
                        <a href="<?= APP_URL ?>/uploads/<?= h($r['foto_ruta']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-image me-1"></i>Ver foto
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <hr class="my-3">
            <?php
                $firmaVal   = $r['firma_b64'] ?? '';
                $latVal     = $r['lat'] ?? '';
                $lngVal     = $r['lng'] ?? '';
                $firmaLabel = 'Firma de quien reporta';
                $firmaAyuda = 'Firme para dejar constancia del reporte.';
                include ROOT . '/includes/firma_gps.php';
            ?>

            <hr class="my-3">
            <div class="d-flex gap-2 form-acciones">
                <button type="submit" class="btn btn-danger fw-semibold">
                    <i class="fas fa-save me-2"></i><?= $edit ? 'Actualizar' : 'Registrar Incidente' ?>
                </button>
                <a href="index?tab=incidentes" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
