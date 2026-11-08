<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requirePermiso('transporte', 'crear');
CSRF::require();

$emp  = Auth::empresaId();
$id   = getInt('id');
$edit = $id > 0;
$r    = [];

if ($edit) {
    $r = DB::one("SELECT * FROM guias_despacho WHERE id=? AND empresa_id=?", [$id, $emp]);
    if (!$r) { Flash::error('Guía no encontrada.'); redirect('/modules/transporte/guias'); }
}

if (isPost()) {
    $data = [
        'numero_guia'   => post('numero_guia'),
        'fecha'         => post('fecha', date('Y-m-d')),
        'obra_id'       => postInt('obra_id')      ?: null,
        'viaje_id'      => postInt('viaje_id')     ?: null,
        'vehiculo_id'   => postInt('vehiculo_id')  ?: null,
        'conductor_id'  => postInt('conductor_id') ?: null,
        'material_id'   => postInt('material_id')  ?: null,
        'cantidad'      => postFloat('cantidad')   ?: null,
        'unidad'        => post('unidad')          ?: null,
        'origen'        => post('origen')          ?: null,
        'destino'       => post('destino')         ?: null,
        'receptor'      => post('receptor')        ?: null,
        'rut_receptor'  => post('rut_receptor')    ?: null,
        'observaciones' => post('observaciones')   ?: null,
        // Conformidad de entrega capturada en terreno
        'lat'           => post('lat')       !== '' ? postFloat('lat')  : null,
        'lng'           => post('lng')       !== '' ? postFloat('lng')  : null,
        'firma_b64'     => post('firma_b64') !== '' ? post('firma_b64') : null,
    ];

    $errors = [];
    if (!$data['numero_guia']) $errors[] = 'El número de guía es obligatorio.';
    if (!$data['fecha'])       $errors[] = 'La fecha es obligatoria.';

    // Foto
    $fotoRuta = $r['foto_ruta'] ?? null;
    if (!empty($_FILES['foto']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $mime    = mime_content_type($_FILES['foto']['tmp_name']);
        if (!in_array($mime, $allowed)) {
            $errors[] = 'Solo se aceptan imágenes JPG, PNG o WEBP.';
        } elseif ($_FILES['foto']['size'] > UPLOAD_MAX_SIZE) {
            $errors[] = 'La imagen supera el tamaño máximo permitido.';
        } else {
            $ext      = uploadExtFromMime($mime) ?? 'bin';
            $nombre   = 'guia_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $destPath = UPLOAD_PATH . 'guias/' . $nombre;
            if (!is_dir(UPLOAD_PATH . 'guias/')) mkdir(UPLOAD_PATH . 'guias/', 0755, true);
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $destPath)) {
                $fotoRuta = 'guias/' . $nombre;
            }
        }
    }

    if ($errors) {
        foreach ($errors as $e) Flash::error($e);
    } else {
        $data['foto_ruta'] = $fotoRuta;
        if ($edit) {
            $sets = implode(', ', array_map(fn($k) => "{$k}=?", array_keys($data)));
            DB::run("UPDATE guias_despacho SET {$sets} WHERE id=? AND empresa_id=?", [...array_values($data), $id, $emp]);
            Auth::audit('editar', 'transporte', $id);
            Flash::success('Guía actualizada.');
        } else {
            $data['empresa_id'] = $emp;
            $data['creado_por'] = Auth::id();
            $cols = implode(', ', array_keys($data));
            $plc  = implode(', ', array_fill(0, count($data), '?'));
            DB::run("INSERT INTO guias_despacho ({$cols}) VALUES ({$plc})", array_values($data));
            Auth::audit('crear', 'transporte', (int)DB::lastId());
            Flash::success('Guía de despacho registrada.');
        }
        redirect('/modules/transporte/guias');
    }
}

$vehiculos  = DB::all("SELECT v.id, v.patente, v.marca FROM vehiculos v WHERE v.empresa_id=? AND v.activo=1 ORDER BY v.patente", [$emp]);
$conductores= DB::all("SELECT c.id, CONCAT(t.nombre,' ',t.apellido) AS nombre FROM conductores c INNER JOIN trabajadores t ON t.id=c.trabajador_id WHERE t.empresa_id=? AND c.activo=1 ORDER BY t.apellido", [$emp]);
$obras      = DB::all("SELECT id, nombre FROM obras WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);
$materiales = DB::all("SELECT id, nombre FROM materiales WHERE activo=1 ORDER BY nombre", []);
$viajes     = DB::all(
    "SELECT v.id, CONCAT(v.fecha,' — ',ve.patente,' — ',o.nombre) AS label
     FROM viajes v
     LEFT JOIN vehiculos ve ON ve.id=v.vehiculo_id
     LEFT JOIN obras     o  ON o.id=v.obra_id
     WHERE v.empresa_id=? AND v.estado IN ('programado','en_ruta','completado')
     ORDER BY v.fecha DESC LIMIT 100", [$emp]);

$pageTitle = $edit ? 'Editar Guía de Despacho' : 'Nueva Guía de Despacho';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index">Transporte</a></li>
    <li class="breadcrumb-item"><a href="guias">Guías</a></li>
    <li class="breadcrumb-item active"><?= h($pageTitle) ?></li>
</ol></nav>

<div class="card form-card" style="max-width:750px">
    <div class="card-header">
        <strong><i class="fas fa-file-invoice text-success me-2"></i><?= h($pageTitle) ?></strong>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" data-offline="1">
            <?= CSRF::field() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">N° Guía <span class="text-danger">*</span></label>
                    <input type="text" name="numero_guia" class="form-control fw-bold" required
                           maxlength="50" value="<?= h($r['numero_guia'] ?? '') ?>" placeholder="Ej: 000123">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha <span class="text-danger">*</span></label>
                    <input type="date" name="fecha" class="form-control" required value="<?= h($r['fecha'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Viaje asociado</label>
                    <select name="viaje_id" class="form-select">
                        <option value="">— Sin viaje —</option>
                        <?php foreach ($viajes as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= ($r['viaje_id'] ?? '') == $v['id'] ? 'selected' : '' ?>>
                                <?= h($v['label']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Vehículo</label>
                    <select name="vehiculo_id" class="form-select">
                        <option value="">— Seleccione —</option>
                        <?php foreach ($vehiculos as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= ($r['vehiculo_id'] ?? '') == $v['id'] ? 'selected' : '' ?>>
                                <?= h($v['patente'] . ' — ' . $v['marca']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Conductor</label>
                    <select name="conductor_id" class="form-select">
                        <option value="">— Seleccione —</option>
                        <?php foreach ($conductores as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($r['conductor_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                <?= h($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-5">
                    <label class="form-label">Origen</label>
                    <input type="text" name="origen" class="form-control" maxlength="200" value="<?= h($r['origen'] ?? '') ?>">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Destino</label>
                    <input type="text" name="destino" class="form-control" maxlength="200" value="<?= h($r['destino'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Obra</label>
                    <select name="obra_id" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($obras as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= ($r['obra_id'] ?? '') == $o['id'] ? 'selected' : '' ?>><?= h($o['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Material</label>
                    <select name="material_id" class="form-select">
                        <option value="">— Sin material —</option>
                        <?php foreach ($materiales as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= ($r['material_id'] ?? '') == $m['id'] ? 'selected' : '' ?>>
                                <?= h($m['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Cantidad</label>
                    <input type="number" name="cantidad" class="form-control" min="0" step="0.01"
                           value="<?= h($r['cantidad'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Unidad</label>
                    <select name="unidad" class="form-select">
                        <?php foreach (['' => '—', 'ton' => 'Toneladas', 'm3' => 'm³', 'unidad' => 'Unidades', 'viaje' => 'Viaje'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($r['unidad'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Receptor</label>
                    <input type="text" name="receptor" class="form-control" maxlength="150" value="<?= h($r['receptor'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">RUT Receptor</label>
                    <input type="text" name="rut_receptor" class="form-control" maxlength="12"
                           value="<?= h($r['rut_receptor'] ?? '') ?>" placeholder="12.345.678-9">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Foto de la guía</label>
                    <input type="file" name="foto" class="form-control" accept="image/*" capture="environment">
                    <?php if (!empty($r['foto_ruta'])): ?>
                    <div class="mt-1"><small class="text-success"><i class="fas fa-check me-1"></i>Foto guardada</small></div>
                    <?php endif; ?>
                </div>

                <div class="col-12">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="2" maxlength="500"><?= h($r['observaciones'] ?? '') ?></textarea>
                </div>
            </div>

            <hr class="my-3">
            <?php
                $firmaVal   = $r['firma_b64'] ?? '';
                $latVal     = $r['lat'] ?? '';
                $lngVal     = $r['lng'] ?? '';
                $firmaLabel = 'Firma de recepción';
                $firmaAyuda = 'Quien recibe la carga firma aquí.';
                include ROOT . '/includes/firma_gps.php';
            ?>

            <hr class="my-3">
            <div class="d-flex gap-2 form-acciones">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-2"></i><?= $edit ? 'Actualizar Guía' : 'Registrar Guía' ?>
                </button>
                <a href="guias" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
