<?php
declare(strict_types=1);

namespace TriTransportes\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TriTransportes\Exception\ApiException;
use TriTransportes\Security\Jwt;

final class JwtTest extends TestCase
{
    private const SECRETO = 'secreto-de-prueba-de-al-menos-32-caracteres';

    private function jwt(): Jwt
    {
        return new Jwt(self::SECRETO, 3600);
    }

    private function codigoDeError(callable $fn): string
    {
        try {
            $fn();
        } catch (ApiException $e) {
            $this->assertSame(401, $e->status);
            return $e->errorCode;
        }
        $this->fail('Se esperaba un 401.');
    }

    #[Test]
    public function un_token_emitido_se_decodifica_con_sus_claims(): void
    {
        $t = $this->jwt()->encode(['sub' => 7, 'emp' => 1, 'rol' => 'Supervisor'], 1_000_000);
        $c = $this->jwt()->decode($t, 1_000_100);
        $this->assertSame(7, $c['sub']);
        $this->assertSame('Supervisor', $c['rol']);
        $this->assertSame(1_000_000 + 3600, $c['exp']);
        $this->assertSame('tritransportes', $c['iss']);
    }

    #[Test]
    public function un_token_expirado_se_rechaza(): void
    {
        $t = $this->jwt()->encode(['sub' => 1], 1_000_000);
        $this->assertSame('TOKEN_EXPIRADO', $this->codigoDeError(fn () => $this->jwt()->decode($t, 1_000_000 + 3600 + 31)));
    }

    #[Test]
    public function una_firma_alterada_se_rechaza(): void
    {
        $t = $this->jwt()->encode(['sub' => 1, 'rol' => 'Conductor'], 1_000_000);
        [$h, , $s] = explode('.', $t);
        $payloadFalso = rtrim(strtr(base64_encode(json_encode(['sub' => 1, 'rol' => 'Administrador', 'iss' => 'tritransportes', 'exp' => 9_999_999_999])), '+/', '-_'), '=');
        $this->assertSame('TOKEN_INVALIDO', $this->codigoDeError(fn () => $this->jwt()->decode("$h.$payloadFalso.$s", 1_000_100)));
    }

    #[Test]
    public function un_token_firmado_con_otra_clave_se_rechaza(): void
    {
        $otro = new Jwt('otra-clave-distinta-de-al-menos-32-caracteres!!');
        $t = $otro->encode(['sub' => 1], 1_000_000);
        $this->assertSame('TOKEN_INVALIDO', $this->codigoDeError(fn () => $this->jwt()->decode($t, 1_000_100)));
    }

    #[Test]
    public function el_algoritmo_none_se_rechaza(): void
    {
        $b64 = fn ($a) => rtrim(strtr(base64_encode(json_encode($a)), '+/', '-_'), '=');
        $t = $b64(['alg' => 'none', 'typ' => 'JWT']) . '.' . $b64(['sub' => 1, 'iss' => 'tritransportes', 'exp' => 9_999_999_999]) . '.';
        $this->assertSame('TOKEN_INVALIDO', $this->codigoDeError(fn () => $this->jwt()->decode($t)));
    }

    #[Test]
    public function un_texto_que_no_es_jwt_se_rechaza(): void
    {
        $this->assertSame('TOKEN_INVALIDO', $this->codigoDeError(fn () => $this->jwt()->decode('no-es-un-token')));
    }

    #[Test]
    public function una_clave_corta_no_se_acepta(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Jwt('corta');
    }
}
