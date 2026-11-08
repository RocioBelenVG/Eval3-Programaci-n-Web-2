<?php
$tabla           = 'fuentes_combustible';
$titulo          = 'Fuentes de Combustible';
$singular        = 'Fuente';
$empresa_filtro  = false;
$ordenar_por     = 'nombre';
$campos_lista    = ['nombre', 'tipo'];
$etiquetas_lista = ['Nombre', 'Tipo'];
$campos_form     = [
    ['nombre' => 'nombre', 'etiqueta' => 'Nombre', 'tipo' => 'text',   'required' => true, 'max' => 80, 'col' => '8'],
    ['nombre' => 'tipo',   'etiqueta' => 'Tipo',   'tipo' => 'select', 'required' => true, 'col' => '4',
     'opciones' => ['estanque_propio' => 'Estanque Propio', 'bencinera' => 'Bencinera', 'camion_estanque' => 'Camión Estanque', 'otro' => 'Otro']],
];
require_once __DIR__ . '/_crud.php';
