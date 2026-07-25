<?php

declare(strict_types=1);

namespace Tests\Integration\SeguimientoSyllabus;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de GET /seguimiento-syllabus/periodos (indicador I2),
 * parte de los tests de integración faltantes de I2 (ver MEMORIA v70,
 * §47.8 punto 8) -- los 6 endpoints de SeguimientoSyllabusController que
 * quedaron sin cubrir cuando se migró I2 a Slim (v67), a diferencia de
 * resultado-asignatura y evidencia-subir, que ya tenían tests desde antes.
 *
 * Datos de referencia sembrados por CohortesSeeder/PeriodoAcademicoSeeder:
 *   - id_cohorte=1 (B2025) tiene 3 períodos (PAO 1/2/3, orden 1/2/3).
 *   - id_cohorte=2 (A2026) no tiene ningún período sembrado.
 */
final class PeriodosTest extends IntegrationTestCase
{
    public function testSinParametroDevuelve400(): void
    {
        $respuesta = $this->peticion('GET', '/seguimiento-syllabus/periodos');

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testCohorteConPeriodosDevuelveLosTresOrdenadosPorOrden(): void
    {
        $respuesta = $this->peticion('GET', '/seguimiento-syllabus/periodos?id_cohorte=1');

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);

        $datos = $respuesta['json']['datos'];
        $this->assertCount(3, $datos);
        $this->assertSame(['PAO 1', 'PAO 2', 'PAO 3'], array_column($datos, 'nombre'));
        $this->assertSame([1, 2, 3], array_map('intval', array_column($datos, 'orden')));
    }

    public function testCohorteSinPeriodosDevuelveArrayVacio(): void
    {
        $respuesta = $this->peticion('GET', '/seguimiento-syllabus/periodos?id_cohorte=2');

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame([], $respuesta['json']['datos']);
    }
}
