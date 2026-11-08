<?php
$tabla           = 'estados_contrato';
$titulo          = 'Estados de Contrato';
$singular        = 'Estado';
$empresa_filtro  = false;
$ordenar_por     = 'nombre';
$campos_lista    = ['nombre', 'color'];
$etiquetas_lista = ['Nombre', 'Color'];
$campos_form     = [
    ['nombre' => 'nombre', 'etiqueta' => 'Nombre',     'tipo' => 'text', 'required' => true, 'max' => 80, 'col' => '8'],
    ['nombre' => 'color',  'etiqueta' => 'Color (hex)','tipo' => 'text', 'required' => true, 'max' => 20, 'col' => '4', 'placeholder' => '#28a745'],
];
require_once __DIR__ . '/_crud.php';
