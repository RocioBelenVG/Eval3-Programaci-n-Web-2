<?php
$tabla           = 'vehiculos';
$titulo          = 'Vehículos';
$singular        = 'Vehículo';
$empresa_filtro  = true;
$ordenar_por     = 'patente';
$list_joins      = 'LEFT JOIN categorias_vehiculo c ON c.id = t.categoria_id';
$list_select     = 't.*, c.nombre AS categoria_nombre';
$campos_lista    = ['patente', 'marca', 'modelo', 'anio', 'categoria_nombre'];
$etiquetas_lista = ['Patente', 'Marca', 'Modelo', 'Año', 'Categoría'];
$campos_form     = [
    ['nombre' => 'patente',      'etiqueta' => 'Patente',    'tipo' => 'text',   'required' => true,  'max' => 10,  'col' => '3', 'placeholder' => 'BJXX00'],
    ['nombre' => 'categoria_id', 'etiqueta' => 'Categoría',  'tipo' => 'select', 'required' => false, 'col' => '5',
     'fuente' => ['tabla' => 'categorias_vehiculo', 'valor' => 'id', 'texto' => 'nombre', 'where' => '1=1']],
    ['nombre' => 'anio',         'etiqueta' => 'Año',        'tipo' => 'number', 'required' => false, 'min' => 1990,'col' => '4'],
    ['nombre' => 'marca',        'etiqueta' => 'Marca',      'tipo' => 'text',   'required' => false, 'max' => 60,  'col' => '6'],
    ['nombre' => 'modelo',       'etiqueta' => 'Modelo',     'tipo' => 'text',   'required' => false, 'max' => 80,  'col' => '6'],
    ['nombre' => 'color',        'etiqueta' => 'Color',      'tipo' => 'text',   'required' => false, 'max' => 40,  'col' => '4'],
    ['nombre' => 'odometro',     'etiqueta' => 'Odómetro actual (km)', 'tipo' => 'number', 'required' => false, 'min' => 0, 'col' => '4'],
];
require_once __DIR__ . '/_crud.php';
