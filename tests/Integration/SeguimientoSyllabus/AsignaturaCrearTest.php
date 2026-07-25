<?php

declare(strict_types=1);

namespace Tests\Integration\SeguimientoSyllabus;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de POST /seguimiento-syllabus/asignaturas (indicador
 * I2) -- ver MEMORIA v70, §47.8 punto 8. Cubre el comportamiento de
 * "get_or_create" descrito en el propio controlador: si ya existe una
 * asignatura con el mismo nombre en el mismo período, devuelve su id en vez
 * de duplicarla.
 *
 * Datos de referencia: AsignaturaSeeder siembra 'Humanismo y Persona' con
 * id_asignatura=3 en id_periodoacademico=1. El período 2 (misma cohorte,
 * PAO 2) no tiene ninguna asignatura sembrada -- se usa para crear una
 * nueva sin chocar con datos existentes.
 */
final class AsignaturaCrearTest extends IntegrationTestCase
{
    private const RUTA = '/seguimiento-syllabus/asignaturas';

    public function testSinParametrosDevuelve400(): void
    {
        $respuesta = $this->peticion('POST', self::RUTA, ['json' => []]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testCreaAsignaturaNuevaEnPeriodoSinAsignaturas(): void
    {
        $respuesta = $this->peticion('POST', self::RUTA, [
            'json' => ['id_periodo' => 2, 'nombre' => 'Asignatura de prueba de integración', 'docente' => 'Prof. Prueba'],
        ]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame('Asignatura creada.', $respuesta['json']['mensaje']);

        $idAsignatura = $respuesta['json']['datos']['id_asignatura'];
        $this->assertIsInt($idAsignatura);

        $conexion = self::conexionBd();
        $stmt = $conexion->prepare(
            'SELECT nombre, docente, id_periodoacademico FROM asignatura WHERE id_asignatura = ?'
        );
        $stmt->bind_param('i', $idAsignatura);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();

        $this->assertNotNull($fila);
        $this->assertSame('Asignatura de prueba de integración', $fila['nombre']);
        $this->assertSame('Prof. Prueba', $fila['docente']);
        $this->assertSame(2, (int) $fila['id_periodoacademico']);
    }

    public function testNombreYaExistenteEnElMismoPeriodoDevuelveLaAsignaturaExistente(): void
    {
        // id_asignatura=3 ('Humanismo y Persona') ya existe en id_periodo=1
        // (ver AsignaturaSeeder) -- no debe crear una fila nueva.
        $respuesta = $this->peticion('POST', self::RUTA, [
            'json' => ['id_periodo' => 1, 'nombre' => 'Humanismo y Persona'],
        ]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame('La asignatura ya existía.', $respuesta['json']['mensaje']);
        $this->assertSame(3, $respuesta['json']['datos']['id_asignatura']);

        $conexion = self::conexionBd();
        $stmt = $conexion->prepare(
            "SELECT COUNT(*) AS total FROM asignatura WHERE id_periodoacademico = 1 AND nombre = 'Humanismo y Persona'"
        );
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();

        $this->assertSame(1, (int) $fila['total']);
    }
}
