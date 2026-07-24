<?php

declare(strict_types=1);

namespace Tests\Unit\TasaTitulacion;

use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../../../api/tasa_titulacion/_calculo.php';

/**
 * Tests de extraerDatosTitulacion() (api/tasa_titulacion/_calculo.php),
 * extraída de leer_pdf.php en la Fase 5. Los patrones de "total" son
 * distintos según $tipoDato ("matriculados" vs "graduados") -- ver el
 * propio archivo.
 */
final class CalculoTest extends TestCase
{
    /**
     * @dataProvider proveedorFrasesMatriculados
     */
    public function testReconoceFrasesDeTotalParaMatriculados(string $frase, int $totalEsperado): void
    {
        $resultado = extraerDatosTitulacion("Encabezado\n{$frase}\nPie", 'matriculados');

        $this->assertSame($totalEsperado, $resultado['total']);
        $this->assertSame('total_reportado', $resultado['metodo']);
    }

    public static function proveedorFrasesMatriculados(): array
    {
        return [
            ['Total alumnos por ciclo: 20', 20],
            ['Total de alumnos: 21', 21],
            ['Total alumnos: 22', 22],
            ['Total matriculados: 23', 23],
        ];
    }

    /**
     * @dataProvider proveedorFrasesGraduados
     */
    public function testReconoceFrasesDeTotalParaGraduados(string $frase, int $totalEsperado): void
    {
        $resultado = extraerDatosTitulacion("Encabezado\n{$frase}\nPie", 'graduados');

        $this->assertSame($totalEsperado, $resultado['total']);
        $this->assertSame('total_reportado', $resultado['metodo']);
    }

    public static function proveedorFrasesGraduados(): array
    {
        return [
            ['Total de graduados: 5', 5],
            ['Total graduados: 6', 6],
            ['Total de estudiantes graduados: 7', 7],
            ['Total estudiantes graduados: 8', 8],
            ['Total de alumnos graduados: 9', 9],
            ['Total alumnos graduados: 10', 10],
            ['Número de graduados: 11', 11],
        ];
    }

    public function testUnaFraseDeGraduadosNoAplicaParaMatriculados(): void
    {
        // "Total de graduados: 30" no es un patrón válido para $tipoDato=matriculados
        // -- debe caer al respaldo de cédulas (0 en este caso, por lo tanto excepción).
        $this->expectException(RuntimeException::class);

        extraerDatosTitulacion("Total de graduados: 30\nsin cedulas.", 'matriculados');
    }

    public function testRespaldoCuentaCedulasUnicasDe10Digitos(): void
    {
        $texto = "1712345678 Ana\n0912345678 Luis\n1712345678 Ana\n";

        $resultado = extraerDatosTitulacion($texto, 'graduados');

        $this->assertSame(2, $resultado['total']);
        $this->assertSame('identificaciones_unicas', $resultado['metodo']);
    }

    public function testSinEstudiantesDetectadosLanzaExcepcion(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se pudo detectar ningún estudiante en el PDF.');

        extraerDatosTitulacion('Documento vacío de datos.', 'matriculados');
    }

    public function testDetectaCohorteYPeriodo(): void
    {
        $resultado = extraerDatosTitulacion(
            "Total graduados: 3\nCohorte A2026\nPeriodo: PAO 3 2025 Fecha: 01/01/2026",
            'graduados',
        );

        $this->assertSame('A2026', $resultado['cohorte_detectada']);
        $this->assertSame('PAO 3 2025', $resultado['periodo_detectado']);
    }
}
