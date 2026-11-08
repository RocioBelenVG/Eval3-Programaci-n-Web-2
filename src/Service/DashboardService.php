<?php
declare(strict_types=1);

namespace TriTransportes\Service;

use TriTransportes\Exception\ApiException;
use TriTransportes\Repository\DashboardRepository;
use TriTransportes\Security\AuthUser;

/**
 * Indicadores (los 6 de la sección 8.1 del informe) y series de los gráficos.
 * Cada indicador entrega su valor, fórmula, pregunta de negocio, numerador, denominador
 * y la variación frente al período anterior de igual duración.
 */
final class DashboardService
{
    public const MAX_DIAS = 731;

    public function __construct(private readonly DashboardRepository $repo)
    {
    }

    /** Valida el rango y devuelve [desde, hasta]; por defecto, los últimos 12 meses completos más el mes en curso. */
    public static function rango(array $f): array
    {
        $hasta = $f['hasta'] ?? date('Y-m-d');
        $desde = $f['desde'] ?? date('Y-m-01', strtotime('-12 months', strtotime($hasta)));
        if ($hasta < $desde) {
            throw ApiException::field('hasta', 'La fecha final debe ser igual o posterior a la inicial.');
        }
        if (self::dias($desde, $hasta) > self::MAX_DIAS) {
            throw ApiException::field('desde', 'El período no puede superar dos años.');
        }
        return [$desde, $hasta];
    }

    /** Días calendario entre dos fechas, ambas incluidas (sin el error de dividir segundos por 86.400 en un cambio de horario). */
    public static function dias(string $desde, string $hasta): int
    {
        return (new \DateTimeImmutable($desde))->diff(new \DateTimeImmutable($hasta))->days + 1;
    }

    public function kpis(AuthUser $u, array $f): array
    {
        [$desde, $hasta] = self::rango($f);
        $act = $this->calcular($this->repo->componentes($u->empresaId, $desde, $hasta, $f));
        $dias = self::dias($desde, $hasta);
        $antHasta = date('Y-m-d', strtotime("$desde -1 day"));
        $antDesde = date('Y-m-d', strtotime("$antHasta -" . ($dias - 1) . ' days'));
        $ant = $this->calcular($this->repo->componentes($u->empresaId, $antDesde, $antHasta, $f));

        $sinHistoria = $this->repo->componentes($u->empresaId, $antDesde, $antHasta, $f)['viajes'] === 0;
        foreach ($act as $k => &$kpi) {
            $a = $sinHistoria ? null : $ant[$k]['valor']; // sin viajes en el período anterior no hay con qué comparar
            $kpi['valor_periodo_anterior'] = $a;
            $kpi['variacion_vs_periodo_anterior'] = ($a === null || $kpi['valor'] === null || $a == 0) ? null
                : round(($kpi['valor'] - $a) / abs($a) * 100, 1);
        }
        return ['periodo' => compact('desde', 'hasta'), 'periodo_anterior' => ['desde' => $antDesde, 'hasta' => $antHasta], 'indicadores' => $act];
    }

    private function calcular(array $c): array
    {
        $div = fn ($a, $b, $d = 2) => $b > 0 ? round($a / $b, $d) : null;
        $kpi = fn ($titulo, $valor, $unidad, $formula, $pregunta, $num, $den, $mejor) =>
            compact('titulo', 'valor', 'unidad', 'formula', 'pregunta') + ['numerador' => $num, 'denominador' => $den, 'mejor_si' => $mejor];
        return [
            'rendimiento_km_l' => $kpi('Rendimiento de combustible', $div($c['km'], $c['litros']), 'km/L',
                'km de viajes completados ÷ litros cargados a vehículos', '¿Qué vehículos consumen de más?', $c['km'], round($c['litros'], 1), 'sube'),
            'costo_combustible_por_viaje' => $kpi('Costo de combustible por viaje', $div($c['costo'], $c['completados'], 0), 'CLP',
                'Σ (litros × precio por litro) ÷ viajes completados', '¿Cuánto cuesta mover cada viaje?', round($c['costo']), $c['completados'], 'baja'),
            'cumplimiento_viajes' => $kpi('Cumplimiento de viajes', $c['viajes'] ? round(100 * $c['completados'] / $c['viajes'], 1) : null, '%',
                'viajes completados ÷ viajes con fecha hasta hoy', '¿Se cumple lo que se programa?', $c['completados'], $c['viajes'], 'sube'),
            'disponibilidad_flota' => $kpi('Disponibilidad de equipos', $c['horas_calendario'] ? round(100 * (1 - $c['horas_detenidas'] / $c['horas_calendario']), 1) : null, '%',
                '1 − horas detenidas ÷ horas calendario de los equipos', '¿Cuánta maquinaria está operativa?', round($c['horas_detenidas']), $c['horas_calendario'], 'sube'),
            'mtbf_horas' => $kpi('MTBF', $c['fallas'] ? round($c['horas_operacion'] / $c['fallas']) : null, 'h',
                'horas de operación ÷ número de fallas correctivas', '¿Cada cuánto falla un equipo?', round($c['horas_operacion']), $c['fallas'], 'sube'),
            'tasa_cancelacion' => $kpi('Tasa de cancelación', $c['viajes'] ? round(100 * $c['cancelados'] / $c['viajes'], 1) : null, '%',
                'viajes cancelados ÷ viajes con fecha hasta hoy', '¿Cuánta capacidad se pierde?', $c['cancelados'], $c['viajes'], 'baja'),
        ];
    }

    /** Series para los 4 gráficos, en el formato {titulo, descripcion, labels, datasets} del contrato. */
    public function serie(AuthUser $u, string $serie, array $f): array
    {
        [$desde, $hasta] = self::rango($f);
        $e = $u->empresaId;
        switch ($serie) {
            case 'viajes_mes':
                $r = $this->repo->viajesPorMes($e, $desde, $hasta, $f);
                return $this->armar('Viajes por mes', 'Viajes completados, cancelados y abiertos de cada mes.', array_column($r, 'mes'), [
                    'Completados' => array_map('intval', array_column($r, 'completados')),
                    'Cancelados' => array_map('intval', array_column($r, 'cancelados')),
                    'Abiertos' => array_map('intval', array_column($r, 'abiertos')),
                ]);
            case 'costo_combustible_mes':
                $v = array_column($this->repo->viajesPorMes($e, $desde, $hasta, $f), 'completados', 'mes');
                $r = $this->repo->combustiblePorMes($e, $desde, $hasta, $f);
                return $this->armar('Costo de combustible por viaje', 'Costo de combustible de los vehículos dividido por los viajes completados del mes (CLP).',
                    array_column($r, 'mes'), [
                        'Costo por viaje (CLP)' => array_map(fn ($m) => !empty($v[$m['mes']]) ? (int) round($m['costo'] / $v[$m['mes']]) : null, $r),
                        'Precio por litro (CLP)' => array_map(fn ($m) => $m['litros'] > 0 ? (int) round($m['costo'] / $m['litros']) : null, $r),
                    ]);
            case 'rendimiento_vehiculo':
                $r = array_filter($this->repo->porVehiculo($e, $desde, $hasta, true, $f), fn ($x) => $x['litros'] > 0 && $x['km'] > 0);
                $r = array_map(fn ($x) => $x + ['kml' => round($x['km'] / $x['litros'], 2)], $r);
                usort($r, fn ($a, $b) => $b['kml'] <=> $a['kml']);
                return $this->armar('Rendimiento por camión', 'Km de viajes completados por litro cargado; solo camiones (las camionetas son otra clase de consumo).',
                    array_column($r, 'patente'), ['km/L' => array_column($r, 'kml')]);
            case 'viajes_por_estado':
                $r = array_column($this->repo->viajesPorEstado($e, $desde, $hasta, $f), 'n', 'estado');
                $orden = ['completado' => 'Completado', 'en_ruta' => 'En ruta', 'programado' => 'Programado', 'cancelado' => 'Cancelado'];
                return $this->armar('Viajes por estado', 'Distribución de los viajes del período según su estado.',
                    array_values($orden), ['Viajes' => array_map(fn ($k) => (int) ($r[$k] ?? 0), array_keys($orden))]);
        }
        throw ApiException::field('serie', 'Serie desconocida.');
    }

    private function armar(string $titulo, string $descripcion, array $labels, array $sets): array
    {
        return [
            'titulo' => $titulo, 'descripcion' => $descripcion, 'labels' => array_values($labels),
            'datasets' => array_map(fn ($k, $d) => ['label' => $k, 'data' => array_values($d)], array_keys($sets), $sets),
        ];
    }
}
