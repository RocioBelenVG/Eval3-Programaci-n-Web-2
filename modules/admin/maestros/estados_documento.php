<?php
$tabla           = 'estados_documento';
$titulo          = 'Estados de Documento';
$singular        = 'Estado';
$empresa_filtro  = false;
$ordenar_por     = 'nombre';
$campos_lista    = ['nombre', 'color', 'alerta_dias'];
$etiquetas_lista = ['Nombre', 'Color', 'Alerta (días)'];
$campos_form     = [
    ['nombre' => 'nombre',      'etiqueta' => 'Nombre',          'tipo' => 'text',   'required' => true, 'max' => 80,   'col' => '6'],
    ['nombre' => 'color',       'etiqueta' => 'Color (hex)',      'tipo' => 'text',   'required' => true, 'max' => 20,   'col' => '3', 'placeholder' => '#28a745'],
    ['nombre' => 'alerta_dias', 'etiqueta' => 'Días de alerta',  'tipo' => 'number', 'required' => false,'min' => 0,    'col' => '3'],
];
require_once __DIR__ . '/_crud.php';
