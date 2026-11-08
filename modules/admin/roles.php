<?php
$tabla           = 'roles';
$titulo          = 'Roles';
$singular        = 'Rol';
$empresa_filtro  = false;
$ordenar_por     = 'nombre';
$campos_lista    = ['nombre', 'descripcion'];
$etiquetas_lista = ['Nombre', 'Descripción'];
$campos_form     = [
    ['nombre' => 'nombre',      'etiqueta' => 'Nombre del Rol', 'tipo' => 'text',     'required' => true,  'max' => 80,  'col' => '5'],
    ['nombre' => 'descripcion', 'etiqueta' => 'Descripción',   'tipo' => 'textarea', 'required' => false, 'col' => '7'],
];
require_once __DIR__ . '/maestros/_crud.php';
