<?php

declare(strict_types=1);

namespace Tests\Unit\TasaDesercion;

use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../../api/tasa_desercion/_calculo.php';

/**
 * Tests de extraerDatosDesercion() (api/tasa_desercion/_calculo.php),
 * extraída de leer_pdf.php en la Fase 5 para poder testear el parseo por
 * regex del PDF de I4 sin necesidad de subir un archivo real ni tocar HTTP.
 */
final class CalculoTest extends TestCase
{
    public function testDetectaTotalReportadoExplicitamente(): void
    {
        $texto = "Reporte de matriculados\nPeriodo: PAO 1 2026 Fecha Inicio: 01/03/2026\n"
            . "Total alumnos por ciclo: 42\nCohorte: B 2025\n";

        $resultado = extraerDatosDesercion($texto);

        $this->assertSame(42, $resultado['total']);
        $this->assertSame('total_reportado', $resultado['metodo']);
        $this->assertSame('B2025', $resultado['cohorte_detectada']);
        $this->assertSame('PAO 1 2026', $resultado['periodo_detectado']);
    }

    /**
     * @dataProvider proveedorFrasesDeTotal
     */
    public function testReconoceCadaVarianteDeFraseDeTotal(string $frase, int $totalEsperado): void
    {
        $resultado = extraerDatosDesercion("Encabezado\n{$frase}\nPie de página");

        $this->assertSame($totalEsperado, $resultado['total']);
        $this->assertSame('total_reportado', $resultado['metodo']);
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

        $resultado = extraerDatosDesercion($texto);

        $this->assertSame(2, $resultado['total']);
        $this->assertSame('identificaciones_unicas', $resultado['metodo']);
        $this->assertSame(2, $resultado['identificaciones_detectadas']);
    }

    public function testSinEstudiantesDetectadosLanzaExcepcion(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se pudo detectar ningún estudiante en el PDF.');

        extraerDatosDesercion("Documento sin datos reconocibles ni cedulas.");
    }

    /**
     * @dataProvider proveedorFormatosCohorte
     */
    public function testDetectaCohorteEnDistintosFormatos(string $texto, string $esperado): void
    {
        $resultado = extraerDatosDesercion("Total alumnos: 5\n" . $texto);

        $this->assertSame($esperado, $resultado['cohorte_detectada']);
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
        $resultado = extraerDatosDesercion("Total alumnos: 5\nSin ninguna mención de cohorte aquí.");

        $this->assertNull($resultado['cohorte_detectada']);
    }

    public function testPeriodoSeCortaAntesDeFechaInicio(): void
    {
        $resultado = extraerDatosDesercion(
            "Total alumnos: 5\nPeriodo: PAO   2   2026   Fecha Inicio: 01/09/2026 Fecha Fin: 01/12/2026",
        );

        $this->assertSame('PAO 2 2026', $resultado['periodo_detectado']);
    }
}
