<?php
declare(strict_types=1);

namespace TriTransportes\Repository;

use TriTransportes\Security\AuthUser;

/** Bitácora de auditoría: quién cambió qué, cuándo y desde dónde, con el antes y el después en JSON. */
final class AuditoriaRepository extends Repository
{
    public function registrar(AuthUser $u, string $modulo, string $accion, string $entidad, int $entidadId, ?array $antes, ?array $despues, string $ip): void
    {
        $json = fn (?array $d) => $d === null ? null : json_encode($d, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $this->insert('auditoria', [
            'empresa_id' => $u->empresaId,
            'usuario_id' => $u->id,
            'modulo' => $modulo,
            'accion' => $accion,
            'entidad_tipo' => $entidad,
            'entidad_id' => $entidadId,
            'datos_antes' => $json($antes),
            'datos_despues' => $json($despues),
            'ip' => substr($ip, 0, 45),
            'fecha' => date('Y-m-d H:i:s'),
        ]);
    }

    public function ultimo(int $empresaId, string $entidad, int $entidadId): ?array
    {
        return $this->one(
            'SELECT accion, datos_antes, datos_despues FROM auditoria WHERE empresa_id = ? AND entidad_tipo = ? AND entidad_id = ? ORDER BY id DESC LIMIT 1',
            [$empresaId, $entidad, $entidadId]
        );
    }
}
