<?php

declare(strict_types=1);

namespace Tests\Unit\TutoriasAcademicas;

use App\Services\TutoriasCalculoService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests de las funciones/métodos estáticos puros (sin mysqli) de
 * App\Services\TutoriasCalculoService (I3 — Tutorías Académicas), migrado
 * en la Fase 3 desde api/tutorias_academicas/_calculo.php.
 *
 * Mismo alcance que tests/Unit/SeguimientoSyllabus/CalculoTest.php para I2:
 * solo lo que no depende de una conexión real a BD. calcularResultadoAsignatura()
 * y calcularResultadoGeneral() (que sí dependen de TutoriasRepository/mysqli)
 * quedan fuera de esta primera pasada — decisión de alcance acordada con el
 * usuario antes de escribir este archivo.
 */
final class CalculoTest extends TestCase
{
    // ── porcentajePorPuntos ─────────────────────────────────────────────

    #[DataProvider('proveedorPorcentajesBase3')]
    public function testPorcentajePorPuntosBase3(int $cumplidos, float $esperado): void
    {
        $this->assertSame($esperado, TutoriasCalculoService::porcentajePorPuntos($cumplidos, 3));
    }

    public static function proveedorPorcentajesBase3(): array
    {
        return [
            '0 de 3 -> 0%' => [0, 0.0],
            '1 de 3 -> 33%' => [1, 33.0],
            '2 de 3 -> 66%' => [2, 66.0],
            '3 de 3 -> 100%' => [3, 100.0],
        ];
    }

    #[DataProvider('proveedorPorcentajesBase2')]
    public function testPorcentajePorPuntosBase2(int $cumplidos, float $esperado): void
    {
        $this->assertSame($esperado, TutoriasCalculoService::porcentajePorPuntos($cumplidos, 2));
    }

    public static function proveedorPorcentajesBase2(): array
    {
        return [
            '0 de 2 -> 0%' => [0, 0.0],
            '1 de 2 -> 50%' => [1, 50.0],
            '2 de 2 -> 100%' => [2, 100.0],
        ];
    }

    /**
     * Base 4 ya NO es una escala oficial desde v86 (EF4 pasó de 4 a 2
     * puntos) — este test queda para confirmar que ese caso sigue cayendo
     * en el fallback genérico (regla de tres) y no rompe nada, no porque
     * sea una escala soportada.
     */
    #[DataProvider('proveedorPorcentajesBase4ComoFallback')]
    public function testPorcentajePorPuntosBase4CaeEnFallbackGenerico(int $cumplidos, float $esperado): void
    {
        $this->assertSame($esperado, TutoriasCalculoService::porcentajePorPuntos($cumplidos, 4));
    }

    public static function proveedorPorcentajesBase4ComoFallback(): array
    {
        return [
            '0 de 4 -> 0%' => [0, 0.0],
            '1 de 4 -> 25%' => [1, 25.0],
            '2 de 4 -> 50%' => [2, 50.0],
            '3 de 4 -> 75%' => [3, 75.0],
            '4 de 4 -> 100%' => [4, 100.0],
        ];
    }

    /** 0 cumplidos siempre es 0%, sin importar la base — regla explícita del negocio. */
    public function testPorcentajePorPuntosCeroCumplidosEsSiempreCero(): void
    {
        $this->assertSame(0.0, TutoriasCalculoService::porcentajePorPuntos(0, 3));
        $this->assertSame(0.0, TutoriasCalculoService::porcentajePorPuntos(0, 4));
        // Incluso con una base "rara" (fuera de los EF actuales, que solo usan 3 o 4).
        $this->assertSame(0.0, TutoriasCalculoService::porcentajePorPuntos(0, 5));
    }

    /** Cumplidos negativos (no debería pasar en la práctica) también caen a 0%, no a un valor negativo. */
    public function testPorcentajePorPuntosCumplidosNegativoEsCero(): void
    {
        $this->assertSame(0.0, TutoriasCalculoService::porcentajePorPuntos(-1, 3));
    }

    /**
     * Base distinta de 3/4 (no debería usarse con los EF actuales, pero el
     * método la soporta): usa el fallback genérico (regla de tres simple,
     * redondeado a 1 decimal) en vez de la tabla fija de 3/4 puntos.
     */
    public function testPorcentajePorPuntosFallbackGenerico(): void
    {
        $this->assertSame(40.0, TutoriasCalculoService::porcentajePorPuntos(2, 5));
        $this->assertSame(42.9, TutoriasCalculoService::porcentajePorPuntos(3, 7));
    }

    // ── calcularEscala ──────────────────────────────────────────────────

    #[DataProvider('proveedorEscalas')]
    public function testCalcularEscala(?float $valoracion, ?string $escalaEsperada, ?string $colorEsperado): void
    {
        [$escala, $color] = TutoriasCalculoService::calcularEscala($valoracion);

        $this->assertSame($escalaEsperada, $escala);
        $this->assertSame($colorEsperado, $color);
    }

    public static function proveedorEscalas(): array
    {
        return [
            'null -> sin datos' => [null, null, null],
            'límite inferior 0 -> Deficiente' => [0.0, 'Deficiente', '#EF4444'],
            'justo debajo de 25 -> Deficiente' => [24.9, 'Deficiente', '#EF4444'],
            'límite 25 exacto -> Poco Satisfactorio' => [25.0, 'Poco Satisfactorio', '#F97316'],
            'justo debajo de 50 -> Poco Satisfactorio' => [49.9, 'Poco Satisfactorio', '#F97316'],
            'límite 50 exacto -> Cuasi Satisfactorio' => [50.0, 'Cuasi Satisfactorio', '#CA8A04'],
            'justo debajo de 75 -> Cuasi Satisfactorio' => [74.9, 'Cuasi Satisfactorio', '#CA8A04'],
            'límite 75 exacto -> Satisfactorio' => [75.0, 'Satisfactorio', '#15803D'],
            '100 -> Satisfactorio' => [100.0, 'Satisfactorio', '#15803D'],
        ];
    }

    // ── pesosEf ──────────────────────────────────────────────────────────

    public function testPesosEfSumanUno(): void
    {
        $pesos = TutoriasCalculoService::pesosEf();

        $this->assertSame(['EF1' => 0.40, 'EF2' => 0.30, 'EF3' => 0.20, 'EF4' => 0.10], $pesos);
        $this->assertEqualsWithDelta(1.0, array_sum($pesos), 0.0001);
    }

    // ── etiquetasEf ──────────────────────────────────────────────────────

    public function testEtiquetasEfIncluyeLosCuatroEf(): void
    {
        $etiquetas = TutoriasCalculoService::etiquetasEf();

        $this->assertSame(
            [
                'EF1' => 'Planeación de tutorías',
                'EF2' => 'Cumplimiento de tutorías',
                'EF3' => 'Seguimiento académico',
                'EF4' => 'Normativas institucionales',
            ],
            $etiquetas,
        );
    }

    // ── tipoAEf / TIPOS_TUTORIAS ──────────────────────────────────────────

    public function testTipoAEfMapeaLosCuatroTipos(): void
    {
        $this->assertSame(
            [
                'plan_tutorias' => 'EF1',
                'registro_tutorias' => 'EF2',
                'informe_tutorias' => 'EF3',
                'evidencia_atencion' => 'EF4',
            ],
            TutoriasCalculoService::tipoAEf(),
        );
    }

    /** TIPOS_TUTORIAS y las claves de tipoAEf() deben coincidir exactamente (mismo orden, mismo contenido). */
    public function testTiposTutoriasCoincideConClavesDeTipoAEf(): void
    {
        $this->assertSame(
            array_keys(TutoriasCalculoService::tipoAEf()),
            TutoriasCalculoService::TIPOS_TUTORIAS,
        );
    }
}
