<?php
declare(strict_types=1);

namespace TriTransportes\Service;

use TriTransportes\Domain\EstadoViaje;
use TriTransportes\Domain\Rol;
use TriTransportes\Exception\ApiException;
use TriTransportes\Infrastructure\Database;
use TriTransportes\Repository\AuditoriaRepository;
use TriTransportes\Repository\FlotaRepository;
use TriTransportes\Repository\GuiaRepository;
use TriTransportes\Repository\ViajeRepository;
use TriTransportes\Security\AuthUser;

/** Reglas de negocio de los viajes: programación, modificación, máquina de estados y guías. */
final class ViajeService
{
    public function __construct(
        private readonly Database $db,
        private readonly ViajeRepository $viajes,
        private readonly FlotaRepository $flota,
        private readonly GuiaRepository $guias,
        private readonly AuditoriaRepository $auditoria,
    ) {
    }

    public function listar(AuthUser $u, array $filtros, string $sort, int $page, int $perPage): array
    {
        if ($u->rol === Rol::Conductor) {
            $filtros['conductor_id'] = $u->conductorId ?? -1; // el conductor solo ve sus viajes
        }
        $r = $this->viajes->listar($u->empresaId, $filtros, $sort, $page, $perPage);
        $r['data'] = array_map(fn ($v) => $this->presentar($v, $u), $r['data']);
        return $r;
    }

    public function obtener(AuthUser $u, int $id): array
    {
        return $this->presentar($this->cargar($u, $id), $u);
    }

    public function crear(AuthUser $u, array $d, string $ip): array
    {
        $this->validarReferencias($u, $d);
        $id = $this->db->transactional(function () use ($u, $d, $ip) {
            if (!isset($d['km_inicio'])) {
                $d['km_inicio'] = (int) ($this->flota->vehiculo($u->empresaId, $d['vehiculo_id'])['odometro'] ?? 0);
            }
            $id = $this->viajes->crear($d + ['empresa_id' => $u->empresaId, 'estado' => EstadoViaje::Programado->value, 'creado_por' => $u->id]);
            $this->auditoria->registrar($u, 'transporte', 'crear', 'viaje', $id, null, $d, $ip);
            return $id;
        });
        return $this->obtener($u, $id);
    }

    public function actualizar(AuthUser $u, int $id, array $d, string $ip): array
    {
        $antes = $this->cargar($u, $id);
        if ($antes['estado'] !== EstadoViaje::Programado->value) {
            throw ApiException::conflict("Solo se puede modificar un viaje programado; este está '{$antes['estado']}'.", 'VIAJE_NO_EDITABLE');
        }
        $this->validarReferencias($u, $d);
        $this->db->transactional(function () use ($u, $id, $d, $antes, $ip) {
            $this->viajes->actualizar($id, $d);
            $this->auditoria->registrar($u, 'transporte', 'editar', 'viaje', $id, $antes, $d, $ip);
        });
        return $this->obtener($u, $id);
    }

    public function eliminar(AuthUser $u, int $id, string $ip): void
    {
        $antes = $this->cargar($u, $id);
        if ($antes['estado'] !== EstadoViaje::Programado->value) {
            throw ApiException::conflict('Solo se puede eliminar un viaje programado. Un viaje iniciado se cancela.', 'VIAJE_NO_ELIMINABLE');
        }
        if ($this->viajes->cantidadGuias($id) > 0) {
            throw ApiException::conflict('El viaje tiene guías de despacho emitidas y no se puede eliminar.', 'VIAJE_CON_GUIAS');
        }
        $this->db->transactional(function () use ($u, $id, $antes, $ip) {
            $this->viajes->eliminar($id);
            $this->auditoria->registrar($u, 'transporte', 'eliminar', 'viaje', $id, $antes, null, $ip);
        });
    }

    /**
     * Aplica una transición de la máquina de estados.
     * Orden de las verificaciones: existe (404) → transición válida (409) → rol/asignación (403) → datos (422).
     */
    public function cambiarEstado(AuthUser $u, int $id, EstadoViaje $destino, array $d, string $ip): array
    {
        $viaje = $this->cargar($u, $id);
        $actual = EstadoViaje::from($viaje['estado']);

        if (!$actual->puedePasarA($destino)) {
            $permitidas = array_map(fn ($e) => $e->value, $actual->siguientes());
            $msg = "No se puede pasar de '{$actual->value}' a '{$destino->value}'."
                . ($permitidas ? ' Transiciones válidas: ' . implode(', ', $permitidas) . '.' : " '{$actual->value}' es un estado final.");
            throw ApiException::conflict($msg, 'TRANSICION_INVALIDA');
        }
        if (!in_array($u->rol, EstadoViaje::rolesPara($destino), true)) {
            throw ApiException::forbidden("Su rol no puede dejar un viaje en '{$destino->value}'.");
        }

        $cambios = ['estado' => $destino->value];
        if ($destino === EstadoViaje::EnRuta && $viaje['hora_salida'] === null) {
            $cambios['hora_salida'] = date('H:i');
        }
        if ($destino === EstadoViaje::Completado) {
            if (!isset($d['km_fin'])) {
                throw ApiException::field('km_fin', 'Para completar el viaje debe informar el kilometraje final.');
            }
            if ($viaje['km_inicio'] !== null && $d['km_fin'] < (int) $viaje['km_inicio']) {
                throw ApiException::field('km_fin', "El kilometraje final debe ser mayor o igual al inicial ({$viaje['km_inicio']}).");
            }
            $cambios['km_fin'] = $d['km_fin'];
            $llegada = date('H:i');
            $cambios['hora_llegada'] = ($viaje['hora_salida'] !== null && $llegada < $viaje['hora_salida']) ? $viaje['hora_salida'] : $llegada;
        }
        if ($destino === EstadoViaje::Cancelado) {
            if (empty($d['motivo'])) {
                throw ApiException::field('motivo', 'Para cancelar el viaje debe indicar el motivo.');
            }
            $obs = trim(($viaje['observaciones'] ?? '') . "\n[Cancelado " . date('Y-m-d H:i') . " por {$u->nombre}] {$d['motivo']}");
            $cambios['observaciones'] = $obs;
        }

        $this->db->transactional(function () use ($u, $id, $viaje, $cambios, $ip) {
            $this->viajes->actualizar($id, $cambios);
            if (isset($cambios['km_fin'])) {
                $this->flota->avanzarOdometro((int) $viaje['vehiculo_id'], (int) $cambios['km_fin']);
            }
            $this->auditoria->registrar($u, 'transporte', 'estado', 'viaje', $id, ['estado' => $viaje['estado']], $cambios, $ip);
        });
        return $this->obtener($u, $id);
    }

    public function emitirGuia(AuthUser $u, int $viajeId, array $d, string $ip): array
    {
        $viaje = $this->cargar($u, $viajeId);
        if ($viaje['estado'] !== EstadoViaje::Completado->value) {
            throw ApiException::conflict('Solo se emite una guía de despacho para un viaje completado.', 'VIAJE_NO_COMPLETADO');
        }
        if ($this->guias->numeroExiste($u->empresaId, $d['numero_guia'])) {
            throw ApiException::conflict("Ya existe una guía con el número {$d['numero_guia']}.", 'GUIA_DUPLICADA');
        }
        $id = $this->db->transactional(function () use ($u, $viaje, $d, $ip) {
            // La guía copia los datos del viaje: es un documento legal que conserva los valores del momento de su emisión.
            $id = $this->guias->crear([
                'empresa_id' => $u->empresaId,
                'viaje_id' => $viaje['id'],
                'obra_id' => $viaje['obra_id'],
                'numero_guia' => $d['numero_guia'],
                'fecha' => date('Y-m-d'),
                'vehiculo_id' => $viaje['vehiculo_id'],
                'conductor_id' => $viaje['conductor_id'],
                'cantidad' => $viaje['cantidad'],
                'unidad' => $viaje['unidad'],
                'origen' => $viaje['origen'],
                'destino' => $viaje['destino'],
                'receptor' => $d['receptor'],
                'rut_receptor' => $d['rut_receptor'] ?? null,
                'observaciones' => $d['observaciones'] ?? null,
                'creado_por' => $u->id,
            ]);
            $this->auditoria->registrar($u, 'transporte', 'crear', 'guia', $id, null, $d + ['viaje_id' => $viaje['id']], $ip);
            return $id;
        });
        return $this->guias->buscar($u->empresaId, $id);
    }

    /** Busca el viaje dentro de la empresa; para el Conductor, solo si está asignado a él (si no, 404: no se revela que existe). */
    private function cargar(AuthUser $u, int $id): array
    {
        $v = $this->viajes->buscar($u->empresaId, $id);
        if (!$v || ($u->rol === Rol::Conductor && (int) $v['conductor_id'] !== $u->conductorId)) {
            throw ApiException::notFound('El viaje no existe.');
        }
        return $v;
    }

    private function validarReferencias(AuthUser $u, array $d): void
    {
        $errores = [];
        $veh = $this->flota->vehiculo($u->empresaId, $d['vehiculo_id']);
        if (!$veh) {
            $errores[] = ['field' => 'vehiculo_id', 'message' => 'El vehículo no existe.'];
        } elseif (!(int) $veh['activo']) {
            $errores[] = ['field' => 'vehiculo_id', 'message' => "El vehículo {$veh['patente']} está dado de baja."];
        }
        $con = $this->flota->conductor($u->empresaId, $d['conductor_id']);
        if (!$con || !(int) $con['activo']) {
            $errores[] = ['field' => 'conductor_id', 'message' => 'El conductor no existe o no está activo.'];
        } elseif ($con['licencia_vence'] !== null && $con['licencia_vence'] < $d['fecha']) {
            $errores[] = ['field' => 'conductor_id', 'message' => "La licencia de {$con['nombre']} vence el {$con['licencia_vence']}, antes de la fecha del viaje."];
        }
        if (!empty($d['obra_id']) && !$this->flota->obra($u->empresaId, $d['obra_id'])) {
            $errores[] = ['field' => 'obra_id', 'message' => 'La obra no existe.'];
        }
        if ($errores) {
            throw ApiException::validation($errores);
        }
    }

    /** Da forma a la respuesta según el esquema `Viaje` del contrato. */
    private function presentar(array $v, AuthUser $u): array
    {
        $estado = EstadoViaje::from($v['estado']);
        $puedeOperar = $u->rol !== Rol::Conductor || (int) $v['conductor_id'] === $u->conductorId;
        return [
            'id' => (int) $v['id'],
            'estado' => $v['estado'],
            'fecha' => $v['fecha'],
            'hora_salida' => $v['hora_salida'],
            'hora_llegada' => $v['hora_llegada'],
            'origen' => $v['origen'],
            'destino' => $v['destino'],
            'km_inicio' => $v['km_inicio'] !== null ? (int) $v['km_inicio'] : null,
            'km_fin' => $v['km_fin'] !== null ? (int) $v['km_fin'] : null,
            'cantidad' => $v['cantidad'] !== null ? (float) $v['cantidad'] : null,
            'unidad' => $v['unidad'],
            'observaciones' => $v['observaciones'],
            'vehiculo_id' => (int) $v['vehiculo_id'],
            'conductor_id' => (int) $v['conductor_id'],
            'obra_id' => $v['obra_id'] !== null ? (int) $v['obra_id'] : null,
            'vehiculo' => ['id' => (int) $v['vehiculo_id'], 'patente' => $v['patente']],
            'conductor' => ['id' => (int) $v['conductor_id'], 'nombre' => $v['conductor_nombre']],
            'obra' => $v['obra_id'] !== null ? ['id' => (int) $v['obra_id'], 'nombre' => $v['obra_nombre']] : null,
            'transiciones_permitidas' => $puedeOperar ? $estado->siguientesPara($u->rol) : [],
            'created_at' => $v['created_at'],
            'updated_at' => $v['updated_at'],
        ];
    }
}
