<?php
$tabla           = 'tipos_documento';
$titulo          = 'Tipos de Documento';
$singular        = 'Tipo de Documento';
$empresa_filtro  = false;
$ordenar_por     = 'nombre';
$campos_lista    = ['nombre', 'aplica_a'];
$etiquetas_lista = ['Nombre', 'Aplica a'];
$campos_form     = [
    ['nombre' => 'nombre',                'etiqueta' => 'Nombre',             'tipo' => 'text',     'required' => true,  'max' => 100, 'col' => '8'],
    ['nombre' => 'aplica_a',              'etiqueta' => 'Aplica a',           'tipo' => 'select',   'required' => false, 'col' => '4',
     'opciones' => ['trabajador' => 'Trabajador', 'conductor' => 'Conductor', 'operador' => 'Operador',
                    'vehiculo' => 'Vehículo', 'equipo' => 'Equipo', 'obra' => 'Obra',
                    'contrato' => 'Contrato', 'empresa' => 'Empresa']],
    ['nombre' => 'requiere_vencimiento',  'etiqueta' => 'Requiere fecha de vencimiento', 'tipo' => 'checkbox', 'required' => false, 'col' => '12'],
];
require_once __DIR__ . '/_crud.php';
