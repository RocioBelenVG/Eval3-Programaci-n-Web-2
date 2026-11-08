<?php
/**
 * Conmutador de Empresa Activa (Multiempresa)
 * Cambia la empresa sobre la que operan TODOS los módulos.
 */
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::require();

$destino = APP_URL . '/modules/dashboard/';

// Soporta GET (?empresa_id=) y POST con CSRF.
if (isPost()) {
    CSRF::require();
    $empresaId = postInt('empresa_id');
    $destino   = post('redirect') ?: $destino;
} else {
    $empresaId = getInt('empresa_id');
}

if ($empresaId > 0 && Auth::setActiveEmpresa($empresaId)) {
    Flash::success('Empresa activa: ' . Auth::empresaNombre());
} else {
    Flash::error('No fue posible cambiar de empresa. Verifique que esté activa y que tenga permisos.');
}

// Evitar open redirect: solo rutas internas.
if (!str_starts_with($destino, APP_URL . '/') && !str_starts_with($destino, '/')) {
    $destino = APP_URL . '/modules/dashboard/';
}
redirect(str_replace(APP_URL, '', $destino) ?: '/modules/dashboard/');
