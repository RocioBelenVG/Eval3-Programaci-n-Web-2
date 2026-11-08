<?php
declare(strict_types=1);

namespace TriTransportes\Service;

use TriTransportes\Domain\EstadoMantencion;
use TriTransportes\Exception\ApiException;
use TriTransportes\Infrastructure\Database;
use TriTransportes\Repository\AuditoriaRepository;
use TriTransportes\Repository\FlotaRepository;
use TriTransportes\Repository\MantencionRepository;
use TriTransportes\Security\AuthUser;

final class MantencionService
{
    public function __construct(
        private readonly Database $db,
        private readonly MantencionRepository $mantenciones,
        private readonly FlotaRepository $flota,
        private readonly AuditoriaRepository $auditoria,
    ) {
    }

    public function listar(AuthUser $u, ?string $estado, int $page, int $perPage): array
    {
        $r = $this->mantenciones->listar($u->empresaId, $estado, $page, $perPage);
        $r['data'] = array_map([$this, 'presentar'], $r['data']);
        return $r;
    }

    public function crear(AuthUser $u, array $d, string $ip): array
    {
        if (!$this->flota->equipo($u->empresaId, $d['equipo_id'])) {
            throw ApiException::field('equipo_id', 'El equipo no existe.');
        }
        $fila = $d + ['empresa_id' => $u->empresaId, 'estado' => EstadoMantencion::Pendiente->value, 'creado_por' => $u->id];
        $id = $this->db->transactional(function () use ($u, $fila, $ip) {
            $id = $this->mantenciones->crear($fila);
            $this->auditoria->registrar($u, 'mantencion', 'crear', 'mantencion', $id, null, $fila, $ip);
            return $id;
        });
        return $this->presentar($this->mantenciones->buscar($u->empresaId, $id));
    }

    public function cambiarEstado(AuthUser $u, int $id, EstadoMantencion $destino, string $ip): array
    {
        $m = $this->mantenciones->buscar($u->empresaId, $id);
        if (!$m) {
            throw ApiException::notFound('La mantención no existe.');
        }
        $actual = EstadoMantencion::from($m['estado']);
        if (!$actual->puedePasarA($destino)) {
            $sig = $actual->siguiente();
            throw ApiException::conflict(
                "No se puede pasar de '{$actual->value}' a '{$destino->value}'." . ($sig ? " El siguiente estado es '{$sig->value}'." : ' La mantención ya está completada.'),
                'TRANSICION_INVALIDA'
            );
        }
        $this->db->transactional(function () use ($u, $id, $actual, $destino, $ip) {
            $this->mantenciones->cambiarEstado($id, $destino->value);
            $this->auditoria->registrar($u, 'mantencion', 'estado', 'mantencion', $id, ['estado' => $actual->value], ['estado' => $destino->value], $ip);
        });
        return $this->presentar($this->mantenciones->buscar($u->empresaId, $id));
    }

    private function presentar(array $m): array
    {
        return [
            'id' => (int) $m['id'],
            'equipo' => ['id' => (int) $m['equipo_id'], 'codigo' => $m['equipo_codigo'], 'nombre' => $m['equipo_nombre']],
            'tipo' => $m['tipo'],
            'fecha' => $m['fecha'],
            'descripcion' => $m['descripcion'],
            'costo_total' => $m['costo_total'] !== null ? (float) $m['costo_total'] : null,
            'estado' => $m['estado'],
            'created_at' => $m['created_at'],
            'updated_at' => $m['updated_at'],
        ];
    }
}
