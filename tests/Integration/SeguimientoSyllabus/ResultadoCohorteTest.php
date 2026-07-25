<?php

declare(strict_types=1);

namespace Tests\Integration\SeguimientoSyllabus;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de GET /seguimiento-syllabus/resultado-cohorte
 * (indicador I2) -- ver MEMORIA v70, §47.8 punto 8.
 *
 * Datos de referencia: id_cohorte=1 (B2025) tiene 5 asignaturas, todas en
 * id_periodoacademico=1 (ver AsignaturaSeeder/PeriodoAcademicoSeeder);
 * id_periodoacademico=2 es de la misma cohorte pero no tiene ninguna
 * asignatura sembrada. id_evaluacion=1 está ligada a id_cohorte=1
 * (EvaluacionesSeeder). Ninguna asignatura trae evidencia sembrada por
 * defecto -- igual que en ResultadoAsignaturaTest, no se depende de eso acá.
 */
final class ResultadoCohorteTest extends IntegrationTestCase
{
    private const ID_EVALUACION = 1;

    public function testSinParametrosDevuelve400(): void
    {
        $respuesta = $this->peticion('GET', '/seguimiento-syllabus/resultado-cohorte');

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testCohorteSinFiltroDePeriodoDevuelveDetallePorCadaAsignatura(): void
    {
        $respuesta = $this->peticion(
            'GET',
            '/seguimiento-syllabus/resultado-cohorte?id_cohorte=1&id_evaluacion=' . self::ID_EVALUACION
        );

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);

        $datos = $respuesta['json']['datos'];
        $this->assertCount(5, $datos['detalle_asignaturas']);
        // Sin ninguna evidencia sembrada, ninguna asignatura está completa.
        $this->assertSame('parcial', $datos['estado_general']);
    }

    public function testFiltradoPorUnPeriodoSinAsignaturasDevuelveEstadoSinDatos(): void
    {
        $respuesta = $this->peticion(
            'GET',
            '/seguimiento-syllabus/resultado-cohorte?id_cohorte=1&id_evaluacion='
                . self::ID_EVALUACION . '&id_periodo=2'
        );

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);

        $datos = $respuesta['json']['datos'];
        $this->assertSame([], $datos['detalle_asignaturas']);
        $this->assertSame('sin_datos', $datos['estado_general']);
        $this->assertNull($datos['valoracion_general']);
    }

    public function testResultadoCohorteGuardaSnapshotParaCadaAsignaturaDelDetalle(): void
    {
        $respuesta = $this->peticion(
            'GET',
            '/seguimiento-syllabus/resultado-cohorte?id_cohorte=1&id_evaluacion=' . self::ID_EVALUACION
        );
        $this->assertSame(200, $respuesta['status']);

        // El propio endpoint guarda un snapshot por cada asignatura del
        // detalle -- se verifica que las 5 de la cohorte queden registradas
        // para esta evaluación, sin asumir el nombre exacto de las columnas.
        $conexion = self::conexionBd();
        $idEvaluacion = self::ID_EVALUACION;
        $stmt = $conexion->prepare(
            'SELECT COUNT(DISTINCT id_asignatura) AS total FROM seguimiento_syllabus WHERE id_evaluacion = ?'
        );
        $stmt->bind_param('i', $idEvaluacion);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();

        $this->assertGreaterThanOrEqual(5, (int) $fila['total']);
    }
}
