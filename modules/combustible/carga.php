<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requirePermiso('combustible', 'crear');
CSRF::require();

$emp  = Auth::empresaId();
$id   = getInt('id');
$edit = $id > 0;

// ---- Cargar datos para el form ----
$obras      = DB::all("SELECT id, nombre FROM obras     WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);
$vehiculos  = DB::all("SELECT id, patente, marca, modelo FROM vehiculos WHERE empresa_id=? AND activo=1 ORDER BY patente", [$emp]);
$equipos    = DB::all("SELECT id, codigo, nombre FROM equipos WHERE empresa_id=? AND activo=1 ORDER BY codigo", [$emp]);
$conductores= DB::all("SELECT c.id, CONCAT(t.nombre,' ',t.apellido) AS nombre FROM conductores c INNER JOIN trabajadores t ON t.id=c.trabajador_id WHERE t.empresa_id=? AND c.activo=1 ORDER BY t.apellido", [$emp]);
$operadores = DB::all("SELECT o.id, CONCAT(t.nombre,' ',t.apellido) AS nombre FROM operadores o  INNER JOIN trabajadores t ON t.id=o.trabajador_id WHERE t.empresa_id=? AND o.activo=1 ORDER BY t.apellido", [$emp]);
$fuentes    = DB::all("SELECT id, nombre FROM fuentes_combustible WHERE activo=1 ORDER BY nombre");
$estanques  = DB::all("SELECT id, nombre, tipo_combustible FROM estanques WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);
$proveedores= DB::all("SELECT id, nombre FROM proveedores_combustible WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);

// ---- Cargar registro existente (edit) ----
$r = [];
if ($edit) {
    $r = DB::one("SELECT * FROM cargas_combustible WHERE id=? AND empresa_id=?", [$id, $emp]);
    if (!$r) { Flash::error('Registro no encontrado.'); redirect('/modules/combustible/'); }
}

// ---- POST ----
if (isPost()) {
    $data = [
        'tipo_unidad'      => post('tipo_unidad', 'vehiculo'),
        'fecha'            => post('fecha', date('Y-m-d')),
        'obra_id'          => postInt('obra_id') ?: null,
        'contrato_id'      => postInt('contrato_id') ?: null,
        'vehiculo_id'      => postInt('vehiculo_id') ?: null,
        'equipo_id'        => postInt('equipo_id') ?: null,
        'conductor_id'     => postInt('conductor_id') ?: null,
        'operador_id'      => postInt('operador_id') ?: null,
        'litros'           => postFloat('litros'),
        'precio_litro'     => postFloat('precio_litro') ?: null,
        'tipo_combustible' => post('tipo_combustible', 'Petróleo'),
        'horometro'        => postFloat('horometro') ?: null,
        'odometro'         => postInt('odometro') ?: null,
        'fuente_id'        => postInt('fuente_id') ?: null,
        'estanque_id'      => postInt('estanque_id') ?: null,
        'proveedor_id'     => postInt('proveedor_id') ?: null,
        'lat'              => post('lat') ?: null,
        'lng'              => post('lng') ?: null,
        'observacion'      => post('observacion') ?: null,
        'firma_b64'        => post('firma_b64') !== '' ? post('firma_b64') : null,
    ];

    $errors = [];
    if (!$data['fecha'])   $errors[] = 'La fecha es obligatoria.';
    if ($data['litros'] <= 0) $errors[] = 'Los litros deben ser mayor a 0.';
    if ($data['tipo_unidad'] === 'vehiculo' && !$data['vehiculo_id']) $errors[] = 'Seleccione un vehículo.';
    if ($data['tipo_unidad'] === 'equipo'   && !$data['equipo_id'])   $errors[] = 'Seleccione un equipo.';

    // Foto
    $fotoRuta = $r['foto_ruta'] ?? null;
    if (!empty($_FILES['foto']['name'])) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo   = finfo_open(FILEINFO_MIME_TYPE);
        $mime    = finfo_file($finfo, $_FILES['foto']['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed)) {
            $errors[] = 'Solo se permiten imágenes JPG, PNG o WebP.';
        } elseif ($_FILES['foto']['size'] > UPLOAD_MAX_SIZE) {
            $errors[] = 'La imagen supera el tamaño máximo (20MB).';
        } else {
            $dir = UPLOAD_PATH . 'fotos/' . date('Y/m/');
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext      = uploadExtFromMime($mime) ?? 'bin';
            $fname    = uniqid('cc_') . '.' . $ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $fname);
            $fotoRuta = 'fotos/' . date('Y/m/') . $fname;
        }
    }
    $data['foto_ruta'] = $fotoRuta;

    if ($errors) {
        foreach ($errors as $e) Flash::error($e);
    } else {
        if ($edit) {
            $sets = implode(', ', array_map(fn($k) => "{$k}=?", array_keys($data)));
            DB::run("UPDATE cargas_combustible SET {$sets} WHERE id=? AND empresa_id=?",
                [...array_values($data), $id, $emp]);
            Auth::audit('editar', 'combustible', $id, $r, $data);
            Flash::success('Carga actualizada correctamente.');
        } else {
            $data['empresa_id']  = $emp;
            $data['creado_por']  = Auth::id();
            $cols = implode(', ', array_keys($data));
            $plc  = implode(', ', array_fill(0, count($data), '?'));
            DB::run("INSERT INTO cargas_combustible ({$cols}) VALUES ({$plc})", array_values($data));
            Auth::audit('crear', 'combustible', (int)DB::lastId());
            Flash::success('Carga de combustible registrada.');
        }
        redirect('/modules/combustible/');
    }
}

$pageTitle = $edit ? 'Editar Carga' : 'Nueva Carga de Combustible';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index">Combustible</a></li>
    <li class="breadcrumb-item active"><?= $edit ? 'Editar' : 'Nueva Carga' ?></li>
</ol></nav>

<div class="card form-card" style="max-width:800px">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="fas fa-gas-pump text-warning me-1"></i>
        <strong><?= h($pageTitle) ?></strong>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data" id="formCarga" data-offline="1">
            <?= CSRF::field() ?>

            <!-- TIPO UNIDAD -->
            <div class="mb-4">
                <label class="form-label fw-semibold">¿Qué vas a cargar?</label>
                <div class="d-flex gap-2">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="tipo_unidad" id="tipoVehiculo" value="vehiculo"
                               <?= ($r['tipo_unidad'] ?? 'vehiculo') === 'vehiculo' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tipoVehiculo">
                            <i class="fas fa-truck text-primary me-1"></i>Vehículo
                        </label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="tipo_unidad" id="tipoEquipo" value="equipo"
                               <?= ($r['tipo_unidad'] ?? '') === 'equipo' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tipoEquipo">
                            <i class="fas fa-cogs text-warning me-1"></i>Equipo
                        </label>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <!-- Fecha -->
                <div class="col-md-3">
                    <label class="form-label">Fecha <span class="text-danger">*</span></label>
                    <input type="date" name="fecha" class="form-control" required
                           value="<?= h($r['fecha'] ?? date('Y-m-d')) ?>">
                </div>

                <!-- Obra -->
                <div class="col-md-5">
                    <label class="form-label">Obra</label>
                    <select name="obra_id" class="form-select">
                        <option value="">— Sin obra —</option>
                        <?php foreach ($obras as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= ($r['obra_id'] ?? 0) == $o['id'] ? 'selected' : '' ?>><?= h($o['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Tipo combustible -->
                <div class="col-md-4">
                    <label class="form-label">Tipo Combustible</label>
                    <select name="tipo_combustible" class="form-select">
                        <?php foreach (['Petróleo'=>'Petróleo','Gasolina 93'=>'Gasolina 93','Gasolina 95'=>'Gasolina 95','Gasolina 97'=>'Gasolina 97','Gas'=>'Gas'] as $v => $l): ?>
                            <option value="<?= h($v) ?>" <?= ($r['tipo_combustible'] ?? 'Petróleo') === $v ? 'selected' : '' ?>><?= h($l) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- VEHÍCULO -->
                <div id="secVehiculo" class="col-md-6 campo-vehiculo">
                    <label class="form-label">Patente / Vehículo <span class="text-danger">*</span></label>
                    <select name="vehiculo_id" class="form-select" id="selectVehiculo">
                        <?php if (empty($vehiculos)): ?>
                            <option value="" disabled selected>Sin vehículos — Agregue en Admin → Flota</option>
                        <?php else: ?>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($vehiculos as $v): ?>
                                <option value="<?= $v['id'] ?>" <?= ($r['vehiculo_id'] ?? 0) == $v['id'] ? 'selected' : '' ?>>
                                    <?= h($v['patente']) ?><?= $v['marca'] ? ' — ' . h($v['marca']) . ' ' . h($v['modelo'] ?? '') : '' ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($vehiculos)): ?>
                    <div class="form-text text-danger">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        <a href="<?= APP_URL ?>/modules/admin/maestros/vehiculos?action=create" target="_blank">Agregar vehículo</a> antes de registrar cargas.
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Conductor -->
                <div id="secConductor" class="col-md-6 campo-vehiculo">
                    <label class="form-label">Conductor</label>
                    <select name="conductor_id" class="form-select">
                        <?php if (empty($conductores)): ?>
                            <option value="">Sin conductores registrados</option>
                        <?php else: ?>
                            <option value="">— Opcional —</option>
                            <?php foreach ($conductores as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($r['conductor_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= h($c['nombre']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- EQUIPO -->
                <div id="secEquipo" class="col-md-6 campo-equipo" style="display:none">
                    <label class="form-label">Equipo <span class="text-danger">*</span></label>
                    <select name="equipo_id" class="form-select">
                        <?php if (empty($equipos)): ?>
                            <option value="" disabled selected>Sin equipos — Agregue en Admin → Flota</option>
                        <?php else: ?>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($equipos as $e): ?>
                                <option value="<?= $e['id'] ?>" <?= ($r['equipo_id'] ?? 0) == $e['id'] ? 'selected' : '' ?>>
                                    <?= h($e['codigo']) ?> — <?= h($e['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Operador -->
                <div id="secOperador" class="col-md-6 campo-equipo" style="display:none">
                    <label class="form-label">Operador</label>
                    <select name="operador_id" class="form-select">
                        <?php if (empty($operadores)): ?>
                            <option value="">Sin operadores registrados</option>
                        <?php else: ?>
                            <option value="">— Opcional —</option>
                            <?php foreach ($operadores as $o): ?>
                                <option value="<?= $o['id'] ?>" <?= ($r['operador_id'] ?? 0) == $o['id'] ? 'selected' : '' ?>><?= h($o['nombre']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Litros -->
                <div class="col-md-3">
                    <label class="form-label">Litros <span class="text-danger">*</span></label>
                    <input type="number" name="litros" class="form-control" required min="0.1" step="0.1"
                           value="<?= h($r['litros'] ?? '') ?>" placeholder="0.0">
                </div>

                <!-- Precio litro -->
                <div class="col-md-3">
                    <label class="form-label">Precio / Litro</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="precio_litro" class="form-control" min="0" step="1"
                               value="<?= h($r['precio_litro'] ?? '') ?>" placeholder="Opcional">
                    </div>
                </div>

                <!-- Horómetro (equipo) -->
                <div id="secHorometro" class="col-md-3 campo-equipo" style="display:none">
                    <label class="form-label">Horómetro (hrs)</label>
                    <input type="number" name="horometro" class="form-control" min="0" step="0.1"
                           value="<?= h($r['horometro'] ?? '') ?>" placeholder="0.0">
                </div>

                <!-- Odómetro (vehículo) -->
                <div id="secOdometro" class="col-md-3 campo-vehiculo">
                    <label class="form-label">Odómetro (km)</label>
                    <input type="number" name="odometro" class="form-control" min="0"
                           value="<?= h($r['odometro'] ?? '') ?>" placeholder="km actual">
                </div>

                <!-- Fuente -->
                <div class="col-md-4">
                    <label class="form-label">Fuente</label>
                    <select name="fuente_id" class="form-select" id="selectFuente">
                        <option value="">— Seleccione —</option>
                        <?php foreach ($fuentes as $f): ?>
                            <option value="<?= $f['id'] ?>" data-tipo="<?= h($f['nombre']) ?>"
                                    <?= ($r['fuente_id'] ?? 0) == $f['id'] ? 'selected' : '' ?>><?= h($f['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Estanque (condicional) -->
                <div class="col-md-4" id="secEstanque" style="display:none">
                    <label class="form-label">Estanque</label>
                    <select name="estanque_id" class="form-select">
                        <option value="">— Seleccione —</option>
                        <?php foreach ($estanques as $e): ?>
                            <option value="<?= $e['id'] ?>" <?= ($r['estanque_id'] ?? 0) == $e['id'] ? 'selected' : '' ?>>
                                <?= h($e['nombre']) ?> (<?= h($e['tipo_combustible']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Proveedor -->
                <div class="col-md-4">
                    <label class="form-label">Proveedor</label>
                    <select name="proveedor_id" class="form-select">
                        <option value="">— Opcional —</option>
                        <?php foreach ($proveedores as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($r['proveedor_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>><?= h($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- GPS -->
                <div class="col-12">
                    <label class="form-label">Ubicación GPS</label>
                    <div class="d-flex gap-2 align-items-center flex-wrap">
                        <input type="text" name="lat" id="lat" class="form-control" style="max-width:140px"
                               placeholder="Latitud" value="<?= h($r['lat'] ?? '') ?>">
                        <input type="text" name="lng" id="lng" class="form-control" style="max-width:140px"
                               placeholder="Longitud" value="<?= h($r['lng'] ?? '') ?>">
                        <button type="button" class="btn btn-outline-secondary" id="gpsBtn">
                            <i class="fas fa-location-arrow me-1"></i>Capturar GPS
                        </button>
                    </div>
                </div>

                <!-- Foto -->
                <div class="col-md-6">
                    <label class="form-label">Foto del medidor (opcional)</label>
                    <input type="file" name="foto" class="form-control" accept="image/*" capture="environment" id="fotoInput">
                    <?php if (!empty($r['foto_ruta'])): ?>
                        <div class="mt-2">
                            <small class="text-muted"><i class="fas fa-image me-1"></i>Foto actual guardada</small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Observación -->
                <div class="col-12">
                    <label class="form-label">Observación</label>
                    <textarea name="observacion" class="form-control" rows="2" placeholder="Opcional..."><?= h($r['observacion'] ?? '') ?></textarea>
                </div>
            </div>

            <hr class="my-3">
            <?php
                // Este formulario ya captura coordenadas en sus propios campos,
                // por eso aquí sólo se agrega la firma.
                $firmaVal   = $r['firma_b64'] ?? '';
                $firmaLabel = 'Firma del responsable de la carga';
                $firmaAyuda = 'Operador o conductor que recibe el combustible.';
                $conGps     = false;
                include ROOT . '/includes/firma_gps.php';
            ?>

            <hr class="my-3">
            <div class="d-flex gap-2 flex-wrap form-acciones">
                <button type="submit" class="btn btn-warning fw-semibold">
                    <i class="fas fa-save me-2"></i><?= $edit ? 'Actualizar' : 'Registrar Carga' ?>
                </button>
                <a href="index" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const tipoUVh = document.getElementById('tipoVehiculo');
    const tipoUEq = document.getElementById('tipoEquipo');
    const camposVeh = document.querySelectorAll('.campo-vehiculo');
    const camposEq  = document.querySelectorAll('.campo-equipo');

    function toggleTipo() {
        const isVeh = tipoUVh.checked;
        camposVeh.forEach(el => el.style.display = isVeh ? '' : 'none');
        camposEq.forEach(el  => el.style.display = isVeh ? 'none' : '');
        // Required
        document.getElementById('selectVehiculo').required = isVeh;
    }

    tipoUVh.addEventListener('change', toggleTipo);
    tipoUEq.addEventListener('change', toggleTipo);
    toggleTipo(); // init

    // Fuente → mostrar estanque si es estanque propio
    const selectFuente = document.getElementById('selectFuente');
    const secEstanque  = document.getElementById('secEstanque');
    selectFuente.addEventListener('change', function () {
        const txt = this.options[this.selectedIndex]?.text.toLowerCase() || '';
        secEstanque.style.display = txt.includes('estanque') ? '' : 'none';
    });
    // init
    const fTxt = selectFuente.options[selectFuente.selectedIndex]?.text.toLowerCase() || '';
    if (fTxt.includes('estanque')) secEstanque.style.display = '';

    // GPS
    document.getElementById('gpsBtn').addEventListener('click', function () {
        if (!navigator.geolocation) { alert('GPS no disponible.'); return; }
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Obteniendo...';
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                document.getElementById('lat').value = pos.coords.latitude.toFixed(7);
                document.getElementById('lng').value = pos.coords.longitude.toFixed(7);
                btn.innerHTML = '<i class="fas fa-check text-success me-1"></i>GPS capturado';
                btn.disabled = false;
            },
            function (e) {
                alert('Error GPS: ' + e.message);
                btn.innerHTML = '<i class="fas fa-location-arrow me-1"></i>Capturar GPS';
                btn.disabled = false;
            }
        );
    });

    // Preview foto
    document.getElementById('fotoInput').addEventListener('change', function () {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                let img = document.getElementById('fotoPreview');
                if (!img) {
                    img = document.createElement('img');
                    img.id = 'fotoPreview';
                    img.className = 'mt-2 rounded';
                    img.style.maxHeight = '120px';
                    this.parentNode.appendChild(img);
                }
                img.src = e.target.result;
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
})();
</script>

<?php include ROOT . '/includes/footer.php'; ?>
