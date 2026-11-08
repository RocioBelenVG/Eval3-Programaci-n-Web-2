<?php
$tabla           = 'conductores';
$titulo          = 'Conductores Autorizados';
$singular        = 'Conductor';
$empresa_filtro  = true;
$empresa_col     = 'tr.empresa_id';
$buscar_col      = "CONCAT(tr.nombre, ' ', tr.apellido)";
$ordenar_por     = 'id';
$list_joins      = 'LEFT JOIN trabajadores tr ON tr.id = t.trabajador_id';
$list_select     = 't.*, CONCAT(tr.nombre, " ", tr.apellido) AS nombre_completo, tr.rut';
$campos_lista    = ['nombre_completo', 'rut', 'licencia_tipo', 'licencia_vence'];
$etiquetas_lista = ['Trabajador', 'RUT', 'Tipo Licencia', 'Vencimiento'];
$campos_form     = [
    ['nombre' => 'trabajador_id',   'etiqueta' => 'Trabajador',     'tipo' => 'select', 'required' => true, 'col' => '12',
     'fuente' => ['tabla' => 'trabajadores', 'valor' => 'id', 'texto' => "CONCAT(nombre, ' ', apellido)", 'where' => '1=1', 'empresa_filter' => true]],
    ['nombre' => 'licencia_tipo',   'etiqueta' => 'Tipo Licencia',  'tipo' => 'select', 'required' => false,'col' => '3',
     'opciones' => ['A1' => 'A1', 'A2' => 'A2', 'A3' => 'A3', 'A4' => 'A4', 'A5' => 'A5', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E', 'F' => 'F']],
    ['nombre' => 'licencia_numero', 'etiqueta' => 'Nro. Licencia',  'tipo' => 'text',   'required' => false,'max' => 30,  'col' => '4'],
    ['nombre' => 'licencia_vence',  'etiqueta' => 'Vencimiento',    'tipo' => 'date',   'required' => false,'col' => '5'],
];
require_once __DIR__ . '/_crud.php';
