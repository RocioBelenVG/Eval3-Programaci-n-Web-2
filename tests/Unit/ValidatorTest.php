<?php
declare(strict_types=1);

namespace TriTransportes\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TriTransportes\Exception\ApiException;
use TriTransportes\Validation\Validator;

final class ValidatorTest extends TestCase
{
    #[Test]
    public function convierte_los_tipos_y_descarta_los_campos_no_declarados(): void
    {
        $d = Validator::check(
            ['vehiculo_id' => '5', 'litros' => '120.5', 'origen' => '  Planta  ', 'es_admin' => true],
            ['vehiculo_id' => ['required', 'int', 'min:1'], 'litros' => ['numeric', 'gt:0'], 'origen' => ['string', 'min:2']]
        );
        $this->assertSame(['vehiculo_id' => 5, 'litros' => 120.5, 'origen' => 'Planta'], $d);
    }

    #[Test]
    public function junta_todos_los_errores_en_un_solo_422(): void
    {
        try {
            Validator::check(
                ['fecha' => '2026-02-30', 'litros' => 0, 'unidad' => 'kg'],
                ['vehiculo_id' => ['required', 'int'], 'fecha' => ['date'], 'litros' => ['numeric', 'gt:0'], 'unidad' => ['in:m3,ton,viaje']]
            );
            $this->fail('Se esperaba un error de validación.');
        } catch (ApiException $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame('VALIDATION_ERROR', $e->errorCode);
            $this->assertSame(['vehiculo_id', 'fecha', 'litros', 'unidad'], array_column($e->details, 'field'));
        }
    }

    #[Test]
    public function valida_el_digito_verificador_del_rut(): void
    {
        $this->assertTrue(Validator::rutValido('11111111-1'));
        $this->assertTrue(Validator::rutValido('12345678-5'));
        $this->assertTrue(Validator::rutValido('10000013-K'));
        $this->assertFalse(Validator::rutValido('12345678-9'));
        $this->assertFalse(Validator::rutValido('12.345.678-5'));
    }

    #[Test]
    public function rechaza_horas_y_enteros_mal_formados(): void
    {
        $this->expectException(ApiException::class);
        Validator::check(['hora' => '25:00', 'n' => '3.5'], ['hora' => ['time'], 'n' => ['int']]);
    }

    #[Test]
    public function la_paginacion_respeta_los_limites_del_contrato(): void
    {
        $this->assertSame([1, 25], Validator::paginacion([]));
        $this->assertSame([3, 100], Validator::paginacion(['page' => '3', 'per_page' => '100']));
        $this->expectException(ApiException::class);
        Validator::paginacion(['per_page' => '101']);
    }
}
