<?php
$tabla           = 'equipos';
$titulo          = 'Equipos';
$singular        = 'Equipo';
$empresa_filtro  = true;
$ordenar_por     = 'codigo';
$list_joins      = 'LEFT JOIN marcas_equipo m ON m.id = t.marca_id LEFT JOIN categorias_equipo c ON c.id = t.categoria_id';
$list_select     = 't.*, m.nombre AS marca_nombre, c.nombre AS categoria_nombre';
$campos_lista    = ['codigo', 'nombre', 'marca_nombre', 'categoria_nombre', 'anio'];
$etiquetas_lista = ['Código', 'Nombre', 'Marca', 'Categoría', 'Año'];
$campos_form     = [
    ['nombre' => 'codigo',       'etiqueta' => 'Código Interno', 'tipo' => 'text',   'required' => true,  'max' => 30,  'col' => '3'],
    ['nombre' => 'nombre',       'etiqueta' => 'Nombre / Descripción', 'tipo' => 'text','required' => true,'max' => 120, 'col' => '9'],
    ['nombre' => 'categoria_id', 'etiqueta' => 'Categoría',      'tipo' => 'select', 'required' => false, 'col' => '4',
     'fuente' => ['tabla' => 'categorias_equipo', 'valor' => 'id', 'texto' => 'nombre', 'where' => '1=1']],
    ['nombre' => 'marca_id',     'etiqueta' => 'Marca',          'tipo' => 'select', 'required' => false, 'col' => '4',
     'fuente' => ['tabla' => 'marcas_equipo', 'valor' => 'id', 'texto' => 'nombre', 'where' => '1=1']],
    ['nombre' => 'modelo_id',    'etiqueta' => 'Modelo',         'tipo' => 'select', 'required' => false, 'col' => '4',
     'fuente' => ['tabla' => 'modelos_equipo', 'valor' => 'id', 'texto' => 'nombre', 'where' => '1=1']],
    ['nombre' => 'anio',         'etiqueta' => 'Año Fabricación','tipo' => 'number', 'required' => false, 'min' => 1980,'col' => '3'],
    ['nombre' => 'numero_serie', 'etiqueta' => 'Número de Serie','tipo' => 'text',   'required' => false, 'max' => 60,  'col' => '5'],
    ['nombre' => 'horometro',    'etiqueta' => 'Horómetro Actual (hrs)', 'tipo' => 'number','required' => false,'min' => 0,'col' => '4'],
];
require_once __DIR__ . '/_crud.php';
