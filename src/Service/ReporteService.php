<?php
declare(strict_types=1);

namespace TriTransportes\Service;

use TriTransportes\Domain\Rol;
use TriTransportes\Exception\ApiException;
use TriTransportes\Export\CsvExportador;
use TriTransportes\Export\Exportador;
use TriTransportes\Export\PdfExportador;
use TriTransportes\Export\XlsxExportador;
use TriTransportes\Report\Reporte;
use TriTransportes\Repository\AuditoriaRepository;
use TriTransportes\Repository\DashboardRepository;
use TriTransportes\Repository\FlotaRepository;
use TriTransportes\Security\AuthUser;

/**
 * Los tres reportes de la pauta:
 *   operativo-viajes      detalle de viajes (operativo)
 *   gerencial-mensual     resumen por mes con totales (gerencial; la interfaz agrega el gráfico)
 *   combustible-periodo   litros, costo y rendimiento por vehículo entre dos fechas (parametrizable)
 * La misma definición alimenta la tabla en pantalla y la exportación, así que ambas muestran lo mismo.
 */
final class ReporteService
{
    public const MAX_FILAS_EXPORTACION = 20000;

    /** Roles por reporte (la ruta ya exige Administrador, Supervisor o Gerencia). */
    private const ROLES = [
        'operativo-viajes' => [Rol::Administrador, Rol::Supervisor, Rol::Gerencia],
        'gerencial-mensual' => [Rol::Administrador, Rol::Gerencia],
        'combustible-periodo' => [Rol::Administrador, Rol::Supervisor, Rol::Gerencia],
    ];

    private const ESTADOS = ['programado' => 'Programado', 'en_ruta' => 'En ruta', 'completado' => 'Completado', 'cancelado' => 'Cancelado'];

    public function __construct(
        private readonly DashboardRepository $repo,
        private readonly FlotaRepository $flota,
        private readonly AuditoriaRepository $auditoria,
        private readonly string $empresa = 'TRITRANSPORTES SpA',
    ) {
    }

    /** @return array<string, Exportador> */
    public static function exportadores(): array
    {
        return ['csv' => new CsvExportador(), 'xlsx' => new XlsxExportador(), 'pdf' => new PdfExportador()];
    }

    /** Datos paginados para la interfaz. */
    public function datos(AuthUser $u, string $nombre, array $f, int $page, int $perPage): array
    {
        $paginado = $nombre === 'operativo-viajes';
        $r = $this->construir($u, $nombre, $f, $paginado ? $page : null, $perPage);
        $filas = $r['reporte']->filas;
        $meta = $r['meta'] ?? ['page' => 1, 'per_page' => count($filas), 'total' => count($filas), 'total_pages' => 1];
        return [
            'reporte' => $nombre,
            'titulo' => $r['reporte']->titulo,
            'columnas' => array_map(fn ($k, $c) => ['clave' => $k, 'etiqueta' => $c[0], 'tipo' => $c[1]], array_keys($r['reporte']->columnas), $r['reporte']->columnas),
            'data' => $filas,
            'totales' => $r['reporte']->totales,
            'filtros' => $r['reporte']->filtros,
            'meta' => $meta,
        ];
    }

    /** @return array{0: string, 1: string, 2: string} [contenido, tipo MIME, nombre de archivo] */
    public function exportar(AuthUser $u, string $nombre, string $formato, array $f, string $ip): array
    {
        $exp = self::exportadores()[$formato] ?? throw ApiException::field('formato', 'Formato no soportado. Opciones: csv, xlsx, pdf.');
        $reporte = $this->construir($u, $nombre, $f, null, 0)['reporte'];
        if (count($reporte->filas) > self::MAX_FILAS_EXPORTACION) {
            throw ApiException::field('desde', 'El reporte supera ' . self::MAX_FILAS_EXPORTACION . ' filas. Acote el período o los filtros.');
        }
        $contenido = $exp->generar($reporte);
        // Registro de quién exportó qué, con qué filtros y en qué formato
        $this->auditoria->registrar($u, 'reportes', 'exportar', 'reporte', 0, null,
            ['reporte' => $nombre, 'formato' => $formato, 'filtros' => $reporte->filtros, 'filas' => count($reporte->filas), 'bytes' => strlen($contenido)], $ip);
        return [$contenido, $exp->tipoContenido(), $reporte->nombreArchivo($exp->extension())];
    }

    private function construir(AuthUser $u, string $nombre, array $f, ?int $page, int $perPage): array
    {
        if (!isset(self::ROLES[$nombre])) {
            throw ApiException::notFound('El reporte no existe.');
        }
        if (!in_array($u->rol, self::ROLES[$nombre], true)) {
            throw ApiException::forbidden('Su rol no tiene acceso a este reporte.');
        }
        [$desde, $hasta] = DashboardService::rango($f);
        $filtros = ['Período' => date('d-m-Y', strtotime($desde)) . ' al ' . date('d-m-Y', strtotime($hasta))];
        $e = $u->empresaId;

        if ($nombre === 'operativo-viajes') {
            if (!empty($f['estado'])) $filtros['Estado'] = self::ESTADOS[$f['estado']] ?? $f['estado'];
            if (!empty($f['vehiculo_id'])) $filtros['Vehículo'] = $this->flota->vehiculo($e, (int) $f['vehiculo_id'])['patente'] ?? '#' . $f['vehiculo_id'];
            if (!empty($f['conductor_id'])) $filtros['Conductor'] = $this->flota->conductor($e, (int) $f['conductor_id'])['nombre'] ?? '#' . $f['conductor_id'];
            if (!empty($f['obra_id'])) $filtros['Obra'] = $this->flota->obra($e, (int) $f['obra_id'])['nombre'] ?? '#' . $f['obra_id'];
            $r = $this->repo->detalleViajes($e, ['desde' => $desde, 'hasta' => $hasta] + $f, $page, $perPage);
            $filas = array_map(fn ($v) => ['estado' => self::ESTADOS[$v['estado']] ?? $v['estado'], 'cantidad' => $v['cantidad'] !== null ? (float) $v['cantidad'] : null] + $v, $r['data']);
            $rep = new Reporte('operativo-viajes', 'Detalle de viajes', [
                'id' => ['N.º', 'entero', 7], 'fecha' => ['Fecha', 'fecha', 10], 'patente' => ['Vehículo', 'texto', 9],
                'conductor' => ['Conductor', 'texto', 17], 'origen' => ['Origen', 'texto', 19], 'destino' => ['Destino', 'texto', 19],
                'obra' => ['Obra', 'texto', 22], 'cantidad' => ['Carga', 'decimal', 8], 'unidad' => ['Unidad', 'texto', 6],
                'km_recorridos' => ['Km', 'entero', 7], 'estado' => ['Estado', 'texto', 11],
            ], $filas, ['id' => (int) $r['totales']['viajes'], 'cantidad' => (float) $r['totales']['cantidad'], 'km_recorridos' => (int) $r['totales']['km']],
                $filtros, $this->empresa, $u->nombre, ['cantidad', 'km_recorridos']);
            return ['reporte' => $rep, 'meta' => $r['meta'] ?? null];
        }

        if ($nombre === 'gerencial-mensual') {
            $viajes = $this->repo->viajesPorMes($e, $desde, $hasta);
            $comb = array_column($this->repo->combustiblePorMes($e, $desde, $hasta), null, 'mes');
            $filas = [];
            $t = ['viajes' => 0, 'completados' => 0, 'cancelados' => 0, 'km' => 0, 'litros' => 0.0, 'costo' => 0.0];
            foreach ($viajes as $m) {
                $c = $comb[$m['mes']] ?? ['litros' => 0, 'costo' => 0];
                $fila = [
                    'mes' => $m['mes'], 'viajes' => (int) $m['viajes'], 'completados' => (int) $m['completados'], 'cancelados' => (int) $m['cancelados'],
                    'cumplimiento' => $m['viajes'] ? round(100 * $m['completados'] / $m['viajes'], 1) : null,
                    'km' => (int) $m['km'], 'litros' => round((float) $c['litros'], 1), 'costo' => round((float) $c['costo']),
                    'costo_viaje' => $m['completados'] ? round($c['costo'] / $m['completados']) : null,
                    'kml' => $c['litros'] > 0 ? round($m['km'] / $c['litros'], 2) : null,
                ];
                foreach ($t as $k => $_) $t[$k] += $fila[$k];
                $filas[] = $fila;
            }
            $tot = $t + [
                'cumplimiento' => $t['viajes'] ? round(100 * $t['completados'] / $t['viajes'], 1) : null,
                'costo_viaje' => $t['completados'] ? round($t['costo'] / $t['completados']) : null,
                'kml' => $t['litros'] > 0 ? round($t['km'] / $t['litros'], 2) : null,
            ];
            $rep = new Reporte('gerencial-mensual', 'Resumen gerencial mensual', [
                'mes' => ['Mes', 'texto', 9], 'viajes' => ['Viajes', 'entero', 9], 'completados' => ['Completados', 'entero', 11],
                'cancelados' => ['Cancelados', 'entero', 10], 'cumplimiento' => ['Cumplimiento', 'pct', 11], 'km' => ['Km', 'entero', 10],
                'litros' => ['Litros', 'decimal', 11], 'costo' => ['Costo combustible', 'clp', 14], 'costo_viaje' => ['Costo por viaje', 'clp', 12],
                'kml' => ['km/L', 'decimal', 8],
            ], $filas, $tot, $filtros, $this->empresa, $u->nombre, ['viajes', 'completados', 'cancelados', 'km', 'litros', 'costo']);
            return ['reporte' => $rep];
        }

        // combustible-periodo
        $filas = [];
        $t = ['cargas' => 0, 'litros' => 0.0, 'costo' => 0.0, 'viajes' => 0, 'km' => 0];
        foreach ($this->repo->porVehiculo($e, $desde, $hasta) as $v) {
            $fila = [
                'patente' => $v['patente'], 'categoria' => $v['categoria'], 'cargas' => (int) $v['cargas'], 'litros' => round((float) $v['litros'], 1),
                'costo' => round((float) $v['costo']), 'precio' => $v['litros'] > 0 ? round($v['costo'] / $v['litros']) : null,
                'viajes' => (int) $v['viajes'], 'km' => (int) $v['km'], 'kml' => $v['litros'] > 0 && $v['km'] > 0 ? round($v['km'] / $v['litros'], 2) : null,
            ];
            foreach ($t as $k => $_) $t[$k] += $fila[$k];
            $filas[] = $fila;
        }
        $tot = $t + ['precio' => $t['litros'] > 0 ? round($t['costo'] / $t['litros']) : null, 'kml' => $t['litros'] > 0 ? round($t['km'] / $t['litros'], 2) : null];
        $rep = new Reporte('combustible-periodo', 'Combustible por vehículo en el período', [
            'patente' => ['Vehículo', 'texto', 10], 'categoria' => ['Categoría', 'texto', 14], 'cargas' => ['Cargas', 'entero', 8],
            'litros' => ['Litros', 'decimal', 11], 'costo' => ['Costo', 'clp', 13], 'precio' => ['Precio medio por litro', 'clp', 13],
            'viajes' => ['Viajes completados', 'entero', 11], 'km' => ['Km', 'entero', 10], 'kml' => ['km/L', 'decimal', 8],
        ], $filas, $tot, $filtros, $this->empresa, $u->nombre, ['cargas', 'litros', 'costo', 'viajes', 'km']);
        return ['reporte' => $rep];
    }
}
