<?php
declare(strict_types=1);

namespace TriTransportes\Controller;

use TriTransportes\Domain\EstadoViaje;
use TriTransportes\Http\Request;
use TriTransportes\Http\Response;
use TriTransportes\Service\ViajeService;
use TriTransportes\Validation\Validator;

final class ViajeController
{
    /** Reglas del esquema ViajeInput del contrato OpenAPI. */
    private const REGLAS_VIAJE = [
        'vehiculo_id' => ['required', 'int', 'min:1'],
        'conductor_id' => ['required', 'int', 'min:1'],
        'obra_id' => ['nullable', 'int', 'min:1'],
        'fecha' => ['required', 'date'],
        'hora_salida' => ['nullable', 'time'],
        'origen' => ['required', 'string', 'min:2', 'max:200'],
        'destino' => ['required', 'string', 'min:2', 'max:200'],
        'km_inicio' => ['nullable', 'int', 'min:0'],
        'cantidad' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        'unidad' => ['nullable', 'in:m3,ton,viaje'],
        'observaciones' => ['nullable', 'string', 'max:1000'],
    ];

    public function __construct(private readonly ViajeService $viajes)
    {
    }

    public function index(Request $r): Response
    {
        [$page, $perPage] = Validator::paginacion($r->query);
        $f = Validator::check($r->query, [
            'q' => ['string', 'max:100'],
            'estado' => ['in:programado,en_ruta,completado,cancelado'],
            'vehiculo_id' => ['int', 'min:1'],
            'conductor_id' => ['int', 'min:1'],
            'obra_id' => ['int', 'min:1'],
            'desde' => ['date'],
            'hasta' => ['date'],
            'sort' => ['in:fecha,-fecha,estado,-estado,vehiculo,-vehiculo'],
        ]);
        $sort = $f['sort'] ?? '-fecha';
        unset($f['sort']);
        return Response::json($this->viajes->listar($r->user(), $f, $sort, $page, $perPage));
    }

    public function show(Request $r): Response
    {
        return Response::json($this->viajes->obtener($r->user(), (int) $r->param('id')));
    }

    public function store(Request $r): Response
    {
        $d = Validator::check($r->json(), self::REGLAS_VIAJE);
        $viaje = $this->viajes->crear($r->user(), $d, $r->ip);
        return Response::json($viaje, 201, ['Location' => "/api/v1/viajes/{$viaje['id']}"]);
    }

    public function update(Request $r): Response
    {
        $d = Validator::check($r->json(), self::REGLAS_VIAJE);
        return Response::json($this->viajes->actualizar($r->user(), (int) $r->param('id'), $d, $r->ip));
    }

    public function destroy(Request $r): Response
    {
        $this->viajes->eliminar($r->user(), (int) $r->param('id'), $r->ip);
        return Response::noContent();
    }

    public function estado(Request $r): Response
    {
        $d = Validator::check($r->json(), [
            'estado' => ['required', 'in:programado,en_ruta,completado,cancelado'],
            'km_fin' => ['nullable', 'int', 'min:0'],
            'motivo' => ['nullable', 'string', 'min:3', 'max:255'],
        ]);
        return Response::json($this->viajes->cambiarEstado($r->user(), (int) $r->param('id'), EstadoViaje::from($d['estado']), $d, $r->ip));
    }

    public function guia(Request $r): Response
    {
        $d = Validator::check($r->json(), [
            'numero_guia' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9\-]+$/'],
            'receptor' => ['required', 'string', 'min:2', 'max:150'],
            'rut_receptor' => ['nullable', 'rut'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);
        return Response::json($this->viajes->emitirGuia($r->user(), (int) $r->param('id'), $d, $r->ip), 201);
    }
}
