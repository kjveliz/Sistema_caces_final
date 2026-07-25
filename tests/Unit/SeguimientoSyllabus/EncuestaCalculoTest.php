<?php

declare(strict_types=1);

namespace Tests\Unit\SeguimientoSyllabus;

use PHPUnit\Framework\TestCase;

use App\Services\EncuestaCalculoService;

/**
 * Tests de las funciones puras (estáticas, sin mysqli/Drive) de
 * EncuestaCalculoService: parseCsvString, buscarColumnasPregunta,
 * textoPregunta y calcularEfDesdeFilas. Migrado en la Fase 3 desde
 * api/seguimiento_syllabus/_encuesta.php (funciones sueltas, extraídas de
 * calcularEfDesdeCsv en la Fase 5 específicamente para poder testear esto
 * sin infraestructura externa) -- misma lógica, mismos asserts.
 *
 * Esta clase existe sobre todo para blindar contra la reaparición del bug
 * real que ya ocurrió acá (ver MEMORIA, 20 jul 2026): el código asumía 3
 * columnas fijas (timestamp/materia/profesor) antes de las 23 preguntas,
 * cuando el formulario oficial real solo tiene 1 columna fija (timestamp).
 * Los valores esperados de testCalcularEfDesdeFilasFormatoOficial() solo dan
 * bien si el offset de columnas sigue siendo 1 -- si alguien reintroduce el
 * offset de 3, este test falla.
 */
final class EncuestaCalculoTest extends TestCase
{
    // ── parseCsvString ──────────────────────────────────────────────────

    public function testParseCsvStringFilaSimple(): void
    {
        $contenido = "Marca temporal,[P1. Pregunta uno]\n2026-07-20 10:00:00,Siempre\n";

        $filas = EncuestaCalculoService::parseCsvString($contenido);

        $this->assertSame(
            [
                ['Marca temporal', '[P1. Pregunta uno]'],
                ['2026-07-20 10:00:00', 'Siempre'],
            ],
            $filas,
        );
    }

    public function testParseCsvStringRespetaComillasConComasInternas(): void
    {
        // Un valor con coma adentro, entre comillas, no debe partirse en dos columnas.
        $contenido = "Marca temporal,[P1. Pregunta uno]\n2026-07-20,\"Algunas veces, casi siempre\"\n";

        $filas = EncuestaCalculoService::parseCsvString($contenido);

        $this->assertCount(2, $filas[1]);
        $this->assertSame('Algunas veces, casi siempre', $filas[1][1]);
    }

    public function testParseCsvStringVacioDevuelveArrayVacio(): void
    {
        $this->assertSame([], EncuestaCalculoService::parseCsvString(''));
    }

    // ── buscarColumnasPregunta ───────────────────────────────────────────

    public function testBuscarColumnasPreguntaNoConfundeP1ConP10(): void
    {
        // Regresión explícita: '[P1.' no debe hacer match contra '[P10.' por
        // ser un prefijo -- el patrón exige que después de "P1" venga "." o "]".
        $preguntas = ['[P1. Primera pregunta]', '[P10. Décima pregunta]', '[P11. Onceava]'];

        $this->assertSame(['[P1. Primera pregunta]'], EncuestaCalculoService::buscarColumnasPregunta($preguntas, 1));
        $this->assertSame(['[P10. Décima pregunta]'], EncuestaCalculoService::buscarColumnasPregunta($preguntas, 10));
    }

    public function testBuscarColumnasPreguntaSoportaFormatoSinTexto(): void
    {
        $preguntas = ['[P5]', '[P6. Con texto]'];

        $this->assertSame(['[P5]'], EncuestaCalculoService::buscarColumnasPregunta($preguntas, 5));
        $this->assertSame(['[P6. Con texto]'], EncuestaCalculoService::buscarColumnasPregunta($preguntas, 6));
    }

    public function testBuscarColumnasPreguntaSinCoincidenciaDevuelveVacio(): void
    {
        $this->assertSame([], EncuestaCalculoService::buscarColumnasPregunta(['[P1. Primera]'], 99));
    }

    // ── textoPregunta ────────────────────────────────────────────────────

    public function testTextoPreguntaExtraeElTextoEntreCorchetes(): void
    {
        $this->assertSame(
            'Se cumplió el syllabus en los tiempos previstos',
            EncuestaCalculoService::textoPregunta('[P5. Se cumplió el syllabus en los tiempos previstos]', 5),
        );
    }

    public function testTextoPreguntaSinTextoDevuelveElHeaderCompleto(): void
    {
        $this->assertSame('[P5]', EncuestaCalculoService::textoPregunta('[P5]', 5));
    }

    // ── calcularEfDesdeFilas: el cálculo real de EF1/EF4 ────────────────

    /**
     * Recrea el formato REAL confirmado del formulario oficial: 1 sola
     * columna fija (timestamp) + 23 preguntas [P1]..[P23], sin columnas de
     * materia/profesor. EF1 = promedio de P5/P8/P13, EF4 = P6 (ver
     * _encuesta.php).
     */
    public function testCalcularEfDesdeFilasFormatoOficial(): void
    {
        $preguntas = [];
        for ($n = 1; $n <= 23; $n++) {
            $preguntas[] = "[P{$n}. Pregunta {$n}]";
        }
        $headers = array_merge(['Marca temporal'], $preguntas);

        // Fila 1: todo "Siempre" (5/5). Fila 2: todo "Nunca" (1/5) salvo
        // P5=Casi siempre(4), P8=Algunas veces(3), P13=Pocas veces(2), P6=Siempre(5)
        // -- así el promedio de cada pregunta EF1/EF4 queda distinto del resto,
        // y un desplazamiento de columnas se notaría inmediatamente.
        $fila1 = array_fill(0, 24, 'Siempre');
        $fila1[0] = '2026-07-20 10:00:00';

        $fila2 = array_fill(0, 24, 'Nunca');
        $fila2[0] = '2026-07-20 11:00:00';
        $fila2[5] = 'Casi siempre';   // índice de P5 en la fila (0=timestamp, 1=P1... 5=P5)
        $fila2[8] = 'Algunas veces';  // P8
        $fila2[13] = 'Pocas veces';   // P13
        $fila2[6] = 'Siempre';        // P6

        $filas = [$headers, $fila1, $fila2];

        $resultado = EncuestaCalculoService::calcularEfDesdeFilas($filas, false);

        // EF1 = promedio(P5, P8, P13) = promedio(90.0, 80.0, 70.0) / 100 = 0.8
        $this->assertSame(0.8, $resultado['ef1']);
        // EF4 = P6 = 100.0 / 100 = 1.0
        $this->assertSame(1.0, $resultado['ef4']);
        $this->assertSame(2, $resultado['respuestas']);
        $this->assertSame(64.3, $resultado['promedio_general']);
        $this->assertFalse($resultado['degradado']);
    }

    public function testCalcularEfDesdeFilasPropagaFlagDegradado(): void
    {
        $filas = [
            ['Marca temporal', '[P5]', '[P6]', '[P8]', '[P13]'],
            ['2026-07-20', 'Siempre', 'Siempre', 'Siempre', 'Siempre'],
        ];

        $resultado = EncuestaCalculoService::calcularEfDesdeFilas($filas, true);

        $this->assertTrue($resultado['degradado']);
    }

    public function testCalcularEfDesdeFilasVacioDevuelveNull(): void
    {
        $this->assertNull(EncuestaCalculoService::calcularEfDesdeFilas([], false));
    }

    public function testCalcularEfDesdeFilasSinFilasDeDatosDevuelveCeros(): void
    {
        // Solo el header, ninguna respuesta -- no debe romper, EF1/EF4 en 0.
        $filas = [['Marca temporal', '[P5]', '[P6]', '[P8]', '[P13]']];

        $resultado = EncuestaCalculoService::calcularEfDesdeFilas($filas, false);

        $this->assertSame(0.0, $resultado['ef1']);
        $this->assertSame(0.0, $resultado['ef4']);
        $this->assertSame(0, $resultado['respuestas']);
    }

    public function testCalcularEfDesdeFilasIgnoraValoresFueraDelMapaDePuntaje(): void
    {
        // Un valor que no está en PUNTAJE_MAP (typo, celda vacía, etc.) se
        // descarta -- no debe contar como 0 puntos ni romper el promedio.
        $filas = [
            ['Marca temporal', '[P5]', '[P6]', '[P8]', '[P13]'],
            ['2026-07-20', 'Siempre', 'Siempre', 'Siempre', 'Siempre'],
            ['2026-07-20', 'texto-invalido', 'Siempre', 'Siempre', 'Siempre'],
        ];

        $resultado = EncuestaCalculoService::calcularEfDesdeFilas($filas, false);

        // P5 solo tuvo 1 respuesta válida (Siempre=5) -> 100.0; P6/P8/P13 tuvieron 2 -> 100.0.
        // EF1 = promedio(100, 100, 100)/100 = 1.0
        $this->assertSame(1.0, $resultado['ef1']);
        $this->assertSame(1.0, $resultado['ef4']);
        // Ambas filas cuentan como "respuesta" (count($fila) >= 2), aunque una tenga un valor inválido.
        $this->assertSame(2, $resultado['respuestas']);
    }
}
