<?php
/**
 * TriTransportes — Exporter
 * ----------------------------------------------------------------------------
 * Generador de archivos Excel (.xlsx) 100% nativo en PHP (usa la extensión Zip,
 * estándar en PHP 8). No requiere librerías externas. Produce un libro con:
 *   - Encabezado de reporte (título + empresa + filtros + fecha de generación)
 *   - Fila de cabeceras con estilo (fondo azul marca, texto blanco, negrita)
 *   - Filas con cebra, números reales (sumables) y fechas formateadas
 *   - Ancho de columnas, panel congelado en la cabecera y autofiltro
 *
 * Si la extensión Zip no estuviera disponible, cae automáticamente a CSV (UTF-8
 * con BOM) que Excel abre sin problemas.
 */
class Exporter
{
    /**
     * Envía el archivo Excel al navegador y termina la ejecución.
     *
     * @param string $titulo       Título del reporte (hoja + cabecera).
     * @param array  $columnas     [['l'=>label,'t'=>tipo,'w'=>ancho], ...]
     * @param array  $filas        Filas de datos ya formateadas: cada fila es
     *                             un array indexado igual que $columnas, con
     *                             ['v'=>valorCrudo,'num'=>bool] por celda.
     * @param array  $meta         ['empresa'=>..., 'filtros'=>..., 'usuario'=>...]
     */
    public static function download(string $titulo, array $columnas, array $filas, array $meta = []): void
    {
        $fileBase = self::slug($titulo) . '_' . date('Ymd_His');

        if (class_exists('ZipArchive')) {
            self::downloadXlsx($fileBase . '.xlsx', $titulo, $columnas, $filas, $meta);
        } else {
            self::downloadCsv($fileBase . '.csv', $columnas, $filas);
        }
        exit;
    }

    // ── XLSX ────────────────────────────────────────────────────────────────
    private static function downloadXlsx(string $filename, string $titulo, array $cols, array $filas, array $meta): void
    {
        $nCols   = count($cols);
        $lastCol = self::colLetter($nCols);

        // Filas superiores informativas
        $infoEmpresa = $meta['empresa'] ?? '';
        $infoFiltros = $meta['filtros'] ?? '';
        $infoFecha   = 'Generado: ' . date('d/m/Y H:i') . (isset($meta['usuario']) ? '  ·  por ' . $meta['usuario'] : '');

        // ── sheetData ──
        $rowsXml = '';
        $r = 1;

        // Fila 1: título
        $rowsXml .= '<row r="' . $r . '" ht="26" customHeight="1">'
                  . self::cell('A' . $r, $titulo, false, 4) . '</row>';
        $r++;
        // Fila 2: empresa
        $rowsXml .= '<row r="' . $r . '" ht="16" customHeight="1">'
                  . self::cell('A' . $r, $infoEmpresa, false, 5) . '</row>';
        $r++;
        // Fila 3: filtros + fecha
        $rowsXml .= '<row r="' . $r . '" ht="16" customHeight="1">'
                  . self::cell('A' . $r, trim($infoFiltros . '   ' . $infoFecha), false, 5) . '</row>';
        $r++;
        // Fila 4: en blanco
        $rowsXml .= '<row r="' . $r . '"></row>';
        $r++;

        $headerRow = $r;
        // Fila de cabeceras
        $cellsXml = '';
        $c = 1;
        foreach ($cols as $col) {
            $cellsXml .= self::cell(self::colLetter($c) . $r, $col['l'], false, 2);
            $c++;
        }
        $rowsXml .= '<row r="' . $r . '" ht="20" customHeight="1">' . $cellsXml . '</row>';
        $r++;

        // Filas de datos
        $zebra = false;
        foreach ($filas as $fila) {
            $cellsXml = '';
            $c = 1;
            foreach ($fila as $cell) {
                $ref   = self::colLetter($c) . $r;
                $isNum = !empty($cell['num']);
                $style = $zebra ? 3 : 1;
                $cellsXml .= self::cell($ref, $cell['v'], $isNum, $style);
                $c++;
            }
            $rowsXml .= '<row r="' . $r . '">' . $cellsXml . '</row>';
            $r++;
            $zebra = !$zebra;
        }
        $lastDataRow = $r - 1;

        // <cols> anchos
        $colsXml = '<cols>';
        $c = 1;
        foreach ($cols as $col) {
            $w = isset($col['w']) ? (float)$col['w'] : 16;
            $colsXml .= '<col min="' . $c . '" max="' . $c . '" width="' . $w . '" customWidth="1"/>';
            $c++;
        }
        $colsXml .= '</cols>';

        $merge   = '<mergeCells count="3">'
                 . '<mergeCell ref="A1:' . $lastCol . '1"/>'
                 . '<mergeCell ref="A2:' . $lastCol . '2"/>'
                 . '<mergeCell ref="A3:' . $lastCol . '3"/>'
                 . '</mergeCells>';

        $freeze  = '<sheetViews><sheetView workbookViewId="0" showGridLines="0">'
                 . '<pane ySplit="' . $headerRow . '" topLeftCell="A' . ($headerRow + 1) . '" activePane="bottomLeft" state="frozen"/>'
                 . '</sheetView></sheetViews>';

        $autofilter = '<autoFilter ref="A' . $headerRow . ':' . $lastCol . $lastDataRow . '"/>';

        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $freeze
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . $colsXml
            . '<sheetData>' . $rowsXml . '</sheetData>'
            . $autofilter
            . '</worksheet>';

        $files = [
            '[Content_Types].xml' => self::contentTypes(),
            '_rels/.rels'         => self::rels(),
            'xl/workbook.xml'     => self::workbook($titulo),
            'xl/_rels/workbook.xml.rels' => self::workbookRels(),
            'xl/styles.xml'       => self::styles(),
            'xl/worksheets/sheet1.xml' => $sheet,
        ];

        // Construir el zip en un archivo temporal
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        $data = file_get_contents($tmp);
        @unlink($tmp);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($data));
        header('Cache-Control: max-age=0, no-cache, must-revalidate');
        echo $data;
    }

    /** Construye una celda. $style: 1=normal,2=cabecera,3=cebra,4=título,5=info */
    private static function cell(string $ref, $value, bool $isNum, int $style): string
    {
        $s = ' s="' . $style . '"';
        if ($isNum && $value !== '' && $value !== null && is_numeric($value)) {
            return '<c r="' . $ref . '"' . $s . '><v>' . $value . '</v></c>';
        }
        $text = self::xml((string)$value);
        return '<c r="' . $ref . '"' . $s . ' t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';
    }

    private static function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbook(string $titulo): string
    {
        $name = self::xml(mb_substr($titulo, 0, 31));
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    /** Hoja de estilos: 0=default,1=normal,2=cabecera,3=cebra,4=título,5=info */
    private static function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="4">'
            .   '<font><sz val="11"/><color theme="1"/><name val="Calibri"/></font>'                                   // 0 default
            .   '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'                          // 1 blanco negrita
            .   '<font><b/><sz val="16"/><color rgb="FF0F172A"/><name val="Calibri"/></font>'                          // 2 título
            .   '<font><sz val="10"/><color rgb="FF64748B"/><name val="Calibri"/></font>'                             // 3 info
            . '</fonts>'
            . '<fills count="4">'
            .   '<fill><patternFill patternType="none"/></fill>'                                                        // 0
            .   '<fill><patternFill patternType="gray125"/></fill>'                                                     // 1
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FF0F172A"/></patternFill></fill>'                 // 2 azul marca
            .   '<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/></patternFill></fill>'                 // 3 cebra
            . '</fills>'
            . '<borders count="2">'
            .   '<border><left/><right/><top/><bottom/><diagonal/></border>'                                            // 0
            .   '<border><left style="thin"><color rgb="FFE2E8F0"/></left><right style="thin"><color rgb="FFE2E8F0"/></right>'
            .     '<top style="thin"><color rgb="FFE2E8F0"/></top><bottom style="thin"><color rgb="FFE2E8F0"/></bottom></border>' // 1 fino
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="6">'
            .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                        // 0 default
            .   '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>' // 1 normal
            .   '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment vertical="center" horizontal="left"/></xf>' // 2 cabecera
            .   '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf>' // 3 cebra
            .   '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>' // 4 título
            .   '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"><alignment vertical="center"/></xf>' // 5 info
            . '</cellXfs>'
            . '</styleSheet>';
    }

    // ── CSV fallback ─────────────────────────────────────────────────────────
    private static function downloadCsv(string $filename, array $cols, array $filas): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF"; // BOM UTF-8
        $out = fopen('php://output', 'w');
        fputcsv($out, array_map(fn($c) => $c['l'], $cols), ';');
        foreach ($filas as $fila) {
            fputcsv($out, array_map(fn($cell) => (string)$cell['v'], $fila), ';');
        }
        fclose($out);
    }

    // ── Utilidades ────────────────────────────────────────────────────────────
    private static function xml(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function colLetter(int $n): string
    {
        $s = '';
        while ($n > 0) {
            $m = ($n - 1) % 26;
            $s = chr(65 + $m) . $s;
            $n = (int)(($n - $m) / 26);
        }
        return $s;
    }

    private static function slug(string $s): string
    {
        $s = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s) ?: $s;
        $s = preg_replace('/[^A-Za-z0-9]+/', '_', $s);
        return trim($s, '_') ?: 'export';
    }
}
