<?php
$uri = $_SERVER['REQUEST_URI'] ?? '';

/**
 * Enlace del menú. $excluir permite que una sección con sub-páginas propias
 * (p. ej. Combustible → Rendimiento) no quede marcada al estar en la hija.
 */
function navLink(string $href, string $icon, string $label, string $uri, array $excluir = []): string {
    $active = strpos($uri, $href) !== false;
    foreach ($excluir as $ex) {
        if (strpos($uri, $ex) !== false) { $active = false; break; }
    }
    $cls = $active ? ' active' : '';
    return "<li class=\"nav-item\"><a class=\"nav-link{$cls}\" href=\"" . APP_URL . $href . "\">"
         . "<i class=\"fas fa-{$icon} nav-icon\"></i><span>{$label}</span></a></li>";
}

function navSection(string $label): string {
    return "<li class=\"nav-section\">{$label}</li>";
}
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <a href="<?= APP_URL ?>/modules/dashboard/" class="brand-link text-decoration-none d-block text-center py-1">
            <span style="font-size:1.15rem; font-weight:900; letter-spacing:.08em; color:#f59e0b; font-family:'Segoe UI',sans-serif;">TRITRANSPORTES</span>
        </a>
    </div>

    <div class="sidebar-user">
        <div class="user-avatar">
            <i class="fas fa-user-circle fa-2x text-secondary"></i>
        </div>
        <div class="user-info">
            <div class="user-name"><?= h(Auth::user()['nombre'] ?? 'Usuario') ?></div>
            <div class="user-role"><?= h(Auth::rolNombre()) ?></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">

            <?= navLink('/modules/dashboard/', 'tachometer-alt', 'Dashboard', $uri) ?>
            <?= navLink('/modules/admin/pdf_manual', 'book-open', 'Ayuda / Manual', $uri) ?>

            <?= navSection('OPERACIONES') ?>
            <?php if (Auth::can('faena')): ?>
                <?= navLink('/modules/faena/', 'hard-hat', 'Partes de Faena', $uri) ?>
            <?php endif; ?>
            <?php if (Auth::can('combustible')): ?>
                <?= navLink('/modules/combustible/', 'gas-pump', 'Combustible', $uri, ['/modules/combustible/rendimiento']) ?>
                <?= navLink('/modules/combustible/rendimiento', 'gauge-high', 'Rendimiento Activos', $uri) ?>
            <?php endif; ?>
            <?php if (Auth::can('maquinaria')): ?>
                <?= navLink('/modules/maquinaria/', 'cogs', 'Maquinaria', $uri) ?>
            <?php endif; ?>
            <?php if (Auth::can('transporte')): ?>
                <?= navLink('/modules/transporte/', 'truck', 'Transporte', $uri) ?>
            <?php endif; ?>
            <?php if (Auth::can('aridos')): ?>
                <?= navLink('/modules/aridos/', 'mountain', 'Áridos', $uri) ?>
            <?php endif; ?>

            <?= navSection('GESTIÓN') ?>
            <?php if (Auth::can('contratos')): ?>
                <?= navLink('/modules/contratos/', 'file-contract', 'Contratos', $uri) ?>
            <?php endif; ?>
            <?php if (Auth::can('documentos')): ?>
                <?= navLink('/modules/documentos/', 'folder-open', 'Documentos', $uri) ?>
            <?php endif; ?>
            <?php if (Auth::can('seguridad')): ?>
                <?= navLink('/modules/seguridad/', 'shield-alt', 'Seguridad', $uri) ?>
            <?php endif; ?>

            <?php if (Auth::isAdmin()): ?>
                <?= navSection('ADMINISTRACIÓN') ?>
                <?= navLink('/modules/admin/empresa_setup',        'sliders-h',      'Configuración Empresa', $uri) ?>
                <?= navLink('/modules/admin/usuarios',             'users',          'Usuarios',              $uri) ?>
                <?= navLink('/modules/admin/roles',                'user-tag',       'Roles',                 $uri) ?>
                <?= navLink('/modules/admin/permisos',             'lock',           'Permisos',              $uri) ?>
                <?= navLink('/modules/admin/',                'database',       'Tablas Maestras',       $uri) ?>

                <?= navSection('PERSONAL') ?>
                <?= navLink('/modules/admin/maestros/trabajadores','id-card',        'Trabajadores',          $uri) ?>
                <?= navLink('/modules/admin/maestros/conductores', 'car',            'Conductores',           $uri) ?>
                <?= navLink('/modules/admin/maestros/operadores',  'user-cog',       'Operadores',            $uri) ?>

                <?= navSection('FLOTA') ?>
                <?= navLink('/modules/admin/maestros/vehiculos',   'truck-pickup',   'Vehículos',             $uri) ?>
                <?= navLink('/modules/admin/maestros/equipos',     'cogs',           'Equipos',               $uri) ?>
            <?php endif; ?>

        </ul>
    </nav>

    <div class="sidebar-footer">
        <small class="text-muted"><?= APP_NAME ?> v<?= APP_VERSION ?></small>
    </div>
</div>
<div class="sidebar-overlay d-md-none" id="sidebarOverlay"></div>
