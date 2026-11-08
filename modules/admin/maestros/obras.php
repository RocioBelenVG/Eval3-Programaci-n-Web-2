<?php
$tabla           = 'obras';
$titulo          = 'Obras';
$singular        = 'Obra';
$empresa_filtro  = true;
$ordenar_por     = 'nombre';
$list_joins      = 'LEFT JOIN clientes c ON c.id = t.cliente_id';
$list_select     = 't.*, c.razon_social AS cliente_nombre';
$campos_lista    = ['codigo', 'nombre', 'cliente_nombre', 'estado'];
$etiquetas_lista = ['Código', 'Nombre', 'Cliente', 'Estado'];
$campos_form     = [
    ['nombre' => 'codigo',        'etiqueta' => 'Código',        'tipo' => 'text',   'required' => false, 'max' => 30,  'col' => '3'],
    ['nombre' => 'nombre',        'etiqueta' => 'Nombre Obra',   'tipo' => 'text',   'required' => true,  'max' => 150, 'col' => '9'],
    ['nombre' => 'cliente_id',    'etiqueta' => 'Cliente',       'tipo' => 'select', 'required' => false, 'col' => '6',
     'fuente' => ['tabla' => 'clientes', 'valor' => 'id', 'texto' => 'razon_social', 'where' => '1=1']],
    ['nombre' => 'estado',        'etiqueta' => 'Estado',        'tipo' => 'select', 'required' => true,  'col' => '3',
     'opciones' => ['activa' => 'Activa', 'pausada' => 'Pausada', 'finalizada' => 'Finalizada']],
    ['nombre' => 'fecha_inicio',  'etiqueta' => 'Fecha Inicio',  'tipo' => 'date',   'required' => false, 'col' => '3'],
    ['nombre' => 'fecha_termino', 'etiqueta' => 'Fecha Término', 'tipo' => 'date',   'required' => false, 'col' => '3'],
    ['nombre' => 'descripcion',   'etiqueta' => 'Descripción',   'tipo' => 'textarea','required' => false, 'col' => '12'],
];
require_once __DIR__ . '/_crud.php';
