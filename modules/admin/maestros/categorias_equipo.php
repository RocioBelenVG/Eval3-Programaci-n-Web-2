<?php
$tabla           = 'categorias_equipo';
$titulo          = 'Categorías de Equipo';
$singular        = 'Categoría';
$empresa_filtro  = false;
$ordenar_por     = 'nombre';
$campos_lista    = ['nombre', 'tipo'];
$etiquetas_lista = ['Nombre', 'Tipo'];
$campos_form     = [
    ['nombre' => 'nombre', 'etiqueta' => 'Nombre',  'tipo' => 'text', 'required' => true, 'max' => 80, 'col' => '8'],
    ['nombre' => 'tipo',   'etiqueta' => 'Tipo',    'tipo' => 'text', 'required' => false,'max' => 60, 'col' => '4'],
];
require_once __DIR__ . '/_crud.php';
