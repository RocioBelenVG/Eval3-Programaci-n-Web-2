<?php
declare(strict_types=1);

namespace TriTransportes\Controller;

use TriTransportes\Http\Request;
use TriTransportes\Http\Response;
use TriTransportes\Service\DashboardService;
use TriTransportes\Service\ReporteService;
use TriTransportes\Validation\Validator;

/** Dashboard (indicadores y series) y reportes con exportación. */
final class DashboardController
{
    private const FILTROS = [
        'desde' => ['date'], 'hasta' => ['date'],
        'obra_id' => ['int', 'min:1'], 'vehiculo_id' => ['int', 'min:1'], 'conductor_id' => ['int', 'min:1'],
        'estado' => ['in:programado,en_ruta,completado,cancelado'],
    ];

    public function __construct(private readonly DashboardService $dashboard, private readonly ReporteService $reportes)
    {
    }

    private function filtros(Request $r): array
    {
        return array_filter(Validator::check($r->query, self::FILTROS), fn ($v) => $v !== null);
    }

    public function kpis(Request $r): Response
    {
        return Response::json($this->dashboard->kpis($r->user(), $this->filtros($r)));
    }

    public function series(Request $r): Response
    {
        $s = Validator::check($r->query, ['serie' => ['required', 'in:viajes_mes,rendimiento_vehiculo,costo_combustible_mes,viajes_por_estado']]);
        return Response::json($this->dashboard->serie($r->user(), $s['serie'], $this->filtros($r)));
    }

    public function reporte(Request $r): Response
    {
        [$page, $perPage] = Validator::paginacion($r->query);
        return Response::json($this->reportes->datos($r->user(), $r->param('reporte'), $this->filtros($r), $page, $perPage));
    }

    public function exportar(Request $r): Response
    {
        $f = Validator::check($r->query, ['formato' => ['required', 'in:xlsx,pdf,csv']]);
        [$contenido, $tipo, $archivo] = $this->reportes->exportar($r->user(), $r->param('reporte'), $f['formato'], $this->filtros($r), $r->ip);
        return new Response(200, $contenido, [
            'Content-Type' => $tipo,
            'Content-Disposition' => 'attachment; filename="' . $archivo . '"',
            'Content-Length' => (string) strlen($contenido),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
