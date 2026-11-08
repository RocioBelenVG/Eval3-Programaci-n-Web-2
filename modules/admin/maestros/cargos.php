<?php
$tabla           = 'cargos';
$titulo          = 'Cargos';
$singular        = 'Cargo';
$empresa_filtro  = false;
$ordenar_por     = 'nombre';
$campos_lista    = ['nombre', 'area'];
$etiquetas_lista = ['Nombre', 'Área'];
$campos_form     = [
    ['nombre' => 'nombre', 'etiqueta' => 'Nombre del Cargo', 'tipo' => 'text',  'required' => true,  'max' => 100, 'col' => '8'],
    ['nombre' => 'area',   'etiqueta' => 'Área',             'tipo' => 'text',  'required' => false, 'max' => 100, 'col' => '4'],
];
require_once __DIR__ . '/_crud.php';
