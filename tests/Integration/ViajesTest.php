<?php
declare(strict_types=1);

namespace TriTransportes\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

final class ViajesTest extends ApiTestCase
{
    #[Test]
    public function el_supervisor_programa_un_viaje_y_queda_auditado(): void
    {
        $id = $this->programarViaje();
        $r = $this->request('GET', "/viajes/$id", [], [], $this->token('Supervisor'));
        $this->assertSame(200, $r->status);
        $v = $r->data();
        $this->assertSame('programado', $v['estado']);
        $this->assertSame(['en_ruta', 'cancelado'], $v['transiciones_permitidas']);
        $this->assertSame('Prueba Conductor', $v['conductor']['nombre']);
        $this->assertNotEmpty($v['vehiculo']['patente']);

        $aud = self::$db->pdo->query("SELECT accion, usuario_id FROM auditoria WHERE entidad_tipo = 'viaje' AND entidad_id = $id")->fetch();
        $this->assertSame('crear', $aud['accion']);
        $this->assertSame($this->usuarios['Supervisor']['id'], (int) $aud['usuario_id']);
    }

    #[Test]
    public function el_conductor_y_la_gerencia_no_pueden_programar_viajes(): void
    {
        foreach (['Conductor', 'Gerencia'] as $rol) {
            $this->assertError($this->request('POST', '/viajes', ['fecha' => date('Y-m-d')], [], $this->token($rol)), 403, 'SIN_PERMISO');
        }
    }

    #[Test]
    public function programar_un_viaje_valida_campos_y_referencias(): void
    {
        $t = $this->token('Supervisor');
        $r = $this->request('POST', '/viajes', ['vehiculo_id' => 'x', 'fecha' => '2026-13-01', 'origen' => 'A', 'unidad' => 'kg'], [], $t);
        $this->assertError($r, 422, 'VALIDATION_ERROR');
        $campos = array_column($r->data()['error']['details'], 'field');
        foreach (['vehiculo_id', 'conductor_id', 'fecha', 'origen', 'destino', 'unidad'] as $c) {
            $this->assertContains($c, $campos);
        }

        $r = $this->request('POST', '/viajes', [
            'vehiculo_id' => 999999, 'conductor_id' => 999999, 'obra_id' => 999999,
            'fecha' => date('Y-m-d'), 'origen' => 'Planta', 'destino' => 'Obra',
        ], [], $t);
        $this->assertError($r, 422, 'VALIDATION_ERROR');
        $this->assertSame(['vehiculo_id', 'conductor_id', 'obra_id'], array_column($r->data()['error']['details'], 'field'));
    }

    #[Test]
    public function no_se_programa_un_viaje_con_la_licencia_del_conductor_vencida(): void
    {
        $r = $this->request('POST', '/viajes', [
            'vehiculo_id' => $this->vehiculoId, 'conductor_id' => $this->usuarios['Conductor']['conductor_id'],
            'fecha' => date('Y-m-d', strtotime('+2 years')), 'origen' => 'Planta', 'destino' => 'Obra',
        ], [], $this->token('Supervisor'));
        $this->assertError($r, 422, 'VALIDATION_ERROR');
        $this->assertStringContainsString('licencia', $r->data()['error']['details'][0]['message']);
    }

    #[Test]
    public function el_flujo_completo_programado_en_ruta_completado_actualiza_el_odometro(): void
    {
        $id = $this->programarViaje();
        $t = $this->token('Conductor');

        $r = $this->request('PATCH', "/viajes/$id/estado", ['estado' => 'en_ruta'], [], $t);
        $this->assertSame(200, $r->status, $r->body);
        $this->assertSame('en_ruta', $r->data()['estado']);
        $this->assertNotNull($r->data()['hora_salida']);
        $this->assertSame(['completado'], $r->data()['transiciones_permitidas']);

        $r = $this->request('PATCH', "/viajes/$id/estado", ['estado' => 'completado', 'km_fin' => 900000], [], $t);
        $this->assertSame(200, $r->status, $r->body);
        $this->assertSame('completado', $r->data()['estado']);
        $this->assertSame([], $r->data()['transiciones_permitidas']);

        $odo = (int) self::$db->pdo->query("SELECT odometro FROM vehiculos WHERE id = {$this->vehiculoId}")->fetchColumn();
        $this->assertSame(900000, $odo);
    }

    #[Test]
    public function una_transicion_no_permitida_responde_409(): void
    {
        $id = $this->programarViaje();
        $t = $this->token('Supervisor');

        $r = $this->request('PATCH', "/viajes/$id/estado", ['estado' => 'completado', 'km_fin' => 100100], [], $t);
        $this->assertError($r, 409, 'TRANSICION_INVALIDA');
        $this->assertStringContainsString("'programado' a 'completado'", $r->data()['error']['message']);

        $this->request('PATCH', "/viajes/$id/estado", ['estado' => 'cancelado', 'motivo' => 'Cliente suspende la obra'], [], $t);
        $r = $this->request('PATCH', "/viajes/$id/estado", ['estado' => 'en_ruta'], [], $t);
        $this->assertError($r, 409, 'TRANSICION_INVALIDA');
        $this->assertStringContainsString('estado final', $r->data()['error']['message']);
    }

    #[Test]
    public function completar_exige_km_fin_mayor_o_igual_al_inicial(): void
    {
        $id = $this->programarViaje();
        $t = $this->token('Supervisor');
        $this->request('PATCH', "/viajes/$id/estado", ['estado' => 'en_ruta'], [], $t);

        $sinKm = $this->request('PATCH', "/viajes/$id/estado", ['estado' => 'completado'], [], $t);
        $this->assertError($sinKm, 422, 'VALIDATION_ERROR');
        $this->assertSame('km_fin', $sinKm->data()['error']['details'][0]['field']);

        $menor = $this->request('PATCH', "/viajes/$id/estado", ['estado' => 'completado', 'km_fin' => 99999], [], $t);
        $this->assertError($menor, 422, 'VALIDATION_ERROR');
    }

    #[Test]
    public function cancelar_exige_motivo_y_el_conductor_no_puede_cancelar(): void
    {
        $id = $this->programarViaje();
        $this->assertError($this->request('PATCH', "/viajes/$id/estado", ['estado' => 'cancelado', 'motivo' => 'Falla mecánica'], [], $this->token('Conductor')), 403, 'SIN_PERMISO');
        $this->assertError($this->request('PATCH', "/viajes/$id/estado", ['estado' => 'cancelado'], [], $this->token('Supervisor')), 422, 'VALIDATION_ERROR');

        $r = $this->request('PATCH', "/viajes/$id/estado", ['estado' => 'cancelado', 'motivo' => 'Falla mecánica'], [], $this->token('Supervisor'));
        $this->assertSame(200, $r->status);
        $this->assertStringContainsString('Falla mecánica', $r->data()['observaciones']);
    }

    #[Test]
    public function el_conductor_solo_ve_y_opera_sus_propios_viajes(): void
    {
        $propio = $this->programarViaje();
        $ajeno = (int) self::$db->pdo->query("SELECT id FROM viajes WHERE empresa_id = 1 AND estado = 'programado' AND conductor_id <> {$this->usuarios['Conductor']['conductor_id']} LIMIT 1")->fetchColumn();
        $t = $this->token('Conductor');

        $lista = $this->request('GET', '/viajes', [], ['per_page' => 100], $t)->data();
        $this->assertSame(1, $lista['meta']['total']);
        $this->assertSame($propio, $lista['data'][0]['id']);

        $this->assertError($this->request('GET', "/viajes/$ajeno", [], [], $t), 404, 'NO_ENCONTRADO');
        $this->assertError($this->request('PATCH', "/viajes/$ajeno/estado", ['estado' => 'en_ruta'], [], $t), 404, 'NO_ENCONTRADO');
    }

    #[Test]
    public function el_listado_pagina_filtra_y_ordena(): void
    {
        $t = $this->token('Gerencia');
        $r = $this->request('GET', '/viajes', [], ['estado' => 'completado', 'desde' => '2026-01-01', 'hasta' => '2026-03-31', 'sort' => 'fecha', 'per_page' => 10, 'page' => 2], $t);
        $this->assertSame(200, $r->status, $r->body);
        $d = $r->data();
        $this->assertSame(['page' => 2, 'per_page' => 10], array_intersect_key($d['meta'], ['page' => 0, 'per_page' => 0]));
        $this->assertGreaterThan(10, $d['meta']['total']);
        $this->assertCount(10, $d['data']);
        $fechas = array_column($d['data'], 'fecha');
        $ordenadas = $fechas;
        sort($ordenadas);
        $this->assertSame($ordenadas, $fechas);
        foreach ($d['data'] as $v) {
            $this->assertSame('completado', $v['estado']);
            $this->assertGreaterThanOrEqual('2026-01-01', $v['fecha']);
            $this->assertLessThanOrEqual('2026-03-31', $v['fecha']);
        }

        $this->assertError($this->request('GET', '/viajes', [], ['sort' => 'password', 'per_page' => 500], $t), 422, 'VALIDATION_ERROR');
    }

    #[Test]
    public function la_busqueda_de_texto_no_permite_inyeccion_sql(): void
    {
        $r = $this->request('GET', '/viajes', [], ['q' => "' OR 1=1 -- "], $this->token('Supervisor'));
        $this->assertSame(200, $r->status);
        $this->assertSame(0, $r->data()['meta']['total']);
    }

    #[Test]
    public function solo_se_modifica_un_viaje_programado(): void
    {
        $id = $this->programarViaje();
        $t = $this->token('Supervisor');
        $datos = ['vehiculo_id' => $this->vehiculoId, 'conductor_id' => $this->usuarios['Conductor']['conductor_id'], 'fecha' => date('Y-m-d'), 'origen' => 'Planta Maipú', 'destino' => 'Obra Ruta 68'];

        $r = $this->request('PUT', "/viajes/$id", $datos, [], $t);
        $this->assertSame(200, $r->status, $r->body);
        $this->assertSame('Planta Maipú', $r->data()['origen']);

        $this->request('PATCH', "/viajes/$id/estado", ['estado' => 'en_ruta'], [], $t);
        $this->assertError($this->request('PUT', "/viajes/$id", $datos, [], $t), 409, 'VIAJE_NO_EDITABLE');
    }

    #[Test]
    public function solo_el_administrador_elimina_y_solo_viajes_programados(): void
    {
        $id = $this->programarViaje();
        $this->assertError($this->request('DELETE', "/viajes/$id", [], [], $this->token('Supervisor')), 403, 'SIN_PERMISO');

        $admin = $this->token('Administrador');
        $r = $this->request('DELETE', "/viajes/$id", [], [], $admin);
        $this->assertSame(204, $r->status);
        $this->assertSame('', $r->body);
        $this->assertError($this->request('GET', "/viajes/$id", [], [], $admin), 404, 'NO_ENCONTRADO');

        $otro = $this->programarViaje();
        $this->request('PATCH', "/viajes/$otro/estado", ['estado' => 'en_ruta'], [], $admin);
        $this->assertError($this->request('DELETE', "/viajes/$otro", [], [], $admin), 409, 'VIAJE_NO_ELIMINABLE');
    }

    #[Test]
    public function la_guia_de_despacho_solo_se_emite_para_un_viaje_completado_y_con_numero_unico(): void
    {
        $id = $this->programarViaje();
        $t = $this->token('Supervisor');
        $guia = ['numero_guia' => 'GD-TEST-' . bin2hex(random_bytes(3)), 'receptor' => 'Jefe de obra', 'rut_receptor' => '12345678-5'];

        $this->assertError($this->request('POST', "/viajes/$id/guias", $guia, [], $t), 409, 'VIAJE_NO_COMPLETADO');

        $this->request('PATCH', "/viajes/$id/estado", ['estado' => 'en_ruta'], [], $t);
        $this->request('PATCH', "/viajes/$id/estado", ['estado' => 'completado', 'km_fin' => 100150], [], $t);

        $this->assertError($this->request('POST', "/viajes/$id/guias", ['rut_receptor' => '12345678-9'] + $guia, [], $t), 422, 'VALIDATION_ERROR');

        $r = $this->request('POST', "/viajes/$id/guias", $guia, [], $t);
        $this->assertSame(201, $r->status, $r->body);
        $this->assertSame($id, (int) $r->data()['viaje_id']);
        $this->assertSame(14.0, (float) $r->data()['cantidad']);

        $this->assertError($this->request('POST', "/viajes/$id/guias", $guia, [], $t), 409, 'GUIA_DUPLICADA');
    }

    #[Test]
    public function un_cuerpo_que_no_es_json_responde_400(): void
    {
        $req = new \TriTransportes\Http\Request('POST', '/viajes', [], ['authorization' => 'Bearer ' . $this->token('Supervisor')], '{no json');
        $this->assertError($this->kernel->handle($req), 400, 'JSON_INVALIDO');
    }
}
