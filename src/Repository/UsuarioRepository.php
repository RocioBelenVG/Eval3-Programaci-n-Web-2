<?php
declare(strict_types=1);

namespace TriTransportes\Repository;

final class UsuarioRepository extends Repository
{
    private const SELECT = 'SELECT u.id, u.empresa_id, u.nombre, u.email, u.password_hash, u.activo,
                                   r.nombre AS rol, c.id AS conductor_id
                            FROM usuarios u
                            JOIN roles r ON r.id = u.rol_id
                            LEFT JOIN conductores c ON c.trabajador_id = u.trabajador_id AND c.activo = 1';

    public function porEmail(string $email): ?array
    {
        return $this->one(self::SELECT . ' WHERE u.email = ?', [$email]);
    }

    public function porId(int $id): ?array
    {
        return $this->one(self::SELECT . ' WHERE u.id = ?', [$id]);
    }

    public function registrarAcceso(int $id): void
    {
        $this->exec('UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?', [$id]);
    }
}
