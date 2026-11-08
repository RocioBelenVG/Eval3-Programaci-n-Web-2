<?php
declare(strict_types=1);

namespace TriTransportes\Controller;

use TriTransportes\Domain\EstadoMantencion;
use TriTransportes\Exception\ApiException;
use TriTransportes\Http\Request;
use TriTransportes\Http\Response;
use TriTransportes\Repository\FlotaRepository;
use TriTransportes\Service\CargaService;
use TriTransportes\Service\MantencionService;
use TriTransportes\Validation\Validator;

/** Vehículos, cargas de combustible y mantenciones. */
final class FlotaController
{
    public function __construct(
        private readonly FlotaRepository $flota,
        private readonly CargaService $cargas,
        private readonly MantencionService $mantenciones,
    ) {
    }

    public function vehiculos(Request $r): Response
    {
        [$page, $perPage] = Validator::paginacion($r->query);
        $f = Validator::check($r->query, ['q' => ['string', 'max:100']]);
        $res = $this->flota->listarVehiculos($r->user()->empresaId, $f['q'] ?? null, $page, $perPage);
        $res['data'] = array_map(fn ($v) => [
            'id' => (int) $v['id'], 'patente' => $v['patente'], 'marca' => $v['marca'], 'modelo' => $v['modelo'],
            'anio' => $v['anio'] !== null ? (int) $v['anio'] : null, 'odometro' => (int) $v['odometro'],
            'categoria' => $v['categoria'], 'activo' => (bool) $v['activo'],
        ], $res['data']);
        return Response::json($res);
    }

    public function rendimiento(Request $r): Response
    {
        $u = $r->user();
        $id = (int) $r->param('id');
        if (!$this->flota->vehiculo($u->empresaId, $id)) {
            throw ApiException::notFound('El vehículo no existe.');
        }
        $f = Validator::check($r->query, ['desde' => ['date'], 'hasta' => ['date']]);
        $serie = array_map(fn ($m) => [
            'mes' => $m['mes'], 'km' => (int) $m['km'], 'litros' => (float) $m['litros'],
            'km_por_litro' => $m['km_por_litro'] !== null ? (float) $m['km_por_litro'] : null, 'costo' => (float) $m['costo'],
        ], $this->flota->rendimientoMensual($u->empresaId, $id, $f['desde'] ?? null, $f['hasta'] ?? null));
        return Response::json($serie);
    }

    public function cargasIndex(Request $r): Response
    {
        [$page, $perPage] = Validator::paginacion($r->query);
        $f = Validator::check($r->query, ['vehiculo_id' => ['int', 'min:1'], 'desde' => ['date'], 'hasta' => ['date']]);
        return Response::json($this->cargas->listar($r->user(), array_filter($f, fn ($v) => $v !== null), $page, $perPage));
    }

    public function cargasStore(Request $r): Response
    {
        $d = Validator::check($r->json(), [
            'tipo_unidad' => ['required', 'in:vehiculo,equipo'],
            'vehiculo_id' => ['nullable', 'int', 'min:1'],
            'equipo_id' => ['nullable', 'int', 'min:1'],
            'fecha' => ['required', 'date'],
            'litros' => ['required', 'numeric', 'gt:0', 'max:' . CargaService::MAX_LITROS_EQUIPO],
            'precio_litro' => ['nullable', 'numeric', 'gt:0', 'max:10000'],
            'odometro' => ['nullable', 'int', 'min:0'],
            'horometro' => ['nullable', 'numeric', 'min:0'],
            'proveedor_id' => ['nullable', 'int', 'min:1'],
        ]);
        return Response::json($this->cargas->crear($r->user(), $d, $r->ip), 201);
    }

    public function mantencionesIndex(Request $r): Response
    {
        [$page, $perPage] = Validator::paginacion($r->query);
        $f = Validator::check($r->query, ['estado' => ['in:pendiente,en_proceso,completada']]);
        return Response::json($this->mantenciones->listar($r->user(), $f['estado'] ?? null, $page, $perPage));
    }

    public function mantencionesStore(Request $r): Response
    {
        $d = Validator::check($r->json(), [
            'equipo_id' => ['required', 'int', 'min:1'],
            'tipo' => ['required', 'in:preventiva,correctiva'],
            'fecha' => ['required', 'date'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'costo_total' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);
        return Response::json($this->mantenciones->crear($r->user(), $d, $r->ip), 201);
    }

    public function mantencionesEstado(Request $r): Response
    {
        $d = Validator::check($r->json(), ['estado' => ['required', 'in:en_proceso,completada']]);
        return Response::json($this->mantenciones->cambiarEstado($r->user(), (int) $r->param('id'), EstadoMantencion::from($d['estado']), $r->ip));
    }
}
