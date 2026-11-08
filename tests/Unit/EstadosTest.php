<?php
declare(strict_types=1);

namespace TriTransportes\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use TriTransportes\Domain\EstadoMantencion;
use TriTransportes\Domain\EstadoViaje;
use TriTransportes\Domain\Rol;

final class EstadosTest extends TestCase
{
    /** Las 16 combinaciones origen → destino de la máquina de estados del viaje. */
    public static function transicionesViaje(): array
    {
        $validas = ['programado>en_ruta', 'programado>cancelado', 'en_ruta>completado', 'en_ruta>cancelado'];
        $casos = [];
        foreach (EstadoViaje::cases() as $o) {
            foreach (EstadoViaje::cases() as $d) {
                $k = "{$o->value}>{$d->value}";
                $casos[$k] = [$o, $d, in_array($k, $validas, true)];
            }
        }
        return $casos;
    }

    #[Test]
    #[DataProvider('transicionesViaje')]
    public function la_maquina_de_estados_del_viaje_solo_acepta_las_transiciones_definidas(EstadoViaje $o, EstadoViaje $d, bool $esperada): void
    {
        $this->assertSame($esperada, $o->puedePasarA($d));
    }

    #[Test]
    public function completado_y_cancelado_son_estados_finales(): void
    {
        $this->assertTrue(EstadoViaje::Completado->esFinal());
        $this->assertTrue(EstadoViaje::Cancelado->esFinal());
        $this->assertFalse(EstadoViaje::Programado->esFinal());
    }

    #[Test]
    public function el_conductor_no_puede_cancelar_pero_si_iniciar_y_completar(): void
    {
        $this->assertSame(['en_ruta'], EstadoViaje::Programado->siguientesPara(Rol::Conductor));
        $this->assertSame(['completado'], EstadoViaje::EnRuta->siguientesPara(Rol::Conductor));
        $this->assertSame(['en_ruta', 'cancelado'], EstadoViaje::Programado->siguientesPara(Rol::Supervisor));
        $this->assertSame([], EstadoViaje::Programado->siguientesPara(Rol::Gerencia));
    }

    #[Test]
    public function la_mantencion_avanza_sin_saltos_ni_retrocesos(): void
    {
        $this->assertTrue(EstadoMantencion::Pendiente->puedePasarA(EstadoMantencion::EnProceso));
        $this->assertTrue(EstadoMantencion::EnProceso->puedePasarA(EstadoMantencion::Completada));
        $this->assertFalse(EstadoMantencion::Pendiente->puedePasarA(EstadoMantencion::Completada));
        $this->assertFalse(EstadoMantencion::Completada->puedePasarA(EstadoMantencion::EnProceso));
        $this->assertNull(EstadoMantencion::Completada->siguiente());
    }

    #[Test]
    public function los_roles_heredados_se_traducen_o_quedan_fuera_de_la_api(): void
    {
        $this->assertSame(Rol::Administrador, Rol::desdeBd('Administrador General'));
        $this->assertSame(Rol::Conductor, Rol::desdeBd('Conductor'));
        $this->assertNull(Rol::desdeBd('Mecánico'));
    }
}
