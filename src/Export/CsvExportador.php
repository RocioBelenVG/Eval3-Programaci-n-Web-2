<?php
declare(strict_types=1);

namespace TriTransportes\Export;

use TriTransportes\Report\Reporte;

/**
 * CSV en UTF-8 con BOM y separador punto y coma (;), con coma decimal y fechas AAAA-MM-DD:
 * es el formato que Excel en español de Chile abre directamente en columnas.
 * La primera fila son los encabezados y la última, los totales.
 */
final class CsvExportador implements Exportador
{
    public const SEPARADOR = ';';

    public function extension(): string
    {
        return 'csv';
    }

    public function tipoContenido(): string
    {
        return 'text/csv; charset=utf-8';
    }

    public function generar(Reporte $r): string
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_column($r->columnas, 0), self::SEPARADOR, '"', '');
        foreach ($r->filas as $f) {
            fputcsv($out, $this->fila($r, $f), self::SEPARADOR, '"', '');
        }
        if ($r->totales) {
            $t = $this->fila($r, $r->totales);
            $t[0] = 'TOTALES';
            fputcsv($out, $t, self::SEPARADOR, '"', '');
        }
        rewind($out);
        return (string) stream_get_contents($out);
    }

    private function fila(Reporte $r, array $f): array
    {
        $celdas = [];
        foreach ($r->columnas as $clave => [, $tipo]) {
            $v = $f[$clave] ?? null;
            $celdas[] = match (true) {
                $v === null => '',
                $tipo === 'fecha' => substr((string) $v, 0, 10),
                in_array($tipo, ['entero', 'clp'], true) => (string) round((float) $v),
                in_array($tipo, ['decimal', 'pct'], true) => str_replace('.', ',', (string) round((float) $v, 2)),
                default => (string) $v,
            };
        }
        return $celdas;
    }
}
