<?php
declare(strict_types=1);

namespace TriTransportes\Service;

use TriTransportes\Domain\Rol;
use TriTransportes\Exception\ApiException;
use TriTransportes\Infrastructure\Database;
use TriTransportes\Repository\AuditoriaRepository;
use TriTransportes\Repository\CargaRepository;
use TriTransportes\Repository\FlotaRepository;
use TriTransportes\Security\AuthUser;

final class CargaService
{
    public const MAX_LITROS_VEHICULO = 600;
    public const MAX_LITROS_EQUIPO = 1500;

    public function __construct(
        private readonly Database $db,
        private readonly CargaRepository $cargas,
        private readonly FlotaRepository $flota,
        private readonly AuditoriaRepository $auditoria,
    ) {
    }

    public function listar(AuthUser $u, array $f, int $page, int $perPage): array
    {
        if ($u->rol === Rol::Conductor) {
            $f['conductor_id'] = $u->conductorId ?? -1;
        }
        $r = $this->cargas->listar($u->empresaId, $f, $page, $perPage);
        $r['data'] = array_map([$this, 'presentar'], $r['data']);
        return $r;
    }

    public function crear(AuthUser $u, array $d, string $ip): array
    {
        $errores = [];
        if ($d['tipo_unidad'] === 'vehiculo') {
            if (empty($d['vehiculo_id'])) {
                $errores[] = ['field' => 'vehiculo_id', 'message' => 'Indique el vehículo que recibe la carga.'];
            } elseif (!$this->flota->vehiculo($u->empresaId, $d['vehiculo_id'])) {
                $errores[] = ['field' => 'vehiculo_id', 'message' => 'El vehículo no existe.'];
            }
            if (!empty($d['equipo_id'])) {
                $errores[] = ['field' => 'equipo_id', 'message' => 'Una carga de vehículo no lleva equipo.'];
            }
            if ($d['litros'] > self::MAX_LITROS_VEHICULO) {
                $errores[] = ['field' => 'litros', 'message' => 'Un camión no carga más de ' . self::MAX_LITROS_VEHICULO . ' L de una vez.'];
            }
            if (isset($d['odometro']) && !empty($d['vehiculo_id'])) {
                $ultimo = $this->cargas->ultimoOdometro($d['vehiculo_id']);
                if ($ultimo !== null && $d['odometro'] < $ultimo) {
                    $errores[] = ['field' => 'odometro', 'message' => "El odómetro no puede ser menor al último registrado ($ultimo km)."];
                }
            }
        } else {
            if (empty($d['equipo_id'])) {
                $errores[] = ['field' => 'equipo_id', 'message' => 'Indique el equipo que recibe la carga.'];
            } elseif (!$this->flota->equipo($u->empresaId, $d['equipo_id'])) {
                $errores[] = ['field' => 'equipo_id', 'message' => 'El equipo no existe.'];
            }
            if (!empty($d['vehiculo_id'])) {
                $errores[] = ['field' => 'vehiculo_id', 'message' => 'Una carga de equipo no lleva vehículo.'];
            }
        }
        if (!empty($d['proveedor_id']) && !$this->flota->proveedorExiste($d['proveedor_id'])) {
            $errores[] = ['field' => 'proveedor_id', 'message' => 'El proveedor no existe.'];
        }
        if ($d['fecha'] > date('Y-m-d')) {
            $errores[] = ['field' => 'fecha', 'message' => 'La fecha de la carga no puede ser futura.'];
        }
        if ($errores) {
            throw ApiException::validation($errores);
        }

        $fila = [
            'empresa_id' => $u->empresaId,
            'tipo_unidad' => $d['tipo_unidad'],
            'vehiculo_id' => $d['tipo_unidad'] === 'vehiculo' ? $d['vehiculo_id'] : null,
            'equipo_id' => $d['tipo_unidad'] === 'equipo' ? $d['equipo_id'] : null,
            'conductor_id' => $u->rol === Rol::Conductor ? $u->conductorId : null,
            'fecha' => $d['fecha'],
            'litros' => $d['litros'],
            'precio_litro' => $d['precio_litro'] ?? null,
            'odometro' => $d['odometro'] ?? null,
            'horometro' => $d['horometro'] ?? null,
            'proveedor_id' => $d['proveedor_id'] ?? null,
            'creado_por' => $u->id,
        ];
        $id = $this->db->transactional(function () use ($u, $fila, $ip) {
            $id = $this->cargas->crear($fila);
            if ($fila['vehiculo_id'] && $fila['odometro']) {
                $this->flota->avanzarOdometro($fila['vehiculo_id'], $fila['odometro']);
            }
            $this->auditoria->registrar($u, 'combustible', 'crear', 'carga', $id, null, $fila, $ip);
            return $id;
        });
        return $this->presentar($this->cargas->buscar($u->empresaId, $id));
    }

    private function presentar(array $c): array
    {
        return [
            'id' => (int) $c['id'],
            'tipo_unidad' => $c['tipo_unidad'],
            'vehiculo_id' => $c['vehiculo_id'] !== null ? (int) $c['vehiculo_id'] : null,
            'equipo_id' => $c['equipo_id'] !== null ? (int) $c['equipo_id'] : null,
            'unidad' => $c['patente'] ?? $c['equipo_codigo'],
            'fecha' => $c['fecha'],
            'litros' => (float) $c['litros'],
            'precio_litro' => $c['precio_litro'] !== null ? (float) $c['precio_litro'] : null,
            'odometro' => $c['odometro'] !== null ? (int) $c['odometro'] : null,
            'horometro' => $c['horometro'] !== null ? (float) $c['horometro'] : null,
            'proveedor_id' => $c['proveedor_id'] !== null ? (int) $c['proveedor_id'] : null,
            'costo_total' => (float) $c['costo_total'],
        ];
    }
}
