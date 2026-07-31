<?php

declare(strict_types=1);

namespace Tests\Integration\Administracion;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de POST /administracion/cohortes/cambiar-estado --
 * Parte 13 del plan de migración slim-legacy (ver
 * plan_migracion_slim_legacy_v3.txt §3 Grupo E), reemplaza a
 * api/administracion/cohortes/cambiar_estado.php. Última Parte del
 * subgrupo de cohortes del Grupo E.
 *
 * Evaluación de referencia sembrada por EvaluacionesSeeder: id_evaluacion=1
 * (asociada a la cohorte B2025, id_carrera=1), estado inicial 'Activa'.
 */
final class CohortesCambiarEstadoTest extends IntegrationTestCase
{
    public function testSinSesionActivaDevuelve401(): void
    {
        $respuesta = $this->peticion('POST', '/administracion/cohortes/cambiar-estado', [
            'json' => ['id_evaluacion' => 1, 'estado' => 'Cerrada'],
        ]);

        $this->assertSame(401, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testConRolNoAdministradorDevuelve403(): void
    {
        $login = $this->loguearComo('coordinador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/cohortes/cambiar-estado', [
            'json' => ['id_evaluacion' => 1, 'estado' => 'Cerrada'],
        ]);

        $this->assertSame(403, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('No tiene permisos para cambiar el estado.', $respuesta['json']['mensaje']);
    }

    public function testConDatosValidosActualizaElEstado(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/cohortes/cambiar-estado', [
            'json' => ['id_evaluacion' => 1, 'estado' => 'Cerrada'],
        ]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame('Estado actualizado correctamente.', $respuesta['json']['mensaje']);

        // Verificación directa contra la BD.
        $conexion = self::conexionBd();
        $fila = $conexion->query('SELECT estado FROM evaluaciones WHERE id_evaluacion = 1')->fetch_assoc();
        $this->assertSame('Cerrada', $fila['estado']);
    }

    public function testConEstadoInvalidoDevuelve400(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/cohortes/cambiar-estado', [
            'json' => ['id_evaluacion' => 1, 'estado' => 'Finalizada'],
        ]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Los datos recibidos no son válidos.', $respuesta['json']['mensaje']);
    }

    public function testConIdEvaluacionInvalidoDevuelve400(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/cohortes/cambiar-estado', [
            'json' => ['id_evaluacion' => 0, 'estado' => 'Activa'],
        ]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    /**
     * Documenta el mismo comportamiento del original (no corregido, ver
     * SeguimientoSyllabusRepository::cambiarEstadoEvaluacion()): un
     * id_evaluacion inexistente no da 404 -- el UPDATE afecta 0 filas y
     * mysqli lo sigue considerando éxito.
     */
    public function testConIdEvaluacionInexistenteIgualDevuelveOk(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/cohortes/cambiar-estado', [
            'json' => ['id_evaluacion' => 9999, 'estado' => 'Activa'],
        ]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
    }

    public function testConMetodoGetDevuelve405(): void
    {
        $respuesta = $this->peticion('GET', '/administracion/cohortes/cambiar-estado');

        $this->assertSame(405, $respuesta['status']);
    }
}
