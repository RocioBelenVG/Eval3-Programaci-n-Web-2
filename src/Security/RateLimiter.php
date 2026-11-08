<?php
declare(strict_types=1);

namespace TriTransportes\Security;

use TriTransportes\Exception\ApiException;

/**
 * Límite de tasa de ventana fija guardado en la tabla `rate_limits`.
 * Una sola sentencia atómica incrementa el contador o lo reinicia al cambiar de ventana,
 * así que funciona bien con varias solicitudes simultáneas.
 */
final class RateLimiter
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    /** Registra un intento y lanza 429 si se superó el límite. */
    public function hit(string $clave, int $limite, int $segundos, ?int $now = null): void
    {
        $now ??= time();
        $ventana = intdiv($now, $segundos);
        $clave = substr($clave, 0, 190);

        $this->pdo->prepare(
            'INSERT INTO rate_limits (clave, ventana, conteo) VALUES (?, ?, 1)
             ON DUPLICATE KEY UPDATE conteo = IF(ventana = VALUES(ventana), conteo + 1, 1), ventana = VALUES(ventana)'
        )->execute([$clave, $ventana]);

        $st = $this->pdo->prepare('SELECT conteo FROM rate_limits WHERE clave = ?');
        $st->execute([$clave]);
        if ((int) $st->fetchColumn() > $limite) {
            throw ApiException::tooManyRequests(($ventana + 1) * $segundos - $now);
        }
    }

    /** Olvida los intentos de una clave (por ejemplo, tras un login correcto). */
    public function reset(string $clave): void
    {
        $this->pdo->prepare('DELETE FROM rate_limits WHERE clave = ?')->execute([substr($clave, 0, 190)]);
    }
}
