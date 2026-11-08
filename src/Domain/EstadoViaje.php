<?php
declare(strict_types=1);

namespace TriTransportes\Domain;

/**
 * Máquina de estados del viaje (patrón State expresado con un enum):
 *
 *   programado ──► en_ruta ──► completado
 *        │            │
 *        └────────────┴──► cancelado
 *
 * completado y cancelado son estados finales. Cualquier otra transición es un 409.
 */
enum EstadoViaje: string
{
    case Programado = 'programado';
    case EnRuta = 'en_ruta';
    case Completado = 'completado';
    case Cancelado = 'cancelado';

    /** @return list<self> */
    public function siguientes(): array
    {
        return match ($this) {
            self::Programado => [self::EnRuta, self::Cancelado],
            self::EnRuta => [self::Completado, self::Cancelado],
            self::Completado, self::Cancelado => [],
        };
    }

    public function puedePasarA(self $destino): bool
    {
        return in_array($destino, $this->siguientes(), true);
    }

    public function esFinal(): bool
    {
        return $this->siguientes() === [];
    }

    /** Roles que pueden ejecutar la transición hacia $destino (el Conductor, además, debe estar asignado). */
    public static function rolesPara(self $destino): array
    {
        return match ($destino) {
            self::EnRuta, self::Completado => [Rol::Conductor, Rol::Supervisor, Rol::Administrador],
            self::Cancelado => [Rol::Supervisor, Rol::Administrador],
            self::Programado => [],
        };
    }

    /**
     * Transiciones que un usuario con este rol puede ejecutar desde el estado actual.
     * @return list<string>
     */
    public function siguientesPara(Rol $rol): array
    {
        return array_values(array_map(
            fn (self $e) => $e->value,
            array_filter($this->siguientes(), fn (self $e) => in_array($rol, self::rolesPara($e), true)),
        ));
    }
}
