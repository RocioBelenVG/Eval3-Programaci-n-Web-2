<?php
$tabla           = 'contratos';
$titulo          = 'Contratos';
$singular        = 'Contrato';
$empresa_filtro  = true;
$ordenar_por     = 'numero';
$list_joins      = 'LEFT JOIN obras o ON o.id = t.obra_id LEFT JOIN clientes c ON c.id = t.cliente_id LEFT JOIN estados_contrato e ON e.id = t.estado_id';
$list_select     = 't.*, o.nombre AS obra_nombre, c.razon_social AS cliente_nombre, e.nombre AS estado_nombre';
$campos_lista    = ['numero', 'obra_nombre', 'cliente_nombre', 'estado_nombre', 'fecha_inicio', 'fecha_termino'];
$etiquetas_lista = ['Número', 'Obra', 'Cliente', 'Estado', 'Inicio', 'Término'];
$campos_form     = [
    ['nombre' => 'numero',         'etiqueta' => 'Número / Código',   'tipo' => 'text',   'required' => false, 'max' => 50,  'col' => '4'],
    ['nombre' => 'tipo',           'etiqueta' => 'Tipo',              'tipo' => 'text',   'required' => false, 'max' => 80,  'col' => '4'],
    ['nombre' => 'estado_id',      'etiqueta' => 'Estado',            'tipo' => 'select', 'required' => false, 'col' => '4',
     'fuente' => ['tabla' => 'estados_contrato', 'valor' => 'id', 'texto' => 'nombre', 'where' => '1=1']],
    ['nombre' => 'obra_id',        'etiqueta' => 'Obra',              'tipo' => 'select', 'required' => false, 'col' => '6',
     'fuente' => ['tabla' => 'obras', 'valor' => 'id', 'texto' => 'nombre', 'where' => '1=1', 'empresa_filter' => true]],
    ['nombre' => 'cliente_id',     'etiqueta' => 'Cliente',           'tipo' => 'select', 'required' => false, 'col' => '6',
     'fuente' => ['tabla' => 'clientes', 'valor' => 'id', 'texto' => 'razon_social', 'where' => '1=1', 'empresa_filter' => true]],
    ['nombre' => 'monto_contrato', 'etiqueta' => 'Monto Contrato',    'tipo' => 'number', 'required' => false, 'min' => 0, 'col' => '4'],
    ['nombre' => 'fecha_inicio',   'etiqueta' => 'Fecha Inicio',      'tipo' => 'date',   'required' => false, 'col' => '4'],
    ['nombre' => 'fecha_termino',  'etiqueta' => 'Fecha Término',     'tipo' => 'date',   'required' => false, 'col' => '4'],
    ['nombre' => 'descripcion',    'etiqueta' => 'Descripción',       'tipo' => 'textarea','required' => false,'col' => '12'],
];
require_once __DIR__ . '/_crud.php';
