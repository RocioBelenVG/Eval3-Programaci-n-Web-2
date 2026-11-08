<?php
$tabla           = 'trabajadores';
$titulo          = 'Trabajadores';
$singular        = 'Trabajador';
$empresa_filtro  = true;
$ordenar_por     = 'apellido';
$list_joins      = 'LEFT JOIN cargos c ON c.id = t.cargo_id';
$list_select     = 't.*, c.nombre AS cargo_nombre, CONCAT(t.apellido, ", ", t.nombre) AS nombre_completo';
$campos_lista    = ['nombre_completo', 'rut', 'cargo_nombre'];
$buscar_col      = "CONCAT(t.nombre, ' ', t.apellido)";
$etiquetas_lista = ['Nombre', 'RUT', 'Cargo'];
$campos_form     = [
    ['nombre' => 'nombre',        'etiqueta' => 'Nombre(s)',      'tipo' => 'text',   'required' => true,  'max' => 100, 'col' => '5'],
    ['nombre' => 'apellido',      'etiqueta' => 'Apellidos',      'tipo' => 'text',   'required' => true,  'max' => 100, 'col' => '5'],
    ['nombre' => 'rut',           'etiqueta' => 'RUT',            'tipo' => 'text',   'required' => true,  'max' => 20,  'col' => '4', 'placeholder' => '12.345.678-9'],
    ['nombre' => 'cargo_id',      'etiqueta' => 'Cargo',          'tipo' => 'select', 'required' => false, 'col' => '4',
     'fuente' => ['tabla' => 'cargos', 'valor' => 'id', 'texto' => 'nombre', 'where' => '1=1']],
    ['nombre' => 'fecha_ingreso', 'etiqueta' => 'Fecha Ingreso',  'tipo' => 'date',   'required' => false, 'col' => '4'],
    ['nombre' => 'telefono',      'etiqueta' => 'Teléfono',       'tipo' => 'tel',    'required' => false, 'max' => 30,  'col' => '6'],
    ['nombre' => 'email',         'etiqueta' => 'Email',          'tipo' => 'email',  'required' => false, 'max' => 120, 'col' => '6'],
];
require_once __DIR__ . '/_crud.php';
