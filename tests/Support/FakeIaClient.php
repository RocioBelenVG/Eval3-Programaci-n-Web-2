<?php
declare(strict_types=1);

namespace TriTransportes\Tests\Support;

use TriTransportes\Ia\IaClient;
use TriTransportes\Ia\IaException;

/** Doble de prueba del servicio de IA: responde lo que se le programe, sin salir a internet. */
final class FakeIaClient implements IaClient
{
    public ?string $ultimosDatos = null;
    public int $llamadas = 0;

    /** @param array<string, mixed>|IaException $respuesta */
    public function __construct(
        private array|IaException $respuesta = [],
        private readonly bool $configurado = true,
    ) {
        if ($respuesta === []) {
            $this->respuesta = [
                'resumen' => 'En el período se completó la mayor parte de los viajes programados, pero el costo de combustible por viaje subió respecto del período anterior.',
                'recomendaciones' => ['Revisar el consumo del camión con peor rendimiento.', 'Cerrar a diario los viajes que quedan abiertos.'],
            ];
        }
    }

    public function completarJson(string $instrucciones, string $datos): array
    {
        $this->llamadas++;
        $this->ultimosDatos = $datos;
        if ($this->respuesta instanceof IaException) {
            throw $this->respuesta;
        }
        return $this->respuesta;
    }

    public function modelo(): string
    {
        return 'modelo-simulado';
    }

    public function configurado(): bool
    {
        return $this->configurado;
    }
}
