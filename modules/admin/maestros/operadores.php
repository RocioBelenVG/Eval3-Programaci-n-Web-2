<?php
$tabla           = 'operadores';
$titulo          = 'Operadores Autorizados';
$singular        = 'Operador';
$empresa_filtro  = true;
$empresa_col     = 'tr.empresa_id';
$buscar_col      = "CONCAT(tr.nombre, ' ', tr.apellido)";
$ordenar_por     = 'id';
$list_joins      = 'LEFT JOIN trabajadores tr ON tr.id = t.trabajador_id';
$list_select     = 't.*, CONCAT(tr.nombre, " ", tr.apellido) AS nombre_completo, tr.rut';
$campos_lista    = ['nombre_completo', 'rut', 'certificacion', 'certif_vence'];
$etiquetas_lista = ['Trabajador', 'RUT', 'Certificación', 'Vencimiento'];
$campos_form     = [
    ['nombre' => 'trabajador_id',  'etiqueta' => 'Trabajador',     'tipo' => 'select', 'required' => true,  'col' => '12',
     'fuente' => ['tabla' => 'trabajadores', 'valor' => 'id', 'texto' => "CONCAT(nombre, ' ', apellido)", 'where' => '1=1', 'empresa_filter' => true]],
    ['nombre' => 'certificacion',  'etiqueta' => 'Certificación',  'tipo' => 'text',   'required' => false, 'max' => 150, 'col' => '5'],
    ['nombre' => 'certif_numero',  'etiqueta' => 'Nro. Certificado','tipo' => 'text',   'required' => false, 'max' => 50,  'col' => '4'],
    ['nombre' => 'certif_vence',   'etiqueta' => 'Vencimiento',     'tipo' => 'date',   'required' => false, 'col' => '3'],
];
require_once __DIR__ . '/_crud.php';
