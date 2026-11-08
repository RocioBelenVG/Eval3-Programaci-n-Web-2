<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requirePermiso('transporte', 'crear');
CSRF::require();

$emp  = Auth::empresaId();
$id   = getInt('id');
$edit = $id > 0;
$r    = [];

if ($edit) {
    $r = DB::one("SELECT v.* FROM viajes v WHERE v.id=? AND v.empresa_id=?", [$id, $emp]);
    if (!$r) { Flash::error('Viaje no encontrado.'); redirect('/modules/transporte/'); }
}

if (isPost()) {
    $data = [
        'obra_id'       => postInt('obra_id')      ?: null,
        'contrato_id'   => postInt('contrato_id')  ?: null,
        'vehiculo_id'   => postInt('vehiculo_id'),
        'conductor_id'  => postInt('conductor_id'),
        'fecha'         => post('fecha', date('Y-m-d')),
        'hora_salida'   => post('hora_salida')     ?: null,
        'hora_llegada'  => post('hora_llegada')    ?: null,
        'origen'        => post('origen')          ?: null,
        'destino'       => post('destino')         ?: null,
        'km_inicio'     => postInt('km_inicio'),
        'km_fin'        => postInt('km_fin'),
        'material_id'   => postInt('material_id')  ?: null,
        'cantidad'      => postFloat('cantidad')   ?: null,
        'unidad'        => post('unidad')          ?: null,
        'estado'        => post('estado', 'programado'),
        'observaciones' => post('observaciones')   ?: null,
        'lat_origen'    => postFloat('lat_origen') ?: null,
        'lng_origen'    => postFloat('lng_origen') ?: null,
    ];

    $errors = [];
    if (!$data['vehiculo_id'])  $errors[] = 'Seleccione un vehículo.';
    if (!$data['conductor_id']) $errors[] = 'Seleccione un conductor.';
    if (!$data['fecha'])        $errors[] = 'La fecha es obligatoria.';
    if ($data['km_fin'] > 0 && $data['km_fin'] < $data['km_inicio']) $errors[] = 'Km final no puede ser menor al inicial.';

    if ($errors) {
        foreach ($errors as $e) Flash::error($e);
    } else {
        if ($edit) {
            $sets = implode(', ', array_map(fn($k) => "{$k}=?", array_keys($data)));
            DB::run("UPDATE viajes SET {$sets} WHERE id=? AND empresa_id=?", [...array_values($data), $id, $emp]);
            Auth::audit('editar', 'transporte', $id);
            Flash::success('Viaje actualizado.');
        } else {
            $data['empresa_id'] = $emp;
            $data['creado_por'] = Auth::id();
            $cols = implode(', ', array_keys($data));
            $plc  = implode(', ', array_fill(0, count($data), '?'));
            DB::run("INSERT INTO viajes ({$cols}) VALUES ({$plc})", array_values($data));
            Auth::audit('crear', 'transporte', (int)DB::lastId());
            Flash::success('Viaje registrado correctamente.');
        }
        redirect('/modules/transporte/');
    }
}

$vehiculos  = DB::all("SELECT v.id, v.patente, v.marca FROM vehiculos v WHERE v.empresa_id=? AND v.activo=1 ORDER BY v.patente", [$emp]);
$conductores= DB::all("SELECT c.id, CONCAT(t.nombre,' ',t.apellido) AS nombre FROM conductores c INNER JOIN trabajadores t ON t.id=c.trabajador_id WHERE t.empresa_id=? AND c.activo=1 ORDER BY t.apellido", [$emp]);
$obras      = DB::all("SELECT id, nombre FROM obras WHERE empresa_id=? AND activo=1 ORDER BY nombre", [$emp]);
$contratos  = DB::all("SELECT id, numero AS codigo, COALESCE(descripcion, numero, 'Sin descripción') AS nombre FROM contratos WHERE empresa_id=? AND activo=1 ORDER BY numero", [$emp]);
$materiales = DB::all("SELECT id, nombre FROM materiales WHERE activo=1 ORDER BY nombre", []);

$pageTitle = $edit ? 'Editar Viaje' : 'Registrar Viaje';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="index">Transporte</a></li>
    <li class="breadcrumb-item active"><?= h($pageTitle) ?></li>
</ol></nav>

<div class="card form-card" style="max-width:750px">
    <div class="card-header">
        <strong><i class="fas fa-truck text-primary me-2"></i><?= h($pageTitle) ?></strong>
    </div>
    <div class="card-body">
        <form method="POST" data-offline="1">
            <?= CSRF::field() ?>
            <input type="hidden" name="lat_origen" id="fLat" value="<?= h($r['lat_origen'] ?? '') ?>">
            <input type="hidden" name="lng_origen" id="fLng" value="<?= h($r['lng_origen'] ?? '') ?>">

            <div class="row g-3">
                <!-- Fecha y estado -->
                <div class="col-md-4">
                    <label class="form-label">Fecha <span class="text-danger">*</span></label>
                    <input type="date" name="fecha" class="form-control" required value="<?= h($r['fecha'] ?? date('Y-m-d')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Hora Salida</label>
                    <input type="time" name="hora_salida" class="form-control" value="<?= h($r['hora_salida'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Hora Llegada</label>
                    <input type="time" name="hora_llegada" class="form-control" value="<?= h($r['hora_llegada'] ?? '') ?>">
                </div>

                <!-- Vehículo y conductor -->
                <div class="col-md-6">
                    <label class="form-label">Vehículo <span class="text-danger">*</span></label>
                    <select name="vehiculo_id" class="form-select" <?= empty($vehiculos) ? '' : 'required' ?>>
                        <?php if (empty($vehiculos)): ?>
                            <option value="" disabled selected>Sin vehículos — Agregue en Admin → Flota</option>
                        <?php else: ?>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($vehiculos as $v): ?>
                                <option value="<?= $v['id'] ?>" <?= ($r['vehiculo_id'] ?? '') == $v['id'] ? 'selected' : '' ?>>
                                    <?= h($v['patente'] . ' — ' . $v['marca']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($vehiculos)): ?>
                    <div class="form-text text-danger"><i class="fas fa-exclamation-triangle me-1"></i>
                        <a href="<?= APP_URL ?>/modules/admin/maestros/vehiculos?action=create" target="_blank">Agregar vehículo</a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Conductor <span class="text-danger">*</span></label>
                    <select name="conductor_id" class="form-select" <?= empty($conductores) ? '' : 'required' ?>>
                        <?php if (empty($conductores)): ?>
                            <option value="" disabled selected>Sin conductores — Agregue en Admin → Personal</option>
                        <?php else: ?>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($conductores as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($r['conductor_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                                    <?= h($c['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if (empty($conductores)): ?>
                    <div class="form-text text-danger"><i class="fas fa-exclamation-triangle me-1"></i>
                        <a href="<?= APP_URL ?>/modules/admin/maestros/conductores?action=create" target="_blank">Agregar conductor</a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Origen y destino -->
                <div class="col-md-5">
                    <label class="form-label">Origen</label>
                    <div class="input-group">
                        <input type="text" name="origen" class="form-control" maxlength="200"
                               value="<?= h($r['origen'] ?? '') ?>" placeholder="Ubicación de origen">
                        <button type="button" class="btn btn-outline-secondary" id="btnGps" title="Capturar GPS">
                            <i class="fas fa-map-marker-alt"></i>
                        </button>
                    </div>
                    <div id="gpsStatus" class="form-text"></div>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Destino</label>
                    <input type="text" name="destino" class="form-control" maxlength="200"
                           value="<?= h($r['destino'] ?? '') ?>" placeholder="Ubicación de destino">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <?php foreach (['programado'=>'Programado','en_ruta'=>'En Ruta','completado'=>'Completado','cancelado'=>'Cancelado'] as $v => $l): ?>
                            <option value="<?= $v ?>" <?= ($r['estado'] ?? 'programado') === $v ? 'selected' : '' ?>><?= $l ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Odómetro -->
                <div class="col-md-3">
                    <label class="form-label">Km Inicio</label>
                    <input type="number" name="km_inicio" class="form-control" min="0"
                           value="<?= h($r['km_inicio'] ?? '0') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Km Final</label>
                    <input type="number" name="km_fin" class="form-control" min="0"
                           value="<?= h($r['km_fin'] ?? '0') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Obra</label>
                    <select name="obra_id" class="form-select">
                        <option value="">— Sin obra —</option>
                        <?php foreach ($obras as $o): ?>
                            <option value="<?= $o['id'] ?>" <?= ($r['obra_id'] ?? '') == $o['id'] ? 'selected' : '' ?>>
                                <?= h($o['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Carga -->
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

                <div class="col-12">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="2" maxlength="500"><?= h($r['observaciones'] ?? '') ?></textarea>
                </div>
            </div>

            <hr class="my-3">
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i><?= $edit ? 'Actualizar' : 'Registrar Viaje' ?>
                </button>
                <a href="index" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('btnGps').addEventListener('click', function () {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    navigator.geolocation.getCurrentPosition(
        pos => {
            document.getElementById('fLat').value = pos.coords.latitude.toFixed(7);
            document.getElementById('fLng').value = pos.coords.longitude.toFixed(7);
            document.getElementById('gpsStatus').innerHTML =
                '<span class="text-success"><i class="fas fa-check me-1"></i>' +
                pos.coords.latitude.toFixed(5) + ', ' + pos.coords.longitude.toFixed(5) + '</span>';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check text-success"></i>';
        },
        err => {
            document.getElementById('gpsStatus').innerHTML = '<span class="text-danger">No disponible</span>';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-map-marker-alt"></i>';
        },
        { enableHighAccuracy: true, timeout: 10000 }
    );
});
</script>

<?php include ROOT . '/includes/footer.php'; ?>
