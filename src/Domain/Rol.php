<?php
declare(strict_types=1);

namespace TriTransportes\Domain;

/** Los cuatro roles del alcance evaluado (RF de seguridad). */
enum Rol: string
{
    case Administrador = 'Administrador';
    case Supervisor = 'Supervisor';
    case Conductor = 'Conductor';
    case Gerencia = 'Gerencia';

    /** Traduce el nombre del rol en la tabla `roles`; los demás roles heredados no acceden a la API. */
    public static function desdeBd(string $nombre): ?self
    {
        return match ($nombre) {
            'Administrador General', 'Administrador' => self::Administrador,
            'Supervisor' => self::Supervisor,
            'Conductor' => self::Conductor,
            'Gerencia' => self::Gerencia,
            default => null,
        };
    }

    /** Roles que gestionan la operación (programan, modifican y cancelan). */
    public function gestiona(): bool
    {
        return $this === self::Administrador || $this === self::Supervisor;
    }
}
