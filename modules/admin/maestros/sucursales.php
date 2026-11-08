<?php
$tabla           = 'sucursales';
$titulo          = 'Sucursales';
$singular        = 'Sucursal';
$empresa_filtro  = true;
$ordenar_por     = 'nombre';
$list_joins      = 'LEFT JOIN empresas e ON e.id = t.empresa_id';
$list_select     = 't.*, e.razon_social AS empresa_nombre';
$campos_lista    = ['empresa_nombre', 'nombre', 'ciudad'];
$etiquetas_lista = ['Empresa', 'Nombre', 'Ciudad'];
$campos_form     = [
    ['nombre' => 'nombre',    'etiqueta' => 'Nombre Sucursal', 'tipo' => 'text', 'required' => true, 'max' => 100, 'col' => '12'],
    ['nombre' => 'direccion', 'etiqueta' => 'Dirección',       'tipo' => 'text', 'required' => false,'max' => 200, 'col' => '8'],
    ['nombre' => 'ciudad',    'etiqueta' => 'Ciudad',          'tipo' => 'text', 'required' => false,'max' => 100, 'col' => '4'],
    ['nombre' => 'telefono',  'etiqueta' => 'Teléfono',        'tipo' => 'tel',  'required' => false,'max' => 30,  'col' => '6'],
];
require_once __DIR__ . '/_crud.php';
