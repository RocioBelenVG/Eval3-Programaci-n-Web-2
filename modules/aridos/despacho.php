<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requirePermiso('aridos', 'crear');
CSRF::require();

$emp  = Auth::empresaId();
$id   = getInt('id');
$edit = $id > 0;
$r    = [];

if ($edit) {
    $r = DB::one("SELECT * FROM despachos_aridos WHERE id=? AND empresa_id=?", [$id, $emp]);
    if (!$r) { Flash::error('Despacho no encontrado.'); redirect('/modules/aridos/'); }
}

if (isPost()) {
    $data = [
        'material_id'      => postInt('material_id'),
        'clasificacion_id' => postInt('clasificacion_id') ?: null,
        'obra_id'          => postInt('obra_id')          ?: null,
        'cliente_id'       => postInt('cliente_id')       ?: null,
        'fecha'            => post('fecha', date('Y-m-d')),
        'guia_numero'      => post('guia_numero')         ?: null,
        'vehiculo_id'      => postInt('vehiculo_id')      ?: null,
        'conductor_id'     => postInt('conductor_id')     ?: null,
        'cantidad'         => postFloat('cantidad'),
        'unidad'           => post('unidad', 'ton'),
        'precio_unitario'  => postFloat('precio_unitario'),
        'destino'          => post('destino')             ?: null,
        'receptor'         => post('receptor')            ?: null,
        'rut_receptor'     => post('rut_receptor')        ?: null,
        'observaciones'    => post('observaciones')       ?: null,
        // Conformidad de recepción del material en destino
        'lat'              => post('lat')       !== '' ? postFloat('lat')  : null,
        'lng'              => post('lng')       !== '' ? postFloat('lng')  : null,
        'firma_b64'        => post('firma_b64') !== '' ? post('firma_b64') : null,
    ];

    $errors = [];
    if (!$data['material_id']) $errors[] = 'Seleccione un material.';
    if ($data['cantidad'] <= 0) $errors[] = 'La cantidad debe ser mayor a 0.';
    if (!$data['fecha'])        $errors[] = 'La fecha es obligatoria.';

    // Validar stock suficiente (solo al crear)
    if (!$edit && !$errors) {
        $stockRow = DB::one(
            "SELECT cantidad FROM stock_aridos WHERE empresa_id=? AND material_id=? AND " .
            ($data['obra_id']          ? "obra_id=?"          : "obra_id IS NULL") . " AND " .
            ($data['clasificacion_id'] ? "clasificacion_id=?" : "clasificacion_id IS NULL"),
            array_filter([$emp, $data['material_id'], $data['obra_id'], $data['clasificacion_id']], fn($v) => $v !== null)
        );
        $stockDisp = (float)($stockRow['cantidad'] ?? 0);
        if ($stockDisp < $data['cantidad']) {
            $errors[] = "Stock insuficiente. Disponible: {$stockDisp} {$data['unidad']}.";
        }
    }

    // Foto
    $fotoRuta = $r['foto_ruta'] ?? null;
    if (!empty($_FILES['foto']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $mime    = mime_content_type($_FILES['foto']['tmp_name']);
        if (!in_array($mime, $allowed)) {
            $errors[] = 'Solo se aceptan imágenes JPG, PNG o WEBP.';
        } else {
            $ext  = uploadExtFromMime($mime) ?? 'bin';
            $name = 'desp_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = UPLOAD_PATH . 'aridos/' . $name;
            if (!is_dir(UPLOAD_PATH . 'aridos/')) mkdir(UPLOAD_PATH . 'aridos/', 0755, true);
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $dest)) {
                $fotoRuta = 'aridos/' . $name;
            }
        }
    }

    if ($errors) {
        foreach ($errors as $e) Flash::error($e);
    } else {
        $pdo = DB::conn();
        $pdo->beginTransaction();
        try {
            $data['foto_ruta'] = $fotoRuta;
            if ($edit) {
                $sets = implode(', ', array_map(fn($k) => "{$k}=?", array_keys($data)));
                DB::run("UPDATE despachos_aridos SET {$sets} WHERE id=? AND empresa_id=?", [...array_values($data), $id, $emp]);
                // Revertir stock anterior, descontar nuevo
                $delta = (float)$r['cantidad'] - $data['cantidad'];
                updateStockD($emp, $r['obra_id'] ?: null, $r['material_id'], $r['clasificacion_id'] ?: null, $r['unidad'], $delta);
                Auth::audit('editar', 'aridos', $id);
                Flash::success('Despacho actualizado.');
            } else {
                $data['empresa_id'] = $emp;
                $data['creado_por'] = Auth::id();
                $cols = implode(', ', array_keys($data));
                $plc  = implode(', ', array_fill(0, count($data), '?'));
                DB::run("INSERT INTO despachos_aridos ({$cols}) VALUES ({$plc})", array_values($data));
                $newId = (int)DB::lastId();
                updateStockD($emp, $data['obra_id'], $data['material_id'], $data['clasificacion_id'], $data['unidad'], -$data['cantidad']);
                Auth::audit('crear', 'aridos', $newId);
                Flash::success('Despacho registrado. Stock descontado.');
            }
            $pdo->commit();
        } catch (Exception $ex) {
            $pdo->rollBack();
            Flash::error('Error al guardar el despacho.');
        }
        redirect('/modules/aridos/index?tab=despachos');
    }
}

function updateStockD(int $emp, ?int $obraId, int $matId, ?int $clasId, string $unidad, float $delta): void {
    $row = DB::one(
        "SELECT id, cantidad FROM stock_aridos WHERE empresa_id=? AND material_id=? AND " .
        ($obraId ? "obra_id=?" : "obra_id IS NULL") . " AND " .
        ($clasId ? "clasificacion_id=?" : "clasificacion_id IS NULL"),
        array_filter([$emp, $matId, $obraId, $clasId], fn($v) => $v !== null)
    );
    if ($row) {
        DB::run("UPDATE stock_aridos SET cantidad=? WHERE id=? AND empresa_id=?",
            [max(0, (float)$row['cantidad'] + $delta), $row['id'], $emp]);
    } elseif ($delta > 0) {
        DB::run("INSERT INTO stock_aridos (empresa_id,obra_id,material_id,clasificacion_id,cantidad,unidad) VALUES (?,?,?,?,?,?)",
            [$emp, $obraId, $matId, $clasId, $delta, $unidad]);
    }
}

$materiales      = DB::all("SELECT id, nombre FROM materiales WHERE activo=1 ORDER BY nombre", []);
$clasificaciones = DB::all("SELECT id, nombre FROM clasificaciones_arido WHERE activo=1 ORDER BY nombre", []);
$obras           = DB::all("SELECT id, nombre FROM obras WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);
$clientes        = DB::all("SELECT id, razon_social FROM clientes WHERE empresa_id=? AND activo=1 ORDER BY razon_social", [$emp]);
$vehiculos       = DB::all("SELECT v.id, v.patente, v.marca FROM vehiculos v WHERE v.empresa_id=? AND v.activo=1 ORDER BY v.patente", [$emp]);
$conductores     = DB::all("SELECT c.id, CONCAT(t.nombre,' ',t.apellido) AS nombre FROM conductores c INNER JOIN trabajadores t ON t.id=c.trabajador_id WHERE t.empresa_id=? AND c.activo=1 ORDER BY t.apellido", [$emp]);

$pageTitle = $edit ? 'Editar Despacho' : 'Nuevo Despacho de Áridos';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index">Áridos</a></li>
    <li class="breadcrumb-item active"><?= h($pageTitle) ?></li>
</ol></nav>

<div class="card form-card" style="max-width:750px">
    <div class="card-header">
        <strong><i class="fas fa-truck-loading text-success me-2"></i><?= h($pageTitle) ?></strong>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" data-offline="1">
            <?= CSRF::field() ?>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Fecha <span class="text-danger">*</span></label>
                    <input type="date" name="fecha" class="form-control" required value="<?= h($r['fecha'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">N° Guía</label>
                    <input type="text" name="guia_numero" class="form-control" maxlength="50" value="<?= h($r['guia_numero'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Obra</label>
                    <select name="obra_id" class="form-select">
                        <option value="">— Sin obra —</option>
                        <?php foreach ($obras as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= ($r['obra_id'] ?? '') == $o['id'] ? 'selected' : '' ?>><?= h($o['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Material <span class="text-danger">*</span></label>
                    <select name="material_id" class="form-select" required id="selMat">
                        <option value="">— Seleccione —</option>
                        <?php foreach ($materiales as $m): ?>
                            <option value="<?= $m['id'] ?>" <?= ($r['material_id'] ?? '') == $m['id'] ? 'selected' : '' ?>><?= h($m['nombre']) ?></option>
                        <?php endforeach; ?>
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

                <div class="col-md-4">
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
                <div class="col-md-5">
                    <label class="form-label">Precio Unitario</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="precio_unitario" class="form-control" min="0" step="1"
                               value="<?= h($r['precio_unitario'] ?? '0') ?>">
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Vehículo</label>
                    <select name="vehiculo_id" class="form-select">
                        <option value="">—</option>
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
                        <option value="">—</option>
                        <?php foreach ($conductores as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($r['conductor_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                <?= h($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Cliente</label>
                    <select name="cliente_id" class="form-select">
                        <option value="">—</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($r['cliente_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= h($c['razon_social']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Destino</label>
                    <input type="text" name="destino" class="form-control" maxlength="200" value="<?= h($r['destino'] ?? '') ?>">
                </div>

                <div class="col-md-5">
                    <label class="form-label">Receptor</label>
                    <input type="text" name="receptor" class="form-control" maxlength="150" value="<?= h($r['receptor'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">RUT Receptor</label>
                    <input type="text" name="rut_receptor" class="form-control" maxlength="12" value="<?= h($r['rut_receptor'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Foto guía</label>
                    <input type="file" name="foto" class="form-control" accept="image/*" capture="environment">
                    <?php if (!empty($r['foto_ruta'])): ?>
                    <small class="text-success"><i class="fas fa-check me-1"></i>Foto guardada</small>
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
                $firmaAyuda = 'Quien recibe el material firma aquí.';
                include ROOT . '/includes/firma_gps.php';
            ?>

            <hr class="my-3">
            <div class="alert alert-warning py-2 small">
                <i class="fas fa-exclamation-triangle me-1"></i>El stock se descontará automáticamente al registrar el despacho.
            </div>
            <div class="d-flex gap-2 form-acciones">
                <button type="submit" class="btn btn-success fw-semibold">
                    <i class="fas fa-save me-2"></i><?= $edit ? 'Actualizar Despacho' : 'Registrar Despacho' ?>
                </button>
                <a href="index?tab=despachos" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
