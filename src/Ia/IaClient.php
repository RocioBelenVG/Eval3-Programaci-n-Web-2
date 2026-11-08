<?php
declare(strict_types=1);

namespace TriTransportes\Ia;

/**
 * Cliente de un modelo de lenguaje que responde JSON.
 * La interfaz permite cambiar de proveedor y, sobre todo, simularlo en las pruebas.
 */
interface IaClient
{
    /**
     * Envía las instrucciones y los datos, y devuelve la respuesta del modelo ya decodificada.
     * @return array<string, mixed>
     * @throws IaException
     */
    public function completarJson(string $instrucciones, string $datos): array;

    public function modelo(): string;

    public function configurado(): bool;
}
