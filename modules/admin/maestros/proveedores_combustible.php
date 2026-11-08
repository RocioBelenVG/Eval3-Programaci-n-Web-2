<?php
$tabla           = 'proveedores_combustible';
$titulo          = 'Proveedores de Combustible';
$singular        = 'Proveedor';
$empresa_filtro  = true;
$ordenar_por     = 'nombre';
$campos_lista    = ['nombre', 'rut', 'telefono', 'email'];
$etiquetas_lista = ['Nombre', 'RUT', 'Teléfono', 'Email'];
$campos_form     = [
    ['nombre' => 'nombre',   'etiqueta' => 'Nombre / Razón Social', 'tipo' => 'text',  'required' => true,  'max' => 120, 'col' => '8'],
    ['nombre' => 'rut',      'etiqueta' => 'RUT',      'tipo' => 'text',  'required' => false, 'max' => 20,  'col' => '4', 'placeholder' => '76.000.000-0'],
    ['nombre' => 'telefono', 'etiqueta' => 'Teléfono', 'tipo' => 'tel',   'required' => false, 'max' => 30,  'col' => '6'],
    ['nombre' => 'email',    'etiqueta' => 'Email',    'tipo' => 'email', 'required' => false, 'max' => 120, 'col' => '6'],
];
require_once __DIR__ . '/_crud.php';
