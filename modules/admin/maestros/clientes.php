<?php
$tabla           = 'clientes';
$titulo          = 'Clientes';
$singular        = 'Cliente';
$empresa_filtro  = true;
$ordenar_por     = 'razon_social';
$campos_lista    = ['razon_social', 'rut', 'tipo', 'ciudad'];
$etiquetas_lista = ['Razón Social', 'RUT', 'Tipo', 'Ciudad'];
$campos_form     = [
    ['nombre' => 'razon_social', 'etiqueta' => 'Razón Social / Nombre', 'tipo' => 'text',   'required' => true, 'max' => 150, 'col' => '9'],
    ['nombre' => 'tipo',         'etiqueta' => 'Tipo',                   'tipo' => 'select', 'required' => true, 'col' => '3',
     'opciones' => ['empresa' => 'Empresa', 'persona_natural' => 'Persona Natural']],
    ['nombre' => 'rut',          'etiqueta' => 'RUT',       'tipo' => 'text',  'required' => false, 'max' => 20,  'col' => '4', 'placeholder' => '76.000.000-0'],
    ['nombre' => 'giro',         'etiqueta' => 'Giro',      'tipo' => 'text',  'required' => false, 'max' => 150, 'col' => '8'],
    ['nombre' => 'direccion',    'etiqueta' => 'Dirección', 'tipo' => 'text',  'required' => false, 'max' => 200, 'col' => '8'],
    ['nombre' => 'ciudad',       'etiqueta' => 'Ciudad',    'tipo' => 'text',  'required' => false, 'max' => 100, 'col' => '4'],
    ['nombre' => 'telefono',     'etiqueta' => 'Teléfono',  'tipo' => 'tel',   'required' => false, 'max' => 30,  'col' => '6'],
    ['nombre' => 'email',        'etiqueta' => 'Email',     'tipo' => 'email', 'required' => false, 'max' => 120, 'col' => '6'],
];
require_once __DIR__ . '/_crud.php';
