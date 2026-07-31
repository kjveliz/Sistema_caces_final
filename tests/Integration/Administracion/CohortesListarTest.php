<?php

declare(strict_types=1);

namespace Tests\Integration\Administracion;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de GET /administracion/cohortes/listar -- Parte 11
 * del plan de migración slim-legacy (ver plan_migracion_slim_legacy_v3.txt
 * §3 Grupo E), reemplaza a api/administracion/cohortes/listar.php.
 *
 * Datos de referencia sembrados por CohortesSeeder/EvaluacionesSeeder/
 * PeriodoAcademicoSeeder:
 *   - id_cohorte=1 (B2025, id_carrera=1) tiene evaluación id=1 y 3 períodos
 *     (PAO 1/2/3) -> total_periodos=3.
 *   - id_cohorte=2 (A2026, id_carrera=1) tiene evaluación id=2 y 0 períodos
 *     sembrados -> total_periodos=0.
 */
final class CohortesListarTest extends IntegrationTestCase
{
    public function testSinSesionActivaDevuelve401(): void
    {
        $respuesta = $this->peticion('GET', '/administracion/cohortes/listar');

        $this->assertSame(401, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testConSesionActivaDevuelveLasCohortesConEvaluacionYTotalPeriodos(): void
    {
        $login = $this->loguearComo('coordinador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('GET', '/administracion/cohortes/listar');

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);

        $datos = $respuesta['json']['datos'];
        $this->assertCount(2, $datos);

        $porCohorte = [];
        foreach ($datos as $fila) {
            $porCohorte[$fila['id_cohorte']] = $fila;
        }

        $this->assertSame('B2025', $porCohorte[1]['nombre_cohorte']);
        $this->assertSame(1, $porCohorte[1]['id_carrera']);
        $this->assertSame(1, $porCohorte[1]['id_evaluacion']);
        $this->assertSame(3, $porCohorte[1]['total_periodos']);

        $this->assertSame('A2026', $porCohorte[2]['nombre_cohorte']);
        $this->assertSame(2, $porCohorte[2]['id_evaluacion']);
        $this->assertSame(0, $porCohorte[2]['total_periodos']);
    }

    public function testConMetodoPostDevuelve405(): void
    {
        $respuesta = $this->peticion('POST', '/administracion/cohortes/listar');

        $this->assertSame(405, $respuesta['status']);
    }
}
