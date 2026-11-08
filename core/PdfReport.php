<?php
/**
 * TriTransportes — Generador de reportes PDF
 * ----------------------------------------------------------------------------
 * Envoltura sobre FPDF que produce el formato estándar de la plataforma:
 * encabezado con marca y empresa, metadatos del reporte (filtros, emisor,
 * fecha), tabla de datos con anchos proporcionales, fila de totales y pie con
 * numeración de páginas.
 *
 * FPDF trabaja en ISO-8859-1: todo texto pasa por tx() antes de imprimirse.
 */

require_once ROOT . '/libs/fpdf/fpdf.php';

class PdfReport extends FPDF
{
    private string $tituloReporte = '';
    private string $empresa       = '';
    private string $filtros       = '';
    private string $emisor        = '';
    private array  $cols          = [];   // [['l'=>label,'w'=>ancho,'align'=>'L|R|C'], ...]
    private array  $anchos        = [];
    private float  $fuenteTabla   = 8.0;

    /** Convierte a la codificación que entiende FPDF. */
    public static function tx(?string $s): string
    {
        $s = (string)$s;
        if ($s === '') return '';
        $out = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
        return $out === false ? utf8_decode($s) : $out;
    }

    public function __construct(string $orientacion = 'L')
    {
        parent::__construct($orientacion, 'mm', 'A4');
        $this->SetAutoPageBreak(true, 18);
        $this->AliasNbPages();
    }

    public function configurar(string $titulo, string $empresa, string $filtros, string $emisor): void
    {
        $this->tituloReporte = $titulo;
        $this->empresa       = $empresa;
        $this->filtros       = $filtros;
        $this->emisor        = $emisor;
    }

    /**
     * Define las columnas y reparte el ancho disponible de forma proporcional
     * a los pesos que ya declara DataViews para cada campo.
     */
    public function definirColumnas(array $cols): void
    {
        $this->cols = $cols;
        $util  = $this->GetPageWidth() - 20;               // márgenes de 10 mm
        $suma  = array_sum(array_map(fn($c) => max(6, (float)($c['w'] ?? 14)), $cols));
        $this->anchos = [];
        foreach ($cols as $c) {
            $peso = max(6, (float)($c['w'] ?? 14));
            $this->anchos[] = round($util * ($peso / $suma), 2);
        }
        // Con muchas columnas se reduce la tipografía para que quepa todo
        $n = count($cols);
        $this->fuenteTabla = $n > 12 ? 6.0 : ($n > 9 ? 6.8 : ($n > 6 ? 7.5 : 8.5));
    }

    // ── Encabezado de cada página ───────────────────────────────────────────
    function Header(): void
    {
        // Franja de marca
        $this->SetFillColor(15, 23, 42);          // navy corporativo
        $this->Rect(0, 0, $this->GetPageWidth(), 22, 'F');

        $this->SetY(6);
        $this->SetX(10);
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor(245, 158, 11);        // ámbar
        $this->Cell(60, 6, self::tx('TriTransportes'), 0, 0, 'L');

        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 6, self::tx($this->tituloReporte), 0, 1, 'R');

        $this->SetX(10);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(200, 210, 225);
        $this->Cell(90, 5, self::tx($this->empresa), 0, 0, 'L');
        $this->Cell(0, 5, self::tx('Emitido: ' . date('d/m/Y H:i') . ($this->emisor ? '  ·  Por: ' . $this->emisor : '')), 0, 1, 'R');

        // Línea de filtros aplicados
        $this->SetY(24);
        $this->SetX(10);
        $this->SetFont('Helvetica', 'I', 7.5);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 4, self::tx($this->filtros), 0, 1, 'L');

        $this->Ln(1);
        $this->encabezadoTabla();
    }

    /** Fila de títulos de la tabla (se repite en cada página). */
    private function encabezadoTabla(): void
    {
        if (!$this->cols) return;
        $this->SetFont('Helvetica', 'B', $this->fuenteTabla);
        $this->SetFillColor(241, 245, 249);
        $this->SetTextColor(51, 65, 85);
        $this->SetDrawColor(203, 213, 225);
        $this->SetLineWidth(0.15);
        $this->SetX(10);

        foreach ($this->cols as $i => $c) {
            $this->Cell($this->anchos[$i], 6.5, self::tx($c['l']), 1, 0, 'C', true);
        }
        $this->Ln();
    }

    // ── Pie de página ───────────────────────────────────────────────────────
    function Footer(): void
    {
        $this->SetY(-14);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(148, 163, 184);
        $this->Cell(0, 5, self::tx('TriTransportes · Documento generado automáticamente'), 0, 0, 'L');
        $this->Cell(0, 5, self::tx('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    /**
     * Imprime las filas. Cada celda ya viene formateada como texto.
     * $filas: array de arrays con el mismo orden de columnas.
     */
    public function filas(array $filas): void
    {
        $this->SetFont('Helvetica', '', $this->fuenteTabla);
        $this->SetTextColor(30, 41, 59);
        $this->SetDrawColor(226, 232, 240);

        $alterna = false;
        foreach ($filas as $fila) {
            // Salto de página controlado: el Header vuelve a dibujar los títulos
            if ($this->GetY() > $this->GetPageHeight() - 22) {
                $this->AddPage($this->CurOrientation);
                $this->SetFont('Helvetica', '', $this->fuenteTabla);
            }

            $alterna = !$alterna;
            $this->SetFillColor(250, 251, 252);
            $this->SetX(10);

            foreach ($this->cols as $i => $c) {
                $valor = (string)($fila[$i] ?? '');
                $ancho = $this->anchos[$i];
                $this->Cell($ancho, 5.6, self::tx($this->ajustar($valor, $ancho)), 'LR', 0,
                            $c['align'] ?? 'L', $alterna);
            }
            $this->Ln();
        }

        // Cierre inferior de la tabla
        $this->SetX(10);
        $this->Cell(array_sum($this->anchos), 0, '', 'T');
        $this->Ln(1);
    }

    /** Fila de totales al pie de la tabla. */
    public function totales(array $valores): void
    {
        if (!$valores) return;
        $this->SetFont('Helvetica', 'B', $this->fuenteTabla);
        $this->SetFillColor(255, 247, 230);
        $this->SetTextColor(146, 64, 14);
        $this->SetX(10);

        foreach ($this->cols as $i => $c) {
            $v = $valores[$i] ?? '';
            if ($i === 0 && $v === '') $v = 'TOTALES';
            $this->Cell($this->anchos[$i], 6.2, self::tx($this->ajustar((string)$v, $this->anchos[$i])),
                        1, 0, $c['align'] ?? 'L', true);
        }
        $this->Ln();
    }

    /** Nota final con el número de registros. */
    public function resumenFinal(int $nRegistros): void
    {
        $this->Ln(2);
        $this->SetFont('Helvetica', 'I', 8);
        $this->SetTextColor(100, 116, 139);
        $this->Cell(0, 5, self::tx($nRegistros . ' registro' . ($nRegistros === 1 ? '' : 's') . ' en el reporte.'), 0, 1, 'L');
    }

    /** Recorta el texto que no cabe en el ancho de la celda. */
    private function ajustar(string $texto, float $ancho): string
    {
        $texto = trim(preg_replace('/\s+/', ' ', $texto));
        if ($texto === '') return '';
        $max = $ancho - 2;
        if ($this->GetStringWidth(self::tx($texto)) <= $max) return $texto;

        while (mb_strlen($texto) > 1 && $this->GetStringWidth(self::tx($texto . '...')) > $max) {
            $texto = mb_substr($texto, 0, mb_strlen($texto) - 1);
        }
        return $texto . '...';
    }
}
