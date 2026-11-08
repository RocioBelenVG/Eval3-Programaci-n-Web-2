<?php
declare(strict_types=1);

namespace TriTransportes\Export;

use TriTransportes\Report\Reporte;

/**
 * PDF con FPDF (core/PdfReport.php): título, empresa, fecha de emisión, emisor y filtros en el
 * encabezado de cada página; tabla con títulos repetidos; fila de totales; "Página X de Y";
 * texto seleccionable (no es una imagen).
 */
final class PdfExportador implements Exportador
{
    public function extension(): string
    {
        return 'pdf';
    }

    public function tipoContenido(): string
    {
        return 'application/pdf';
    }

    public function generar(Reporte $r): string
    {
        require_once ROOT . '/core/PdfReport.php';
        $pdf = new \PdfReport('L');
        $pdf->SetTitle(\PdfReport::tx($r->titulo));
        $pdf->SetAuthor('TriTransportes');
        $pdf->configurar($r->titulo, $r->empresa, $r->filtrosTexto(), $r->emisor);
        $cols = [];
        foreach ($r->columnas as $c) {
            $cols[] = ['l' => $c[0], 'w' => $c[2] ?? 14, 'align' => $c[1] === 'texto' || $c[1] === 'fecha' ? 'L' : 'R'];
        }
        $pdf->definirColumnas($cols);
        $pdf->AddPage();
        $filas = array_map(fn ($f) => array_map(
            fn ($clave, $c) => Reporte::texto($f[$clave] ?? null, $c[1]),
            array_keys($r->columnas), $r->columnas
        ), $r->filas);
        $pdf->filas($filas);
        if ($r->totales) {
            $pdf->totales(array_map(
                fn ($clave, $c) => array_key_exists($clave, $r->totales) ? Reporte::texto($r->totales[$clave], $c[1]) : '',
                array_keys($r->columnas), $r->columnas
            ));
        }
        $pdf->resumenFinal(count($r->filas));
        return $pdf->Output('S');
    }
}
