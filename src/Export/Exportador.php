<?php
declare(strict_types=1);

namespace TriTransportes\Export;

use TriTransportes\Report\Reporte;

/**
 * Estrategia de exportación (patrón Strategy, ADR-06): cada formato es una clase.
 * Agregar un formato nuevo no cambia el servicio de reportes ni el controlador.
 */
interface Exportador
{
    public function extension(): string;

    public function tipoContenido(): string;

    /** Genera el archivo completo en memoria. */
    public function generar(Reporte $r): string;
}
