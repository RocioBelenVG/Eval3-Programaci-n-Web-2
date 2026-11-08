<?php
declare(strict_types=1);

namespace TriTransportes\Security;

use TriTransportes\Exception\ApiException;

/**
 * JSON Web Token firmado con HS256 (RFC 7519), sin dependencias externas.
 * - Solo acepta alg=HS256 (rechaza "none" y cualquier otro algoritmo).
 * - Compara la firma en tiempo constante (hash_equals).
 * - Valida exp, nbf e iss con una tolerancia de reloj pequeña.
 */
final class Jwt
{
    private const LEEWAY = 30;

    public function __construct(
        private readonly string $secret,
        private readonly int $ttl = 3600,
        private readonly string $issuer = 'tritransportes',
    ) {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('JWT_SECRET debe tener al menos 32 caracteres.');
        }
    }

    public function ttl(): int
    {
        return $this->ttl;
    }

    /** @param array<string, mixed> $claims */
    public function encode(array $claims, ?int $now = null): string
    {
        $now ??= time();
        $payload = $claims + [
            'iss' => $this->issuer,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->ttl,
            'jti' => bin2hex(random_bytes(12)),
        ];
        $h = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $p = self::b64(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return "$h.$p." . self::b64(hash_hmac('sha256', "$h.$p", $this->secret, true));
    }

    /** @return array<string, mixed> */
    public function decode(string $token, ?int $now = null): array
    {
        $now ??= time();
        $partes = explode('.', $token);
        if (count($partes) !== 3) {
            throw ApiException::unauthorized('El token no tiene un formato válido.', 'TOKEN_INVALIDO');
        }
        [$h, $p, $s] = $partes;
        $header = json_decode(self::unb64($h), true);
        if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            throw ApiException::unauthorized('Algoritmo de firma no permitido.', 'TOKEN_INVALIDO');
        }
        $esperada = self::b64(hash_hmac('sha256', "$h.$p", $this->secret, true));
        if (!hash_equals($esperada, $s)) {
            throw ApiException::unauthorized('La firma del token no es válida.', 'TOKEN_INVALIDO');
        }
        $claims = json_decode(self::unb64($p), true);
        if (!is_array($claims)) {
            throw ApiException::unauthorized('El token no tiene un formato válido.', 'TOKEN_INVALIDO');
        }
        if (($claims['iss'] ?? null) !== $this->issuer) {
            throw ApiException::unauthorized('El token no fue emitido por TriTransportes.', 'TOKEN_INVALIDO');
        }
        if (!isset($claims['exp']) || $now - self::LEEWAY >= (int) $claims['exp']) {
            throw ApiException::unauthorized('La sesión expiró. Inicie sesión de nuevo.', 'TOKEN_EXPIRADO');
        }
        if (isset($claims['nbf']) && $now + self::LEEWAY < (int) $claims['nbf']) {
            throw ApiException::unauthorized('El token todavía no es válido.', 'TOKEN_INVALIDO');
        }
        return $claims;
    }

    private static function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function unb64(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
