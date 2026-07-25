<?php

declare(strict_types=1);

namespace Tests\Integration\SeguimientoSyllabus;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de GET /seguimiento-syllabus/resultado-asignatura
 * (resultado del indicador I2 para una asignatura), contra la BD de prueba
 * sembrada por los seeders de la Fase 2.
 *
 * Migrado a Slim en la Fase 3 (ver SeguimientoSyllabusController) -- antes
 * le pegaba directo a api/seguimiento_syllabus/resultado_asignatura.php,
 * ahora archivo eliminado. Misma lógica, mismos asserts.
 *
 * Datos de referencia sembrados por AsignaturaSeeder/EvaluacionesSeeder:
 *   - id_asignatura 1..5, todas del PAO 1 (id_periodoacademico=1, cohorte
 *     B2025, id_cohorte=1).
 *   - id_evaluacion 1, ligada a id_cohorte=1 (misma cohorte de esas
 *     asignaturas).
 * Ninguna trae evidencia_asignatura sembrada por defecto -- cada test que
 * necesita evidencia la inserta ella misma, para no depender de qué haya
 * hecho otro test en la misma clase.
 */
final class ResultadoAsignaturaTest extends IntegrationTestCase
{
    private const ID_EVALUACION = 1;

    public function testSinParametrosDevuelve400(): void
    {
        $respuesta = $this->peticion('GET', '/seguimiento-syllabus/resultado-asignatura');

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testAsignaturaInexistenteDevuelve404(): void
    {
        $respuesta = $this->peticion(
            'GET',
            '/seguimiento-syllabus/resultado-asignatura?id_asignatura=99999&id_evaluacion=' . self::ID_EVALUACION
        );

        $this->assertSame(404, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testAsignaturaSinEvidenciasDevuelveEstadoParcialYEfNulos(): void
    {
        // id_asignatura=2 (Cultura tecnológica y digital) se deja sin ninguna
        // evidencia_asignatura sembrada a propósito.
        $respuesta = $this->peticion(
            'GET',
            '/seguimiento-syllabus/resultado-asignatura?id_asignatura=2&id_evaluacion=' . self::ID_EVALUACION
        );

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);

        $datos = $respuesta['json']['datos'];
        $this->assertSame('parcial', $datos['estado_general']);
        $this->assertFalse($datos['evidencias_info']['syllabus']['subida']);
        $this->assertNull($datos['ef1']);
        $this->assertNull($datos['ef2']);
        $this->assertSame(0, $datos['total_evidencias']);
    }

    public function testAsignaturaConSyllabusSubidoReflejaEvidenciaYEf1(): void
    {
        $idAsignatura = 4; // Fundamentos de Programación y Algoritmos

        $conexion = self::conexionBd();
        $stmt = $conexion->prepare(
            "INSERT INTO evidencia_asignatura (id_asignatura, tipo, nombre_archivo, url_archivo, subido_por, vigente)
             VALUES (?, 'syllabus', 'syllabus_prueba.pdf', 'https://drive.example/fake', '1', 1)"
        );
        $stmt->bind_param('i', $idAsignatura);
        $stmt->execute();

        $respuesta = $this->peticion(
            'GET',
            "/seguimiento-syllabus/resultado-asignatura?id_asignatura={$idAsignatura}&id_evaluacion=" . self::ID_EVALUACION
        );

        $this->assertSame(200, $respuesta['status']);
        $datos = $respuesta['json']['datos'];

        $this->assertTrue($datos['evidencias_info']['syllabus']['subida']);
        $this->assertNotNull($datos['ef1']);
        // Solo syllabus subido (de los 5 componentes de EF1: encuesta,
        // syllabus, malla, reporte_control_siu, reporte_avances_siu) ->
        // EF1 = 1/5 = 20.0 (ver fórmula en _calculo.php::calcularResultadoAsignatura).
        $this->assertEqualsWithDelta(20.0, $datos['ef1'], 0.01);
        $this->assertSame('parcial', $datos['estado_general']);
        $this->assertSame(1, $datos['total_evidencias']);
    }

    public function testResultadoAsignaturaGuardaSnapshotEnResultadosSeguimiento(): void
    {
        $idAsignatura = 5; // Desarrollo de Interfaces de Usuario y UX

        $respuesta = $this->peticion(
            'GET',
            "/seguimiento-syllabus/resultado-asignatura?id_asignatura={$idAsignatura}&id_evaluacion=" . self::ID_EVALUACION
        );
        $this->assertSame(200, $respuesta['status']);

        // El propio endpoint llama a guardarSnapshotSeguimiento() -- se
        // verifica que efectivamente haya quedado un snapshot para esta
        // asignatura+evaluación, sin asumir el nombre exacto de todas las
        // columnas (eso ya lo cubre el test de esquema de la Fase 2).
        $conexion = self::conexionBd();
        $idEvaluacion = self::ID_EVALUACION;
        $stmt = $conexion->prepare(
            'SELECT COUNT(*) AS total FROM seguimiento_syllabus WHERE id_asignatura = ? AND id_evaluacion = ?'
        );
        $stmt->bind_param('ii', $idAsignatura, $idEvaluacion);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();

        $this->assertGreaterThanOrEqual(1, (int) $fila['total']);
    }
}
