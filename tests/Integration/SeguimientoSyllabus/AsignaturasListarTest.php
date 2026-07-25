<?php

declare(strict_types=1);

namespace Tests\Integration\SeguimientoSyllabus;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de GET /seguimiento-syllabus/asignaturas (indicador
 * I2) -- ver MEMORIA v70, §47.8 punto 8 (tests de integración faltantes del
 * resto de SeguimientoSyllabusController).
 *
 * Datos de referencia sembrados por AsignaturaSeeder: id_asignatura 1..5,
 * todas con id_periodoacademico=1 (PAO 1 de la cohorte B2025). Ningún otro
 * período (2 y 3, también de la misma cohorte) tiene asignaturas sembradas.
 */
final class AsignaturasListarTest extends IntegrationTestCase
{
    public function testSinParametroDevuelve400(): void
    {
        $respuesta = $this->peticion('GET', '/seguimiento-syllabus/asignaturas');

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testPeriodoConAsignaturasDevuelveLasCincoOrdenadasPorNombre(): void
    {
        $respuesta = $this->peticion('GET', '/seguimiento-syllabus/asignaturas?id_periodo=1');

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);

        $datos = $respuesta['json']['datos'];
        $this->assertCount(5, $datos);

        // Orden alfabético esperado (ORDER BY nombre) de los 5 nombres
        // reales sembrados por AsignaturaSeeder.
        $this->assertSame(
            [
                'Comunicación efectiva y trabajo en equipo',
                'Cultura tecnológica y digital',
                'Desarrollo de Interfaces de Usuario y Experiencia de Usuario (UI/UX)',
                'Fundamentos de Programación y Algoritmos',
                'Humanismo y Persona',
            ],
            array_column($datos, 'nombre'),
        );
    }

    public function testPeriodoSinAsignaturasDevuelveArrayVacio(): void
    {
        $respuesta = $this->peticion('GET', '/seguimiento-syllabus/asignaturas?id_periodo=2');

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame([], $respuesta['json']['datos']);
    }
}
