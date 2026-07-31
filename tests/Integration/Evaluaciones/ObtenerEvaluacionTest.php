<?php

declare(strict_types=1);

namespace Tests\Integration\Evaluaciones;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de GET /evaluaciones/obtener-evaluacion (Parte 10
 * del plan de migración de PHP suelto a Slim -- ver
 * plan_migracion_slim_legacy_v3.txt §3; reemplaza a
 * api/evaluaciones/obtener_evaluacion.php), segunda y última Parte de
 * código del Grupo C (misceláneos).
 *
 * Usa los datos reales de EvaluacionesSeeder/CarrerasSeeder/CohortesSeeder:
 * carrera DESSOF (id_carrera=1), cohorte B2025 (id_cohorte=1) ->
 * evaluación id_evaluacion=1; cohorte A2026 (id_cohorte=2) ->
 * id_evaluacion=2. No hay dos evaluaciones para la misma carrera+cohorte en
 * el seeder, así que para el caso "ORDER BY id_evaluacion DESC" (cuando hay
 * más de una) se inserta una fila extra a mano.
 */
final class ObtenerEvaluacionTest extends IntegrationTestCase
{
    private const RUTA = '/evaluaciones/obtener-evaluacion';

    private ?int $idEvaluacionExtraInsertada = null;

    protected function tearDown(): void
    {
        if ($this->idEvaluacionExtraInsertada !== null) {
            self::conexionBd()->query(
                'DELETE FROM evaluaciones WHERE id_evaluacion = ' . $this->idEvaluacionExtraInsertada
            );
            $this->idEvaluacionExtraInsertada = null;
        }
    }

    public function testSinParametrosDevuelve400(): void
    {
        $respuesta = $this->peticion('GET', self::RUTA);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Debe enviar el código de la carrera y la cohorte.', $respuesta['json']['mensaje']);
    }

    public function testSoloCodigoCarreraDevuelve400(): void
    {
        $respuesta = $this->peticion('GET', self::RUTA . '?codigo_carrera=DESSOF');

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testCarreraOCohorteInexistenteDevuelve404(): void
    {
        $respuesta = $this->peticion('GET', self::RUTA . '?codigo_carrera=NOEXISTE&cohorte=Z9999');

        $this->assertSame(404, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame(
            'No existe una evaluación para la carrera y cohorte seleccionadas.',
            $respuesta['json']['mensaje']
        );
    }

    public function testCasoFelizDevuelveLaEvaluacion(): void
    {
        $respuesta = $this->peticion('GET', self::RUTA . '?codigo_carrera=DESSOF&cohorte=B2025');

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertArrayNotHasKey('mensaje', $respuesta['json']);

        $datos = $respuesta['json']['datos'];
        $this->assertSame(1, $datos['id_evaluacion']);
        $this->assertSame('Evaluación Desarrollo de Software - B2025', $datos['nombre_evaluacion']);
        $this->assertSame('Activa', $datos['estado']);
        $this->assertSame(1, $datos['id_carrera']);
        $this->assertSame('DESSOF', $datos['codigo_carrera']);
        $this->assertSame(1, $datos['id_cohorte']);
        $this->assertSame('B2025', $datos['nombre_cohorte']);
    }

    public function testEsInsensibleAMayusculasYEspaciosEnLaCohorte(): void
    {
        // El original normaliza codigo_carrera/cohorte a mayúsculas y quita
        // espacios (strtoupper + preg_replace) antes de la consulta.
        $respuesta = $this->peticion('GET', self::RUTA . '?codigo_carrera=dessof&cohorte=' . rawurlencode('b 20 25'));

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame(1, $respuesta['json']['datos']['id_evaluacion']);
    }

    public function testDevuelveLaEvaluacionMasRecienteCuandoHayVarias(): void
    {
        $conexion = self::conexionBd();
        $conexion->query(
            "INSERT INTO evaluaciones
                (nombre_evaluacion, id_cohorte, fecha_inicio, fecha_fin, estado, id_usuario, id_carrera)
             VALUES
                ('Evaluación extra de prueba', 1, NULL, NULL, 'Activa', 1, 1)"
        );
        $this->idEvaluacionExtraInsertada = $conexion->insert_id;

        $respuesta = $this->peticion('GET', self::RUTA . '?codigo_carrera=DESSOF&cohorte=B2025');

        $this->assertSame(200, $respuesta['status']);
        // ORDER BY id_evaluacion DESC LIMIT 1 -- debe traer la recién
        // insertada (id mayor), no la original id_evaluacion=1 del seeder.
        $this->assertSame($this->idEvaluacionExtraInsertada, $respuesta['json']['datos']['id_evaluacion']);
        $this->assertSame('Evaluación extra de prueba', $respuesta['json']['datos']['nombre_evaluacion']);
    }

    public function testConMetodoPostDevuelve405(): void
    {
        $respuesta = $this->peticion('POST', self::RUTA);

        $this->assertSame(405, $respuesta['status']);
    }
}
