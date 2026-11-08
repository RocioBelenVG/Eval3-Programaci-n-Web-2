<?php
declare(strict_types=1);

namespace TriTransportes\Export;

use TriTransportes\Report\Reporte;

/**
 * XLSX (Office Open XML) sin dependencias externas, con ZipArchive.
 * - Título, empresa, fecha de emisión y filtros en las primeras filas.
 * - Encabezados en negrita con fondo, fila fija al desplazarse y autofiltro.
 * - Fechas como fechas reales de Excel (dd-mm-aaaa) y números con su formato (miles, CLP, %).
 * - Fila de totales con fórmulas SUMA sobre las columnas sumables (y el valor calculado guardado).
 */
final class XlsxExportador implements Exportador
{
    private const ESTILO = ['texto' => 0, 'fecha' => 2, 'entero' => 3, 'decimal' => 4, 'clp' => 5, 'pct' => 6];
    private const FILA_ENCABEZADO = 5;

    public function extension(): string
    {
        return 'xlsx';
    }

    public function tipoContenido(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function generar(Reporte $r): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ttx');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>' . $this->e($r->titulo) . '</dc:title><dc:creator>TriTransportes</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created></cp:coreProperties>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Reporte" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">Reporte!$A$' . self::FILA_ENCABEZADO . ':$' . $this->col(count($r->columnas) - 1) . '$' . (self::FILA_ENCABEZADO + count($r->filas)) . '</definedName></definedNames></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', $this->estilos());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->hoja($r));
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        unlink($tmp);
        return $bytes;
    }

    private function hoja(Reporte $r): string
    {
        $cols = $r->columnas;
        $n = count($cols);
        $ultima = $this->col($n - 1);
        $filas = '';
        $filas .= $this->filaTexto(1, [$r->titulo], 7);
        $filas .= $this->filaTexto(2, [$r->empresa . ' · Emitido el ' . date('d-m-Y H:i') . ' por ' . $r->emisor], 0);
        $filas .= $this->filaTexto(3, [$r->filtrosTexto()], 0);
        $filas .= $this->filaTexto(self::FILA_ENCABEZADO, array_column($cols, 0), 1);

        $i = self::FILA_ENCABEZADO;
        foreach ($r->filas as $f) {
            $i++;
            $celdas = '';
            $c = 0;
            foreach ($cols as $clave => [, $tipo]) {
                $celdas .= $this->celda($this->col($c++) . $i, $f[$clave] ?? null, $tipo);
            }
            $filas .= "<row r=\"$i\">$celdas</row>";
        }
        if ($r->totales) {
            $t = $i + 1;
            $celdas = '<c r="A' . $t . '" t="inlineStr" s="8"><is><t>TOTALES</t></is></c>';
            $c = 0;
            foreach ($cols as $clave => [, $tipo]) {
                $ref = $this->col($c) . $t;
                if ($c > 0 && array_key_exists($clave, $r->totales)) {
                    $v = $r->totales[$clave];
                    $estilo = 10 + (self::ESTILO[$tipo] ?? 0);
                    if (in_array($clave, $r->sumables, true) && $i > self::FILA_ENCABEZADO) {
                        $rango = $this->col($c) . (self::FILA_ENCABEZADO + 1) . ':' . $this->col($c) . $i;
                        $celdas .= "<c r=\"$ref\" s=\"$estilo\"><f>SUM($rango)</f><v>" . (float) $v . '</v></c>';
                    } else {
                        $celdas .= $v === null ? "<c r=\"$ref\" s=\"8\"/>" : "<c r=\"$ref\" s=\"$estilo\"><v>" . (float) $v . '</v></c>';
                    }
                }
                $c++;
            }
            $filas .= "<row r=\"$t\">$celdas</row>";
        }

        $anchos = '';
        $c = 0;
        foreach ($cols as [$etiqueta, $tipo]) {
            $w = max(mb_strlen($etiqueta) + 2, $tipo === 'texto' ? 22 : 13);
            $anchos .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $w . '" customWidth="1"/>';
            $c++;
        }
        $fe = self::FILA_ENCABEZADO;
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . $fe . '" topLeftCell="A' . ($fe + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            . "<cols>$anchos</cols><sheetData>$filas</sheetData>"
            . '<autoFilter ref="A' . $fe . ':' . $ultima . ($fe + count($r->filas)) . '"/>'
            . '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
    }

    private function celda(string $ref, mixed $v, string $tipo): string
    {
        if ($v === null || $v === '') {
            return "<c r=\"$ref\"/>";
        }
        $s = self::ESTILO[$tipo] ?? 0;
        if ($tipo === 'fecha') {
            $serial = (int) (strtotime(substr((string) $v, 0, 10) . ' UTC') / 86400) + 25569; // días desde 1900 en Excel
            return "<c r=\"$ref\" s=\"$s\"><v>$serial</v></c>";
        }
        if ($tipo !== 'texto') {
            return "<c r=\"$ref\" s=\"$s\"><v>" . (float) $v . '</v></c>';
        }
        return "<c r=\"$ref\" t=\"inlineStr\"><is><t xml:space=\"preserve\">" . $this->e((string) $v) . '</t></is></c>';
    }

    private function filaTexto(int $fila, array $textos, int $estilo): string
    {
        $c = '';
        foreach ($textos as $i => $t) {
            $c .= '<c r="' . $this->col($i) . $fila . "\" t=\"inlineStr\" s=\"$estilo\"><is><t xml:space=\"preserve\">" . $this->e((string) $t) . '</t></is></c>';
        }
        return "<row r=\"$fila\">$c</row>";
    }

    /** Estilos: 0 normal · 1 encabezado · 2 fecha · 3 entero · 4 decimal · 5 CLP · 6 % · 7 título · 8 total texto · 10+ igual que 0..6 pero en negrita (totales). */
    private function estilos(): string
    {
        $fmt = [0 => 0, 1 => 0, 2 => 164, 3 => 3, 4 => 4, 5 => 165, 6 => 166, 7 => 0, 8 => 0];
        $xfs = '';
        foreach ($fmt as $i => $numFmt) {
            $font = match ($i) { 1 => 1, 7 => 2, 8 => 1, default => 0 };
            $fill = $i === 1 ? 2 : ($i === 8 ? 3 : 0);
            $xfs .= "<xf numFmtId=\"$numFmt\" fontId=\"$font\" fillId=\"$fill\" borderId=\"0\" applyNumberFormat=\"1\" applyFont=\"1\" applyFill=\"1\"/>";
        }
        $xfs .= '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'; // 9 (reservado)
        foreach ([0, 0, 164, 3, 4, 165, 166] as $numFmt) {           // 10..16: totales en negrita con fondo
            $xfs .= "<xf numFmtId=\"$numFmt\" fontId=\"1\" fillId=\"3\" borderId=\"0\" applyNumberFormat=\"1\" applyFont=\"1\" applyFill=\"1\"/>";
        }
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="3"><numFmt numFmtId="164" formatCode="dd\-mm\-yyyy"/><numFmt numFmtId="165" formatCode="&quot;$&quot;#,##0"/><numFmt numFmtId="166" formatCode="0.0&quot; %&quot;"/></numFmts>'
            . '<fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="14"/><color rgb="FF0F172A"/><name val="Calibri"/></font></fonts>'
            . '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE2E8F0"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFEF3C7"/></patternFill></fill></fills>'
            . '<borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="17">' . $xfs . '</cellXfs></styleSheet>';
    }

    private function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }
        return $s;
    }

    private function e(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
