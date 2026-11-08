<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::require();
$pageTitle    = 'Manual de Usuario';
$empresaNom   = Auth::empresaNombre();
$sistemaNom   = defined('APP_NAME') ? APP_NAME : 'TriTransportes';
$version      = defined('APP_VERSION') ? APP_VERSION : '1.0';
include ROOT . '/includes/header.php';
?>
<style>
/* ── MANUAL DE USUARIO — Estilo profesional ───────────────── */
.man-doc { max-width: 1100px; }
.man-cover {
    background: linear-gradient(135deg,#0f172a 0%,#1e293b 100%);
    border:1px solid #1e293b; border-radius:14px;
    padding:2.6rem 2.6rem 2.2rem; margin-bottom:1.75rem; position:relative; overflow:hidden;
}
.man-cover::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:linear-gradient(90deg,#f59e0b,#d97706); }
.man-cover .man-company { color:#f59e0b; font-size:.78rem; font-weight:800; letter-spacing:.18em; text-transform:uppercase; }
.man-cover .man-system  { color:#fff; font-size:2.1rem; font-weight:900; letter-spacing:.04em; margin:.15rem 0 .1rem; }
.man-cover .man-doctype { color:#cbd5e1; font-size:1rem; font-weight:500; }
.man-cover .man-rule { width:46px; height:3px; background:#f59e0b; border-radius:3px; margin:1rem 0; }
.man-cover .man-meta { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.4rem; }
.man-cover .man-meta span { background:rgba(255,255,255,.07); color:#cbd5e1; border:1px solid rgba(255,255,255,.08); border-radius:6px; padding:4px 12px; font-size:.76rem; }

.man-index { position:sticky; top:70px; border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; }
.man-index .idx-head { background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:.7rem 1rem; font-size:.7rem; font-weight:800; text-transform:uppercase; letter-spacing:.12em; color:#64748b; }
.man-index a { display:block; border-left:3px solid transparent; font-size:.82rem; color:#475569; padding:.5rem 1rem; text-decoration:none; transition:all .15s; }
.man-index a:hover { background:#fffbeb; border-left-color:#f59e0b; color:#0f172a; }
.man-index a.active { background:#fef3c7; border-left-color:#f59e0b; color:#92400e; font-weight:700; }
.man-index .idx-group { font-size:.64rem; font-weight:800; text-transform:uppercase; letter-spacing:.12em; color:#94a3b8; padding:.7rem 1rem .2rem; }

.man-sec { border:1px solid #e2e8f0; border-radius:12px; overflow:hidden; margin-bottom:1.4rem; background:#fff; }
.man-sec > .sec-head { background:#0f172a; color:#fff; padding:.85rem 1.3rem; display:flex; align-items:center; gap:.7rem; }
.man-sec > .sec-head .sec-no { color:#f59e0b; font-weight:900; font-size:.95rem; }
.man-sec > .sec-head h2 { margin:0; font-size:.98rem; font-weight:700; }
.man-sec > .sec-body { padding:1.35rem 1.5rem; }
.man-sec h3 { font-size:.86rem; font-weight:800; color:#0f172a; margin:1rem 0 .55rem; }
.man-sec h3:first-child { margin-top:0; }
.man-sec p { font-size:.875rem; color:#334155; margin-bottom:.7rem; }

.man-steps { list-style:none; counter-reset:s; padding:0; margin:0 0 .4rem; }
.man-steps li { counter-increment:s; display:flex; gap:.75rem; align-items:flex-start; padding:.45rem 0; border-bottom:1px solid #f1f5f9; font-size:.86rem; color:#334155; }
.man-steps li:last-child { border-bottom:none; }
.man-steps li::before { content:counter(s); flex-shrink:0; width:22px; height:22px; border-radius:50%; background:#f59e0b; color:#0f172a; font-weight:900; font-size:.72rem; display:flex; align-items:center; justify-content:center; margin-top:1px; }

.man-note { border-radius:9px; padding:.75rem .95rem; margin:.6rem 0; font-size:.82rem; display:flex; gap:.55rem; align-items:flex-start; }
.man-note i { margin-top:2px; flex-shrink:0; }
.man-note.i  { background:#eff6ff; border-left:3px solid #3b82f6; color:#1e40af; }
.man-note.w  { background:#fffbeb; border-left:3px solid #f59e0b; color:#92400e; }
.man-note.s  { background:#f0fdf4; border-left:3px solid #22c55e; color:#166534; }
.man-note.d  { background:#fef2f2; border-left:3px solid #ef4444; color:#991b1b; }

.man-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); gap:.6rem; margin:.4rem 0; }
.man-card { background:#f8fafc; border:1px solid #eef2f7; border-radius:9px; padding:.7rem .9rem; }
.man-card strong { display:block; font-size:.8rem; color:#0f172a; }
.man-card small { color:#64748b; font-size:.73rem; }

.man-table { width:100%; font-size:.82rem; border-collapse:collapse; }
.man-table thead th { background:#0f172a; color:#f59e0b; text-align:left; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; padding:.55rem .85rem; }
.man-table tbody td { padding:.5rem .85rem; border-bottom:1px solid #f1f5f9; color:#334155; vertical-align:top; }
.man-table tbody tr:nth-child(even) { background:#f8fafc; }

@media print {
    .sidebar,.top-navbar,#chatbot-toggle,#chatbot-panel,nav[aria-label="breadcrumb"],.man-index-col,.btn { display:none !important; }
    .main-content { margin-left:0 !important; }
    .man-body-col { width:100% !important; flex:0 0 100%; max-width:100%; }
    .man-sec { break-inside:avoid; border-color:#cbd5e1; }
}
</style>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/dashboard/">Dashboard</a></li>
    <li class="breadcrumb-item active">Manual de Usuario</li>
</ol></nav>

<div class="man-doc mx-auto">

    <!-- PORTADA (sin logo) -->
    <div class="man-cover">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <div class="man-company"><?= h($empresaNom ?: 'Empresa') ?></div>
                <div class="man-system"><?= h($sistemaNom) ?></div>
                <div class="man-doctype">Manual de Usuario</div>
                <div class="man-rule"></div>
                <div class="man-meta">
                    <span><i class="fas fa-code-branch me-1"></i>Versión <?= h($version) ?></span>
                    <span><i class="fas fa-layer-group me-1"></i>Sistema Multiempresa</span>
                    <span><i class="fas fa-calendar-day me-1"></i><?= date('d/m/Y') ?></span>
                </div>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= APP_URL ?>/modules/admin/pdf_manual" target="_blank" class="btn btn-warning fw-semibold btn-sm">
                    <i class="fas fa-file-pdf me-1"></i>Descargar PDF
                </a>
                <button onclick="window.print()" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-print me-1"></i>Imprimir
                </button>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- ÍNDICE -->
        <div class="col-lg-3 man-index-col">
            <div class="man-index">
                <div class="idx-head">Contenido</div>
                <nav id="manIdx">
                    <a href="#s1">1. Introducción</a>
                    <a href="#s2">2. Acceso y Roles</a>
                    <a href="#s3">3. Multiempresa</a>
                    <a href="#s4">4. Dashboard</a>
                    <div class="idx-group">Módulos</div>
                    <a href="#s5">5. Partes de Faena</a>
                    <a href="#s6">6. Combustible</a>
                    <a href="#s7">7. Maquinaria</a>
                    <a href="#s8">8. Transporte</a>
                    <a href="#s9">9. Áridos</a>
                    <a href="#s10">10. Contratos</a>
                    <a href="#s11">11. Documentos</a>
                    <a href="#s12">12. Seguridad</a>
                    <div class="idx-group">Administración</div>
                    <a href="#s13">13. Datos Maestros</a>
                    <a href="#s14">14. Usuarios y Permisos</a>
                    <a href="#s15">15. Asistente IA</a>
                    <a href="#s16">16. Preguntas Frecuentes</a>
                </nav>
            </div>
        </div>

        <!-- CONTENIDO -->
        <div class="col-lg-9 man-body-col">

            <!-- 1 -->
            <section class="man-sec" id="s1">
                <div class="sec-head"><span class="sec-no">1</span><h2>Introducción al Sistema</h2></div>
                <div class="sec-body">
                    <p><strong><?= h($sistemaNom) ?></strong> es la plataforma de gestión operacional utilizada por <strong><?= h($empresaNom ?: 'la empresa') ?></strong> para centralizar y controlar sus operaciones diarias: faenas, combustible, maquinaria, transporte, producción de áridos, contratos, documentación y seguridad.</p>
                    <p>El sistema es <strong>multiempresa</strong>: una misma instalación puede administrar varias empresas de forma independiente, manteniendo separados sus datos, personal, flota y obras.</p>
                    <div class="man-grid">
                        <div class="man-card"><strong>8 módulos integrados</strong><small>Toda la operación conectada en un solo lugar.</small></div>
                        <div class="man-card"><strong>Multiempresa</strong><small>Gestione varias empresas desde una cuenta.</small></div>
                        <div class="man-card"><strong>Roles y permisos</strong><small>Acceso controlado según el perfil del usuario.</small></div>
                        <div class="man-card"><strong>Asistente IA</strong><small>Ayuda contextual integrada en todas las pantallas.</small></div>
                    </div>
                    <div class="man-note i"><i class="fas fa-circle-info"></i><div>El menú lateral izquierdo agrupa todos los módulos. Lo que cada usuario ve depende de su rol y de los permisos asignados.</div></div>
                </div>
            </section>

            <!-- 2 -->
            <section class="man-sec" id="s2">
                <div class="sec-head"><span class="sec-no">2</span><h2>Acceso al Sistema y Roles</h2></div>
                <div class="sec-body">
                    <h3>Inicio de sesión</h3>
                    <ol class="man-steps">
                        <li>Abra el sistema en su navegador (Chrome, Edge, Firefox o Safari).</li>
                        <li>Ingrese su <strong>correo electrónico</strong> y su <strong>contraseña</strong>.</li>
                        <li>Presione <strong>Ingresar</strong>: será dirigido al Dashboard de su empresa.</li>
                    </ol>
                    <div class="man-note w"><i class="fas fa-triangle-exclamation"></i><div>Por seguridad, la sesión se cierra automáticamente tras 30 minutos de inactividad. Cambie su contraseña inicial en el primer ingreso.</div></div>
                    <h3>Roles del sistema</h3>
                    <p>El rol determina a qué módulos y acciones accede cada usuario:</p>
                    <table class="man-table">
                        <thead><tr><th>Rol</th><th>Alcance</th></tr></thead>
                        <tbody>
                            <tr><td>Administrador General</td><td>Acceso total + cambio entre empresas</td></tr>
                            <tr><td>Jefe de Operaciones</td><td>Gestión operativa completa</td></tr>
                            <tr><td>Supervisor</td><td>Faena, transporte y maquinaria</td></tr>
                            <tr><td>Prevención de Riesgos</td><td>Seguridad y documentos</td></tr>
                            <tr><td>Operador / Conductor</td><td>Registro de sus propias actividades</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- 3 -->
            <section class="man-sec" id="s3">
                <div class="sec-head"><span class="sec-no">3</span><h2>Funcionamiento Multiempresa</h2></div>
                <div class="sec-body">
                    <p>El sistema permite administrar <strong>varias empresas</strong> dentro de una misma instalación. Cada empresa tiene su propio personal, flota, obras, contratos y registros. Todos los módulos muestran y guardan información de la <strong>empresa activa</strong>.</p>
                    <h3>Crear una nueva empresa</h3>
                    <ol class="man-steps">
                        <li>En la barra superior, abra el selector de empresa (botón con el nombre de la empresa actual).</li>
                        <li>Elija <strong>«Crear nueva empresa»</strong>, o vaya a <em>Administración → Datos Maestros → Empresas → Nueva</em>.</li>
                        <li>Complete razón social, RUT, giro y datos de contacto, y guarde.</li>
                        <li>La nueva empresa queda disponible de inmediato en el selector.</li>
                    </ol>
                    <h3>Cambiar de empresa activa</h3>
                    <ol class="man-steps">
                        <li>Haga clic en el <strong>selector de empresa</strong> (esquina superior derecha).</li>
                        <li>Seleccione la empresa con la que desea trabajar; la activa se marca con un visto verde.</li>
                        <li>Al elegirla, <strong>todos los módulos se actualizan automáticamente</strong> con los datos de esa empresa.</li>
                    </ol>
                    <div class="man-note s"><i class="fas fa-circle-check"></i><div>Todo registro que cree (un parte, una carga, un viaje, etc.) se asocia automáticamente a la empresa activa en ese momento.</div></div>
                    <div class="man-note i"><i class="fas fa-user-shield"></i><div>Solo el <strong>Administrador General</strong> puede cambiar entre empresas. Los demás usuarios trabajan con la empresa a la que pertenecen.</div></div>
                </div>
            </section>

            <!-- 4 -->
            <section class="man-sec" id="s4">
                <div class="sec-head"><span class="sec-no">4</span><h2>Panel Principal — Dashboard</h2></div>
                <div class="sec-body">
                    <p>El Dashboard es la pantalla de inicio. Resume el estado de la <strong>empresa activa</strong> mediante indicadores (KPI) y gráficos en tiempo real.</p>
                    <table class="man-table">
                        <thead><tr><th>Indicador</th><th>Significado</th></tr></thead>
                        <tbody>
                            <tr><td>Obras Activas</td><td>Obras en ejecución</td></tr>
                            <tr><td>Partes Hoy</td><td>Partes de faena registrados en el día</td></tr>
                            <tr><td>Combustible del Mes</td><td>Litros consumidos en el mes</td></tr>
                            <tr><td>Viajes</td><td>Transportes registrados</td></tr>
                            <tr><td>Equipos Detenidos</td><td>Maquinaria fuera de servicio</td></tr>
                            <tr><td>Documentos por Vencer</td><td>Alertas de vencimiento próximas</td></tr>
                        </tbody>
                    </table>
                    <div class="man-note i"><i class="fas fa-lightbulb"></i><div>Los indicadores y gráficos se recalculan al cambiar de empresa, mostrando siempre la información de la empresa activa.</div></div>
                </div>
            </section>

            <!-- 5 -->
            <section class="man-sec" id="s5">
                <div class="sec-head"><span class="sec-no">5</span><h2>Partes de Faena</h2></div>
                <div class="sec-body">
                    <p>Registre el avance diario de cada obra: actividades, personal y equipos utilizados, y condiciones del día.</p>
                    <h3>Crear un parte de faena</h3>
                    <ol class="man-steps">
                        <li>Ingrese a <strong>Faena</strong> y presione <strong>Nuevo Parte</strong>.</li>
                        <li>Seleccione la obra, la fecha, el supervisor y el clima.</li>
                        <li>Agregue las <strong>actividades</strong> con su cantidad y unidad.</li>
                        <li>Registre el <strong>personal</strong> y los <strong>equipos</strong> con sus horas.</li>
                        <li>Guarde como <em>borrador</em> o <strong>ciérrelo</strong> cuando esté completo.</li>
                    </ol>
                    <div class="man-note w"><i class="fas fa-pen"></i><div><strong>Borrador:</strong> editable, aún no confirmado. &nbsp;·&nbsp; <strong>Cerrado:</strong> registro oficial, no editable.</div></div>
                </div>
            </section>

            <!-- 6 -->
            <section class="man-sec" id="s6">
                <div class="sec-head"><span class="sec-no">6</span><h2>Control de Combustible</h2></div>
                <div class="sec-body">
                    <p>Controle cada carga de combustible a vehículos y equipos, con su costo, fuente y lectura de horómetro/odómetro.</p>
                    <ol class="man-steps">
                        <li>En <strong>Combustible</strong> presione <strong>Nueva Carga</strong>.</li>
                        <li>Indique si la carga es a un <strong>vehículo</strong> o a un <strong>equipo</strong> y selecciónelo.</li>
                        <li>Registre litros, precio por litro, fuente (estanque propio o externo) y horómetro/odómetro.</li>
                        <li>Opcionalmente asóciela a una obra o contrato y guarde.</li>
                    </ol>
                    <div class="man-note i"><i class="fas fa-chart-line"></i><div>El sistema acumula litros y costos por mes, vehículo, equipo y obra para análisis de consumo.</div></div>
                </div>
            </section>

            <!-- 7 -->
            <section class="man-sec" id="s7">
                <div class="sec-head"><span class="sec-no">7</span><h2>Gestión de Maquinaria</h2></div>
                <div class="sec-body">
                    <p>Administre el mantenimiento de equipos, sus repuestos, detenciones y la lectura de horómetro.</p>
                    <h3>Registrar una mantención</h3>
                    <ol class="man-steps">
                        <li>En <strong>Maquinaria</strong> elija el equipo y presione <strong>Nueva Mantención</strong>.</li>
                        <li>Indique el tipo (preventiva/correctiva), fecha, horómetro y técnico.</li>
                        <li>Agregue los <strong>repuestos</strong> usados con su costo.</li>
                        <li>Defina el estado: pendiente, en proceso o completada.</li>
                    </ol>
                    <div class="man-note w"><i class="fas fa-triangle-exclamation"></i><div>Una <strong>detención</strong> sin fecha de término se considera activa: el equipo está fuera de servicio y aparece en el dashboard.</div></div>
                </div>
            </section>

            <!-- 8 -->
            <section class="man-sec" id="s8">
                <div class="sec-head"><span class="sec-no">8</span><h2>Gestión de Transporte</h2></div>
                <div class="sec-body">
                    <p>Controle los viajes de transporte de material y emita sus guías de despacho.</p>
                    <ol class="man-steps">
                        <li>En <strong>Transporte</strong> presione <strong>Nuevo Viaje</strong>.</li>
                        <li>Seleccione vehículo, conductor, obra, material y cantidad.</li>
                        <li>Indique origen, destino, horas y kilometraje.</li>
                        <li>Desde el viaje, genere la <strong>Guía de Despacho</strong> con número, receptor y RUT.</li>
                    </ol>
                    <div class="man-note i"><i class="fas fa-circle-info"></i><div>Estados del viaje: programado, en ruta, completado o cancelado.</div></div>
                </div>
            </section>

            <!-- 9 -->
            <section class="man-sec" id="s9">
                <div class="sec-head"><span class="sec-no">9</span><h2>Gestión de Áridos</h2></div>
                <div class="sec-body">
                    <p>Registre la producción de áridos, controle el stock por clasificación y gestione los despachos a clientes.</p>
                    <div class="man-grid">
                        <div class="man-card"><strong>Producción</strong><small>Material producido por turno, equipo y operador.</small></div>
                        <div class="man-card"><strong>Stock</strong><small>Existencias actuales por material y clasificación.</small></div>
                        <div class="man-card"><strong>Despachos</strong><small>Salidas a clientes con guía, vehículo y precio.</small></div>
                    </div>
                    <div class="man-note i"><i class="fas fa-lightbulb"></i><div>Cada producción y despacho usa las clasificaciones de árido (grava 3/4", gravilla 1/2", arena, base, etc.) definidas en Datos Maestros.</div></div>
                </div>
            </section>

            <!-- 10 -->
            <section class="man-sec" id="s10">
                <div class="sec-head"><span class="sec-no">10</span><h2>Gestión de Contratos</h2></div>
                <div class="sec-body">
                    <p>Administre los contratos por obra y cliente, sus ítems (partidas) y el avance valorizado.</p>
                    <ol class="man-steps">
                        <li>En <strong>Contratos</strong> cree un contrato indicando obra, cliente, monto y vigencia.</li>
                        <li>Agregue los <strong>ítems</strong> con unidad, cantidad total y precio unitario.</li>
                        <li>Registre los <strong>avances</strong> por ítem y fecha; el sistema acumula el monto ejecutado.</li>
                    </ol>
                    <div class="man-note s"><i class="fas fa-percent"></i><div>El sistema calcula el porcentaje de avance comparando lo ejecutado con la cantidad total contratada.</div></div>
                </div>
            </section>

            <!-- 11 -->
            <section class="man-sec" id="s11">
                <div class="sec-head"><span class="sec-no">11</span><h2>Control Documental</h2></div>
                <div class="sec-body">
                    <p>Centralice documentos (licencias, seguros, revisiones técnicas, permisos, contratos) con control de vencimientos.</p>
                    <ol class="man-steps">
                        <li>En <strong>Documentos</strong> presione <strong>Nuevo Documento</strong>.</li>
                        <li>Elija el tipo y, si corresponde, la entidad asociada (trabajador, conductor, vehículo, equipo, obra…).</li>
                        <li>Registre número, fecha de emisión y de vencimiento, y adjunte el archivo.</li>
                    </ol>
                    <div class="man-note d"><i class="fas fa-bell"></i><div>El sistema alerta sobre documentos <strong>por vencer</strong> y <strong>vencidos</strong> en el dashboard, evitando multas o detenciones.</div></div>
                </div>
            </section>

            <!-- 12 -->
            <section class="man-sec" id="s12">
                <div class="sec-head"><span class="sec-no">12</span><h2>Seguridad Operacional</h2></div>
                <div class="sec-body">
                    <h3>Checklists de inspección</h3>
                    <ol class="man-steps">
                        <li>Cree un <strong>registro de checklist</strong> indicando tipo, obra y equipo/vehículo inspeccionado.</li>
                        <li>Marque cada ítem como cumple / no cumple, con observaciones.</li>
                        <li>El resultado puede ser aprobado, observado o rechazado.</li>
                    </ol>
                    <h3>Registro de incidentes</h3>
                    <ol class="man-steps">
                        <li>Registre el incidente con fecha, lugar, tipo y gravedad.</li>
                        <li>Describa lo ocurrido y las acciones tomadas.</li>
                        <li>Haga seguimiento del estado: investigando o cerrado.</li>
                    </ol>
                </div>
            </section>

            <!-- 13 -->
            <section class="man-sec" id="s13">
                <div class="sec-head"><span class="sec-no">13</span><h2>Datos Maestros</h2></div>
                <div class="sec-body">
                    <p>En <strong>Administración → Datos Maestros</strong> se mantienen los catálogos base. Tenerlos completos hace que el resto de los módulos funcione correctamente.</p>
                    <div class="man-grid">
                        <div class="man-card"><strong>Personal</strong><small>Trabajadores, conductores y operadores.</small></div>
                        <div class="man-card"><strong>Flota</strong><small>Vehículos y equipos con sus categorías.</small></div>
                        <div class="man-card"><strong>Obras y clientes</strong><small>Obras, clientes, contactos y centros de costo.</small></div>
                        <div class="man-card"><strong>Combustible</strong><small>Estanques y proveedores.</small></div>
                        <div class="man-card"><strong>Áridos</strong><small>Materiales, clasificaciones y pozos.</small></div>
                        <div class="man-card"><strong>Empresas y sucursales</strong><small>Datos de cada empresa del sistema.</small></div>
                    </div>
                    <div class="man-note w"><i class="fas fa-triangle-exclamation"></i><div>Los datos maestros propios de cada empresa (personal, flota, obras…) pertenecen a la <strong>empresa activa</strong>. Verifique la empresa seleccionada antes de crearlos.</div></div>
                </div>
            </section>

            <!-- 14 -->
            <section class="man-sec" id="s14">
                <div class="sec-head"><span class="sec-no">14</span><h2>Usuarios y Permisos</h2></div>
                <div class="sec-body">
                    <ol class="man-steps">
                        <li>En <strong>Administración → Usuarios</strong> cree cada cuenta con su correo, empresa, rol y trabajador asociado.</li>
                        <li>En <strong>Roles</strong> defina los perfiles de la organización.</li>
                        <li>En <strong>Permisos</strong> active o desactive, por rol, qué puede ver, crear, editar o eliminar en cada módulo.</li>
                    </ol>
                    <div class="man-note i"><i class="fas fa-user-shield"></i><div>El <strong>Administrador General</strong> tiene acceso total y es el único que puede cambiar entre empresas.</div></div>
                    <div class="man-note d"><i class="fas fa-lock"></i><div>Recuerde cambiar la contraseña del administrador tras la primera instalación.</div></div>
                </div>
            </section>

            <!-- 15 -->
            <section class="man-sec" id="s15">
                <div class="sec-head"><span class="sec-no">15</span><h2>Asistente de Inteligencia Artificial</h2></div>
                <div class="sec-body">
                    <p>El sistema incluye un asistente virtual disponible en todas las pantallas, mediante el botón flotante en la esquina inferior derecha.</p>
                    <ol class="man-steps">
                        <li>Haga clic en el botón del asistente para abrir el panel de conversación.</li>
                        <li>Escriba su pregunta sobre el uso del sistema y envíela.</li>
                        <li>El asistente responde con orientación contextual sobre módulos y procesos.</li>
                    </ol>
                    <div class="man-note i"><i class="fas fa-robot"></i><div>El asistente conoce el nombre de su empresa y su rol. Las conversaciones son privadas y no se comparten entre usuarios.</div></div>
                </div>
            </section>

            <!-- 16 -->
            <section class="man-sec" id="s16">
                <div class="sec-head"><span class="sec-no">16</span><h2>Preguntas Frecuentes</h2></div>
                <div class="sec-body">
                    <p><strong>¿Cómo cambio de empresa?</strong><br>Con el selector de empresa en la esquina superior derecha (solo Administrador General).</p>
                    <p><strong>¿Por qué no veo algunos módulos?</strong><br>Su rol no tiene permiso para ellos. Solicite acceso al administrador.</p>
                    <p><strong>¿Los datos de una empresa se mezclan con otra?</strong><br>No. Cada registro queda asociado a una empresa y los módulos filtran por la empresa activa.</p>
                    <p class="mb-0"><strong>¿Cómo obtengo ayuda dentro del sistema?</strong><br>Use el asistente IA (botón flotante) o descargue este manual en PDF desde el botón superior.</p>
                </div>
            </section>

        </div>
    </div>
</div>

<script>
(function(){
    const links = Array.from(document.querySelectorAll('#manIdx a'));
    const map = {};
    links.forEach(a => { const el = document.querySelector(a.getAttribute('href')); if (el) map[a.getAttribute('href')] = el; });
    function onScroll(){
        let cur = null;
        for (const href in map) { if (map[href].getBoundingClientRect().top < 140) cur = href; }
        links.forEach(a => a.classList.toggle('active', a.getAttribute('href') === cur));
    }
    window.addEventListener('scroll', onScroll); onScroll();
    links.forEach(a => a.addEventListener('click', e => {
        e.preventDefault();
        const el = map[a.getAttribute('href')];
        if (el) window.scrollTo({ top: el.offsetTop - 80, behavior:'smooth' });
    }));
})();
</script>

<?php include ROOT . '/includes/footer.php'; ?>