<?php
$tabla           = 'clasificaciones_arido';
$titulo          = 'Clasificaciones de Árido';
$singular        = 'Clasificación';
$empresa_filtro  = false;
$ordenar_por     = 'nombre';
$list_joins      = 'LEFT JOIN materiales m ON m.id = t.material_id';
$list_select     = 't.*, m.nombre AS material_nombre';
$campos_lista    = ['material_nombre', 'nombre', 'tamano'];
$etiquetas_lista = ['Material', 'Clasificación', 'Tamaño'];
$campos_form     = [
    ['nombre' => 'material_id', 'etiqueta' => 'Material', 'tipo' => 'select', 'required' => true, 'col' => '4',
     'fuente' => ['tabla' => 'materiales', 'valor' => 'id', 'texto' => 'nombre', 'where' => '1=1']],
    ['nombre' => 'nombre',   'etiqueta' => 'Nombre',  'tipo' => 'text', 'required' => true,  'max' => 100, 'col' => '5'],
    ['nombre' => 'tamano',   'etiqueta' => 'Tamaño',  'tipo' => 'text', 'required' => false, 'max' => 30,  'col' => '3', 'placeholder' => 'Ej: 20mm'],
];
require_once __DIR__ . '/_crud.php';
