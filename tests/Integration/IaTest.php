<?php
declare(strict_types=1);

namespace TriTransportes\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TriTransportes\Ia\IaClient;
use TriTransportes\Ia\IaException;
use TriTransportes\Service\IaService;
use TriTransportes\Tests\Support\FakeIaClient;

/** POST /ia/resumen con el servicio de IA simulado (sin llamadas reales a Groq). */
final class IaTest extends ApiTestCase
{
    private const PERIODO = ['desde' => '2026-09-01', 'hasta' => '2026-09-30', 'enfoque' => 'combustible'];

    private function simular(FakeIaClient $fake): FakeIaClient
    {
        // Contenedor nuevo con el doble de prueba en lugar de Groq (misma conexión y transacción)
        $this->container = \TriTransportes\App::container(self::$db);
        $this->container->instance(IaClient::class, $fake);
        $this->kernel = new \TriTransportes\Kernel($this->container);
        return $fake;
    }

    #[Test]
    public function la_gerencia_obtiene_un_resumen_con_recomendaciones_y_los_datos_usados(): void
    {
        $fake = $this->simular(new FakeIaClient());
        $r = $this->request('POST', '/ia/resumen', self::PERIODO, [], $this->token('Gerencia'));
        $this->assertSame(200, $r->status, $r->body);
        $d = $r->data();
        $this->assertStringContainsString('combustible', $d['resumen']);
        $this->assertCount(2, $d['recomendaciones']);
        $this->assertSame('modelo-simulado', $d['modelo']);
        $this->assertGreaterThan(0, $d['datos']['periodo_actual']['viajes']['total']);
        $this->assertSame('2026-08-02', $d['datos']['periodo_anterior']['periodo']['desde']);
        $this->assertSame('2026-08-31', $d['datos']['periodo_anterior']['periodo']['hasta']);

        // Al modelo solo le llegan agregados: ni nombres de conductores ni correos
        $this->assertSame(1, $fake->llamadas);
        $this->assertStringNotContainsString('@', $fake->ultimosDatos);
        $this->assertStringNotContainsString('Prueba Conductor', $fake->ultimosDatos);
        $this->assertStringContainsString('"enfoque":"combustible"', $fake->ultimosDatos);

        $aud = self::$db->pdo->query("SELECT accion FROM auditoria WHERE modulo = 'ia' AND usuario_id = {$this->usuarios['Gerencia']['id']}")->fetchColumn();
        $this->assertSame('resumen', $aud);
    }

    #[Test]
    public function el_conductor_no_tiene_acceso_al_resumen(): void
    {
        $this->simular(new FakeIaClient());
        $this->assertError($this->request('POST', '/ia/resumen', self::PERIODO, [], $this->token('Conductor')), 403, 'SIN_PERMISO');
    }

    #[Test]
    public function la_entrada_se_valida_antes_de_llamar_al_servicio(): void
    {
        $fake = $this->simular(new FakeIaClient());
        $t = $this->token('Supervisor');
        $this->assertError($this->request('POST', '/ia/resumen', ['desde' => '2026-13-01', 'enfoque' => 'chistes'], [], $t), 422, 'VALIDATION_ERROR');
        $this->assertError($this->request('POST', '/ia/resumen', ['desde' => '2026-09-30', 'hasta' => '2026-09-01'], [], $t), 422, 'VALIDATION_ERROR');
        $this->assertError($this->request('POST', '/ia/resumen', ['desde' => '2024-01-01', 'hasta' => '2026-01-01'], [], $t), 422, 'VALIDATION_ERROR');
        $this->assertError($this->request('POST', '/ia/resumen', ['desde' => '2020-01-01', 'hasta' => '2020-01-31'], [], $t), 422, 'VALIDATION_ERROR');
        $this->assertSame(0, $fake->llamadas);
    }

    public static function fallas(): array
    {
        return [
            'sin respuesta a tiempo' => [new IaException(IaException::TIMEOUT, 'x'), 504, 'IA_SIN_RESPUESTA'],
            'error del proveedor' => [new IaException(IaException::PROVEEDOR, 'x'), 502, 'IA_ERROR_PROVEEDOR'],
            'respuesta no JSON' => [new IaException(IaException::RESPUESTA, 'x'), 502, 'IA_ERROR_PROVEEDOR'],
            'límite del proveedor' => [new IaException(IaException::SATURADO, 'x'), 503, 'IA_SATURADA'],
        ];
    }

    #[Test]
    #[DataProvider('fallas')]
    public function las_fallas_del_servicio_externo_usan_el_formato_de_error_estandar(IaException $falla, int $status, string $code): void
    {
        $this->simular(new FakeIaClient($falla));
        $r = $this->request('POST', '/ia/resumen', self::PERIODO, [], $this->token('Gerencia'));
        $this->assertError($r, $status, $code);
        $this->assertStringNotContainsString('groq', strtolower($r->body)); // no se filtran detalles internos
    }

    #[Test]
    public function una_respuesta_incompleta_o_con_html_del_modelo_no_llega_al_usuario_tal_cual(): void
    {
        $this->simular(new FakeIaClient(['resumen' => 'Corto', 'recomendaciones' => []]));
        $this->assertError($this->request('POST', '/ia/resumen', self::PERIODO, [], $this->token('Gerencia')), 502, 'IA_RESPUESTA_INVALIDA');

        $this->simular(new FakeIaClient([
            'resumen' => '<script>alert(1)</script>El cumplimiento se mantuvo estable y el costo por viaje bajó levemente en el período.',
            'recomendaciones' => ['<b>Cerrar</b> los viajes abiertos cada día.', 42, 'Uno', 'Dos', 'Tres', 'Cuatro'],
        ]));
        $d = $this->request('POST', '/ia/resumen', self::PERIODO, [], $this->token('Supervisor'))->data();
        $this->assertStringNotContainsString('<script>', $d['resumen']);
        $this->assertSame('Cerrar los viajes abiertos cada día.', $d['recomendaciones'][0]);
        $this->assertCount(4, $d['recomendaciones']);
    }

    #[Test]
    public function sin_clave_configurada_responde_503(): void
    {
        $this->simular(new FakeIaClient([], configurado: false));
        $this->assertError($this->request('POST', '/ia/resumen', self::PERIODO, [], $this->token('Gerencia')), 503, 'IA_NO_CONFIGURADA');
    }

    #[Test]
    public function el_resumen_tiene_su_propio_limite_de_tasa(): void
    {
        $this->simular(new FakeIaClient());
        $t = $this->token('Gerencia');
        for ($i = 0; $i < IaService::LIMITE_POR_MINUTO; $i++) {
            $this->assertSame(200, $this->request('POST', '/ia/resumen', self::PERIODO, [], $t)->status);
        }
        $this->assertError($this->request('POST', '/ia/resumen', self::PERIODO, [], $t), 429, 'DEMASIADAS_SOLICITUDES');
    }

    #[Test]
    public function los_catalogos_para_formularios_respetan_el_rol(): void
    {
        $t = $this->token('Supervisor');
        $con = $this->request('GET', '/conductores', [], [], $t)->data();
        $this->assertContains('Prueba Conductor', array_column($con, 'nombre'));
        $this->assertNotEmpty($this->request('GET', '/obras', [], [], $t)->data());
        $this->assertNotEmpty($this->request('GET', '/equipos', [], [], $this->token('Conductor'))->data());
        $this->assertError($this->request('GET', '/conductores', [], [], $this->token('Conductor')), 403, 'SIN_PERMISO');
    }
}
