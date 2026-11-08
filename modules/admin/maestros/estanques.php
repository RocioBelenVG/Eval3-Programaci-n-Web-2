<?php
$tabla           = 'estanques';
$titulo          = 'Estanques de Combustible';
$singular        = 'Estanque';
$empresa_filtro  = true;
$ordenar_por     = 'nombre';
$campos_lista    = ['nombre', 'tipo_combustible', 'capacidad', 'ubicacion'];
$etiquetas_lista = ['Nombre', 'Combustible', 'Capacidad (L)', 'Ubicación'];
$campos_form     = [
    ['nombre' => 'nombre',           'etiqueta' => 'Nombre',           'tipo' => 'text',   'required' => true, 'max' => 100, 'col' => '6'],
    ['nombre' => 'tipo_combustible', 'etiqueta' => 'Tipo Combustible', 'tipo' => 'select', 'required' => true, 'col' => '3',
     'opciones' => ['Petróleo' => 'Petróleo', 'Gasolina' => 'Gasolina', 'Gas' => 'Gas']],
    ['nombre' => 'capacidad',        'etiqueta' => 'Capacidad (litros)','tipo' => 'number', 'required' => false,'min' => 0, 'col' => '3'],
    ['nombre' => 'ubicacion',        'etiqueta' => 'Ubicación',        'tipo' => 'text',   'required' => false,'max' => 200,'col' => '12'],
];
require_once __DIR__ . '/_crud.php';
