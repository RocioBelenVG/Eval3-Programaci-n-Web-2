<?php
require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireAdmin();

$emp = Auth::empresaId();

// ── Datos de la empresa ─────────────────────────────────────
$empresa = DB::one("SELECT * FROM empresas WHERE id=?", [$emp]);

// ── Conteos de entidades ────────────────────────────────────
$counts = [
    'trabajadores'  => DB::count("SELECT COUNT(*) FROM trabajadores   WHERE empresa_id=? AND activo=1", [$emp]),
    'conductores'   => DB::count("SELECT COUNT(*) FROM conductores c  INNER JOIN trabajadores t ON t.id=c.trabajador_id WHERE t.empresa_id=? AND c.activo=1", [$emp]),
    'operadores'    => DB::count("SELECT COUNT(*) FROM operadores o   INNER JOIN trabajadores t ON t.id=o.trabajador_id WHERE t.empresa_id=? AND o.activo=1", [$emp]),
    'vehiculos'     => DB::count("SELECT COUNT(*) FROM vehiculos      WHERE empresa_id=? AND activo=1", [$emp]),
    'equipos'       => DB::count("SELECT COUNT(*) FROM equipos        WHERE empresa_id=? AND activo=1", [$emp]),
    'obras'         => DB::count("SELECT COUNT(*) FROM obras          WHERE empresa_id=? AND activo=1", [$emp]),
    'contratos'     => DB::count("SELECT COUNT(*) FROM contratos      WHERE empresa_id=? AND activo=1", [$emp]),
    'clientes'      => DB::count("SELECT COUNT(*) FROM clientes       WHERE empresa_id=? AND activo=1", [$emp]),
    'estanques'     => DB::count("SELECT COUNT(*) FROM estanques      WHERE empresa_id=? AND activo=1", [$emp]),
    'proveedores'   => DB::count("SELECT COUNT(*) FROM proveedores_combustible WHERE empresa_id=? AND activo=1", [$emp]),
    'pozos'         => DB::count("SELECT COUNT(*) FROM pozos          WHERE empresa_id=? AND activo=1", [$emp]),
    'sucursales'    => DB::count("SELECT COUNT(*) FROM sucursales     WHERE empresa_id=? AND activo=1", [$emp]),
    'usuarios'      => DB::count("SELECT COUNT(*) FROM usuarios       WHERE empresa_id=? AND activo=1", [$emp]),
];

// ── Calcular completitud ─────────────────────────────────────
$obligatorios = ['trabajadores', 'vehiculos', 'equipos', 'obras', 'clientes'];
$completos    = count(array_filter($obligatorios, fn($k) => $counts[$k] > 0));
$pct          = (int)round(($completos / count($obligatorios)) * 100);

$pageTitle = 'Configuración de Empresa';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/admin/">Administración</a></li>
    <li class="breadcrumb-item active">Configuración de Empresa</li>
</ol></nav>

<!-- ENCABEZADO ─────────────────────────────────────────────── -->
<div class="card form-card mb-4">
    <div class="card-header d-flex align-items-center gap-2">
        <i class="fas fa-building"></i>
        <strong>Datos de la Empresa</strong>
        <a href="<?= APP_URL ?>/modules/admin/maestros/empresas?action=edit&id=<?= $emp ?>"
           class="btn btn-sm btn-outline-warning ms-auto">
            <i class="fas fa-edit me-1"></i>Editar
        </a>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <p class="mb-1 text-muted small fw-bold text-uppercase" style="font-size:.7rem;letter-spacing:.08em">Razón Social</p>
                <p class="mb-0 fw-bold fs-5"><?= h($empresa['razon_social'] ?? '—') ?></p>
            </div>
            <div class="col-md-3">
                <p class="mb-1 text-muted small fw-bold text-uppercase" style="font-size:.7rem;letter-spacing:.08em">RUT</p>
                <p class="mb-0"><?= h($empresa['rut'] ?? '—') ?></p>
            </div>
            <div class="col-md-3">
                <p class="mb-1 text-muted small fw-bold text-uppercase" style="font-size:.7rem;letter-spacing:.08em">Ciudad</p>
                <p class="mb-0"><?= h($empresa['ciudad'] ?? '—') ?></p>
            </div>
            <div class="col-md-6">
                <p class="mb-1 text-muted small fw-bold text-uppercase" style="font-size:.7rem;letter-spacing:.08em">Giro</p>
                <p class="mb-0"><?= h($empresa['giro'] ?? '—') ?></p>
            </div>
            <div class="col-md-3">
                <p class="mb-1 text-muted small fw-bold text-uppercase" style="font-size:.7rem;letter-spacing:.08em">Teléfono</p>
                <p class="mb-0"><?= h($empresa['telefono'] ?? '—') ?></p>
            </div>
            <div class="col-md-3">
                <p class="mb-1 text-muted small fw-bold text-uppercase" style="font-size:.7rem;letter-spacing:.08em">Email</p>
                <p class="mb-0"><?= h($empresa['email'] ?? '—') ?></p>
            </div>
        </div>
    </div>
</div>

<!-- PROGRESO ────────────────────────────────────────────────── -->
<div class="card table-card mb-4">
    <div class="card-body py-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="fw-bold text-secondary" style="font-size:.8rem;text-transform:uppercase;letter-spacing:.06em">
                Completitud del perfil operacional
            </span>
            <span class="fw-bold <?= $pct >= 100 ? 'text-success' : ($pct >= 60 ? 'text-warning' : 'text-danger') ?>">
                <?= $pct ?>%
            </span>
        </div>
        <div class="progress" style="height:8px;border-radius:99px">
            <div class="progress-bar <?= $pct >= 100 ? 'bg-success' : ($pct >= 60 ? 'bg-warning' : 'bg-danger') ?>"
                 role="progressbar" style="width:<?= $pct ?>%;border-radius:99px"></div>
        </div>
        <?php if ($pct < 100): ?>
        <p class="text-muted small mt-2 mb-0">
            <i class="fas fa-info-circle me-1"></i>
            Para que los formularios operacionales funcionen correctamente, necesita agregar datos en las secciones marcadas en rojo.
        </p>
        <?php endif; ?>
    </div>
</div>

<!-- GRID DE CONFIGURACIÓN ─────────────────────────────────── -->
<?php
// [título, icono, clave_count, href_add, href_list, descripción, obligatorio]
$secciones = [
    'Personal y Usuarios' => [
        ['Trabajadores',        'id-card',       'trabajadores', 'maestros/trabajadores?action=create', 'maestros/trabajadores',        'Personal de la empresa',                   true ],
        ['Conductores',         'car',           'conductores',  'maestros/conductores?action=create',  'maestros/conductores',         'Conductores autorizados con licencia',      false],
        ['Operadores',          'user-cog',      'operadores',   'maestros/operadores?action=create',   'maestros/operadores',          'Operadores de maquinaria certificados',     false],
        ['Usuarios del Sistema','users',         'usuarios',     'usuarios?action=create',              'usuarios',                     'Cuentas de acceso al sistema',              false],
    ],
    'Proyectos y Clientes' => [
        ['Clientes',            'handshake',     'clientes',     'maestros/clientes?action=create',     'maestros/clientes',            'Empresas y personas contratantes',         true ],
        ['Obras',               'hard-hat',      'obras',        'maestros/obras?action=create',        'maestros/obras',               'Proyectos y faenas activas',               true ],
        ['Contratos',           'file-contract', 'contratos',    'maestros/contratos?action=create',    'maestros/contratos',           'Contratos comerciales vigentes',           false],
        ['Sucursales',          'map-marker-alt','sucursales',   'maestros/sucursales?action=create',   'maestros/sucursales',          'Oficinas y puntos de operación',           false],
    ],
    'Flota y Maquinaria' => [
        ['Vehículos',           'truck',         'vehiculos',    'maestros/vehiculos?action=create',    'maestros/vehiculos',           'Camiones, camionetas y equipos rodantes',  true ],
        ['Equipos / Maquinaria','cogs',          'equipos',      'maestros/equipos?action=create',      'maestros/equipos',             'Equipos pesados con horómetro',            true ],
    ],
    'Combustible' => [
        ['Estanques',           'oil-can',       'estanques',    'maestros/estanques?action=create',    'maestros/estanques',           'Estanques propios de combustible',         false],
        ['Proveedores',         'store',         'proveedores',  'maestros/proveedores_combustible?action=create', 'maestros/proveedores_combustible', 'Proveedores de combustible externos', false],
    ],
    'Áridos' => [
        ['Pozos / Canteras',    'mountain',      'pozos',        'maestros/pozos?action=create',        'maestros/pozos',               'Canteras y pozos de extracción',           false],
    ],
];
?>

<?php foreach ($secciones as $secTitulo => $items): ?>
<div class="card table-card mb-3">
    <div class="card-header">
        <strong><?= h($secTitulo) ?></strong>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:40%">Módulo</th>
                        <th>Descripción</th>
                        <th class="text-center" style="width:100px">Registros</th>
                        <th class="text-end" style="width:160px">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($items as [$label, $icon, $countKey, $hrefAdd, $hrefList, $desc, $requerido]): ?>
                <?php $n = $counts[$countKey] ?? 0; $ok = $n > 0; ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($requerido && !$ok): ?>
                                <span class="badge bg-danger" style="font-size:.65rem">Requerido</span>
                            <?php elseif ($ok): ?>
                                <i class="fas fa-check-circle text-success"></i>
                            <?php else: ?>
                                <i class="fas fa-circle text-muted" style="font-size:.6rem"></i>
                            <?php endif; ?>
                            <i class="fas fa-<?= $icon ?> text-secondary" style="width:14px"></i>
                            <strong><?= h($label) ?></strong>
                        </div>
                    </td>
                    <td class="text-muted small"><?= h($desc) ?></td>
                    <td class="text-center">
                        <?php if ($ok): ?>
                            <span class="badge bg-success"><?= $n ?></span>
                        <?php elseif ($requerido): ?>
                            <span class="badge bg-danger">0</span>
                        <?php else: ?>
                            <span class="badge bg-light text-muted">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="<?= APP_URL ?>/modules/admin/<?= $hrefList ?>"
                           class="btn btn-sm btn-outline-secondary btn-action" title="Ver listado">
                            <i class="fas fa-list"></i>
                        </a>
                        <a href="<?= APP_URL ?>/modules/admin/<?= $hrefAdd ?>"
                           class="btn btn-sm btn-<?= $requerido && !$ok ? 'danger' : 'outline-primary' ?> btn-action" title="Agregar nuevo">
                            <i class="fas fa-plus"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- TABLAS GLOBALES (no filtradas por empresa) ─────────────── -->
<div class="card table-card mb-3">
    <div class="card-header">
        <strong>Catálogos Globales</strong>
        <span class="text-muted ms-2 small">(compartidos entre todas las empresas)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr>
                    <th>Catálogo</th>
                    <th>Descripción</th>
                    <th class="text-end">Acciones</th>
                </tr></thead>
                <tbody>
                <?php
                $catalogos = [
                    ['Cargos',               'briefcase',       'maestros/cargos.php',               'Puestos de trabajo (Operador, Mecánico…)'],
                    ['Categorías Vehículo',  'tags',            'maestros/categorias_vehiculo.php',  'Tipos de vehículo (Camión Tolva, Camioneta…)'],
                    ['Marcas de Equipo',     'certificate',     'maestros/marcas_equipo.php',        'Caterpillar, Komatsu, Volvo…'],
                    ['Modelos de Equipo',    'list',            'maestros/modelos_equipo.php',       'Modelos por marca'],
                    ['Categorías Equipo',    'th-list',         'maestros/categorias_equipo.php',    'Excavadora, Bulldozer…'],
                    ['Fuentes Combustible',  'plug',            'maestros/fuentes_combustible.php',  'Estanque, Bencinera, Camión Estanque…'],
                    ['Materiales Áridos',    'layer-group',     'maestros/materiales.php',           'Arena, Grava, Piedra…'],
                    ['Clasificaciones Árido','filter',          'maestros/clasificaciones_arido.php','Tamaños y tipos por material'],
                    ['Estados Contrato',     'tag',             'maestros/estados_contrato.php',     'Vigente, En Ejecución, Finalizado…'],
                    ['Tipos Checklist',      'clipboard-check', 'maestros/tipos_checklist.php',      'Pre-operacional, Semanal…'],
                    ['Tipos Documento',      'file-alt',        'maestros/tipos_documento.php',      'Licencias, Seguros, Permisos…'],
                    ['Estados Documento',    'tag',             'maestros/estados_documento.php',    'Vigente, Por Vencer, Vencido…'],
                ];
                foreach ($catalogos as [$lbl, $ico, $href, $desc]):
                ?>
                <tr>
                    <td><i class="fas fa-<?= $ico ?> text-muted me-2" style="width:14px"></i><strong><?= h($lbl) ?></strong></td>
                    <td class="text-muted small"><?= h($desc) ?></td>
                    <td class="text-end">
                        <a href="<?= APP_URL ?>/modules/admin/<?= $href ?>"
                           class="btn btn-sm btn-outline-secondary btn-action">
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include ROOT . '/includes/footer.php'; ?>
