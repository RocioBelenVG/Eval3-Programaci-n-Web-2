<?php
/**
 * TriTransportes — Bloque reutilizable de Firma digital + Ubicación GPS
 * ----------------------------------------------------------------------------
 * Se incluye dentro de cualquier formulario para capturar, desde el teléfono en
 * terreno, la firma del responsable y las coordenadas del registro.
 *
 * Variables opcionales antes del include:
 *   $firmaVal    valor actual de la firma (data URL) para modo edición
 *   $latVal, $lngVal   coordenadas ya guardadas
 *   $firmaLabel  título del recuadro de firma  (por defecto "Firma")
 *   $firmaAyuda  texto de ayuda bajo el título
 *   $conFirma    false para mostrar sólo la ubicación
 *   $conGps      false para mostrar sólo la firma
 */

$firmaVal   = $firmaVal   ?? '';
$latVal     = $latVal     ?? '';
$lngVal     = $lngVal     ?? '';
$firmaLabel = $firmaLabel ?? 'Firma';
$firmaAyuda = $firmaAyuda ?? 'Firme con el dedo sobre el recuadro.';
$conFirma   = $conFirma   ?? true;
$conGps     = $conGps     ?? true;
?>

<div class="row g-3 firma-gps-bloque">

    <?php if ($conFirma): ?>
    <div class="col-12 col-lg-<?= $conGps ? '7' : '12' ?>">
        <label class="form-label"><i class="fas fa-signature me-1 text-warning"></i><?= h($firmaLabel) ?></label>
        <div class="firma-caja" data-firma>
            <canvas class="firma-canvas" height="170"></canvas>
            <div class="firma-placeholder">
                <i class="fas fa-pen-nib"></i>
                <span><?= h($firmaAyuda) ?></span>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary firma-limpiar">
                <i class="fas fa-eraser me-1"></i>Borrar
            </button>
            <input type="hidden" name="firma_b64" class="firma-input" value="<?= h($firmaVal) ?>">
        </div>
    </div>
    <?php endif; ?>

    <?php if ($conGps): ?>
    <div class="col-12 col-lg-<?= $conFirma ? '5' : '12' ?>">
        <label class="form-label"><i class="fas fa-location-dot me-1 text-danger"></i>Ubicación del registro</label>
        <div class="gps-caja" data-gps>
            <div class="gps-estado">
                <?php if ($latVal && $lngVal): ?>
                    <i class="fas fa-circle-check text-success"></i>
                    <span class="gps-texto">Ubicación registrada</span>
                <?php else: ?>
                    <i class="fas fa-location-crosshairs text-muted"></i>
                    <span class="gps-texto">Sin ubicación capturada</span>
                <?php endif; ?>
            </div>
            <div class="gps-coords <?= ($latVal && $lngVal) ? '' : 'd-none' ?>">
                <span class="gps-lat"><?= h($latVal) ?></span>, <span class="gps-lng"><?= h($lngVal) ?></span>
                <a class="gps-mapa ms-2" target="_blank" rel="noopener"
                   href="https://www.google.com/maps?q=<?= h($latVal) ?>,<?= h($lngVal) ?>">
                    <i class="fas fa-map-location-dot me-1"></i>Ver en mapa
                </a>
            </div>
            <button type="button" class="btn btn-sm btn-outline-danger gps-capturar mt-2">
                <i class="fas fa-location-crosshairs me-1"></i>Capturar mi ubicación
            </button>
            <input type="hidden" name="lat" class="gps-input-lat" value="<?= h($latVal) ?>">
            <input type="hidden" name="lng" class="gps-input-lng" value="<?= h($lngVal) ?>">
        </div>
    </div>
    <?php endif; ?>

</div>
