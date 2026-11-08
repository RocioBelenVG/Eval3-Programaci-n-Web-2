<?php
declare(strict_types=1);

namespace TriTransportes\Report;

/**
 * Un reporte ya calculado, independiente del formato: lo usan la tabla de la interfaz
 * y los tres exportadores (CSV, XLSX y PDF).
 *
 * Tipos de columna: texto, fecha, entero, decimal, clp, pct.
 */
final class Reporte
{
    /**
     * @param array<string, array{0: string, 1: string, 2?: int}> $columnas clave => [etiqueta, tipo, peso de ancho]
     * @param list<array<string, mixed>> $filas
     * @param array<string, mixed> $totales clave => valor (solo las columnas que se totalizan)
     * @param array<string, string> $filtros etiqueta => valor legible
     */
    public function __construct(
        public readonly string $clave,
        public readonly string $titulo,
        public readonly array $columnas,
        public readonly array $filas,
        public readonly array $totales,
        public readonly array $filtros,
        public readonly string $empresa,
        public readonly string $emisor,
        public readonly array $sumables = [],
    ) {
    }

    public function filtrosTexto(): string
    {
        return 'Filtros: ' . implode(' · ', array_map(fn ($k, $v) => "$k: $v", array_keys($this->filtros), $this->filtros));
    }

    public function nombreArchivo(string $ext): string
    {
        return 'tritransportes_' . $this->clave . '_' . date('Ymd_Hi') . '.' . $ext;
    }

    /** Valor formateado para texto (PDF y pantalla), con convenciones de Chile. */
    public static function texto(mixed $v, string $tipo): string
    {
        if ($v === null || $v === '') {
            return '—';
        }
        return match ($tipo) {
            'fecha' => date('d-m-Y', strtotime((string) $v)),
            'entero' => number_format((float) $v, 0, ',', '.'),
            'decimal' => number_format((float) $v, 2, ',', '.'),
            'clp' => '$' . number_format((float) $v, 0, ',', '.'),
            'pct' => number_format((float) $v, 1, ',', '.') . ' %',
            default => (string) $v,
        };
    }
}
