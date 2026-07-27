<?php

declare(strict_types=1);

namespace Tests\Unit\TutoriasAcademicas;

use App\Services\TutoriasCsvParserService;
use PHPUnit\Framework\TestCase;

/**
 * Tests de TutoriasCsvParserService (I3 — Tutorías Académicas, v86): CSV
 * de EF1 (Planeación), EF2 (Cumplimiento) y EF3 (Seguimiento académico,
 * solo credenciales por ahora — ver nota de calibración pendiente en la
 * clase). Las fixtures reproducen el formato real confirmado contra los
 * dos CSV de ejemplo del compañero dueño de I3: separador ";", CRLF,
 * codificación CP850 (confirmada byte a byte — NO es latin-1), primera
 * fila título fusionado, y solo la primera fila con datos (no cada
 * estudiante) es la información de la materia.
 */
final class CsvParserTest extends TestCase
{
    /** Arma el contenido binario CP850 tal cual llega el archivo real, a partir de líneas en UTF-8. */
    private static function contenidoCp850(array $lineasUtf8): string
    {
        $texto = implode("\r\n", $lineasUtf8) . "\r\n";

        return mb_convert_encoding($texto, 'CP850', 'UTF-8');
    }

    private static function csvPlanificacion(): string
    {
        return self::contenidoCp850([
            'Planificación detutorias de materia: Bases y fundamentos de programación;;;;;',
            'N.;Estudiantes registrados en tutoria;Horas de tutorias asignadas por semana;Cohorte ;Pao;',
            '1;Daniel Luna;3 horas por semana;B-2025;A;',
            '2;Andrés Chiquito;;;;',
            '3;Kevin Moreno;;;;',
        ]);
    }

    private static function csvSeguimiento(): string
    {
        return self::contenidoCp850([
            'Seguimiento de tutorias de materia: Bases y fundamentos de programación;;;;;',
            'N.;Estudiantes registrados en tutoria;Horas de tutorias asignadas por semana;Cohorte ;Pao;Horas cumplidas por semana',
            '1;Daniel Luna;3 horas por semana;B-2025;A;3/3 horas cumplidas',
            '2;Andrés Chiquito;;;;',
        ]);
    }

    private static function contextoOk(): array
    {
        return ['asignatura' => 'Bases y fundamentos de programación', 'cohorte' => 'B-2025', 'pao' => 'A'];
    }

    // ── parseCsvLatin1 (en realidad CP850) ──────────────────────────────

    public function testParseCsvDecodificaCp850Correctamente(): void
    {
        $filas = TutoriasCsvParserService::parseCsvLatin1(self::csvPlanificacion());

        $this->assertSame('Andrés Chiquito', $filas[3][1]);
    }

    public function testExtraerAsignaturaDeTituloEsTolerranteATypos(): void
    {
        $filas = TutoriasCsvParserService::parseCsvLatin1(self::csvPlanificacion());

        // El título real trae el typo "detutorias" (sin espacio) -- el
        // extractor debe seguir funcionando igual.
        $this->assertSame('Bases y fundamentos de programación', TutoriasCsvParserService::extraerAsignaturaDeTitulo($filas));
    }

    // ── EF1 (Planeación) ─────────────────────────────────────────────────

    public function testEf1CumpleLosTresPuntosConContextoCorrecto(): void
    {
        $resultado = TutoriasCsvParserService::validarCsvPlanificacionOSeguimiento(self::csvPlanificacion(), 'EF1', self::contextoOk());

        $cumplidos = array_column($resultado['puntos'], 'cumplido', 'nombre');
        $this->assertTrue($cumplidos['horas_asignadas_presente']);
        $this->assertTrue($cumplidos['cohorte_coincide']);
        $this->assertTrue($cumplidos['pao_coincide']);
    }

    public function testEf1NoCumpleCohortePaoSiElContextoNoCoincide(): void
    {
        $contexto = ['asignatura' => 'Bases y fundamentos de programación', 'cohorte' => 'B-2024', 'pao' => 'B'];
        $resultado = TutoriasCsvParserService::validarCsvPlanificacionOSeguimiento(self::csvPlanificacion(), 'EF1', $contexto);

        $cumplidos = array_column($resultado['puntos'], 'cumplido', 'nombre');
        $this->assertTrue($cumplidos['horas_asignadas_presente']);
        $this->assertFalse($cumplidos['cohorte_coincide']);
        $this->assertFalse($cumplidos['pao_coincide']);
    }

    public function testEf1NoCumpleHorasSiLaFilaConDatosNoTraeHoras(): void
    {
        $csv = self::contenidoCp850([
            'Planificación de tutorias de materia: X;;;;;',
            'N.;Estudiantes registrados en tutoria;Horas de tutorias asignadas por semana;Cohorte ;Pao;',
            '1;Ana;;B-2025;A;',
        ]);
        $resultado = TutoriasCsvParserService::validarCsvPlanificacionOSeguimiento($csv, 'EF1', self::contextoOk());

        $cumplidos = array_column($resultado['puntos'], 'cumplido', 'nombre');
        $this->assertFalse($cumplidos['horas_asignadas_presente']);
    }

    // ── EF2 (Cumplimiento) ───────────────────────────────────────────────

    public function testEf2CumpleHorasCuandoLaFraccionEsCompleta(): void
    {
        $resultado = TutoriasCsvParserService::validarCsvPlanificacionOSeguimiento(self::csvSeguimiento(), 'EF2', self::contextoOk());

        $cumplidos = array_column($resultado['puntos'], 'cumplido', 'nombre');
        $this->assertTrue($cumplidos['horas_cumplidas_ok']);
    }

    public function testEf2NoCumpleHorasCuandoLaFraccionEsIncompleta(): void
    {
        $csv = self::contenidoCp850([
            'Seguimiento de tutorias de materia: X;;;;;',
            'N.;Estudiantes registrados en tutoria;Horas de tutorias asignadas por semana;Cohorte ;Pao;Horas cumplidas por semana',
            '1;Ana;3 horas por semana;B-2025;A;2/3 horas cumplidas',
        ]);
        $resultado = TutoriasCsvParserService::validarCsvPlanificacionOSeguimiento($csv, 'EF2', self::contextoOk());

        $cumplidos = array_column($resultado['puntos'], 'cumplido', 'nombre');
        $this->assertFalse($cumplidos['horas_cumplidas_ok']);
    }

    // ── EF3 (Seguimiento académico -- solo credenciales) ────────────────

    public function testEf3CumpleCredencialesCuandoTodoCoincide(): void
    {
        // Formato real de EF3 aún no confirmado (ver nota de calibración
        // pendiente en la clase); se reusa el mismo layout de EF1/EF2
        // como fixture de esta primera versión.
        $resultado = TutoriasCsvParserService::validarCsvSeguimientoAcademico(self::csvSeguimiento(), self::contextoOk());

        $cumplidos = array_column($resultado['puntos'], 'cumplido', 'nombre');
        $this->assertTrue($cumplidos['asignatura_coincide']);
        $this->assertTrue($cumplidos['cohorte_coincide']);
        $this->assertTrue($cumplidos['pao_coincide']);
    }

    public function testEf3NoCumpleAsignaturaSiNoCoincideConLaBd(): void
    {
        $contexto = ['asignatura' => 'Otra materia distinta', 'cohorte' => 'B-2025', 'pao' => 'A'];
        $resultado = TutoriasCsvParserService::validarCsvSeguimientoAcademico(self::csvSeguimiento(), $contexto);

        $cumplidos = array_column($resultado['puntos'], 'cumplido', 'nombre');
        $this->assertFalse($cumplidos['asignatura_coincide']);
    }
}
