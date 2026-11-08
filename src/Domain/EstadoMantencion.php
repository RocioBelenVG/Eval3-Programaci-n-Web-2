<?php
declare(strict_types=1);

namespace TriTransportes\Domain;

/** Flujo de una mantención: pendiente → en_proceso → completada (sin retrocesos ni saltos). */
enum EstadoMantencion: string
{
    case Pendiente = 'pendiente';
    case EnProceso = 'en_proceso';
    case Completada = 'completada';

    public function siguiente(): ?self
    {
        return match ($this) {
            self::Pendiente => self::EnProceso,
            self::EnProceso => self::Completada,
            self::Completada => null,
        };
    }

    public function puedePasarA(self $destino): bool
    {
        return $this->siguiente() === $destino;
    }
}
