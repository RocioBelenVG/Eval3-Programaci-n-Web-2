<?php
declare(strict_types=1);

namespace TriTransportes\Service;

use TriTransportes\Exception\ApiException;
use TriTransportes\Ia\IaClient;
use TriTransportes\Ia\IaException;
use TriTransportes\Repository\AuditoriaRepository;
use TriTransportes\Repository\IndicadorRepository;
use TriTransportes\Security\AuthUser;
use TriTransportes\Security\RateLimiter;

/**
 * Resumen gerencial con IA (RF18): calcula los indicadores del período y del período anterior
 * de igual duración, se los entrega al modelo y valida lo que responde.
 *
 * Errores del servicio externo → formato de error estándar:
 *   sin clave 503 · límite del proveedor 503 · sin respuesta a tiempo 504 · error o respuesta inválida 502
 */
final class IaService
{
    public const LIMITE_POR_MINUTO = 10;
    public const MAX_DIAS = 366;

    private const INSTRUCCIONES = <<<'TXT'
        Eres analista de operaciones de TRITRANSPORTES SpA, una empresa chilena de transporte de carga para obras.
        Recibirás indicadores agregados de un período y del período anterior de igual duración, en JSON.
        Escribe en español de Chile, en tono profesional y directo, para la gerencia.
        Usa SOLO las cifras entregadas: no inventes datos, nombres ni causas que no se deduzcan de ellas.
        Compara con el período anterior cuando exista y destaca lo que más afecta el costo o el cumplimiento.
        El campo "enfoque" indica qué priorizar: general, combustible o flota.
        Responde ÚNICAMENTE un objeto JSON con esta forma:
        {"resumen": "un párrafo de 60 a 140 palabras", "recomendaciones": ["acción concreta 1", "acción concreta 2", "acción concreta 3"]}
        Cada recomendación es una acción verificable de máximo 30 palabras. Entre 2 y 4 recomendaciones.
        TXT;

    public function __construct(
        private readonly IaClient $cliente,
        private readonly IndicadorRepository $indicadores,
        private readonly RateLimiter $limiter,
        private readonly AuditoriaRepository $auditoria,
    ) {
    }

    /** @return array{resumen: string, recomendaciones: list<string>, modelo: string, datos: array} */
    public function resumen(AuthUser $u, string $desde, string $hasta, string $enfoque, string $ip): array
    {
        $d1 = new \DateTimeImmutable($desde);
        $d2 = new \DateTimeImmutable($hasta);
        if ($d2 < $d1) {
            throw ApiException::field('hasta', 'La fecha final debe ser igual o posterior a la inicial.');
        }
        $dias = (int) $d1->diff($d2)->days + 1;
        if ($dias > self::MAX_DIAS) {
            throw ApiException::field('hasta', 'El período no puede superar ' . self::MAX_DIAS . ' días.');
        }
        if (!$this->cliente->configurado()) {
            throw new ApiException(503, 'IA_NO_CONFIGURADA', 'El servicio de IA no está configurado en este ambiente (falta GROQ_API_KEY).');
        }
        $this->limiter->hit('ia:' . $u->id, self::LIMITE_POR_MINUTO, 60);

        $antHasta = $d1->modify('-1 day');
        $antDesde = $antHasta->modify('-' . ($dias - 1) . ' days');
        $datos = [
            'enfoque' => $enfoque,
            'periodo_actual' => $this->indicadores->resumenPeriodo($u->empresaId, $desde, $hasta),
            'periodo_anterior' => $this->indicadores->resumenPeriodo($u->empresaId, $antDesde->format('Y-m-d'), $antHasta->format('Y-m-d')),
        ];
        if ($datos['periodo_actual']['viajes']['total'] === 0) {
            throw ApiException::field('desde', 'No hay viajes registrados en el período elegido.');
        }

        try {
            $r = $this->cliente->completarJson(self::INSTRUCCIONES, json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } catch (IaException $e) {
            throw match ($e->tipo) {
                IaException::TIMEOUT => new ApiException(504, 'IA_SIN_RESPUESTA', 'El servicio de IA no respondió a tiempo. Intente de nuevo en unos minutos.'),
                IaException::SATURADO => new ApiException(503, 'IA_SATURADA', 'El servicio de IA alcanzó su límite de uso. Intente de nuevo en unos minutos.', [], ['Retry-After' => '60']),
                default => new ApiException(502, 'IA_ERROR_PROVEEDOR', 'El servicio de IA respondió con un error. El resto del sistema sigue funcionando.'),
            };
        }

        $resultado = $this->validarRespuesta($r) + ['modelo' => $this->cliente->modelo(), 'datos' => $datos];
        $this->auditoria->registrar($u, 'ia', 'resumen', 'ia_resumen', 0, null, ['desde' => $desde, 'hasta' => $hasta, 'enfoque' => $enfoque, 'modelo' => $resultado['modelo']], $ip);
        return $resultado;
    }

    /**
     * El texto del modelo se trata como dato no confiable: se exige la forma pedida,
     * se eliminan etiquetas y se acota el largo. La interfaz lo muestra como texto, nunca como HTML.
     * @return array{resumen: string, recomendaciones: list<string>}
     */
    public function validarRespuesta(array $r): array
    {
        $limpiar = fn (string $t, int $max) => mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($t))), 0, $max);
        $resumen = is_string($r['resumen'] ?? null) ? $limpiar($r['resumen'], 1500) : '';
        $recs = is_array($r['recomendaciones'] ?? null) ? $r['recomendaciones'] : [];
        $recs = array_values(array_filter(array_map(fn ($x) => is_string($x) ? $limpiar($x, 300) : '', $recs)));
        if (mb_strlen($resumen) < 40 || $recs === []) {
            throw new ApiException(502, 'IA_RESPUESTA_INVALIDA', 'El servicio de IA entregó una respuesta incompleta. Intente de nuevo.');
        }
        return ['resumen' => $resumen, 'recomendaciones' => array_slice($recs, 0, 4)];
    }
}
