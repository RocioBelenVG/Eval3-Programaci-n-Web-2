<?php
$tabla           = 'marcas_equipo';
$titulo          = 'Marcas de Equipo';
$singular        = 'Marca';
$empresa_filtro  = false;
$ordenar_por     = 'nombre';
$campos_lista    = ['nombre'];
$etiquetas_lista = ['Nombre'];
$campos_form     = [
    ['nombre' => 'nombre', 'etiqueta' => 'Nombre de la Marca', 'tipo' => 'text', 'required' => true, 'max' => 80, 'col' => '12'],
];
require_once __DIR__ . '/_crud.php';
