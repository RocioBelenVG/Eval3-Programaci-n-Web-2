<?php
$tabla           = 'tipos_checklist';
$titulo          = 'Tipos de Checklist';
$singular        = 'Tipo de Checklist';
$empresa_filtro  = false;
$ordenar_por     = 'nombre';
$campos_lista    = ['nombre', 'aplica_a'];
$etiquetas_lista = ['Nombre', 'Aplica a'];
$campos_form     = [
    ['nombre' => 'nombre',   'etiqueta' => 'Nombre',   'tipo' => 'text',   'required' => true, 'max' => 100, 'col' => '8'],
    ['nombre' => 'aplica_a', 'etiqueta' => 'Aplica a', 'tipo' => 'select', 'required' => true, 'col' => '4',
     'opciones' => ['equipo' => 'Equipo', 'vehiculo' => 'Vehículo', 'faena' => 'Faena', 'general' => 'General']],
];
require_once __DIR__ . '/_crud.php';
