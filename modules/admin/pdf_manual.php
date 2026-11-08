<?php
/**
 * Manual de Usuario en PDF — TriTransportes
 * Documento profesional (sin logo) con nombre de empresa y del sistema.
 * Generado con FPDF.
 */
ini_set('display_errors', '0');
ob_start();

require_once dirname(__DIR__, 2) . '/bootstrap.php';
Auth::require();
require_once ROOT . '/libs/fpdf/fpdf.php';

// Conversión UTF-8 -> ISO-8859-1 (FPDF core no soporta UTF-8 nativo)
function u(string $s): string {
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $s);
}

$SISTEMA = defined('APP_NAME') ? APP_NAME : 'TriTransportes';
$EMPRESA = Auth::empresaNombre() ?: 'Empresa';
$VERSION = defined('APP_VERSION') ? APP_VERSION : '1.0';

// ════════════════════════════════════════════════════════════
// CLASE PDF
// ════════════════════════════════════════════════════════════
class ManualPDF extends FPDF {

    public string $sistema = 'TriTransportes';
    public string $empresa = 'Empresa';
    public string $version = '1.0';
    private float $bottomLimit = 270;

    function Header() {
        if ($this->PageNo() === 1) return; // portada sin cabecera
        $this->SetFillColor(15, 23, 42);
        $this->Rect(0, 0, 210, 13, 'F');
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(245, 158, 11);
        $this->SetXY(14, 3);
        $this->Cell(0, 7, u($this->sistema), 0, 0, 'L');
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(148, 163, 184);
        $this->SetXY(0, 3);
        $this->Cell(196, 7, u($this->empresa), 0, 0, 'R');
        $this->SetTextColor(0, 0, 0);
    }

    function Footer() {
        if ($this->PageNo() === 1) return;
        $this->SetY(-13);
        $this->SetFillColor(241, 245, 249);
        $this->Rect(0, $this->GetY(), 210, 13, 'F');
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(95, 7, u('Manual de Usuario  ·  ' . $this->sistema), 0, 0, 'L');
        $this->Cell(95, 7, u('Página ') . $this->PageNo() . ' de {nb}', 0, 0, 'R');
        $this->SetTextColor(0, 0, 0);
    }

    function checkPage(float $needed = 35): void {
        if ($this->GetY() + $needed > $this->bottomLimit) {
            $this->AddPage();
        }
    }

    // ── Portada (sin logo) ────────────────────────────────
    function portada(): void {
        // La portada es una página de diseño fijo: desactivamos el salto
        // automático para que el pie (Y=280) no genere una hoja en blanco.
        $this->SetAutoPageBreak(false);
        $this->AddPage();
        // Fondo oscuro
        $this->SetFillColor(15, 23, 42);
        $this->Rect(0, 0, 210, 297, 'F');
        // Franjas doradas
        $this->SetFillColor(245, 158, 11);
        $this->Rect(0, 0, 210, 4, 'F');
        $this->Rect(0, 190, 210, 2, 'F');
        // Barra lateral decorativa
        $this->Rect(20, 92, 4, 70, 'F');

        // Nombre de la empresa (cliente)
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(245, 158, 11);
        $this->SetXY(30, 96);
        $this->Cell(160, 8, u(strtoupper($this->empresa)), 0, 1, 'L');

        // Nombre del sistema
        $this->SetFont('Arial', 'B', 40);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(29, 108);
        $this->Cell(160, 18, u($this->sistema), 0, 1, 'L');

        // Separador
        $this->SetFillColor(51, 65, 85);
        $this->Rect(30, 134, 150, 0.5, 'F');

        // Tipo de documento
        $this->SetFont('Arial', '', 16);
        $this->SetTextColor(203, 213, 225);
        $this->SetXY(30, 140);
        $this->Cell(160, 10, u('Manual de Usuario'), 0, 1, 'L');

        // Bajada
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(100, 116, 139);
        $this->SetXY(30, 153);
        $this->Cell(160, 6, u('Plataforma de Gestión Operacional'), 0, 1, 'L');
        $this->SetXY(30, 159);
        $this->Cell(160, 6, u('Áridos  ·  Transporte  ·  Maquinaria  ·  Construcción'), 0, 1, 'L');

        // Metadata
        $y = 205;
        $meta = [
            ['Sistema',          $this->sistema],
            ['Empresa',          $this->empresa],
            ['Versión',          $this->version],
            ['Fecha de emisión', date('d/m/Y')],
            ['Clasificación',    'Documento interno'],
        ];
        foreach ($meta as [$k, $v]) {
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(71, 85, 105);
            $this->SetXY(30, $y);
            $this->Cell(45, 6, u($k . ':'), 0, 0, 'L');
            $this->SetTextColor(148, 163, 184);
            $this->Cell(115, 6, u($v), 0, 1, 'L');
            $y += 7;
        }

        // Pie de portada
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(51, 65, 85);
        $this->SetXY(14, 280);
        $this->Cell(182, 6, u('Documento generado automáticamente por ' . $this->sistema . '.'), 0, 0, 'C');
    }

    // ── Índice ────────────────────────────────────────────
    function indice(array $items): void {
        $this->AddPage();
        $this->SetMargins(15, 22, 15);
        $this->SetY(25);
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(15, 23, 42);
        $this->Cell(0, 10, u('Índice de Contenidos'), 0, 1, 'L');
        $this->SetDrawColor(245, 158, 11);
        $this->SetLineWidth(0.8);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->Ln(6);

        foreach ($items as $k => [$num, $titulo]) {
            if ($k % 2 === 0) {
                $this->SetFillColor(248, 250, 252);
                $this->Rect(15, $this->GetY(), 180, 8, 'F');
            }
            $this->SetFont('Arial', 'B', 9);
            $this->SetTextColor(245, 158, 11);
            $this->SetX(18);
            $this->Cell(12, 8, $num . '.', 0, 0, 'L');
            $this->SetFont('Arial', '', 9.5);
            $this->SetTextColor(30, 41, 59);
            $this->Cell(165, 8, u($titulo), 0, 1, 'L');
        }
        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
    }

    // ── Encabezado de sección ─────────────────────────────
    function seccion(string $num, string $titulo): void {
        $this->checkPage(50);
        $this->Ln(2);
        $this->SetFillColor(15, 23, 42);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(0, 10, "   {$num}.  " . u($titulo), 0, 1, 'L', true);
        $this->SetFillColor(245, 158, 11);
        $this->Cell(0, 1, '', 0, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(4);
    }

    // ── Subtítulo ─────────────────────────────────────────
    function titulo(string $t): void {
        $this->checkPage(22);
        $this->Ln(1);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(15, 23, 42);
        $this->SetX(15);
        $this->Cell(0, 7, u($t), 0, 1, 'L');
        $this->SetDrawColor(203, 213, 225);
        $this->SetLineWidth(0.3);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
        $this->Ln(2);
    }

    // ── Párrafo ───────────────────────────────────────────
    function parrafo(string $t): void {
        $this->checkPage(16);
        $this->SetFont('Arial', '', 9.5);
        $this->SetTextColor(51, 65, 85);
        $this->SetX(15);
        $this->MultiCell(180, 5.5, u($t));
        $this->Ln(2);
        $this->SetTextColor(0, 0, 0);
    }

    // ── Lista con viñetas ─────────────────────────────────
    function lista(array $items): void {
        $this->SetTextColor(51, 65, 85);
        foreach ($items as [$label, $texto]) {
            $this->checkPage(10);
            $this->SetFont('Arial', '', 9.5);
            $this->SetX(17);
            $this->Cell(5, 5.5, chr(149), 0, 0);
            if ($label !== '') {
                $this->SetFont('Arial', 'B', 9.5);
                $lw = min($this->GetStringWidth(u($label) . ':  '), 50);
                $this->Cell($lw, 5.5, u($label) . ':  ', 0, 0);
                $this->SetFont('Arial', '', 9.5);
                $xText = $this->GetX();
                $this->MultiCell(195 - $xText, 5.5, u($texto));
            } else {
                $this->MultiCell(173, 5.5, u($texto));
            }
        }
        $this->Ln(2);
        $this->SetTextColor(0, 0, 0);
    }

    // ── Pasos numerados ───────────────────────────────────
    function pasos(array $items): void {
        $this->SetTextColor(51, 65, 85);
        foreach ($items as $i => $paso) {
            $this->checkPage(10);
            $this->SetFont('Arial', 'B', 9.5);
            $this->SetTextColor(245, 158, 11);
            $this->SetX(17);
            $this->Cell(7, 5.5, ($i + 1) . '.', 0, 0);
            $this->SetFont('Arial', '', 9.5);
            $this->SetTextColor(51, 65, 85);
            $this->MultiCell(171, 5.5, u($paso));
        }
        $this->Ln(2);
        $this->SetTextColor(0, 0, 0);
    }

    // ── Nota / Alerta ─────────────────────────────────────
    function nota(string $texto, string $tipo = 'info'): void {
        $this->checkPage(18);
        $paletas = [
            'info'  => [[219, 234, 254], [30, 58, 138]],
            'aviso' => [[254, 243, 199], [120, 80, 0]],
            'exito' => [[220, 252, 231], [20, 83, 45]],
            'alerta'=> [[254, 226, 226], [153, 27, 27]],
        ];
        [$bg, $tc] = $paletas[$tipo] ?? $paletas['info'];
        $y0 = $this->GetY();
        $this->SetFillColor(...$bg);
        $this->SetFont('Arial', 'I', 9);
        $this->SetTextColor(...$tc);
        $this->SetX(15);
        $this->MultiCell(180, 5.5, '   ' . u($texto), 0, 'L', true);
        $yEnd = $this->GetY();
        $this->SetDrawColor(...$tc);
        $this->SetLineWidth(2);
        $this->Line(15, $y0, 15, $yEnd);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(3);
    }

    // ── Tabla ─────────────────────────────────────────────
    function tabla(array $cabecera, array $anchos, array $filas): void {
        $this->checkPage(count($filas) * 7 + 14);
        $this->SetFillColor(15, 23, 42);
        $this->SetTextColor(245, 158, 11);
        $this->SetFont('Arial', 'B', 8);
        $this->SetX(15);
        foreach ($cabecera as $i => $col) {
            $this->Cell($anchos[$i], 7, ' ' . u($col), 0, 0, 'L', true);
        }
        $this->Ln();
        $this->SetFont('Arial', '', 8.5);
        foreach ($filas as $k => $fila) {
            $this->checkPage(8);
            $par = ($k % 2 === 0);
            $this->SetFillColor($par ? 248 : 255, $par ? 250 : 255, $par ? 252 : 255);
            $this->SetTextColor(30, 41, 59);
            $this->SetX(15);
            foreach ($fila as $i => $col) {
                $this->Cell($anchos[$i], 6.5, ' ' . u($col), 0, 0, 'L', true);
            }
            $this->Ln();
        }
        $this->SetDrawColor(203, 213, 225);
        $this->SetLineWidth(0.3);
        $this->SetX(15);
        $this->Cell(array_sum($anchos), 0, '', 'T');
        $this->Ln(4);
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.2);
        $this->SetTextColor(0, 0, 0);
    }
}

// ════════════════════════════════════════════════════════════
// CONSTRUCCIÓN DEL DOCUMENTO
// ════════════════════════════════════════════════════════════
$pdf = new ManualPDF();
$pdf->sistema = $SISTEMA;
$pdf->empresa = $EMPRESA;
$pdf->version = $VERSION;
$pdf->AliasNbPages();
$pdf->SetMargins(15, 22, 15);
$pdf->SetAutoPageBreak(true, 27);
$pdf->SetCreator($SISTEMA);
$pdf->SetAuthor($EMPRESA);
$pdf->SetTitle(u('Manual de Usuario - ' . $SISTEMA));

$pdf->portada();

$pdf->indice([
    ['1',  'Introducción al Sistema'],
    ['2',  'Acceso al Sistema y Roles'],
    ['3',  'Funcionamiento Multiempresa'],
    ['4',  'Panel Principal — Dashboard'],
    ['5',  'Partes de Faena'],
    ['6',  'Control de Combustible'],
    ['7',  'Gestión de Maquinaria'],
    ['8',  'Gestión de Transporte'],
    ['9',  'Gestión de Áridos'],
    ['10', 'Gestión de Contratos'],
    ['11', 'Control Documental'],
    ['12', 'Seguridad Operacional'],
    ['13', 'Datos Maestros'],
    ['14', 'Usuarios y Permisos'],
    ['15', 'Asistente de Inteligencia Artificial'],
    ['16', 'Preguntas Frecuentes'],
]);

// Reactivar el salto de página automático para las secciones de contenido.
$pdf->SetAutoPageBreak(true, 27);

// 1. INTRODUCCIÓN
$pdf->AddPage();
$pdf->SetY(25);
$pdf->seccion('1', 'Introducción al Sistema');
$pdf->parrafo("$SISTEMA es la plataforma de gestión operacional utilizada por $EMPRESA para centralizar y controlar sus operaciones diarias: faenas, combustible, maquinaria, transporte, producción de áridos, contratos, documentación y seguridad. Reemplaza planillas dispersas por procesos integrados, trazables y disponibles en tiempo real.");
$pdf->parrafo("El sistema es multiempresa: una misma instalación puede administrar varias empresas de forma independiente, manteniendo separados sus datos, personal, flota y obras.");
$pdf->titulo('Módulos del sistema');
$pdf->lista([
    ['Partes de Faena', 'Registro diario de actividades, personal y condiciones en terreno.'],
    ['Combustible',     'Control de cargas, litros, costos y consumo por unidad y obra.'],
    ['Maquinaria',      'Mantenciones preventivas/correctivas, repuestos y horómetros.'],
    ['Transporte',      'Viajes, guías de despacho y trazabilidad de la carga.'],
    ['Áridos',          'Producción, stock por clasificación y despachos a clientes.'],
    ['Contratos',       'Avance físico y financiero de los proyectos por partidas.'],
    ['Documentos',      'Repositorio con control de vencimientos y alertas.'],
    ['Seguridad',       'Checklists de inspección e incidentes de seguridad.'],
]);
$pdf->nota('El menú lateral izquierdo agrupa todos los módulos. Lo que cada usuario ve depende de su rol y de los permisos asignados.', 'info');

// 2. ACCESO Y ROLES
$pdf->seccion('2', 'Acceso al Sistema y Roles');
$pdf->titulo('Inicio de sesión');
$pdf->pasos([
    'Abra el sistema en su navegador (Chrome, Edge, Firefox o Safari).',
    'Ingrese su correo electrónico y su contraseña.',
    'Presione Ingresar: será dirigido al Dashboard de su empresa.',
]);
$pdf->nota('Por seguridad, la sesión se cierra automáticamente tras 30 minutos de inactividad. Cambie su contraseña inicial en el primer ingreso.', 'aviso');
$pdf->titulo('Roles y niveles de acceso');
$pdf->tabla(
    ['Rol', 'Alcance y capacidades'],
    [55, 125],
    [
        ['Administrador General', 'Acceso total + cambio entre empresas, usuarios, roles y permisos.'],
        ['Jefe de Operaciones',   'Gestión operativa completa de la empresa activa.'],
        ['Supervisor',            'Faena, transporte y maquinaria según permisos.'],
        ['Prevención de Riesgos', 'Seguridad operacional y control documental.'],
        ['Operador / Conductor',  'Registro de sus propias actividades.'],
    ]
);

// 3. MULTIEMPRESA
$pdf->seccion('3', 'Funcionamiento Multiempresa');
$pdf->parrafo("El sistema permite administrar varias empresas dentro de una misma instalación. Cada empresa mantiene de forma independiente su personal, flota, obras, contratos y registros. En todo momento existe una empresa activa, y todos los módulos muestran y guardan información correspondiente únicamente a esa empresa.");
$pdf->titulo('Crear una nueva empresa');
$pdf->pasos([
    'En la barra superior, abra el selector de empresa (botón con el nombre de la empresa actual).',
    'Elija «Crear nueva empresa», o vaya a Administración, Datos Maestros, Empresas, Nueva.',
    'Complete la razón social, RUT, giro y datos de contacto, y guarde.',
    'La nueva empresa queda disponible de inmediato en el selector.',
]);
$pdf->titulo('Cambiar de empresa activa');
$pdf->pasos([
    'Haga clic en el selector de empresa, ubicado en la esquina superior derecha.',
    'Seleccione la empresa con la que desea trabajar; la activa se marca con un visto verde.',
    'Al elegirla, todos los módulos se actualizan automáticamente con los datos de esa empresa: dashboard, faenas, flota, contratos y el resto de la información.',
]);
$pdf->nota('Todo registro que cree (un parte de faena, una carga de combustible, un viaje, etc.) se asocia automáticamente a la empresa activa en ese momento. Verifique siempre la empresa seleccionada antes de ingresar datos.', 'exito');
$pdf->nota('Solo el Administrador General puede cambiar entre empresas. Los demás usuarios trabajan con la empresa a la que pertenece su cuenta.', 'info');

// 4. DASHBOARD
$pdf->seccion('4', 'Panel Principal — Dashboard');
$pdf->parrafo('El Dashboard es la pantalla de inicio que se muestra tras el ingreso. Resume el estado de la empresa activa mediante indicadores (KPI) y gráficos en tiempo real, permitiendo detectar situaciones que requieren atención sin recorrer cada módulo.');
$pdf->titulo('Indicadores principales');
$pdf->tabla(
    ['Indicador', 'Significado'],
    [60, 120],
    [
        ['Obras Activas',          'Obras actualmente en ejecución.'],
        ['Partes Hoy',             'Partes de faena registrados en el día.'],
        ['Combustible del Mes',    'Litros de combustible consumidos en el mes.'],
        ['Viajes',                 'Transportes registrados.'],
        ['Equipos Detenidos',      'Maquinaria fuera de servicio.'],
        ['Documentos por Vencer',  'Documentos próximos a su vencimiento.'],
    ]
);
$pdf->nota('Los indicadores y gráficos se recalculan al cambiar de empresa, mostrando siempre la información de la empresa activa.', 'info');

// 5. FAENA
$pdf->seccion('5', 'Partes de Faena');
$pdf->parrafo('Registre el avance diario de cada obra: actividades realizadas, personal y equipos utilizados, y condiciones del día.');
$pdf->titulo('Crear un parte de faena');
$pdf->pasos([
    'Ingrese a Faena y presione Nuevo Parte.',
    'Seleccione la obra, la fecha, el supervisor y el clima.',
    'Agregue las actividades con su cantidad y unidad.',
    'Registre el personal y los equipos con sus horas.',
    'Guarde como borrador o ciérrelo cuando esté completo.',
]);
$pdf->nota('Borrador: el parte es editable y aún no está confirmado. Cerrado: queda como registro oficial y ya no es editable.', 'aviso');

// 6. COMBUSTIBLE
$pdf->seccion('6', 'Control de Combustible');
$pdf->parrafo('Controle cada carga de combustible a vehículos y equipos, con su costo, fuente y lectura de horómetro u odómetro.');
$pdf->titulo('Registrar una carga');
$pdf->pasos([
    'En Combustible presione Nueva Carga.',
    'Indique si la carga es a un vehículo o a un equipo y selecciónelo.',
    'Registre litros, precio por litro, fuente (estanque propio o externo) y la lectura de horómetro u odómetro.',
    'Opcionalmente asocie la carga a una obra o contrato y guarde.',
]);
$pdf->nota('El sistema acumula litros y costos por mes, vehículo, equipo y obra para el análisis de consumo.', 'info');

// 7. MAQUINARIA
$pdf->seccion('7', 'Gestión de Maquinaria');
$pdf->parrafo('Administre el mantenimiento de equipos, sus repuestos, detenciones y la lectura de horómetro.');
$pdf->titulo('Registrar una mantención');
$pdf->pasos([
    'En Maquinaria elija el equipo y presione Nueva Mantención.',
    'Indique el tipo (preventiva o correctiva), fecha, horómetro y técnico.',
    'Agregue los repuestos utilizados con su costo.',
    'Defina el estado: pendiente, en proceso o completada.',
]);
$pdf->nota('Una detención sin fecha de término se considera activa: el equipo está fuera de servicio y aparece en el dashboard.', 'aviso');

// 8. TRANSPORTE
$pdf->seccion('8', 'Gestión de Transporte');
$pdf->parrafo('Controle los viajes de transporte de material y emita sus guías de despacho.');
$pdf->titulo('Registrar un viaje y su guía');
$pdf->pasos([
    'En Transporte presione Nuevo Viaje.',
    'Seleccione vehículo, conductor, obra, material y cantidad.',
    'Indique origen, destino, horas y kilometraje.',
    'Desde el viaje, genere la Guía de Despacho con número, receptor y RUT.',
]);
$pdf->nota('Estados del viaje: programado, en ruta, completado o cancelado.', 'info');

// 9. ÁRIDOS
$pdf->seccion('9', 'Gestión de Áridos');
$pdf->parrafo('Registre la producción de áridos, controle el stock por clasificación y gestione los despachos a clientes.');
$pdf->lista([
    ['Producción', 'Material producido por turno, equipo y operador.'],
    ['Stock',      'Existencias actuales por material y clasificación.'],
    ['Despachos',  'Salidas a clientes con guía, vehículo y precio.'],
]);
$pdf->nota('Cada producción y despacho usa las clasificaciones de árido (grava, gravilla, arena, base, etc.) definidas en Datos Maestros.', 'info');

// 10. CONTRATOS
$pdf->seccion('10', 'Gestión de Contratos');
$pdf->parrafo('Administre los contratos por obra y cliente, sus ítems (partidas) y el avance valorizado.');
$pdf->pasos([
    'En Contratos cree un contrato indicando obra, cliente, monto y vigencia.',
    'Agregue los ítems con unidad, cantidad total y precio unitario.',
    'Registre los avances por ítem y fecha; el sistema acumula el monto ejecutado.',
]);
$pdf->nota('El sistema calcula el porcentaje de avance comparando lo ejecutado con la cantidad total contratada.', 'exito');

// 11. DOCUMENTOS
$pdf->seccion('11', 'Control Documental');
$pdf->parrafo('Centralice documentos como licencias, seguros, revisiones técnicas, permisos y contratos, con control de vencimientos.');
$pdf->pasos([
    'En Documentos presione Nuevo Documento.',
    'Elija el tipo y, si corresponde, la entidad asociada (trabajador, conductor, vehículo, equipo, obra, etc.).',
    'Registre número, fecha de emisión y de vencimiento, y adjunte el archivo.',
]);
$pdf->nota('El sistema alerta sobre documentos por vencer y vencidos en el dashboard, ayudando a evitar multas o detenciones.', 'alerta');

// 12. SEGURIDAD
$pdf->seccion('12', 'Seguridad Operacional');
$pdf->titulo('Checklists de inspección');
$pdf->pasos([
    'Cree un registro de checklist indicando tipo, obra y equipo o vehículo inspeccionado.',
    'Marque cada ítem como cumple o no cumple, con observaciones.',
    'El resultado puede ser aprobado, observado o rechazado.',
]);
$pdf->titulo('Registro de incidentes');
$pdf->pasos([
    'Registre el incidente con fecha, lugar, tipo y gravedad.',
    'Describa lo ocurrido y las acciones tomadas.',
    'Haga seguimiento del estado: investigando o cerrado.',
]);

// 13. MAESTROS
$pdf->seccion('13', 'Datos Maestros');
$pdf->parrafo('En Administración, Datos Maestros se mantienen los catálogos base del sistema. Tenerlos completos hace que el resto de los módulos funcione correctamente.');
$pdf->lista([
    ['Personal',            'Trabajadores, conductores y operadores.'],
    ['Flota',               'Vehículos y equipos con sus categorías.'],
    ['Obras y clientes',    'Obras, clientes, contactos y centros de costo.'],
    ['Combustible',         'Estanques y proveedores.'],
    ['Áridos',              'Materiales, clasificaciones y pozos.'],
    ['Empresas',            'Empresas y sucursales del sistema.'],
]);
$pdf->nota('Los datos maestros propios de cada empresa (personal, flota, obras, etc.) pertenecen a la empresa activa. Verifique la empresa seleccionada antes de crearlos.', 'aviso');

// 14. USUARIOS
$pdf->seccion('14', 'Usuarios y Permisos');
$pdf->pasos([
    'En Administración, Usuarios cree cada cuenta con su correo, empresa, rol y trabajador asociado.',
    'En Roles defina los perfiles de la organización.',
    'En Permisos active o desactive, por rol, qué puede ver, crear, editar o eliminar en cada módulo.',
]);
$pdf->nota('El Administrador General tiene acceso total y es el único que puede cambiar entre empresas.', 'info');
$pdf->nota('Recuerde cambiar la contraseña del administrador tras la primera instalación.', 'alerta');

// 15. ASISTENTE IA
$pdf->seccion('15', 'Asistente de Inteligencia Artificial');
$pdf->parrafo('El sistema incluye un asistente virtual disponible en todas las pantallas, mediante el botón flotante de la esquina inferior derecha.');
$pdf->pasos([
    'Haga clic en el botón del asistente para abrir el panel de conversación.',
    'Escriba su pregunta sobre el uso del sistema y envíela.',
    'El asistente responde con orientación contextual sobre módulos y procesos.',
]);
$pdf->nota('El asistente conoce el nombre de su empresa y su rol. Las conversaciones son privadas y no se comparten entre usuarios.', 'info');

// 16. FAQ
$pdf->seccion('16', 'Preguntas Frecuentes');
$pdf->lista([
    ['Cambiar de empresa', 'Use el selector de empresa en la esquina superior derecha (solo Administrador General).'],
    ['No veo un módulo',   'Su rol no tiene permiso para ese módulo. Solicite acceso al administrador.'],
    ['Datos separados',    'Cada registro queda asociado a una empresa y los módulos filtran por la empresa activa; no se mezclan.'],
    ['Obtener ayuda',      'Use el asistente IA (botón flotante) o este manual, también disponible en el panel del sistema.'],
]);

// ════════════════════════════════════════════════════════════
// SALIDA
// ════════════════════════════════════════════════════════════
$pdfBytes = $pdf->Output('S');
ob_end_clean();
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="Manual_Usuario_TriTransportes.pdf"');
header('Content-Length: ' . strlen($pdfBytes));
echo $pdfBytes;