<?php
declare(strict_types=1);

namespace TriTransportes\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use TriTransportes\Http\Request;

/** Dashboard (6 indicadores y 4 series) y reportes con exportación. */
final class DashboardTest extends ApiTestCase
{
    private const ENERO_2024 = ['desde' => '2024-01-01', 'hasta' => '2024-01-31']; // antes de los datos sintéticos

    /** Escenario controlado: 4 viajes y 2 cargas en enero de 2024, para calcular los indicadores a mano. */
    private function escenario(): void
    {
        $pdo = self::$db->pdo;
        $con = $this->usuarios['Conductor']['conductor_id'];
        $v = $pdo->prepare("INSERT INTO viajes (empresa_id, vehiculo_id, conductor_id, fecha, origen, destino, km_inicio, km_fin, estado, created_at, updated_at)
                            VALUES (1, ?, ?, ?, 'A', 'B', ?, ?, ?, NOW(), NOW())");
        $v->execute([$this->vehiculoId, $con, '2024-01-10', 1000, 1150, 'completado']);   // 150 km
        $v->execute([$this->vehiculoId, $con, '2024-01-11', 1150, 1250, 'completado']);   // 100 km
        $v->execute([$this->vehiculoId, $con, '2024-01-12', 1250, null, 'cancelado']);
        $v->execute([$this->vehiculoId, $con, '2024-01-13', 1250, null, 'programado']);
        $c = $pdo->prepare("INSERT INTO cargas_combustible (empresa_id, fecha, tipo_unidad, vehiculo_id, litros, precio_litro, created_at, updated_at)
                            VALUES (1, ?, 'vehiculo', ?, ?, 1000, NOW(), NOW())");
        $c->execute(['2024-01-09', $this->vehiculoId, 60]);
        $c->execute(['2024-01-12', $this->vehiculoId, 40]);
    }

    #[Test]
    public function los_seis_indicadores_se_calculan_con_su_formula(): void
    {
        $this->escenario();
        $r = $this->request('GET', '/dashboard/kpis', [], self::ENERO_2024, $this->token('Gerencia'));
        $this->assertSame(200, $r->status, $r->body);
        $k = $r->data()['indicadores'];
        $this->assertSame(['rendimiento_km_l', 'costo_combustible_por_viaje', 'cumplimiento_viajes', 'disponibilidad_flota', 'mtbf_horas', 'tasa_cancelacion'], array_keys($k));

        $this->assertEquals(2.5, $k['rendimiento_km_l']['valor']);              // 250 km ÷ 100 L
        $this->assertEquals(50000, $k['costo_combustible_por_viaje']['valor']); // $100.000 ÷ 2 completados
        $this->assertEquals(50.0, $k['cumplimiento_viajes']['valor']);          // 2 ÷ 4
        $this->assertEquals(25.0, $k['tasa_cancelacion']['valor']);             // 1 ÷ 4
        $this->assertEquals(100.0, $k['disponibilidad_flota']['valor']);        // sin detenciones en el período
        $this->assertNull($k['mtbf_horas']['valor']);                           // sin fallas: no se divide por cero
        $this->assertSame('km de viajes completados ÷ litros cargados a vehículos', $k['rendimiento_km_l']['formula']);
        $this->assertSame(250, $k['rendimiento_km_l']['numerador']);
        $this->assertNull($k['cumplimiento_viajes']['variacion_vs_periodo_anterior']); // diciembre de 2023 sin datos
        $this->assertSame(['desde' => '2023-12-01', 'hasta' => '2023-12-31'], $r->data()['periodo_anterior']);
    }

    #[Test]
    public function el_filtro_por_vehiculo_cambia_los_indicadores(): void
    {
        $this->escenario();
        $otro = (int) self::$db->pdo->query("SELECT id FROM vehiculos WHERE empresa_id = 1 AND id <> {$this->vehiculoId} LIMIT 1")->fetchColumn();
        $k = $this->request('GET', '/dashboard/kpis', [], self::ENERO_2024 + ['vehiculo_id' => $otro], $this->token('Supervisor'))->data()['indicadores'];
        $this->assertNull($k['cumplimiento_viajes']['valor']);
        $this->assertNull($k['rendimiento_km_l']['valor']);
    }

    #[Test]
    public function las_cuatro_series_tienen_etiquetas_y_datos_del_mismo_largo(): void
    {
        $t = $this->token('Gerencia');
        foreach (['viajes_mes', 'costo_combustible_mes', 'rendimiento_vehiculo', 'viajes_por_estado'] as $serie) {
            $r = $this->request('GET', '/dashboard/series', [], ['serie' => $serie, 'desde' => '2026-01-01', 'hasta' => '2026-06-30'], $t);
            $this->assertSame(200, $r->status, $r->body);
            $d = $r->data();
            $this->assertNotEmpty($d['titulo']);
            $this->assertNotEmpty($d['descripcion']);
            $this->assertNotEmpty($d['labels'], $serie);
            foreach ($d['datasets'] as $ds) {
                $this->assertCount(count($d['labels']), $ds['data'], $serie);
            }
        }
        $meses = $this->request('GET', '/dashboard/series', [], ['serie' => 'viajes_mes', 'desde' => '2026-01-01', 'hasta' => '2026-06-30'], $t)->data();
        $this->assertSame(['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06'], $meses['labels']);
        $this->assertError($this->request('GET', '/dashboard/series', [], ['serie' => 'otra'], $t), 422, 'VALIDATION_ERROR');
    }

    #[Test]
    public function el_dashboard_valida_el_periodo_y_respeta_el_rol(): void
    {
        $this->assertError($this->request('GET', '/dashboard/kpis', [], [], $this->token('Conductor')), 403, 'SIN_PERMISO');
        $t = $this->token('Gerencia');
        $this->assertError($this->request('GET', '/dashboard/kpis', [], ['desde' => '2026-05-01', 'hasta' => '2026-04-01'], $t), 422, 'VALIDATION_ERROR');
        $this->assertError($this->request('GET', '/dashboard/kpis', [], ['desde' => '2020-01-01', 'hasta' => '2026-01-01'], $t), 422, 'VALIDATION_ERROR');
    }

    #[Test]
    public function el_reporte_operativo_pagina_filtra_y_entrega_totales(): void
    {
        $this->escenario();
        $r = $this->request('GET', '/reportes/operativo-viajes', [], self::ENERO_2024 + ['estado' => 'completado', 'per_page' => 1], $this->token('Supervisor'));
        $this->assertSame(200, $r->status, $r->body);
        $d = $r->data();
        $this->assertSame('Detalle de viajes', $d['titulo']);
        $this->assertSame(2, $d['meta']['total']);
        $this->assertCount(1, $d['data']);
        $this->assertSame(250, $d['totales']['km_recorridos']);
        $this->assertSame('Completado', $d['filtros']['Estado']);
        $this->assertContains('Km', array_column($d['columnas'], 'etiqueta'));
    }

    #[Test]
    public function el_reporte_gerencial_es_solo_para_administracion_y_gerencia(): void
    {
        $this->assertError($this->request('GET', '/reportes/gerencial-mensual', [], [], $this->token('Supervisor')), 403, 'SIN_PERMISO');
        $d = $this->request('GET', '/reportes/gerencial-mensual', [], ['desde' => '2026-01-01', 'hasta' => '2026-03-31'], $this->token('Gerencia'))->data();
        $this->assertSame(['2026-01', '2026-02', '2026-03'], array_column($d['data'], 'mes'));
        $this->assertSame(array_sum(array_column($d['data'], 'viajes')), $d['totales']['viajes']);
        $this->assertError($this->request('GET', '/reportes/no-existe', [], [], $this->token('Gerencia')), 404, 'NO_ENCONTRADO');
    }

    #[Test]
    public function el_csv_usa_utf8_con_bom_punto_y_coma_y_fila_de_totales(): void
    {
        $this->escenario();
        $r = $this->request('GET', '/reportes/combustible-periodo/export', [], self::ENERO_2024 + ['formato' => 'csv'], $this->token('Gerencia'));
        $this->assertSame(200, $r->status, $r->body);
        $this->assertStringStartsWith('text/csv', $r->headers['Content-Type']);
        $this->assertMatchesRegularExpression('/attachment; filename="tritransportes_combustible-periodo_\d{8}_\d{4}\.csv"/', $r->headers['Content-Disposition']);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $r->body);
        $lineas = array_values(array_filter(explode("\n", substr($r->body, 3))));
        $this->assertSame('Vehículo;Categoría;Cargas;Litros;Costo;"Precio medio por litro";"Viajes completados";Km;km/L', $lineas[0]);
        $this->assertStringStartsWith('TOTALES;', end($lineas));
        $this->assertStringContainsString(';100;100000;1000;2;250;2,5', end($lineas)); // litros; costo; precio; viajes; km; km/L
    }

    #[Test]
    public function el_xlsx_es_un_libro_valido_con_formulas_de_total(): void
    {
        $r = $this->request('GET', '/reportes/gerencial-mensual/export', [], ['desde' => '2026-01-01', 'hasta' => '2026-03-31', 'formato' => 'xlsx'], $this->token('Administrador'));
        $this->assertSame(200, $r->status, $r->body);
        $tmp = tempnam(sys_get_temp_dir(), 'ttt');
        file_put_contents($tmp, $r->body);
        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($tmp) === true);
        $hoja = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($tmp);
        $this->assertStringContainsString('<f>SUM(B6:B8)</f>', $hoja);               // total de viajes con fórmula
        $this->assertStringContainsString('Resumen gerencial mensual', $hoja);
        $this->assertStringContainsString('Período: 01-01-2026 al 31-03-2026', $hoja); // filtros en el archivo
        $this->assertStringContainsString('state="frozen"', $hoja);
    }

    #[Test]
    public function el_pdf_se_genera_y_toda_exportacion_queda_en_la_auditoria(): void
    {
        $r = $this->request('GET', '/reportes/operativo-viajes/export', [], ['desde' => '2026-10-01', 'hasta' => '2026-10-07', 'formato' => 'pdf'], $this->token('Supervisor'));
        $this->assertSame(200, $r->status, substr($r->body, 0, 300));
        $this->assertSame('application/pdf', $r->headers['Content-Type']);
        $this->assertStringStartsWith('%PDF-', $r->body);
        $this->assertGreaterThan(5000, strlen($r->body));

        $aud = self::$db->pdo->query("SELECT datos_despues FROM auditoria WHERE modulo = 'reportes' AND accion = 'exportar' AND usuario_id = {$this->usuarios['Supervisor']['id']}")->fetchColumn();
        $d = json_decode((string) $aud, true);
        $this->assertSame('operativo-viajes', $d['reporte']);
        $this->assertSame('pdf', $d['formato']);
        $this->assertSame('01-10-2026 al 07-10-2026', $d['filtros']['Período']);
        $this->assertGreaterThan(0, $d['filas']);

        $this->assertError($this->request('GET', '/reportes/operativo-viajes/export', [], ['formato' => 'docx'], $this->token('Supervisor')), 422, 'VALIDATION_ERROR');
        $this->assertError($this->request('GET', '/reportes/operativo-viajes/export', [], ['formato' => 'csv'], $this->token('Conductor')), 403, 'SIN_PERMISO');
    }
}
