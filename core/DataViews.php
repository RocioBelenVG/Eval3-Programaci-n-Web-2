<?php
/**
 * TriTransportes — DataViews
 * ----------------------------------------------------------------------------
 * Definición central (única fuente de verdad) de cada "vista" de datos de los
 * módulos. La usan por igual:
 *   - api/v1/export.php  → exportar a Excel respetando los filtros del usuario
 *   - api/v1/record.php  → visualizar un registro individual (modal)
 *
 * Cada vista define: el permiso requerido, el SELECT/JOIN, el ORDER, la lógica
 * de filtros (idéntica a la del listado del módulo) y la lista de campos con su
 * tipo de formato. Así, exportación y visualización quedan siempre sincronizadas
 * con lo que el usuario ve en pantalla.
 */
class DataViews
{
    /** Devuelve la definición de una vista o null si no existe. */
    public static function get(string $vista): ?array
    {
        $v = self::all();
        return $v[$vista] ?? null;
    }

    /**
     * Construye la consulta SQL completa (sin LIMIT) para una vista, aplicando
     * el scope de empresa + los filtros recibidos por GET. Si $id > 0 se filtra
     * por ese registro puntual e ignora el resto de filtros.
     *
     * @return array{sql:string, params:array, def:array}
     */
    public static function buildQuery(string $vista, int $empresaId, array $g, int $id = 0): array
    {
        $def = self::get($vista);
        if (!$def) {
            throw new RuntimeException('Vista desconocida: ' . $vista);
        }

        $alias  = $def['alias'];
        $where  = "WHERE {$alias}.empresa_id = ?";
        $params = [$empresaId];

        if (!empty($def['extra_where'])) {
            $where .= ' ' . $def['extra_where'];
        }

        if ($id > 0) {
            $where .= " AND {$alias}.id = ?";
            $params[] = $id;
        } elseif (isset($def['filtros']) && is_callable($def['filtros'])) {
            [$frag, $fparams] = ($def['filtros'])($g);
            if ($frag !== '') {
                $where .= $frag;
                $params = array_merge($params, $fparams);
            }
        }

        $sql = "SELECT {$def['select']} FROM {$def['from']} {$where} ORDER BY {$def['order']}";

        return ['sql' => $sql, 'params' => $params, 'def' => $def];
    }

    /** Diccionario completo de vistas. */
    public static function all(): array
    {
        return [

            // ───────────────────────── PARTES DE FAENA ─────────────────────────
            'faena' => [
                'permiso' => 'faena',
                'titulo'  => 'Partes de Faena',
                'icono'   => 'hard-hat',
                'edit'    => 'modules/faena/parte?id={id}',
                'alias'   => 'p',
                'select'  => "p.*, o.nombre AS obra_nombre, c.numero AS contrato_numero,
                              CONCAT(t.nombre,' ',t.apellido) AS supervisor_nombre, u.nombre AS creado_nombre",
                'from'    => "partes_diarios p
                              LEFT JOIN obras o        ON o.id = p.obra_id
                              LEFT JOIN contratos c    ON c.id = p.contrato_id
                              LEFT JOIN trabajadores t ON t.id = p.supervisor_id
                              LEFT JOIN usuarios u     ON u.id = p.creado_por",
                'order'   => 'p.fecha DESC, p.id DESC',
                'filtros' => fn(array $g) => self::fechaObra($g, 'p'),
                'campos'  => [
                    ['k'=>'fecha',             'l'=>'Fecha',         't'=>'fecha',     'w'=>14],
                    ['k'=>'obra_nombre',       'l'=>'Obra',          't'=>'texto',     'w'=>26],
                    ['k'=>'contrato_numero',   'l'=>'Contrato',      't'=>'texto',     'w'=>16],
                    ['k'=>'supervisor_nombre', 'l'=>'Supervisor',    't'=>'texto',     'w'=>24],
                    ['k'=>'clima',             'l'=>'Clima',         't'=>'texto',     'w'=>14],
                    ['k'=>'estado',            'l'=>'Estado',        't'=>'badge',     'w'=>14],
                    ['k'=>'observaciones',     'l'=>'Observaciones', 't'=>'largo',     'w'=>40],
                    ['k'=>'creado_nombre',     'l'=>'Registró',      't'=>'texto',     'w'=>22],
                    ['k'=>'created_at',        'l'=>'Creado',        't'=>'fechahora', 'w'=>18, 'view_only'=>true],
                    ['k'=>'firma_b64',         'l'=>'Firma',         't'=>'firma',     'view_only'=>true],
                    ['k'=>'__coords',          'l'=>'Ubicación',     't'=>'coords',    'view_only'=>true],
                ],
            ],

            // ───────────────────────── COMBUSTIBLE ─────────────────────────────
            'combustible' => [
                'permiso' => 'combustible',
                'titulo'  => 'Cargas de Combustible',
                'icono'   => 'gas-pump',
                'edit'    => 'modules/combustible/carga?id={id}',
                'alias'   => 'c',
                'select'  => "c.*, o.nombre AS obra_nombre, v.patente,
                              eq.codigo AS equipo_codigo, eq.nombre AS equipo_nombre,
                              CONCAT(tv.nombre,' ',tv.apellido) AS conductor_nombre,
                              CONCAT(to2.nombre,' ',to2.apellido) AS operador_nombre,
                              f.nombre AS fuente_nombre, u.nombre AS creado_nombre",
                'from'    => "cargas_combustible c
                              LEFT JOIN obras o        ON o.id  = c.obra_id
                              LEFT JOIN vehiculos v    ON v.id  = c.vehiculo_id
                              LEFT JOIN equipos eq     ON eq.id = c.equipo_id
                              LEFT JOIN conductores cn ON cn.id = c.conductor_id
                              LEFT JOIN trabajadores tv ON tv.id = cn.trabajador_id
                              LEFT JOIN operadores op   ON op.id = c.operador_id
                              LEFT JOIN trabajadores to2 ON to2.id = op.trabajador_id
                              LEFT JOIN fuentes_combustible f ON f.id = c.fuente_id
                              LEFT JOIN usuarios u     ON u.id  = c.creado_por",
                'order'   => 'c.fecha DESC, c.id DESC',
                'filtros' => function(array $g) {
                    [$w, $p] = self::fechaObra($g, 'c');
                    $tu = trim((string)($g['tipo_unidad'] ?? ''));
                    if ($tu !== '') { $w .= " AND c.tipo_unidad = ?"; $p[] = $tu; }
                    return [$w, $p];
                },
                'campos'  => [
                    ['k'=>'fecha',            'l'=>'Fecha',          't'=>'fecha',     'w'=>14],
                    ['k'=>'tipo_unidad',      'l'=>'Tipo',           't'=>'badge',     'w'=>12],
                    ['k'=>'patente',          'l'=>'Vehículo',       't'=>'texto',     'w'=>14],
                    ['k'=>'equipo_codigo',    'l'=>'Equipo',         't'=>'texto',     'w'=>14],
                    ['k'=>'obra_nombre',      'l'=>'Obra',           't'=>'texto',     'w'=>24],
                    ['k'=>'litros',           'l'=>'Litros',         't'=>'num2',      'w'=>12],
                    ['k'=>'precio_litro',     'l'=>'Precio/Litro',   't'=>'dinero',    'w'=>14],
                    ['k'=>'tipo_combustible', 'l'=>'Combustible',    't'=>'texto',     'w'=>14],
                    ['k'=>'horometro',        'l'=>'Horómetro',      't'=>'num2',      'w'=>14],
                    ['k'=>'odometro',         'l'=>'Odómetro',       't'=>'num',       'w'=>12],
                    ['k'=>'conductor_nombre', 'l'=>'Conductor',      't'=>'texto',     'w'=>22],
                    ['k'=>'operador_nombre',  'l'=>'Operador',       't'=>'texto',     'w'=>22],
                    ['k'=>'fuente_nombre',    'l'=>'Fuente',         't'=>'texto',     'w'=>18],
                    ['k'=>'observacion',      'l'=>'Observación',    't'=>'largo',     'w'=>40],
                    ['k'=>'creado_nombre',    'l'=>'Registró',       't'=>'texto',     'w'=>20, 'view_only'=>true],
                    ['k'=>'created_at',       'l'=>'Creado',         't'=>'fechahora', 'w'=>18, 'view_only'=>true],
                    ['k'=>'foto_ruta',        'l'=>'Foto',           't'=>'foto',      'view_only'=>true],
                    ['k'=>'firma_b64',        'l'=>'Firma',          't'=>'firma',     'view_only'=>true],
                    ['k'=>'__coords',         'l'=>'Ubicación',      't'=>'coords',    'view_only'=>true],
                ],
            ],

            // ───────────────────────── TRANSPORTE · VIAJES ─────────────────────
            'transporte_viajes' => [
                'permiso' => 'transporte',
                'titulo'  => 'Viajes de Transporte',
                'icono'   => 'truck',
                'edit'    => 'modules/transporte/viaje?id={id}',
                'alias'   => 'v',
                'select'  => "v.*, ve.patente, ve.marca AS veh_marca,
                              CONCAT(tc.nombre,' ',tc.apellido) AS conductor_nombre,
                              o.nombre AS obra_nombre, m.nombre AS material_nombre",
                'from'    => "viajes v
                              LEFT JOIN vehiculos  ve ON ve.id = v.vehiculo_id
                              LEFT JOIN conductores cc ON cc.id = v.conductor_id
                              LEFT JOIN trabajadores tc ON tc.id = cc.trabajador_id
                              LEFT JOIN obras       o ON o.id  = v.obra_id
                              LEFT JOIN materiales  m ON m.id  = v.material_id",
                'order'   => 'v.fecha DESC, v.id DESC',
                'filtros' => function(array $g) {
                    [$w, $p] = self::fechaObra($g, 'v');
                    $e = trim((string)($g['estado'] ?? ''));
                    if ($e !== '') { $w .= " AND v.estado = ?"; $p[] = $e; }
                    return [$w, $p];
                },
                'campos'  => [
                    ['k'=>'fecha',            'l'=>'Fecha',         't'=>'fecha',     'w'=>14],
                    ['k'=>'patente',          'l'=>'Vehículo',      't'=>'texto',     'w'=>14],
                    ['k'=>'conductor_nombre', 'l'=>'Conductor',     't'=>'texto',     'w'=>22],
                    ['k'=>'obra_nombre',      'l'=>'Obra',          't'=>'texto',     'w'=>22],
                    ['k'=>'origen',           'l'=>'Origen',        't'=>'texto',     'w'=>22],
                    ['k'=>'destino',          'l'=>'Destino',       't'=>'texto',     'w'=>22],
                    ['k'=>'hora_salida',      'l'=>'Hora Salida',   't'=>'texto',     'w'=>14],
                    ['k'=>'hora_llegada',     'l'=>'Hora Llegada',  't'=>'texto',     'w'=>14],
                    ['k'=>'km_inicio',        'l'=>'Km Inicio',     't'=>'num',       'w'=>12],
                    ['k'=>'km_fin',           'l'=>'Km Fin',        't'=>'num',       'w'=>12],
                    ['k'=>'material_nombre',  'l'=>'Material',      't'=>'texto',     'w'=>20],
                    ['k'=>'cantidad',         'l'=>'Cantidad',      't'=>'num2',      'w'=>12],
                    ['k'=>'unidad',           'l'=>'Unidad',        't'=>'texto',     'w'=>10],
                    ['k'=>'estado',           'l'=>'Estado',        't'=>'badge',     'w'=>14],
                    ['k'=>'observaciones',    'l'=>'Observaciones', 't'=>'largo',     'w'=>40],
                    ['k'=>'created_at',       'l'=>'Creado',        't'=>'fechahora', 'w'=>18, 'view_only'=>true],
                ],
            ],

            // ───────────────────────── TRANSPORTE · GUÍAS ──────────────────────
            'transporte_guias' => [
                'permiso' => 'transporte',
                'titulo'  => 'Guías de Despacho',
                'icono'   => 'file-invoice',
                'edit'    => 'modules/transporte/guia?id={id}',
                'alias'   => 'g',
                'select'  => "g.*, ve.patente,
                              CONCAT(tc.nombre,' ',tc.apellido) AS conductor_nombre,
                              o.nombre AS obra_nombre, m.nombre AS material_nombre",
                'from'    => "guias_despacho g
                              LEFT JOIN vehiculos  ve ON ve.id = g.vehiculo_id
                              LEFT JOIN conductores cc ON cc.id = g.conductor_id
                              LEFT JOIN trabajadores tc ON tc.id = cc.trabajador_id
                              LEFT JOIN obras       o ON o.id  = g.obra_id
                              LEFT JOIN materiales  m ON m.id  = g.material_id",
                'order'   => 'g.fecha DESC, g.id DESC',
                'filtros' => function(array $g) {
                    [$w, $p] = self::fechaObra($g, 'g');
                    $q = trim((string)($g['q'] ?? ''));
                    if ($q !== '') { $w .= " AND g.numero_guia LIKE ?"; $p[] = '%' . $q . '%'; }
                    return [$w, $p];
                },
                'campos'  => [
                    ['k'=>'numero_guia',      'l'=>'N° Guía',       't'=>'texto',     'w'=>16],
                    ['k'=>'fecha',            'l'=>'Fecha',         't'=>'fecha',     'w'=>14],
                    ['k'=>'patente',          'l'=>'Vehículo',      't'=>'texto',     'w'=>14],
                    ['k'=>'conductor_nombre', 'l'=>'Conductor',     't'=>'texto',     'w'=>22],
                    ['k'=>'obra_nombre',      'l'=>'Obra',          't'=>'texto',     'w'=>22],
                    ['k'=>'material_nombre',  'l'=>'Material',      't'=>'texto',     'w'=>20],
                    ['k'=>'cantidad',         'l'=>'Cantidad',      't'=>'num2',      'w'=>12],
                    ['k'=>'unidad',           'l'=>'Unidad',        't'=>'texto',     'w'=>10],
                    ['k'=>'origen',           'l'=>'Origen',        't'=>'texto',     'w'=>22],
                    ['k'=>'destino',          'l'=>'Destino',       't'=>'texto',     'w'=>22],
                    ['k'=>'receptor',         'l'=>'Receptor',      't'=>'texto',     'w'=>22],
                    ['k'=>'rut_receptor',     'l'=>'RUT Receptor',  't'=>'texto',     'w'=>16],
                    ['k'=>'observaciones',    'l'=>'Observaciones', 't'=>'largo',     'w'=>40],
                    ['k'=>'foto_ruta',        'l'=>'Foto',          't'=>'foto',      'view_only'=>true],
                    ['k'=>'firma_b64',        'l'=>'Firma recepción','t'=>'firma',    'view_only'=>true],
                    ['k'=>'__coords',         'l'=>'Ubicación',     't'=>'coords',    'view_only'=>true],
                ],
            ],

            // ───────────────────────── ÁRIDOS · PRODUCCIÓN ─────────────────────
            'aridos_produccion' => [
                'permiso' => 'aridos',
                'titulo'  => 'Producción de Áridos',
                'icono'   => 'industry',
                'edit'    => 'modules/aridos/produccion?id={id}',
                'alias'   => 'pr',
                'select'  => "pr.*, m.nombre AS mat_nombre, cl.nombre AS clas_nombre,
                              o.nombre AS obra_nombre, e.codigo AS equipo_codigo,
                              CONCAT(tt.nombre,' ',tt.apellido) AS operador_nombre",
                'from'    => "produccion_aridos pr
                              LEFT JOIN materiales m  ON m.id = pr.material_id
                              LEFT JOIN clasificaciones_arido cl ON cl.id = pr.clasificacion_id
                              LEFT JOIN obras o       ON o.id = pr.obra_id
                              LEFT JOIN equipos e     ON e.id = pr.equipo_id
                              LEFT JOIN operadores op ON op.id = pr.operador_id
                              LEFT JOIN trabajadores tt ON tt.id = op.trabajador_id",
                'order'   => 'pr.fecha DESC, pr.id DESC',
                'filtros' => fn(array $g) => self::fechaObraMaterial($g, 'pr'),
                'campos'  => [
                    ['k'=>'fecha',           'l'=>'Fecha',         't'=>'fecha', 'w'=>14],
                    ['k'=>'mat_nombre',      'l'=>'Material',      't'=>'texto', 'w'=>22],
                    ['k'=>'clas_nombre',     'l'=>'Clasificación', 't'=>'texto', 'w'=>20],
                    ['k'=>'obra_nombre',     'l'=>'Obra',          't'=>'texto', 'w'=>22],
                    ['k'=>'turno',           'l'=>'Turno',         't'=>'badge', 'w'=>12],
                    ['k'=>'equipo_codigo',   'l'=>'Equipo',        't'=>'texto', 'w'=>14],
                    ['k'=>'operador_nombre', 'l'=>'Operador',      't'=>'texto', 'w'=>22],
                    ['k'=>'cantidad',        'l'=>'Cantidad',      't'=>'num2',  'w'=>12],
                    ['k'=>'unidad',          'l'=>'Unidad',        't'=>'texto', 'w'=>10],
                    ['k'=>'observaciones',   'l'=>'Observaciones', 't'=>'largo', 'w'=>40],
                ],
            ],

            // ───────────────────────── ÁRIDOS · DESPACHOS ──────────────────────
            'aridos_despachos' => [
                'permiso' => 'aridos',
                'titulo'  => 'Despachos de Áridos',
                'icono'   => 'truck-loading',
                'edit'    => 'modules/aridos/despacho?id={id}',
                'alias'   => 'd',
                'select'  => "d.*, m.nombre AS mat_nombre, cl.nombre AS clas_nombre,
                              o.nombre AS obra_nombre, cli.razon_social AS cliente_nombre,
                              ve.patente, CONCAT(tc.nombre,' ',tc.apellido) AS conductor_nombre",
                'from'    => "despachos_aridos d
                              LEFT JOIN materiales m  ON m.id = d.material_id
                              LEFT JOIN clasificaciones_arido cl ON cl.id = d.clasificacion_id
                              LEFT JOIN obras o       ON o.id = d.obra_id
                              LEFT JOIN clientes cli  ON cli.id = d.cliente_id
                              LEFT JOIN vehiculos ve  ON ve.id = d.vehiculo_id
                              LEFT JOIN conductores cc ON cc.id = d.conductor_id
                              LEFT JOIN trabajadores tc ON tc.id = cc.trabajador_id",
                'order'   => 'd.fecha DESC, d.id DESC',
                'filtros' => fn(array $g) => self::fechaObraMaterial($g, 'd'),
                'campos'  => [
                    ['k'=>'fecha',            'l'=>'Fecha',          't'=>'fecha',  'w'=>14],
                    ['k'=>'guia_numero',      'l'=>'N° Guía',        't'=>'texto',  'w'=>16],
                    ['k'=>'mat_nombre',       'l'=>'Material',       't'=>'texto',  'w'=>22],
                    ['k'=>'clas_nombre',      'l'=>'Clasificación',  't'=>'texto',  'w'=>20],
                    ['k'=>'obra_nombre',      'l'=>'Obra',           't'=>'texto',  'w'=>22],
                    ['k'=>'cliente_nombre',   'l'=>'Cliente',        't'=>'texto',  'w'=>26],
                    ['k'=>'destino',          'l'=>'Destino',        't'=>'texto',  'w'=>22],
                    ['k'=>'receptor',         'l'=>'Receptor',       't'=>'texto',  'w'=>22],
                    ['k'=>'rut_receptor',     'l'=>'RUT Receptor',   't'=>'texto',  'w'=>16],
                    ['k'=>'patente',          'l'=>'Vehículo',       't'=>'texto',  'w'=>14],
                    ['k'=>'conductor_nombre', 'l'=>'Conductor',      't'=>'texto',  'w'=>22],
                    ['k'=>'cantidad',         'l'=>'Cantidad',       't'=>'num2',   'w'=>12],
                    ['k'=>'unidad',           'l'=>'Unidad',         't'=>'texto',  'w'=>10],
                    ['k'=>'precio_unitario',  'l'=>'Precio Unit.',   't'=>'dinero', 'w'=>14],
                    ['k'=>'observaciones',    'l'=>'Observaciones',  't'=>'largo',  'w'=>40],
                    ['k'=>'foto_ruta',        'l'=>'Foto',           't'=>'foto',   'view_only'=>true],
                    ['k'=>'firma_b64',        'l'=>'Firma recepción','t'=>'firma',  'view_only'=>true],
                    ['k'=>'__coords',         'l'=>'Ubicación',      't'=>'coords', 'view_only'=>true],
                ],
            ],

            // ───────────────────────── SEGURIDAD · CHECKLISTS ──────────────────
            'seguridad_checklists' => [
                'permiso' => 'seguridad',
                'titulo'  => 'Checklists de Inspección',
                'icono'   => 'clipboard-check',
                'edit'    => 'modules/seguridad/checklist?id={id}',
                'alias'   => 'cr',
                'select'  => "cr.*, tc.nombre AS tipo_nombre, o.nombre AS obra_nombre,
                              e.codigo AS equipo_codigo, ve.patente,
                              CONCAT(t.nombre,' ',t.apellido) AS trab_nombre,
                              u.nombre AS creado_nombre",
                'from'    => "checklists_registros cr
                              LEFT JOIN tipos_checklist tc ON tc.id = cr.tipo_id
                              LEFT JOIN obras o       ON o.id = cr.obra_id
                              LEFT JOIN equipos e     ON e.id = cr.equipo_id
                              LEFT JOIN vehiculos ve  ON ve.id = cr.vehiculo_id
                              LEFT JOIN trabajadores t ON t.id = cr.trabajador_id
                              LEFT JOIN usuarios u    ON u.id = cr.creado_por",
                'order'   => 'cr.fecha DESC, cr.id DESC',
                'filtros' => fn(array $g) => self::fechaObra($g, 'cr'),
                'campos'  => [
                    ['k'=>'fecha',          'l'=>'Fecha',         't'=>'fecha',     'w'=>14],
                    ['k'=>'tipo_nombre',    'l'=>'Tipo',          't'=>'texto',     'w'=>24],
                    ['k'=>'obra_nombre',    'l'=>'Obra',          't'=>'texto',     'w'=>22],
                    ['k'=>'turno',          'l'=>'Turno',         't'=>'badge',     'w'=>12],
                    ['k'=>'equipo_codigo',  'l'=>'Equipo',        't'=>'texto',     'w'=>14],
                    ['k'=>'patente',        'l'=>'Vehículo',      't'=>'texto',     'w'=>14],
                    ['k'=>'trab_nombre',    'l'=>'Trabajador',    't'=>'texto',     'w'=>22],
                    ['k'=>'resultado',      'l'=>'Resultado',     't'=>'badge',     'w'=>14],
                    ['k'=>'observaciones',  'l'=>'Observaciones', 't'=>'largo',     'w'=>40],
                    ['k'=>'creado_nombre',  'l'=>'Registró',      't'=>'texto',     'w'=>20, 'view_only'=>true],
                    ['k'=>'created_at',     'l'=>'Creado',        't'=>'fechahora', 'w'=>18, 'view_only'=>true],
                    ['k'=>'firma_b64',      'l'=>'Firma',         't'=>'firma',     'view_only'=>true],
                    ['k'=>'__coords',       'l'=>'Ubicación',     't'=>'coords',    'view_only'=>true],
                ],
            ],

            // ───────────────────────── SEGURIDAD · INCIDENTES ──────────────────
            'seguridad_incidentes' => [
                'permiso' => 'seguridad',
                'titulo'  => 'Incidentes de Seguridad',
                'icono'   => 'triangle-exclamation',
                'edit'    => 'modules/seguridad/incidente?id={id}',
                'alias'   => 'i',
                'select'  => "i.*, o.nombre AS obra_nombre,
                              CONCAT(t.nombre,' ',t.apellido) AS trab_nombre",
                'from'    => "incidentes i
                              LEFT JOIN obras o        ON o.id = i.obra_id
                              LEFT JOIN trabajadores t ON t.id = i.trabajador_id",
                'order'   => 'i.fecha DESC, i.id DESC',
                'filtros' => fn(array $g) => self::fechaObra($g, 'i'),
                'campos'  => [
                    ['k'=>'fecha',          'l'=>'Fecha',          't'=>'fecha',     'w'=>14],
                    ['k'=>'hora',           'l'=>'Hora',           't'=>'texto',     'w'=>10],
                    ['k'=>'tipo',           'l'=>'Tipo',           't'=>'badge',     'w'=>16],
                    ['k'=>'gravedad',       'l'=>'Gravedad',       't'=>'badge',     'w'=>14],
                    ['k'=>'descripcion',    'l'=>'Descripción',    't'=>'largo',     'w'=>40],
                    ['k'=>'lugar',          'l'=>'Lugar',          't'=>'texto',     'w'=>22],
                    ['k'=>'obra_nombre',    'l'=>'Obra',           't'=>'texto',     'w'=>22],
                    ['k'=>'trab_nombre',    'l'=>'Trabajador',     't'=>'texto',     'w'=>22],
                    ['k'=>'dias_perdidos',  'l'=>'Días Perdidos',  't'=>'num',       'w'=>14],
                    ['k'=>'acciones',       'l'=>'Acciones',       't'=>'largo',     'w'=>40],
                    ['k'=>'estado',         'l'=>'Estado',         't'=>'badge',     'w'=>14],
                    ['k'=>'created_at',     'l'=>'Creado',         't'=>'fechahora', 'w'=>18, 'view_only'=>true],
                    ['k'=>'foto_ruta',      'l'=>'Foto',           't'=>'foto',      'view_only'=>true],
                    ['k'=>'firma_b64',      'l'=>'Firma reporte',  't'=>'firma',     'view_only'=>true],
                    ['k'=>'__coords',       'l'=>'Ubicación',      't'=>'coords',    'view_only'=>true],
                ],
            ],

            // ───────────────────────── CONTRATOS ───────────────────────────────
            'contratos' => [
                'permiso'     => 'contratos',
                'titulo'      => 'Contratos',
                'icono'       => 'file-contract',
                'edit'        => 'modules/contratos/avance?contrato_id={id}',
                'alias'       => 'c',
                'extra_where' => 'AND c.activo = 1',
                'select'      => "c.*, o.nombre AS obra_nombre, cl.razon_social AS cliente_nombre,
                                  ec.nombre AS estado_nombre, ec.color AS estado_color,
                                  COALESCE((SELECT SUM(monto) FROM avances_contrato WHERE contrato_id = c.id),0) AS monto_avanzado",
                'from'        => "contratos c
                                  LEFT JOIN obras o           ON o.id = c.obra_id
                                  LEFT JOIN clientes cl       ON cl.id = c.cliente_id
                                  LEFT JOIN estados_contrato ec ON ec.id = c.estado_id",
                'order'       => 'c.fecha_inicio DESC, c.id DESC',
                'filtros'     => function(array $g) {
                    $w = ''; $p = [];
                    $q = trim((string)($g['q'] ?? ''));
                    if ($q !== '') { $w .= " AND (c.numero LIKE ? OR c.descripcion LIKE ?)"; $p[] = "%{$q}%"; $p[] = "%{$q}%"; }
                    $e = (int)($g['estado_id'] ?? 0);
                    if ($e) { $w .= " AND c.estado_id = ?"; $p[] = $e; }
                    return [$w, $p];
                },
                'campos'      => [
                    ['k'=>'numero',         'l'=>'N° Contrato',   't'=>'texto',  'w'=>16],
                    ['k'=>'descripcion',    'l'=>'Descripción',   't'=>'texto',  'w'=>38],
                    ['k'=>'tipo',           'l'=>'Tipo',          't'=>'texto',  'w'=>18],
                    ['k'=>'obra_nombre',    'l'=>'Obra',          't'=>'texto',  'w'=>24],
                    ['k'=>'cliente_nombre', 'l'=>'Cliente',       't'=>'texto',  'w'=>26],
                    ['k'=>'monto_contrato', 'l'=>'Monto Contrato','t'=>'dinero', 'w'=>18],
                    ['k'=>'monto_avanzado', 'l'=>'Monto Avanzado','t'=>'dinero', 'w'=>18],
                    ['k'=>'fecha_inicio',   'l'=>'Inicio',        't'=>'fecha',  'w'=>14],
                    ['k'=>'fecha_termino',  'l'=>'Término',       't'=>'fecha',  'w'=>14],
                    ['k'=>'estado_nombre',  'l'=>'Estado',        't'=>'texto',  'w'=>16],
                ],
            ],

            // ───────────────────────── DOCUMENTOS ──────────────────────────────
            'documentos' => [
                'permiso'     => 'documentos',
                'titulo'      => 'Control Documental',
                'icono'       => 'folder-open',
                'edit'        => 'modules/documentos/documento?id={id}',
                'alias'       => 'd',
                'extra_where' => 'AND d.activo = 1',
                'select'      => "d.*, td.nombre AS tipo_nombre, ed.nombre AS estado_nombre, o.nombre AS obra_nombre",
                'from'        => "documentos d
                                  LEFT JOIN tipos_documento td  ON td.id = d.tipo_id
                                  LEFT JOIN estados_documento ed ON ed.id = d.estado_id
                                  LEFT JOIN obras o             ON o.id = d.obra_id",
                'order'       => "CASE WHEN d.fecha_vencimiento IS NULL THEN 1 ELSE 0 END, d.fecha_vencimiento ASC",
                'filtros'     => function(array $g) {
                    $w = ''; $p = [];
                    $hoy = date('Y-m-d');
                    $q = trim((string)($g['q'] ?? ''));
                    if ($q !== '') { $w .= " AND (d.titulo LIKE ? OR d.numero LIKE ?)"; $p[] = "%{$q}%"; $p[] = "%{$q}%"; }
                    $t = (int)($g['tipo_id'] ?? 0);
                    if ($t) { $w .= " AND d.tipo_id = ?"; $p[] = $t; }
                    $et = trim((string)($g['entidad_tipo'] ?? ''));
                    if ($et !== '') { $w .= " AND d.entidad_tipo = ?"; $p[] = $et; }
                    $obra = (int)($g['obra_id'] ?? 0);
                    if ($obra) { $w .= " AND d.obra_id = ?"; $p[] = $obra; }
                    $a = trim((string)($g['alerta'] ?? ''));
                    if ($a === 'vencido')      { $w .= " AND d.fecha_vencimiento < ?"; $p[] = $hoy; }
                    elseif ($a === 'prox15')   { $w .= " AND d.fecha_vencimiento BETWEEN ? AND DATE_ADD(?, INTERVAL 15 DAY)"; $p[] = $hoy; $p[] = $hoy; }
                    elseif ($a === 'prox30')   { $w .= " AND d.fecha_vencimiento BETWEEN ? AND DATE_ADD(?, INTERVAL 30 DAY)"; $p[] = $hoy; $p[] = $hoy; }
                    return [$w, $p];
                },
                'campos'      => [
                    ['k'=>'titulo',            'l'=>'Título',        't'=>'texto', 'w'=>34],
                    ['k'=>'numero',            'l'=>'N°',            't'=>'texto', 'w'=>16],
                    ['k'=>'tipo_nombre',       'l'=>'Tipo',          't'=>'texto', 'w'=>20],
                    ['k'=>'entidad_tipo',      'l'=>'Entidad',       't'=>'texto', 'w'=>16],
                    ['k'=>'obra_nombre',       'l'=>'Obra',          't'=>'texto', 'w'=>22],
                    ['k'=>'fecha_emision',     'l'=>'Emisión',       't'=>'fecha', 'w'=>14],
                    ['k'=>'fecha_vencimiento', 'l'=>'Vencimiento',   't'=>'fecha', 'w'=>14],
                    ['k'=>'estado_nombre',     'l'=>'Estado',        't'=>'texto', 'w'=>16],
                    ['k'=>'observaciones',     'l'=>'Observaciones', 't'=>'largo', 'w'=>40],
                    ['k'=>'ruta_archivo',      'l'=>'Archivo',       't'=>'archivo','view_only'=>true],
                ],
            ],

            // ───────────────────────── MAQUINARIA · FLOTA ──────────────────────
            'maquinaria_flota' => [
                'permiso'     => 'maquinaria',
                'titulo'      => 'Flota de Equipos',
                'icono'       => 'cogs',
                'edit'        => 'modules/maquinaria/mantencion?equipo_id={id}',
                'alias'       => 'eq',
                'extra_where' => 'AND eq.activo = 1',
                'select'      => "eq.*, m.nombre AS marca_nombre, c.nombre AS categoria_nombre,
                                  (SELECT estado FROM mantenciones WHERE equipo_id = eq.id AND estado IN ('pendiente','en_proceso') LIMIT 1) AS mant_activa",
                'from'        => "equipos eq
                                  LEFT JOIN marcas_equipo m   ON m.id = eq.marca_id
                                  LEFT JOIN categorias_equipo c ON c.id = eq.categoria_id",
                'order'       => 'eq.codigo',
                'filtros'     => function(array $g) {
                    $w = ''; $p = [];
                    $q = trim((string)($g['q'] ?? ''));
                    if ($q !== '') { $w .= " AND (eq.codigo LIKE ? OR eq.nombre LIKE ?)"; $p[] = "%{$q}%"; $p[] = "%{$q}%"; }
                    return [$w, $p];
                },
                'campos'      => [
                    ['k'=>'codigo',           'l'=>'Código',     't'=>'texto', 'w'=>14],
                    ['k'=>'nombre',           'l'=>'Equipo',     't'=>'texto', 'w'=>30],
                    ['k'=>'marca_nombre',     'l'=>'Marca',      't'=>'texto', 'w'=>18],
                    ['k'=>'categoria_nombre', 'l'=>'Categoría',  't'=>'texto', 'w'=>20],
                    ['k'=>'horometro',        'l'=>'Horómetro',  't'=>'num2',  'w'=>14],
                    ['k'=>'mant_activa',      'l'=>'Mantención', 't'=>'badge', 'w'=>16],
                ],
            ],
        ];
    }

    // ── Helpers de filtros reutilizables ───────────────────────────────────
    private static function fechaObra(array $g, string $a): array
    {
        $w = ''; $p = [];
        $desde = trim((string)($g['desde'] ?? ''));
        $hasta = trim((string)($g['hasta'] ?? ''));
        $obra  = (int)($g['obra_id'] ?? 0);
        if ($desde !== '') { $w .= " AND {$a}.fecha >= ?"; $p[] = $desde; }
        if ($hasta !== '') { $w .= " AND {$a}.fecha <= ?"; $p[] = $hasta; }
        if ($obra)         { $w .= " AND {$a}.obra_id = ?"; $p[] = $obra; }
        return [$w, $p];
    }

    private static function fechaObraMaterial(array $g, string $a): array
    {
        [$w, $p] = self::fechaObra($g, $a);
        $mat = (int)($g['material_id'] ?? 0);
        if ($mat) { $w .= " AND {$a}.material_id = ?"; $p[] = $mat; }
        return [$w, $p];
    }
}
