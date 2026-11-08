<?php
$tabla           = 'modelos_equipo';
$titulo          = 'Modelos de Equipo';
$singular        = 'Modelo';
$empresa_filtro  = false;
$ordenar_por     = 'nombre';
$list_joins      = 'LEFT JOIN marcas_equipo m ON m.id = t.marca_id';
$list_select     = 't.*, m.nombre AS marca_nombre';
$campos_lista    = ['marca_nombre', 'nombre'];
$etiquetas_lista = ['Marca', 'Modelo'];
$campos_form     = [
    ['nombre' => 'marca_id', 'etiqueta' => 'Marca', 'tipo' => 'select', 'required' => true, 'col' => '4',
     'fuente' => ['tabla' => 'marcas_equipo', 'valor' => 'id', 'texto' => 'nombre', 'where' => '1=1']],
    ['nombre' => 'nombre', 'etiqueta' => 'Nombre del Modelo', 'tipo' => 'text', 'required' => true, 'max' => 100, 'col' => '8'],
];
require_once __DIR__ . '/_crud.php';
