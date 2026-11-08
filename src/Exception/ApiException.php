<?php
declare(strict_types=1);

namespace TriTransportes\Exception;

/**
 * Error de negocio o de acceso que la API devuelve con el esquema `Error` del contrato:
 * { "error": { "code", "message", "details"?, "request_id" } }
 */
class ApiException extends \RuntimeException
{
    /**
     * @param list<array{field: string, message: string}> $details
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $details = [],
        public readonly array $headers = [],
    ) {
        parent::__construct($message, $status);
    }

    public static function unauthorized(string $message = 'Debe iniciar sesión.', string $code = 'NO_AUTENTICADO'): self
    {
        return new self(401, $code, $message, [], ['WWW-Authenticate' => 'Bearer']);
    }

    public static function forbidden(string $message = 'Su rol no tiene permiso para esta acción.', string $code = 'SIN_PERMISO'): self
    {
        return new self(403, $code, $message);
    }

    public static function notFound(string $message = 'El recurso no existe.'): self
    {
        return new self(404, 'NO_ENCONTRADO', $message);
    }

    public static function conflict(string $message, string $code = 'CONFLICTO'): self
    {
        return new self(409, $code, $message);
    }

    /** @param list<array{field: string, message: string}> $details */
    public static function validation(array $details, string $message = 'Los datos enviados no son válidos.'): self
    {
        return new self(422, 'VALIDATION_ERROR', $message, $details);
    }

    public static function field(string $field, string $message): self
    {
        return self::validation([['field' => $field, 'message' => $message]]);
    }

    public static function tooManyRequests(int $retryAfter): self
    {
        return new self(429, 'DEMASIADAS_SOLICITUDES', "Demasiadas solicitudes. Intente de nuevo en $retryAfter segundos.", [], ['Retry-After' => (string) $retryAfter]);
    }
}
