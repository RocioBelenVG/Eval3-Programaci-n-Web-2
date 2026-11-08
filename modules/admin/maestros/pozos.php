<?php
$tabla           = 'pozos';
$titulo          = 'Pozos de Áridos';
$singular        = 'Pozo';
$empresa_filtro  = true;
$ordenar_por     = 'nombre';
$campos_lista    = ['codigo', 'nombre', 'ubicacion'];
$etiquetas_lista = ['Código', 'Nombre', 'Ubicación'];
$campos_form     = [
    ['nombre' => 'codigo',    'etiqueta' => 'Código',    'tipo' => 'text', 'required' => false, 'max' => 30,  'col' => '3'],
    ['nombre' => 'nombre',    'etiqueta' => 'Nombre',    'tipo' => 'text', 'required' => true,  'max' => 120, 'col' => '9'],
    ['nombre' => 'ubicacion', 'etiqueta' => 'Ubicación', 'tipo' => 'text', 'required' => false, 'max' => 200, 'col' => '8'],
    ['nombre' => 'lat',       'etiqueta' => 'Latitud',   'tipo' => 'text', 'required' => false, 'col' => '2', 'placeholder' => '-33.4569'],
    ['nombre' => 'lng',       'etiqueta' => 'Longitud',  'tipo' => 'text', 'required' => false, 'col' => '2', 'placeholder' => '-70.6483'],
];
require_once __DIR__ . '/_crud.php';
