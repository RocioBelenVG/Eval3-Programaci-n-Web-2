<?php
$tabla           = 'materiales';
$titulo          = 'Materiales Áridos';
$singular        = 'Material';
$empresa_filtro  = false;
$ordenar_por     = 'nombre';
$campos_lista    = ['nombre', 'unidad_medida'];
$etiquetas_lista = ['Nombre', 'Unidad'];
$campos_form     = [
    ['nombre' => 'nombre',        'etiqueta' => 'Nombre del Material', 'tipo' => 'text',   'required' => true, 'max' => 100, 'col' => '8'],
    ['nombre' => 'unidad_medida', 'etiqueta' => 'Unidad de Medida',    'tipo' => 'select', 'required' => true, 'col' => '4',
     'opciones' => ['m3' => 'm³', 'ton' => 'Tonelada', 'kg' => 'Kilogramo', 'unidad' => 'Unidad']],
];
require_once __DIR__ . '/_crud.php';
