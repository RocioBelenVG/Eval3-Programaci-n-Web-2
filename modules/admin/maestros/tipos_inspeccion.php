<?php
$tabla           = 'tipos_inspeccion';
$titulo          = 'Tipos de Inspección';
$singular        = 'Tipo de Inspección';
$empresa_filtro  = false;
$ordenar_por     = 'nombre';
$campos_lista    = ['nombre', 'periodicidad'];
$etiquetas_lista = ['Nombre', 'Periodicidad'];
$campos_form     = [
    ['nombre' => 'nombre',       'etiqueta' => 'Nombre',       'tipo' => 'text',   'required' => true,  'max' => 100, 'col' => '8'],
    ['nombre' => 'periodicidad', 'etiqueta' => 'Periodicidad', 'tipo' => 'select', 'required' => false, 'col' => '4',
     'opciones' => ['' => '— Seleccione —', 'Diaria' => 'Diaria', 'Semanal' => 'Semanal', 'Mensual' => 'Mensual',
                    'Trimestral' => 'Trimestral', 'Semestral' => 'Semestral', 'Anual' => 'Anual']],
];
require_once __DIR__ . '/_crud.php';
