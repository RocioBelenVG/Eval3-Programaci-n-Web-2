<?php
declare(strict_types=1);

namespace TriTransportes\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use TriTransportes\Service\AuthService;

final class AuthTest extends ApiTestCase
{
    #[Test]
    public function el_login_correcto_entrega_un_jwt_y_los_datos_del_usuario(): void
    {
        $r = $this->request('POST', '/auth/login', ['email' => $this->usuarios['Supervisor']['email'], 'password' => $this->password]);
        $this->assertSame(200, $r->status);
        $d = $r->data();
        $this->assertCount(3, explode('.', $d['token']));
        $this->assertSame(JWT_TTL, $d['expires_in']);
        $this->assertSame('Supervisor', $d['usuario']['rol']);
        $this->assertArrayNotHasKey('password_hash', $d['usuario']);
        $this->assertSame('no-store', $r->headers['Cache-Control']);
    }

    #[Test]
    public function una_contrasena_incorrecta_responde_401_sin_revelar_si_el_correo_existe(): void
    {
        $mala = $this->request('POST', '/auth/login', ['email' => $this->usuarios['Supervisor']['email'], 'password' => 'incorrecta123']);
        $inexistente = $this->request('POST', '/auth/login', ['email' => 'nadie@tritransportes.test', 'password' => 'incorrecta123']);
        $this->assertError($mala, 401, 'CREDENCIALES_INVALIDAS');
        $this->assertError($inexistente, 401, 'CREDENCIALES_INVALIDAS');
        $this->assertSame($mala->data()['error']['message'], $inexistente->data()['error']['message']);
    }

    #[Test]
    public function el_login_valida_los_datos_de_entrada(): void
    {
        $r = $this->request('POST', '/auth/login', ['email' => 'no-es-correo', 'password' => '123']);
        $this->assertError($r, 422, 'VALIDATION_ERROR');
        $this->assertSame(['email', 'password'], array_column($r->data()['error']['details'], 'field'));
    }

    #[Test]
    public function tras_cinco_intentos_fallidos_el_login_responde_429_con_retry_after(): void
    {
        for ($i = 0; $i < AuthService::LOGIN_LIMITE; $i++) {
            $this->assertSame(401, $this->request('POST', '/auth/login', ['email' => $this->usuarios['Gerencia']['email'], 'password' => 'incorrecta123'])->status);
        }
        $r = $this->request('POST', '/auth/login', ['email' => $this->usuarios['Gerencia']['email'], 'password' => $this->password]);
        $this->assertError($r, 429, 'DEMASIADAS_SOLICITUDES');
        $this->assertGreaterThan(0, (int) $r->headers['Retry-After']);
    }

    #[Test]
    public function un_rol_fuera_del_alcance_no_obtiene_token(): void
    {
        $r = $this->request('POST', '/auth/login', ['email' => $this->usuarios['Mecanico']['email'], 'password' => $this->password]);
        $this->assertError($r, 403, 'ROL_SIN_ACCESO');
    }

    #[Test]
    public function las_rutas_protegidas_exigen_un_token_valido(): void
    {
        $this->assertError($this->request('GET', '/auth/me'), 401, 'NO_AUTENTICADO');
        $this->assertError($this->request('GET', '/viajes', [], [], 'token.falso.123'), 401, 'TOKEN_INVALIDO');

        $me = $this->request('GET', '/auth/me', [], [], $this->token('Conductor'));
        $this->assertSame(200, $me->status);
        $this->assertSame('Conductor', $me->data()['rol']);
    }

    #[Test]
    public function un_usuario_desactivado_pierde_el_acceso_aunque_su_token_siga_vigente(): void
    {
        $token = $this->token('Supervisor');
        self::$db->pdo->prepare('UPDATE usuarios SET activo = 0 WHERE id = ?')->execute([$this->usuarios['Supervisor']['id']]);
        $this->assertError($this->request('GET', '/viajes', [], [], $token), 401, 'CUENTA_INACTIVA');
    }

    #[Test]
    public function una_ruta_inexistente_o_un_metodo_no_permitido_usan_el_esquema_de_error(): void
    {
        $t = $this->token('Supervisor');
        $this->assertError($this->request('GET', '/no-existe', [], [], $t), 404, 'NO_ENCONTRADO');
        $r = $this->request('DELETE', '/vehiculos', [], [], $t);
        $this->assertError($r, 405, 'METODO_NO_PERMITIDO');
        $this->assertSame('GET', $r->headers['Allow']);
    }
}
