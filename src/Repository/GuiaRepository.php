<?php
declare(strict_types=1);

namespace TriTransportes\Repository;

final class GuiaRepository extends Repository
{
    public function numeroExiste(int $empresaId, string $numero): bool
    {
        return (bool) $this->scalar('SELECT 1 FROM guias_despacho WHERE empresa_id = ? AND numero_guia = ?', [$empresaId, $numero]);
    }

    public function crear(array $datos): int
    {
        return $this->insert('guias_despacho', $datos);
    }

    public function buscar(int $empresaId, int $id): ?array
    {
        return $this->one(
            'SELECT id, viaje_id, numero_guia, fecha, receptor, rut_receptor, cantidad, unidad, observaciones
             FROM guias_despacho WHERE empresa_id = ? AND id = ?',
            [$empresaId, $id]
        );
    }
}
