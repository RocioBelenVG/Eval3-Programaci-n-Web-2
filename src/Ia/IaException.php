<?php
declare(strict_types=1);

namespace TriTransportes\Ia;

/** Falla del servicio de IA externo, clasificada para traducirla al código HTTP correcto. */
final class IaException extends \RuntimeException
{
    public const TIMEOUT = 'timeout';         // → 504
    public const PROVEEDOR = 'proveedor';     // → 502
    public const RESPUESTA = 'respuesta';     // → 502 (el modelo respondió algo inválido)
    public const SATURADO = 'saturado';       // → 503 (límite del proveedor)

    public function __construct(public readonly string $tipo, string $detalle)
    {
        parent::__construct($detalle);
    }
}
