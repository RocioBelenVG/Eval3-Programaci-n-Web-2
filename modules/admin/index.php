<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireAdmin();

$pageTitle = 'Tablas Maestras';
include ROOT . '/includes/header.php';

$maestras = [
    ['Empresas y Organización', 'building', [
        ['maestros/empresas.php',       'building',       'Empresas'],
        ['maestros/sucursales.php',     'map-marker-alt', 'Sucursales'],
        ['maestros/clientes.php',       'handshake',      'Clientes'],
    ]],
    ['Obras y Contratos', 'hard-hat', [
        ['maestros/obras.php',          'hard-hat',      'Obras'],
        ['maestros/contratos.php',      'file-contract', 'Contratos'],
        ['maestros/estados_contrato.php','tag',           'Estados Contrato'],
    ]],
    ['Personal', 'users', [
        ['maestros/trabajadores.php',   'id-card',    'Trabajadores'],
        ['maestros/cargos.php',         'briefcase',  'Cargos'],
        ['maestros/conductores.php',    'steering-wheel', 'Conductores'],
        ['maestros/operadores.php',     'hard-hat',   'Operadores'],
    ]],
    ['Flota y Maquinaria', 'truck', [
        ['maestros/vehiculos.php',          'truck',       'Vehículos'],
        ['maestros/categorias_vehiculo.php', 'tags',        'Categorías Vehículo'],
        ['maestros/equipos.php',            'cogs',        'Equipos'],
        ['maestros/marcas_equipo.php',      'certificate', 'Marcas Equipo'],
        ['maestros/modelos_equipo.php',     'list',        'Modelos Equipo'],
        ['maestros/categorias_equipo.php',  'th-list',     'Categorías Equipo'],
    ]],
    ['Combustible', 'gas-pump', [
        ['maestros/estanques.php',              'oil-can',    'Estanques'],
        ['maestros/proveedores_combustible.php','store',       'Proveedores Combustible'],
        ['maestros/fuentes_combustible.php',    'plug',        'Fuentes Combustible'],
    ]],
    ['Áridos', 'mountain', [
        ['maestros/pozos.php',               'digging',    'Pozos'],
        ['maestros/materiales.php',          'layer-group','Materiales'],
        ['maestros/clasificaciones_arido.php','filter',     'Clasificaciones'],
    ]],
    ['Seguridad', 'shield-alt', [
        ['maestros/tipos_checklist.php',  'clipboard-check', 'Tipos Checklist'],
        ['maestros/tipos_inspeccion.php', 'search',          'Tipos Inspección'],
    ]],
    ['Documentación', 'folder-open', [
        ['maestros/tipos_documento.php',   'file-alt', 'Tipos Documento'],
        ['maestros/estados_documento.php', 'tag',      'Estados Documento'],
    ]],
];
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/dashboard/">Dashboard</a></li>
    <li class="breadcrumb-item active">Tablas Maestras</li>
</ol></nav>

<div class="alert alert-info d-flex align-items-center gap-3 mb-4 py-2">
    <i class="fas fa-sliders-h fa-lg"></i>
    <div>
        <strong>¿Primera vez configurando el sistema?</strong>
        Use <a href="<?= APP_URL ?>/modules/admin/empresa_setup" class="fw-bold">Configuración de Empresa</a>
        para ver qué datos faltan y agregarlos en el orden correcto.
    </div>
</div>

<div class="row g-4">
<?php foreach ($maestras as [$titulo, $icon, $items]): ?>
<div class="col-md-6 col-xl-4">
    <div class="card table-card h-100">
        <div class="card-header">
            <i class="fas fa-<?= $icon ?> text-primary me-2"></i>
            <strong><?= h($titulo) ?></strong>
        </div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
            <?php foreach ($items as [$href, $ico, $lbl]): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                    <span><i class="fas fa-<?= $ico ?> text-muted me-2" style="width:16px"></i><?= h($lbl) ?></span>
                    <a href="<?= APP_URL ?>/modules/admin/<?= $href ?>" class="btn btn-sm btn-outline-primary btn-action">
                        <i class="fas fa-arrow-right"></i>
                    </a>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
