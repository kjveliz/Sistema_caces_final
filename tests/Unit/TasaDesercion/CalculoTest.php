<?php

declare(strict_types=1);

namespace Tests\Unit\TasaDesercion;

use App\Services\DesercionCalculoService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests de DesercionCalculoService::extraerDatosDesercion(), migrada en la
 * Fase 3 (I4) desde la función suelta homónima de
 * api/tasa_desercion/_calculo.php (que existía desde la Fase 5, extraída de
 * leer_pdf.php para hacerla testeable). Mismos 24 asserts que antes, ahora
 * contra la clase real vía el autoload PSR-4 de Composer (sin require_once
 * manual, igual que se hizo con TitulacionCalculoService/I5 en v66).
 *
 * A diferencia de I5, los patrones de "total" de I4 no dependen del tipo de
 * dato (mismo array de patrones para primer_nivel/segundo_anio/
 * no_continuaron -- ver el propio servicio), así que $tipoDato se fija acá
 * a 'primer_nivel' en todos los tests salvo donde se prueba explícitamente
 * que el valor pasado se refleja en el resultado.
 */
final class CalculoTest extends TestCase
{
    public function testDetectaTotalReportadoExplicitamente(): void
    {
        $texto = "Reporte de matriculados\nPeriodo: PAO 1 2026 Fecha Inicio: 01/03/2026\n"
            . "Total alumnos por ciclo: 42\nCohorte: B 2025\n";

        $resultado = DesercionCalculoService::extraerDatosDesercion($texto, 'primer_nivel');

        $this->assertSame(42, $resultado->total);
        $this->assertSame('total_reportado', $resultado->metodo);
        $this->assertSame('B2025', $resultado->cohorteDetectada);
        $this->assertSame('PAO 1 2026', $resultado->periodoDetectado);
    }

    #[DataProvider('proveedorFrasesDeTotal')]
    public function testReconoceCadaVarianteDeFraseDeTotal(string $frase, int $totalEsperado): void
    {
        $resultado = DesercionCalculoService::extraerDatosDesercion("Encabezado\n{$frase}\nPie de página", 'primer_nivel');

        $this->assertSame($totalEsperado, $resultado->total);
        $this->assertSame('total_reportado', $resultado->metodo);
    }

    public static function proveedorFrasesDeTotal(): array
    {
        return [
            ['Total alumnos por ciclo: 10', 10],
            ['Total de alumnos: 11', 11],
            ['Total alumnos: 12', 12],
            ['Total matriculados: 13', 13],
            ['Total estudiantes: 14', 14],
            ['Total que no continuaron: 5', 5],
            ['Total no continuaron: 6', 6],
            ['Total desertados: 7', 7],
        ];
    }

    public function testRespaldoCuentaCedulasUnicasDe10DigitosCuandoNoHayTotalExplicito(): void
    {
        $texto = "Listado de estudiantes\n"
            . "1712345678 Juan Perez\n"
            . "0912345678 Maria Lopez\n"
            . "1712345678 Juan Perez\n" // repetida -- no debe contarse dos veces
            . "Codigo interno 12345\n"; // 5 dígitos, no cuenta

        $resultado = DesercionCalculoService::extraerDatosDesercion($texto, 'segundo_anio');

        $this->assertSame(2, $resultado->total);
        $this->assertSame('identificaciones_unicas', $resultado->metodo);
        $this->assertSame(2, $resultado->identificacionesDetectadas);
    }

    public function testSinEstudiantesDetectadosLanzaExcepcion(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se pudo detectar ningún estudiante en el PDF.');

        DesercionCalculoService::extraerDatosDesercion('Documento sin datos reconocibles ni cedulas.', 'no_continuaron');
    }

    #[DataProvider('proveedorFormatosCohorte')]
    public function testDetectaCohorteEnDistintosFormatos(string $texto, string $esperado): void
    {
        $resultado = DesercionCalculoService::extraerDatosDesercion("Total alumnos: 5\n" . $texto, 'primer_nivel');

        $this->assertSame($esperado, $resultado->cohorteDetectada);
    }

    public static function proveedorFormatosCohorte(): array
    {
        return [
            ['Cohorte B2025', 'B2025'],
            ['Cohorte B 2025', 'B2025'],
            ['Cohorte a2026', 'A2026'],
            ['Cohorte A 2026', 'A2026'],
        ];
    }

    public function testSinCohorteEnElTextoDevuelveNull(): void
    {
        $resultado = DesercionCalculoService::extraerDatosDesercion("Total alumnos: 5\nSin ninguna mención de cohorte aquí.", 'primer_nivel');

        $this->assertNull($resultado->cohorteDetectada);
    }

    public function testPeriodoSeCortaAntesDeFechaInicio(): void
    {
        $resultado = DesercionCalculoService::extraerDatosDesercion(
            "Total alumnos: 5\nPeriodo: PAO   2   2026   Fecha Inicio: 01/09/2026 Fecha Fin: 01/12/2026",
            'primer_nivel',
        );

        $this->assertSame('PAO 2 2026', $resultado->periodoDetectado);
    }

    public function testElTipoDatoPasadoSeReflejaEnElResultado(): void
    {
        $resultado = DesercionCalculoService::extraerDatosDesercion('Total alumnos: 5', 'no_continuaron');

        $this->assertSame('no_continuaron', $resultado->tipoDato);
    }
}
