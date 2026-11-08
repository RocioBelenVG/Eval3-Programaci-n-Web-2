<?php
declare(strict_types=1);

namespace TriTransportes\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

final class FlotaTest extends ApiTestCase
{
    #[Test]
    public function el_conductor_registra_una_carga_de_combustible_y_se_calcula_el_costo(): void
    {
        $r = $this->request('POST', '/cargas', [
            'tipo_unidad' => 'vehiculo', 'vehiculo_id' => $this->vehiculoId, 'fecha' => date('Y-m-d'),
            'litros' => 250.5, 'precio_litro' => 1000, 'odometro' => 950000,
        ], [], $this->token('Conductor'));
        $this->assertSame(201, $r->status, $r->body);
        $this->assertSame(250500.0, $r->data()['costo_total']);

        $propias = $this->request('GET', '/cargas', [], [], $this->token('Conductor'))->data();
        $this->assertSame(1, $propias['meta']['total']);
    }

    #[Test]
    public function las_reglas_de_combustible_se_validan_en_el_servidor(): void
    {
        $t = $this->token('Supervisor');
        $base = ['tipo_unidad' => 'vehiculo', 'vehiculo_id' => $this->vehiculoId, 'fecha' => date('Y-m-d')];

        $r = $this->request('POST', '/cargas', $base + ['litros' => 650], [], $t);
        $this->assertError($r, 422, 'VALIDATION_ERROR');
        $this->assertSame('litros', $r->data()['error']['details'][0]['field']);

        $this->assertError($this->request('POST', '/cargas', $base + ['litros' => 0], [], $t), 422, 'VALIDATION_ERROR');
        $this->assertError($this->request('POST', '/cargas', ['tipo_unidad' => 'equipo', 'fecha' => date('Y-m-d'), 'litros' => 100], [], $t), 422, 'VALIDATION_ERROR');
        $futura = $this->request('POST', '/cargas', ['litros' => 100, 'fecha' => date('Y-m-d', strtotime('+3 days'))] + $base, [], $t);
        $this->assertError($futura, 422, 'VALIDATION_ERROR');
        $this->assertSame('fecha', $futura->data()['error']['details'][0]['field']);

        $this->request('POST', '/cargas', $base + ['litros' => 100, 'odometro' => 950000], [], $t);
        $r = $this->request('POST', '/cargas', $base + ['litros' => 100, 'odometro' => 949000], [], $t);
        $this->assertError($r, 422, 'VALIDATION_ERROR');
        $this->assertSame('odometro', $r->data()['error']['details'][0]['field']);
    }

    #[Test]
    public function el_check_de_la_base_rechaza_lo_que_la_api_tambien_rechaza(): void
    {
        // Tercer nivel de validación: aunque alguien se salte la API, MariaDB impide el dato.
        $this->expectException(\PDOException::class);
        self::$db->pdo->exec("INSERT INTO cargas_combustible (empresa_id, fecha, tipo_unidad, vehiculo_id, litros) VALUES (1, CURDATE(), 'vehiculo', {$this->vehiculoId}, 700)");
    }

    #[Test]
    public function el_trigger_de_la_base_impide_una_carga_de_vehiculo_sin_vehiculo(): void
    {
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessage('vehículo o de un equipo');
        self::$db->pdo->exec("INSERT INTO cargas_combustible (empresa_id, fecha, tipo_unidad, vehiculo_id, litros) VALUES (1, CURDATE(), 'vehiculo', NULL, 100)");
    }

    #[Test]
    public function el_listado_de_vehiculos_busca_por_patente(): void
    {
        $patente = self::$db->pdo->query("SELECT patente FROM vehiculos WHERE id = {$this->vehiculoId}")->fetchColumn();
        $r = $this->request('GET', '/vehiculos', [], ['q' => $patente], $this->token('Conductor'));
        $this->assertSame(200, $r->status);
        $this->assertSame($patente, $r->data()['data'][0]['patente']);
        $this->assertIsBool($r->data()['data'][0]['activo']);
    }

    #[Test]
    public function el_rendimiento_mensual_entrega_km_por_litro_y_esta_restringido_por_rol(): void
    {
        $r = $this->request('GET', "/vehiculos/{$this->vehiculoId}/rendimiento", [], ['desde' => '2026-01-01', 'hasta' => '2026-06-30'], $this->token('Gerencia'));
        $this->assertSame(200, $r->status, $r->body);
        $serie = $r->data();
        $this->assertNotEmpty($serie);
        $this->assertSame('2026-01', $serie[0]['mes']);
        $this->assertGreaterThan(0, $serie[0]['km_por_litro']);

        $this->assertError($this->request('GET', "/vehiculos/{$this->vehiculoId}/rendimiento", [], [], $this->token('Conductor')), 403, 'SIN_PERMISO');
        $this->assertError($this->request('GET', '/vehiculos/999999/rendimiento', [], [], $this->token('Gerencia')), 404, 'NO_ENCONTRADO');
    }

    #[Test]
    public function la_mantencion_avanza_pendiente_en_proceso_completada(): void
    {
        $t = $this->token('Supervisor');
        $equipo = (int) self::$db->pdo->query('SELECT id FROM equipos WHERE empresa_id = 1 LIMIT 1')->fetchColumn();
        $r = $this->request('POST', '/mantenciones', ['equipo_id' => $equipo, 'tipo' => 'correctiva', 'fecha' => date('Y-m-d'), 'costo_total' => 350000], [], $t);
        $this->assertSame(201, $r->status, $r->body);
        $id = $r->data()['id'];
        $this->assertSame('pendiente', $r->data()['estado']);

        $this->assertError($this->request('PATCH', "/mantenciones/$id/estado", ['estado' => 'completada'], [], $t), 409, 'TRANSICION_INVALIDA');
        $this->assertSame('en_proceso', $this->request('PATCH', "/mantenciones/$id/estado", ['estado' => 'en_proceso'], [], $t)->data()['estado']);
        $this->assertSame('completada', $this->request('PATCH', "/mantenciones/$id/estado", ['estado' => 'completada'], [], $t)->data()['estado']);

        $this->assertError($this->request('POST', '/mantenciones', ['equipo_id' => $equipo, 'tipo' => 'preventiva', 'fecha' => date('Y-m-d')], [], $this->token('Conductor')), 403, 'SIN_PERMISO');
        $this->assertError($this->request('POST', '/mantenciones', ['equipo_id' => $equipo, 'tipo' => 'otra', 'fecha' => date('Y-m-d'), 'costo_total' => -1], [], $t), 422, 'VALIDATION_ERROR');
    }

    #[Test]
    public function una_empresa_no_ve_datos_de_otra(): void
    {
        $ajeno = (int) self::$db->pdo->query("SELECT id FROM viajes WHERE empresa_id = 2 LIMIT 1")->fetchColumn();
        $vehAjeno = (int) self::$db->pdo->query("SELECT id FROM vehiculos WHERE empresa_id = 2 LIMIT 1")->fetchColumn();
        $t = $this->token('Administrador');
        $this->assertError($this->request('GET', "/viajes/$ajeno", [], [], $t), 404, 'NO_ENCONTRADO');
        $this->assertError($this->request('GET', "/vehiculos/$vehAjeno/rendimiento", [], [], $t), 404, 'NO_ENCONTRADO');
        $r = $this->request('POST', '/viajes', [
            'vehiculo_id' => $vehAjeno, 'conductor_id' => $this->usuarios['Conductor']['conductor_id'],
            'fecha' => date('Y-m-d'), 'origen' => 'Planta', 'destino' => 'Obra',
        ], [], $t);
        $this->assertError($r, 422, 'VALIDATION_ERROR');
    }
}
