<?php

declare(strict_types=1);

namespace Tests\Unit\SeguimientoSyllabus;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../../api/seguimiento_syllabus/_calculo.php';

/**
 * Tests de las funciones puras (sin mysqli) de api/seguimiento_syllabus/_calculo.php.
 * El resto de las funciones del archivo (calcularResultadoAsignatura,
 * calcularResultadoGeneral, tiposCarreraVigentes, etc.) dependen de mysqli
 * y quedan fuera del alcance de esta primera pasada de la Fase 5 -- ver
 * MEMORIA para el detalle de qué falta.
 */
final class CalculoTest extends TestCase
{
    /**
     * @dataProvider proveedorEscalas
     */
    public function testCalcularEscala(?float $valoracion, ?string $escalaEsperada, ?string $colorEsperado): void
    {
        [$escala, $color] = calcularEscala($valoracion);

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

    public function testEtiquetasEvidenciaIncluyeLosOchoTipos(): void
    {
        $etiquetas = etiquetasEvidencia();

        $this->assertCount(8, $etiquetas);
        $this->assertSame(
            [
                'malla_curricular',
                'syllabus',
                'acta_ajuste_curricular',
                'evidencia_difusion',
                'reglamento_normativa',
                'encuesta_csv',
                'reporte_control_siu',
                'reporte_avances_siu',
            ],
            array_keys($etiquetas),
        );
    }
}
