<?php
if (!defined('ROOT')) require_once dirname(__DIR__) . '/bootstrap.php';
$pageTitle = 'Acceso Denegado';
include ROOT . '/includes/header.php';
?>
<div class="text-center py-5">
    <i class="fas fa-lock fa-4x text-danger mb-3"></i>
    <h3 class="fw-bold">Acceso Denegado</h3>
    <p class="text-muted">No tiene permisos para acceder a esta sección.</p>
    <a href="<?= APP_URL ?>/modules/dashboard/" class="btn btn-primary">
        <i class="fas fa-home me-2"></i>Volver al Dashboard
    </a>
</div>
<?php include ROOT . '/includes/footer.php'; ?>
