<?php
declare(strict_types=1);

namespace TriTransportes\Ia;

/**
 * Cliente de Groq (API compatible con OpenAI) para Llama 3.3 70B.
 * - La clave llega por constructor desde la variable de entorno GROQ_API_KEY; nunca se registra.
 * - Tiempo máximo de espera configurable (AI_TIMEOUT) y de conexión de 5 s.
 * - Pide respuesta en formato JSON (response_format = json_object).
 */
final class GroqClient implements IaClient
{
    private const URL = 'https://api.groq.com/openai/v1/chat/completions';

    public function __construct(
        private readonly string $apiKey,
        private readonly int $timeout = 20,
        private readonly string $modelo = 'llama-3.3-70b-versatile',
    ) {
    }

    public function modelo(): string
    {
        return $this->modelo;
    }

    public function configurado(): bool
    {
        return $this->apiKey !== '';
    }

    public function completarJson(string $instrucciones, string $datos): array
    {
        $payload = json_encode([
            'model' => $this->modelo,
            'temperature' => 0.2,
            'max_tokens' => 900,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $instrucciones],
                ['role' => 'user', 'content' => $datos],
            ],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $ch = curl_init(self::URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apiKey],
        ]);
        $cuerpo = curl_exec($ch);
        $errno = curl_errno($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            throw new IaException(IaException::TIMEOUT, "Groq no respondió en {$this->timeout} s");
        }
        if ($errno !== 0 || $cuerpo === false) {
            throw new IaException(IaException::PROVEEDOR, "Error de red con Groq (curl $errno)");
        }
        if ($http === 429) {
            throw new IaException(IaException::SATURADO, 'Groq rechazó la solicitud por límite de uso (429)');
        }
        if ($http >= 400) {
            throw new IaException(IaException::PROVEEDOR, "Groq respondió HTTP $http");
        }
        $r = json_decode((string) $cuerpo, true);
        $contenido = $r['choices'][0]['message']['content'] ?? null;
        $json = is_string($contenido) ? json_decode($contenido, true) : null;
        if (!is_array($json)) {
            throw new IaException(IaException::RESPUESTA, 'La respuesta de Groq no es JSON válido');
        }
        return $json;
    }
}
