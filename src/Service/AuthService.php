<?php
declare(strict_types=1);

namespace TriTransportes\Service;

use TriTransportes\Domain\Rol;
use TriTransportes\Exception\ApiException;
use TriTransportes\Repository\UsuarioRepository;
use TriTransportes\Security\AuthUser;
use TriTransportes\Security\Jwt;
use TriTransportes\Security\RateLimiter;

final class AuthService
{
    /** Intentos de login permitidos por IP y correo en cada ventana. */
    public const LOGIN_LIMITE = 5;
    public const LOGIN_VENTANA = 60;


    public function __construct(
        private readonly UsuarioRepository $usuarios,
        private readonly Jwt $jwt,
        private readonly RateLimiter $limiter,
    ) {
    }

    /** @return array{token: string, expires_in: int, usuario: array} */
    public function login(string $email, string $password, string $ip): array
    {
        $clave = "login:$ip:$email";
        $this->limiter->hit($clave, self::LOGIN_LIMITE, self::LOGIN_VENTANA);

        $u = $this->usuarios->porEmail($email);
        // Si el correo no existe se verifica igual contra un hash de relleno, para no revelar
        // por el tiempo de respuesta qué correos están registrados.
        $ok = password_verify($password, $u['password_hash'] ?? password_hash(random_bytes(16), PASSWORD_BCRYPT));
        if (!$u || !$ok || !(int) $u['activo']) {
            throw ApiException::unauthorized('Correo o contraseña incorrectos.', 'CREDENCIALES_INVALIDAS');
        }
        $user = $this->aUsuario($u);
        $this->limiter->reset($clave);
        $this->usuarios->registrarAcceso($user->id);

        return [
            'token' => $this->jwt->encode(['sub' => $user->id, 'emp' => $user->empresaId, 'rol' => $user->rol->value]),
            'expires_in' => $this->jwt->ttl(),
            'usuario' => $user->toArray(),
        ];
    }

    /**
     * Valida el token y vuelve a leer el usuario: si lo desactivaron o le cambiaron el rol,
     * el cambio rige de inmediato aunque el token siga vigente.
     */
    public function autenticar(string $token): AuthUser
    {
        $claims = $this->jwt->decode($token);
        $u = $this->usuarios->porId((int) ($claims['sub'] ?? 0));
        if (!$u || !(int) $u['activo']) {
            throw ApiException::unauthorized('La cuenta no existe o está desactivada.', 'CUENTA_INACTIVA');
        }
        return $this->aUsuario($u);
    }

    private function aUsuario(array $u): AuthUser
    {
        $rol = Rol::desdeBd((string) $u['rol']);
        if ($rol === null) {
            throw ApiException::forbidden('Su rol no tiene acceso a la API de TriTransportes.', 'ROL_SIN_ACCESO');
        }
        return new AuthUser(
            (int) $u['id'],
            (int) $u['empresa_id'],
            (string) $u['nombre'],
            (string) $u['email'],
            $rol,
            $u['conductor_id'] !== null ? (int) $u['conductor_id'] : null,
        );
    }
}
