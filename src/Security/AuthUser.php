<?php
declare(strict_types=1);

namespace TriTransportes\Security;

use TriTransportes\Domain\Rol;

/** Usuario autenticado, reconstruido desde el JWT y confirmado contra la base en cada solicitud. */
final class AuthUser
{
    public function __construct(
        public readonly int $id,
        public readonly int $empresaId,
        public readonly string $nombre,
        public readonly string $email,
        public readonly Rol $rol,
        public readonly ?int $conductorId = null,
    ) {
    }

    public function es(Rol ...$roles): bool
    {
        return in_array($this->rol, $roles, true);
    }

    /** @return array{id: int, nombre: string, email: string, rol: string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'nombre' => $this->nombre, 'email' => $this->email, 'rol' => $this->rol->value];
    }
}
