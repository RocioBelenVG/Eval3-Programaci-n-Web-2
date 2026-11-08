<?php
// Requiere: $pageTitle (string)
if (!isset($pageTitle)) $pageTitle = APP_NAME;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($pageTitle) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.0/css/all.min.css" integrity="sha384-/o6I2CkkWC//PSjvWC/eYN7l3xM3tJm8ZzVkCOfp//W05QcE3mlGskpoHB6XqI+B" crossorigin="anonymous">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css?v=5">
    <meta name="csrf-token" content="<?= CSRF::token() ?>">

    <!-- Aplicación instalable (PWA): permite usarla en terreno sin señal -->
    <link rel="manifest" href="<?= APP_URL ?>/manifest.webmanifest">
    <meta name="theme-color" content="#0f172a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="TriTransportes">
    <link rel="icon" type="image/svg+xml" href="<?= APP_URL ?>/assets/img/icono.svg">
    <link rel="apple-touch-icon" href="<?= APP_URL ?>/assets/img/icono.svg">
</head>
<body>

<!-- SIDEBAR -->
<?php include ROOT . '/includes/sidebar.php'; ?>

<!-- MAIN WRAPPER -->
<div class="main-content" id="mainContent">

    <!-- TOP NAVBAR -->
    <nav class="top-navbar navbar navbar-expand navbar-light bg-white border-bottom px-3 py-2">
        <button class="btn btn-sm btn-outline-secondary me-3 d-md-none" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <span class="fw-semibold text-dark"><?= h($pageTitle) ?></span>
        <div class="ms-auto d-flex align-items-center gap-3">
            <?php
                $empresasDisp = Auth::empresasDisponibles();
                $puedeCambiar = Auth::canSwitchEmpresa() && count($empresasDisp) > 1;
                $empActiva    = Auth::empresaNombre();
            ?>
            <?php if ($puedeCambiar): ?>
            <div class="dropdown">
                <button class="btn btn-sm btn-primary-subtle text-primary border border-primary-subtle dropdown-toggle fw-semibold"
                        type="button" data-bs-toggle="dropdown" title="Cambiar empresa activa">
                    <i class="fas fa-building me-1"></i><?= h($empActiva) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow" style="max-height:340px;overflow:auto">
                    <li><h6 class="dropdown-header"><i class="fas fa-layer-group me-1"></i>Empresas</h6></li>
                    <?php foreach ($empresasDisp as $e):
                        $esActiva = ((int)$e['id'] === Auth::empresaId()); ?>
                        <li>
                            <a class="dropdown-item d-flex justify-content-between align-items-center <?= $esActiva ? 'active' : '' ?>"
                               href="<?= APP_URL ?>/modules/admin/switch_empresa?empresa_id=<?= (int)$e['id'] ?>">
                                <span><i class="fas fa-building me-2 text-muted"></i><?= h($e['razon_social']) ?></span>
                                <?php if ($esActiva): ?><i class="fas fa-check text-success ms-2"></i><?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if (Auth::isAdmin()): ?>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item small text-muted" href="<?= APP_URL ?>/modules/admin/maestros/empresas?action=create">
                        <i class="fas fa-plus me-2"></i>Crear nueva empresa</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php else: ?>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                <i class="fas fa-building me-1"></i><?= h($empActiva) ?>
            </span>
            <?php endif; ?>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle me-1"></i><?= h(Auth::user()['nombre'] ?? '') ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><span class="dropdown-item-text small text-muted"><?= h(Auth::rolNombre()) ?></span></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="<?= APP_URL ?>/logout">
                            <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- PAGE CONTENT -->
    <div class="page-content p-4">
        <?= Flash::render() ?>
