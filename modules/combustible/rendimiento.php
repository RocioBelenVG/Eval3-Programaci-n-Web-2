<?php
/**
 * TriTransportes — M03 Combustible · Rendimiento de activos
 * ----------------------------------------------------------------------------
 * Mide cuánto consume cada máquina y cada vehículo *en relación con su uso*:
 *   · Equipos    → litros por hora de horómetro y costo por hora operada.
 *   · Vehículos  → kilómetros por litro, litros/100 km y costo por kilómetro.
 *
 * El consumo de una carga se imputa al intervalo recorrido desde la carga
 * anterior del mismo activo (función de ventana LAG). Con eso se compara cada
 * activo contra el promedio de su categoría, se mide la tendencia respecto del
 * período anterior y se detectan cargas fuera de patrón.
 */

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::requireAnyPermiso('combustible');

$emp = Auth::empresaId();
$hoy = date('Y-m-d');

// ── Filtros ──────────────────────────────────────────────────────────────────
$fDesde = get('desde', date('Y-m-d', strtotime('-180 days')));
$fHasta = get('hasta', $hoy);
$obraId = getInt('obra_id');

// "unidad" y "cat" vienen como "eq:ID" (equipo) o "ve:ID" (vehículo), para
// ofrecer un único selector de activo y otro de categoría en la interfaz.
$unidadSel = get('unidad');
$catSel    = get('cat');

$parse = function (string $v): array {
    if (!preg_match('/^(eq|ve):(\d+)$/', $v, $m)) return ['', 0];
    return [$m[1], (int)$m[2]];
};
[$uTipo, $uId] = $parse($unidadSel);
[$cTipo, $cId] = $parse($catSel);

// ¿Qué secciones tiene sentido mostrar según lo filtrado?
$verEquipos   = ($uTipo !== 've') && ($cTipo !== 've');
$verVehiculos = ($uTipo !== 'eq') && ($cTipo !== 'eq');

$filtroObra = $obraId ? ' AND obra_id = ?' : '';
$pBase      = [$emp, $fDesde, $fHasta];
if ($obraId) $pBase[] = $obraId;

/**
 * Condición adicional para las consultas de equipos.
 * Devuelve [fragmentoSQL, parámetros].
 */
$condEquipos = function () use ($uTipo, $uId, $cTipo, $cId): array {
    $sql = ''; $p = [];
    if ($uTipo === 'eq' && $uId) { $sql .= ' AND equipo_id = ?'; $p[] = $uId; }
    if ($cTipo === 'eq' && $cId) {
        $sql .= ' AND equipo_id IN (SELECT id FROM equipos WHERE categoria_id = ?)';
        $p[] = $cId;
    }
    return [$sql, $p];
};

$condVehiculos = function () use ($uTipo, $uId, $cTipo, $cId): array {
    $sql = ''; $p = [];
    if ($uTipo === 've' && $uId) { $sql .= ' AND vehiculo_id = ?'; $p[] = $uId; }
    if ($cTipo === 've' && $cId) {
        $sql .= ' AND vehiculo_id IN (SELECT id FROM vehiculos WHERE categoria_id = ?)';
        $p[] = $cId;
    }
    return [$sql, $p];
};

[$sqlCondEq, $pCondEq] = $condEquipos();
[$sqlCondVe, $pCondVe] = $condVehiculos();

// Para las consultas que mezclan equipos y vehículos (evolución y anomalías)
// se combinan ambas restricciones respetando la sección visible.
$sqlCondMix = ''; $pCondMix = [];
if (!$verVehiculos)      { $sqlCondMix .= " AND tipo_unidad = 'equipo'"; }
if (!$verEquipos)        { $sqlCondMix .= " AND tipo_unidad = 'vehiculo'"; }
if ($sqlCondEq !== '')   { $sqlCondMix .= $sqlCondEq; $pCondMix = array_merge($pCondMix, $pCondEq); }
if ($sqlCondVe !== '')   { $sqlCondMix .= $sqlCondVe; $pCondMix = array_merge($pCondMix, $pCondVe); }

// Punto de corte para la tendencia: mitad del período analizado
$mitad = date('Y-m-d', (int)((strtotime($fDesde) + strtotime($fHasta)) / 2));

// ── Rendimiento de EQUIPOS (litros por hora de horómetro) ───────────────────
$sqlEquipos = "
    WITH base AS (
        SELECT equipo_id, fecha, litros, precio_litro, horometro,
               LAG(horometro) OVER (PARTITION BY equipo_id ORDER BY fecha, id) AS horo_prev
        FROM cargas_combustible
        WHERE empresa_id = ? AND tipo_unidad = 'equipo'
          AND equipo_id IS NOT NULL AND horometro IS NOT NULL
          AND fecha BETWEEN ? AND ?
          {$filtroObra}{$sqlCondEq}
    ),
    intervalos AS (
        SELECT equipo_id, fecha, litros, precio_litro,
               (horometro - horo_prev) AS horas
        FROM base
        WHERE horo_prev IS NOT NULL
          AND horometro > horo_prev
          AND (horometro - horo_prev) BETWEEN 1 AND 400   -- descarta saltos por error de digitación
    )
    SELECT i.equipo_id,
           e.codigo, e.nombre, e.horometro AS horometro_actual,
           ce.nombre AS categoria,
           COUNT(*)                                   AS cargas,
           SUM(i.litros)                              AS litros,
           SUM(i.horas)                               AS horas,
           SUM(i.litros * COALESCE(i.precio_litro,0)) AS costo,
           SUM(CASE WHEN i.fecha >  ? THEN i.litros ELSE 0 END) AS litros_rec,
           SUM(CASE WHEN i.fecha >  ? THEN i.horas  ELSE 0 END) AS horas_rec,
           SUM(CASE WHEN i.fecha <= ? THEN i.litros ELSE 0 END) AS litros_prev,
           SUM(CASE WHEN i.fecha <= ? THEN i.horas  ELSE 0 END) AS horas_prev,
           MAX(i.fecha)                               AS ultima_carga
    FROM intervalos i
    INNER JOIN equipos e ON e.id = i.equipo_id
    LEFT  JOIN categorias_equipo ce ON ce.id = e.categoria_id
    GROUP BY i.equipo_id, e.codigo, e.nombre, e.horometro, ce.nombre
    HAVING horas > 0
    ORDER BY (SUM(i.litros) / SUM(i.horas)) DESC";

$equipos = $verEquipos
    ? DB::all($sqlEquipos, array_merge($pBase, $pCondEq, [$mitad, $mitad, $mitad, $mitad]))
    : [];

// ── Rendimiento de VEHÍCULOS (kilómetros por litro) ─────────────────────────
$sqlVehiculos = "
    WITH base AS (
        SELECT vehiculo_id, fecha, litros, precio_litro, odometro,
               LAG(odometro) OVER (PARTITION BY vehiculo_id ORDER BY fecha, id) AS odo_prev
        FROM cargas_combustible
        WHERE empresa_id = ? AND tipo_unidad = 'vehiculo'
          AND vehiculo_id IS NOT NULL AND odometro IS NOT NULL
          AND fecha BETWEEN ? AND ?
          {$filtroObra}{$sqlCondVe}
    ),
    intervalos AS (
        SELECT vehiculo_id, fecha, litros, precio_litro,
               (odometro - odo_prev) AS km
        FROM base
        WHERE odo_prev IS NOT NULL
          AND odometro > odo_prev
          AND (odometro - odo_prev) BETWEEN 10 AND 4000
    )
    SELECT i.vehiculo_id,
           v.patente, v.marca, v.modelo, v.odometro AS odometro_actual,
           cv.nombre AS categoria,
           COUNT(*)                                   AS cargas,
           SUM(i.litros)                              AS litros,
           SUM(i.km)                                  AS km,
           SUM(i.litros * COALESCE(i.precio_litro,0)) AS costo,
           SUM(CASE WHEN i.fecha >  ? THEN i.litros ELSE 0 END) AS litros_rec,
           SUM(CASE WHEN i.fecha >  ? THEN i.km     ELSE 0 END) AS km_rec,
           SUM(CASE WHEN i.fecha <= ? THEN i.litros ELSE 0 END) AS litros_prev,
           SUM(CASE WHEN i.fecha <= ? THEN i.km     ELSE 0 END) AS km_prev,
           MAX(i.fecha)                               AS ultima_carga
    FROM intervalos i
    INNER JOIN vehiculos v ON v.id = i.vehiculo_id
    LEFT  JOIN categorias_vehiculo cv ON cv.id = v.categoria_id
    GROUP BY i.vehiculo_id, v.patente, v.marca, v.modelo, v.odometro, cv.nombre
    HAVING km > 0
    ORDER BY (SUM(i.km) / SUM(i.litros)) ASC";

$vehiculos = $verVehiculos
    ? DB::all($sqlVehiculos, array_merge($pBase, $pCondVe, [$mitad, $mitad, $mitad, $mitad]))
    : [];

// ── Promedios por categoría (referencia de comparación) ─────────────────────
$promCatEq = [];
foreach ($equipos as $e) {
    $cat = $e['categoria'] ?: 'Sin categoría';
    $promCatEq[$cat]['litros'] = ($promCatEq[$cat]['litros'] ?? 0) + (float)$e['litros'];
    $promCatEq[$cat]['horas']  = ($promCatEq[$cat]['horas']  ?? 0) + (float)$e['horas'];
}
foreach ($promCatEq as $cat => $v) {
    $promCatEq[$cat]['lh'] = $v['horas'] > 0 ? $v['litros'] / $v['horas'] : 0;
}

$promCatVe = [];
foreach ($vehiculos as $v) {
    $cat = $v['categoria'] ?: 'Sin categoría';
    $promCatVe[$cat]['litros'] = ($promCatVe[$cat]['litros'] ?? 0) + (float)$v['litros'];
    $promCatVe[$cat]['km']     = ($promCatVe[$cat]['km']     ?? 0) + (float)$v['km'];
}
foreach ($promCatVe as $cat => $v) {
    $promCatVe[$cat]['kml'] = $v['litros'] > 0 ? $v['km'] / $v['litros'] : 0;
}

/**
 * Clasifica una desviación porcentual respecto del promedio de su categoría.
 * $signo = 1 cuando "más alto es peor" (L/h); -1 cuando "más bajo es peor" (km/L).
 */
function semaforo(float $desviacionPct, int $signo = 1): array {
    $d = $desviacionPct * $signo;
    if ($d >= 18)  return ['crit',  'danger',  'Crítico',  'arrow-trend-up'];
    if ($d >= 8)   return ['aten',  'warning', 'Atención', 'triangle-exclamation'];
    if ($d <= -10) return ['exc',   'success', 'Óptimo',   'award'];
    return ['norm', 'secondary', 'Normal', 'equals'];
}

// ── KPIs globales del período ───────────────────────────────────────────────
$totLitrosEq = array_sum(array_column($equipos, 'litros'));
$totHoras    = array_sum(array_column($equipos, 'horas'));
$totCostoEq  = array_sum(array_column($equipos, 'costo'));
$totLitrosVe = array_sum(array_column($vehiculos, 'litros'));
$totKm       = array_sum(array_column($vehiculos, 'km'));
$totCostoVe  = array_sum(array_column($vehiculos, 'costo'));

$lhFlota   = $totHoras    > 0 ? $totLitrosEq / $totHoras    : 0;
$kmlFlota  = $totLitrosVe > 0 ? $totKm       / $totLitrosVe : 0;
$costoHora = $totHoras    > 0 ? $totCostoEq  / $totHoras    : 0;
$costoKm   = $totKm       > 0 ? $totCostoVe  / $totKm       : 0;
$costoTot  = $totCostoEq + $totCostoVe;

// ── Evolución mensual del rendimiento de la flota ───────────────────────────
$sqlMensual = "
    WITH base AS (
        SELECT tipo_unidad, equipo_id, vehiculo_id, fecha, litros, horometro, odometro,
               LAG(horometro) OVER (PARTITION BY equipo_id   ORDER BY fecha, id) AS horo_prev,
               LAG(odometro)  OVER (PARTITION BY vehiculo_id ORDER BY fecha, id) AS odo_prev
        FROM cargas_combustible
        WHERE empresa_id = ? AND fecha BETWEEN ? AND ? {$filtroObra}{$sqlCondMix}
    )
    SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes,
           SUM(CASE WHEN tipo_unidad='equipo'   AND horo_prev IS NOT NULL
                     AND horometro-horo_prev BETWEEN 1 AND 400 THEN litros ELSE 0 END) AS litros_eq,
           SUM(CASE WHEN tipo_unidad='equipo'   AND horo_prev IS NOT NULL
                     AND horometro-horo_prev BETWEEN 1 AND 400 THEN horometro-horo_prev ELSE 0 END) AS horas,
           SUM(CASE WHEN tipo_unidad='vehiculo' AND odo_prev IS NOT NULL
                     AND odometro-odo_prev BETWEEN 10 AND 4000 THEN litros ELSE 0 END) AS litros_ve,
           SUM(CASE WHEN tipo_unidad='vehiculo' AND odo_prev IS NOT NULL
                     AND odometro-odo_prev BETWEEN 10 AND 4000 THEN odometro-odo_prev ELSE 0 END) AS km
    FROM base
    GROUP BY mes ORDER BY mes";

$mensual = DB::all($sqlMensual, array_merge($pBase, $pCondMix));

$gMeses = []; $gLh = []; $gKml = [];
foreach ($mensual as $m) {
    $gMeses[] = $m['mes'];
    $gLh[]    = (float)$m['horas'] > 0 ? round($m['litros_eq'] / $m['horas'], 2) : null;
    $gKml[]   = (float)$m['litros_ve'] > 0 ? round($m['km'] / $m['litros_ve'], 2) : null;
}

// ── Cargas fuera de patrón (posible sobreconsumo, fuga o error de registro) ──
$sqlAnomalias = "
    WITH base AS (
        SELECT id, equipo_id, vehiculo_id, tipo_unidad, fecha, litros, horometro, odometro,
               LAG(horometro) OVER (PARTITION BY equipo_id   ORDER BY fecha, id) AS horo_prev,
               LAG(odometro)  OVER (PARTITION BY vehiculo_id ORDER BY fecha, id) AS odo_prev
        FROM cargas_combustible
        WHERE empresa_id = ? AND fecha BETWEEN ? AND ? {$filtroObra}{$sqlCondMix}
    ),
    medidas AS (
        SELECT id, tipo_unidad, equipo_id, vehiculo_id, fecha, litros,
               CASE WHEN tipo_unidad='equipo'   AND horo_prev IS NOT NULL
                          AND horometro-horo_prev BETWEEN 1 AND 400
                    THEN litros/(horometro-horo_prev) END AS lh,
               CASE WHEN tipo_unidad='vehiculo' AND odo_prev IS NOT NULL
                          AND odometro-odo_prev BETWEEN 10 AND 4000
                    THEN (odometro-odo_prev)/litros END AS kml
        FROM base
    ),
    refs AS (
        SELECT tipo_unidad, equipo_id, vehiculo_id,
               AVG(lh) AS lh_prom, AVG(kml) AS kml_prom
        FROM medidas
        GROUP BY tipo_unidad, equipo_id, vehiculo_id
    )
    SELECT m.id, m.tipo_unidad, m.fecha, m.litros, m.lh, m.kml,
           r.lh_prom, r.kml_prom,
           e.codigo AS equipo_codigo, v.patente
    FROM medidas m
    INNER JOIN refs r
        ON r.tipo_unidad = m.tipo_unidad
       AND (r.equipo_id   <=> m.equipo_id)
       AND (r.vehiculo_id <=> m.vehiculo_id)
    LEFT JOIN equipos   e ON e.id = m.equipo_id
    LEFT JOIN vehiculos v ON v.id = m.vehiculo_id
    WHERE (m.lh  IS NOT NULL AND r.lh_prom  > 0 AND m.lh  > r.lh_prom  * 1.35)
       OR (m.kml IS NOT NULL AND r.kml_prom > 0 AND m.kml < r.kml_prom * 0.70)
    ORDER BY m.fecha DESC
    LIMIT 40";

$anomalias = DB::all($sqlAnomalias, array_merge($pBase, $pCondMix));

$obras = DB::all('SELECT id, nombre FROM obras WHERE empresa_id=? AND activo=1 ORDER BY nombre', [$emp]);

// Listas para los selectores de activo y categoría
$listaEquipos = DB::all("SELECT e.id, e.codigo, e.nombre, c.nombre AS categoria
                         FROM equipos e LEFT JOIN categorias_equipo c ON c.id = e.categoria_id
                         WHERE e.empresa_id=? AND e.activo=1 ORDER BY e.codigo", [$emp]);
$listaVehiculos = DB::all("SELECT v.id, v.patente, v.marca, v.modelo, c.nombre AS categoria
                           FROM vehiculos v LEFT JOIN categorias_vehiculo c ON c.id = v.categoria_id
                           WHERE v.empresa_id=? AND v.activo=1 ORDER BY v.patente", [$emp]);
$catEquipos = DB::all("SELECT DISTINCT c.id, c.nombre FROM categorias_equipo c
                       INNER JOIN equipos e ON e.categoria_id = c.id
                       WHERE e.empresa_id=? AND e.activo=1 ORDER BY c.nombre", [$emp]);
$catVehiculos = DB::all("SELECT DISTINCT c.id, c.nombre FROM categorias_vehiculo c
                         INNER JOIN vehiculos v ON v.categoria_id = c.id
                         WHERE v.empresa_id=? AND v.activo=1 ORDER BY c.nombre", [$emp]);

$pageTitle = 'Rendimiento de Activos';
include ROOT . '/includes/header.php';
?>

<nav aria-label="breadcrumb"><ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/dashboard/">Dashboard</a></li>
    <li class="breadcrumb-item"><a href="<?= APP_URL ?>/modules/combustible/">Combustible</a></li>
    <li class="breadcrumb-item active">Rendimiento</li>
</ol></nav>

<!-- Filtros -->
<div class="card table-card mb-4">
    <div class="card-header">
        <form class="row g-2 align-items-end js-filtros" method="GET">
            <div class="col-auto">
                <label class="form-label form-label-sm mb-1">Desde</label>
                <input type="date" name="desde" class="form-control form-control-sm" value="<?= h($fDesde) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm mb-1">Hasta</label>
                <input type="date" name="hasta" class="form-control form-control-sm" value="<?= h($fHasta) ?>">
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm mb-1">Obra</label>
                <select name="obra_id" class="form-select form-select-sm" style="min-width:160px">
                    <option value="">Todas</option>
                    <?php foreach ($obras as $o): ?>
                        <option value="<?= $o['id'] ?>" <?= $obraId == $o['id'] ? 'selected' : '' ?>><?= h($o['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm mb-1">Activo / Patente</label>
                <select name="unidad" class="form-select form-select-sm" style="min-width:190px">
                    <option value="">Todos los activos</option>
                    <?php if ($listaEquipos): ?>
                    <optgroup label="Equipos">
                        <?php foreach ($listaEquipos as $e): $v = 'eq:' . $e['id']; ?>
                            <option value="<?= $v ?>" <?= $unidadSel === $v ? 'selected' : '' ?>>
                                <?= h($e['codigo']) ?> — <?= h(mb_substr($e['nombre'], 0, 28)) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                    <?php if ($listaVehiculos): ?>
                    <optgroup label="Vehículos (patente)">
                        <?php foreach ($listaVehiculos as $v2): $v = 've:' . $v2['id']; ?>
                            <option value="<?= $v ?>" <?= $unidadSel === $v ? 'selected' : '' ?>>
                                <?= h($v2['patente']) ?> — <?= h(trim($v2['marca'] . ' ' . $v2['modelo'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-auto">
                <label class="form-label form-label-sm mb-1">Categoría</label>
                <select name="cat" class="form-select form-select-sm" style="min-width:180px">
                    <option value="">Todas las categorías</option>
                    <?php if ($catEquipos): ?>
                    <optgroup label="Equipos">
                        <?php foreach ($catEquipos as $c): $v = 'eq:' . $c['id']; ?>
                            <option value="<?= $v ?>" <?= $catSel === $v ? 'selected' : '' ?>><?= h($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                    <?php if ($catVehiculos): ?>
                    <optgroup label="Vehículos">
                        <?php foreach ($catVehiculos as $c): $v = 've:' . $c['id']; ?>
                            <option value="<?= $v ?>" <?= $catSel === $v ? 'selected' : '' ?>><?= h($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                </select>
            </div>
            <div class="col-auto">
                <a href="?" class="btn btn-sm btn-outline-secondary" title="Limpiar filtros">
                    <i class="fas fa-times me-1"></i>Limpiar
                </a>
            </div>
            <div class="col-auto ms-auto d-flex gap-2 align-items-center">
                <span class="filtros-auto-nota d-none d-lg-inline-flex">
                    <i class="fas fa-bolt"></i>Los filtros se aplican solos
                </span>
                <a href="<?= APP_URL ?>/api/v1/export_pdf?vista=combustible&desde=<?= h($fDesde) ?>&hasta=<?= h($fHasta) ?>&obra_id=<?= $obraId ?>"
                   class="btn btn-sm btn-pdf" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf me-1"></i>PDF
                </a>
                <a href="<?= APP_URL ?>/modules/combustible/" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-list me-1"></i>Ver cargas
                </a>
            </div>
        </form>
    </div>
</div>

<!-- KPIs de rendimiento -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3 col-xl-2">
        <div class="card kpi-card kpi-amber h-100">
            <div class="card-body p-3">
                <div class="kpi-icon bg-warning-subtle text-warning mb-2"><i class="fas fa-gauge-high"></i></div>
                <div class="kpi-value text-warning"><?= formatNum($lhFlota, 2) ?></div>
                <div class="kpi-label">Litros / Hora · Equipos</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3 col-xl-2">
        <div class="card kpi-card kpi-cyan h-100">
            <div class="card-body p-3">
                <div class="kpi-icon bg-info-subtle text-info mb-2"><i class="fas fa-road"></i></div>
                <div class="kpi-value text-info"><?= formatNum($kmlFlota, 2) ?></div>
                <div class="kpi-label">Km / Litro · Vehículos</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3 col-xl-2">
        <div class="card kpi-card kpi-green h-100">
            <div class="card-body p-3">
                <div class="kpi-icon bg-success-subtle text-success mb-2"><i class="fas fa-clock"></i></div>
                <div class="kpi-value text-success" style="font-size:1.35rem"><?= formatMoney($costoHora) ?></div>
                <div class="kpi-label">Costo por Hora Operada</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3 col-xl-2">
        <div class="card kpi-card kpi-blue h-100">
            <div class="card-body p-3">
                <div class="kpi-icon bg-primary-subtle text-primary mb-2"><i class="fas fa-truck-fast"></i></div>
                <div class="kpi-value text-primary" style="font-size:1.35rem"><?= formatMoney($costoKm) ?></div>
                <div class="kpi-label">Costo por Kilómetro</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3 col-xl-2">
        <div class="card kpi-card kpi-slate h-100">
            <div class="card-body p-3">
                <div class="kpi-icon bg-secondary-subtle text-secondary mb-2"><i class="fas fa-stopwatch"></i></div>
                <div class="kpi-value text-secondary"><?= formatNum($totHoras, 0) ?></div>
                <div class="kpi-label">Horas Operadas</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3 col-xl-2">
        <div class="card kpi-card kpi-red h-100">
            <div class="card-body p-3">
                <div class="kpi-icon bg-danger-subtle text-danger mb-2"><i class="fas fa-sack-dollar"></i></div>
                <div class="kpi-value text-danger" style="font-size:1.25rem"><?= formatMoney($costoTot) ?></div>
                <div class="kpi-label">Costo Combustible Período</div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($equipos) && empty($vehiculos)): ?>
<div class="card table-card">
    <div class="empty-state">
        <i class="fas fa-gauge-high fa-3x mb-3 text-muted"></i>
        <h6>Sin datos suficientes para calcular rendimiento</h6>
        <p class="mb-0">
            El cálculo necesita al menos dos cargas del mismo activo con horómetro
            (equipos) u odómetro (vehículos) registrado dentro del período.
        </p>
    </div>
</div>
<?php else: ?>

<!-- Evolución del rendimiento -->
<div class="row g-4 mb-4">
    <div class="col-12 col-xl-7">
        <div class="card table-card h-100">
            <div class="card-header">
                <strong><i class="fas fa-chart-line text-primary me-2"></i>Evolución mensual del rendimiento</strong>
            </div>
            <div class="card-body">
                <div class="chart-box" style="height:300px"><canvas id="chartRendimiento"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card table-card h-100">
            <div class="card-header">
                <strong><i class="fas fa-ranking-star text-warning me-2"></i>Mayor consumo por hora</strong>
            </div>
            <div class="card-body">
                <div class="chart-box" style="height:300px"><canvas id="chartRanking"></canvas></div>
            </div>
        </div>
    </div>
</div>

<?php if ($verEquipos): ?>
<!-- Rendimiento por equipo -->
<div class="card table-card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <strong><i class="fas fa-cogs text-warning me-2"></i>Rendimiento por equipo — litros por hora</strong>
        <small class="text-muted">Comparado con el promedio de su categoría</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Equipo</th>
                    <th>Categoría</th>
                    <th class="text-end">Cargas</th>
                    <th class="text-end">Litros</th>
                    <th class="text-end">Horas</th>
                    <th class="text-end">L/hora</th>
                    <th class="text-end">Costo/hora</th>
                    <th class="text-end">vs. Categoría</th>
                    <th class="text-center">Tendencia</th>
                    <th class="text-center">Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($equipos as $e):
                $lh    = (float)$e['horas'] > 0 ? $e['litros'] / $e['horas'] : 0;
                $cHora = (float)$e['horas'] > 0 ? $e['costo']  / $e['horas'] : 0;
                $cat   = $e['categoria'] ?: 'Sin categoría';
                $ref   = $promCatEq[$cat]['lh'] ?? 0;
                $desv  = $ref > 0 ? (($lh - $ref) / $ref) * 100 : 0;
                [$cls, $color, $texto, $icono] = semaforo($desv, 1);

                $lhRec  = (float)$e['horas_rec']  > 0 ? $e['litros_rec']  / $e['horas_rec']  : 0;
                $lhPrev = (float)$e['horas_prev'] > 0 ? $e['litros_prev'] / $e['horas_prev'] : 0;
                $tend   = ($lhPrev > 0 && $lhRec > 0) ? (($lhRec - $lhPrev) / $lhPrev) * 100 : null;
            ?>
                <tr>
                    <td>
                        <a href="<?= APP_URL ?>/modules/maquinaria/ficha?equipo_id=<?= (int)$e['equipo_id'] ?>"
                           class="text-decoration-none" title="Ver ficha completa del activo">
                            <span class="badge bg-secondary me-1"><?= h($e['codigo']) ?></span>
                        </a>
                        <div class="small text-muted mt-1"><?= h($e['nombre']) ?></div>
                    </td>
                    <td><small><?= h($cat) ?></small></td>
                    <td class="text-end"><?= (int)$e['cargas'] ?></td>
                    <td class="text-end"><?= formatNum((float)$e['litros'], 0) ?> L</td>
                    <td class="text-end"><?= formatNum((float)$e['horas'], 1) ?></td>
                    <td class="text-end"><strong class="text-<?= $color ?>"><?= formatNum($lh, 2) ?></strong></td>
                    <td class="text-end"><?= formatMoney($cHora) ?></td>
                    <td class="text-end">
                        <span class="text-<?= $desv > 0 ? 'danger' : 'success' ?>">
                            <?= $desv > 0 ? '+' : '' ?><?= formatNum($desv, 1) ?>%
                        </span>
                        <div class="text-muted" style="font-size:.7rem">ref. <?= formatNum($ref, 2) ?> L/h</div>
                    </td>
                    <td class="text-center">
                        <?php if ($tend === null): ?>
                            <span class="text-muted">—</span>
                        <?php else: ?>
                            <span class="badge bg-<?= $tend > 5 ? 'danger' : ($tend < -5 ? 'success' : 'secondary') ?>">
                                <i class="fas fa-<?= $tend > 5 ? 'arrow-up' : ($tend < -5 ? 'arrow-down' : 'minus') ?> me-1"></i>
                                <?= formatNum(abs($tend), 1) ?>%
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-<?= $color ?>"><i class="fas fa-<?= $icono ?> me-1"></i><?= $texto ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($equipos)): ?>
                <tr><td colspan="10" class="text-center text-muted py-4">Sin equipos con horómetro suficiente en el período.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<?php if ($verVehiculos): ?>
<!-- Rendimiento por vehículo -->
<div class="card table-card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <strong><i class="fas fa-truck text-primary me-2"></i>Rendimiento por vehículo — kilómetros por litro</strong>
        <small class="text-muted">Ordenado del menos al más eficiente</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Vehículo</th>
                    <th>Categoría</th>
                    <th class="text-end">Cargas</th>
                    <th class="text-end">Litros</th>
                    <th class="text-end">Km</th>
                    <th class="text-end">Km/L</th>
                    <th class="text-end">L/100 km</th>
                    <th class="text-end">Costo/km</th>
                    <th class="text-end">vs. Categoría</th>
                    <th class="text-center">Tendencia</th>
                    <th class="text-center">Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($vehiculos as $v):
                $kml   = (float)$v['litros'] > 0 ? $v['km'] / $v['litros'] : 0;
                $l100  = $kml > 0 ? 100 / $kml : 0;
                $cKm   = (float)$v['km'] > 0 ? $v['costo'] / $v['km'] : 0;
                $cat   = $v['categoria'] ?: 'Sin categoría';
                $ref   = $promCatVe[$cat]['kml'] ?? 0;
                $desv  = $ref > 0 ? (($kml - $ref) / $ref) * 100 : 0;
                [$cls, $color, $texto, $icono] = semaforo($desv, -1);

                $kmlRec  = (float)$v['litros_rec']  > 0 ? $v['km_rec']  / $v['litros_rec']  : 0;
                $kmlPrev = (float)$v['litros_prev'] > 0 ? $v['km_prev'] / $v['litros_prev'] : 0;
                $tend    = ($kmlPrev > 0 && $kmlRec > 0) ? (($kmlRec - $kmlPrev) / $kmlPrev) * 100 : null;
            ?>
                <tr>
                    <td>
                        <a href="<?= APP_URL ?>/modules/maquinaria/ficha?vehiculo_id=<?= (int)$v['vehiculo_id'] ?>"
                           class="text-decoration-none" title="Ver ficha completa del activo">
                            <span class="badge bg-dark me-1"><?= h($v['patente']) ?></span>
                        </a>
                        <div class="small text-muted mt-1"><?= h(trim($v['marca'] . ' ' . $v['modelo'])) ?></div>
                    </td>
                    <td><small><?= h($cat) ?></small></td>
                    <td class="text-end"><?= (int)$v['cargas'] ?></td>
                    <td class="text-end"><?= formatNum((float)$v['litros'], 0) ?> L</td>
                    <td class="text-end"><?= formatNum((float)$v['km'], 0) ?></td>
                    <td class="text-end"><strong class="text-<?= $color ?>"><?= formatNum($kml, 2) ?></strong></td>
                    <td class="text-end"><?= formatNum($l100, 1) ?></td>
                    <td class="text-end"><?= formatMoney($cKm) ?></td>
                    <td class="text-end">
                        <span class="text-<?= $desv < 0 ? 'danger' : 'success' ?>">
                            <?= $desv > 0 ? '+' : '' ?><?= formatNum($desv, 1) ?>%
                        </span>
                        <div class="text-muted" style="font-size:.7rem">ref. <?= formatNum($ref, 2) ?> km/L</div>
                    </td>
                    <td class="text-center">
                        <?php if ($tend === null): ?>
                            <span class="text-muted">—</span>
                        <?php else: ?>
                            <span class="badge bg-<?= $tend < -5 ? 'danger' : ($tend > 5 ? 'success' : 'secondary') ?>">
                                <i class="fas fa-<?= $tend < -5 ? 'arrow-down' : ($tend > 5 ? 'arrow-up' : 'minus') ?> me-1"></i>
                                <?= formatNum(abs($tend), 1) ?>%
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-<?= $color ?>"><i class="fas fa-<?= $icono ?> me-1"></i><?= $texto ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($vehiculos)): ?>
                <tr><td colspan="11" class="text-center text-muted py-4">Sin vehículos con odómetro suficiente en el período.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; ?>

<!-- Cargas fuera de patrón -->
<div class="card table-card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <strong><i class="fas fa-triangle-exclamation text-danger me-2"></i>Cargas fuera de patrón</strong>
        <small class="text-muted">Consumo muy por sobre el histórico del propio activo</small>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Unidad</th>
                    <th class="text-end">Litros</th>
                    <th class="text-end">Medición</th>
                    <th class="text-end">Habitual</th>
                    <th class="text-end">Desviación</th>
                    <th class="text-end">Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($anomalias as $a):
                $esEquipo = $a['tipo_unidad'] === 'equipo';
                $valor    = $esEquipo ? (float)$a['lh']      : (float)$a['kml'];
                $ref      = $esEquipo ? (float)$a['lh_prom'] : (float)$a['kml_prom'];
                $desv     = $ref > 0 ? (($valor - $ref) / $ref) * 100 : 0;
            ?>
                <tr>
                    <td><strong><?= formatDate($a['fecha']) ?></strong></td>
                    <td>
                        <?php if ($esEquipo): ?>
                            <i class="fas fa-cogs text-warning me-1"></i><?= h($a['equipo_codigo'] ?? '—') ?>
                        <?php else: ?>
                            <i class="fas fa-truck text-primary me-1"></i><?= h($a['patente'] ?? '—') ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-end"><?= formatNum((float)$a['litros'], 1) ?> L</td>
                    <td class="text-end"><strong class="text-danger"><?= formatNum($valor, 2) ?></strong>
                        <small class="text-muted"><?= $esEquipo ? 'L/h' : 'km/L' ?></small></td>
                    <td class="text-end"><?= formatNum($ref, 2) ?></td>
                    <td class="text-end">
                        <span class="badge bg-danger"><?= $desv > 0 ? '+' : '' ?><?= formatNum($desv, 0) ?>%</span>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-info btn-action btn-view js-view"
                                data-vista="combustible" data-id="<?= (int)$a['id'] ?>" title="Ver carga">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($anomalias)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">
                    <i class="fas fa-circle-check text-success me-1"></i>
                    Sin cargas fuera de patrón en el período.
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    const meses = <?= json_encode($gMeses) ?>;
    const lh    = <?= json_encode($gLh) ?>;
    const kml   = <?= json_encode($gKml) ?>;

    const ctx = document.getElementById('chartRendimiento');
    if (ctx && meses.length) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: meses,
                datasets: [
                    {
                        label: 'Equipos · L/hora',
                        data: lh,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245,158,11,.12)',
                        tension: .35, fill: true, yAxisID: 'y', spanGaps: true,
                    },
                    {
                        label: 'Vehículos · km/L',
                        data: kml,
                        borderColor: '#06b6d4',
                        backgroundColor: 'rgba(6,182,212,.10)',
                        tension: .35, fill: true, yAxisID: 'y1', spanGaps: true,
                    },
                ],
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
                scales: {
                    y:  { position: 'left',  title: { display: true, text: 'L/hora' },  beginAtZero: false },
                    y1: { position: 'right', title: { display: true, text: 'km/litro' }, beginAtZero: false,
                          grid: { drawOnChartArea: false } },
                },
            },
        });
    }

    const rank = <?= json_encode(array_map(function ($e) {
        $lh = (float)$e['horas'] > 0 ? round($e['litros'] / $e['horas'], 2) : 0;
        return ['codigo' => $e['codigo'], 'lh' => $lh];
    }, array_slice($equipos, 0, 8))) ?>;

    const ctx2 = document.getElementById('chartRanking');
    if (ctx2 && rank.length) {
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: rank.map(r => r.codigo),
                datasets: [{
                    label: 'L/hora',
                    data: rank.map(r => r.lh),
                    backgroundColor: rank.map((r, i) => i < 3 ? 'rgba(239,68,68,.75)' : 'rgba(245,158,11,.65)'),
                    borderRadius: 6,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { title: { display: true, text: 'Litros por hora' } } },
            },
        });
    }
})();
</script>

<?php endif; ?>

<?php include ROOT . '/includes/footer.php'; ?>
