<?php

declare(strict_types=1);

namespace Tests\Integration\Administracion;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de POST /administracion/cohortes/crear -- Parte 12
 * del plan de migración slim-legacy (ver plan_migracion_slim_legacy_v3.txt
 * §3 Grupo E), reemplaza a api/administracion/cohortes/crear.php.
 *
 * Carrera de referencia sembrada por CarrerasSeeder: id_carrera=1
 * (DESSOF / Desarrollo de Software).
 */
final class CohortesCrearTest extends IntegrationTestCase
{
    public function testSinSesionActivaDevuelve401(): void
    {
        $respuesta = $this->peticion('POST', '/administracion/cohortes/crear', [
            'json' => [
                'id_carrera' => 1,
                'nombre_cohorte' => 'C2027',
                'fecha_inicio' => '2027-01-01',
                'fecha_fin' => '2028-06-30',
                'estado' => 'Pendiente',
            ],
        ]);

        $this->assertSame(401, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testConRolNoAdministradorDevuelve403(): void
    {
        $login = $this->loguearComo('coordinador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/cohortes/crear', [
            'json' => [
                'id_carrera' => 1,
                'nombre_cohorte' => 'C2027',
                'fecha_inicio' => '2027-01-01',
                'fecha_fin' => '2028-06-30',
                'estado' => 'Pendiente',
            ],
        ]);

        $this->assertSame(403, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Solo un administrador puede crear cohortes.', $respuesta['json']['mensaje']);
    }

    public function testConDatosValidosCreaLaCohorteYSuEvaluacion(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/cohortes/crear', [
            'json' => [
                'id_carrera' => 1,
                'nombre_cohorte' => 'C 2027!',
                'fecha_inicio' => '2027-01-01',
                'fecha_fin' => '2028-06-30',
                'estado' => 'Pendiente',
            ],
        ]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);

        $datos = $respuesta['json']['datos'];
        // El original sanitiza nombre_cohorte quitando todo lo que no sea
        // A-Z0-9 -- "C 2027!" queda "C2027" (espacio y "!" fuera).
        $this->assertSame('C2027', $datos['nombre_cohorte']);
        $this->assertSame(1, $datos['id_carrera']);
        $this->assertSame('Desarrollo de Software', $datos['carrera']);
        $this->assertSame('DESSOF', $datos['codigo_carrera']);
        $this->assertSame('Pendiente', $datos['estado']);
        $this->assertSame('Evaluación Desarrollo de Software C2027', $datos['nombre_evaluacion']);
        $this->assertIsInt($datos['id_cohorte']);
        $this->assertIsInt($datos['id_evaluacion']);

        // Verificación directa contra la BD (mismo criterio que otras Partes
        // con efectos secundarios en 2 tablas).
        $conexion = self::conexionBd();
        $fila = $conexion->query(
            'SELECT nombre_cohorte, id_carrera FROM cohortes WHERE id_cohorte = ' . (int) $datos['id_cohorte'],
        )->fetch_assoc();
        $this->assertSame('C2027', $fila['nombre_cohorte']);

        $filaEvaluacion = $conexion->query(
            'SELECT estado, id_carrera FROM evaluaciones WHERE id_evaluacion = ' . (int) $datos['id_evaluacion'],
        )->fetch_assoc();
        $this->assertSame('Pendiente', $filaEvaluacion['estado']);
    }

    /**
     * Documenta un quirk real del sanitizado de `nombre_cohorte`, preservado
     * a propósito (mismo criterio del plan §4 -- no se corrige lógica de
     * negocio en esta migración, se documenta aparte): el original aplica
     * `preg_replace("/[^A-Z0-9]/", "", ...)` ANTES de `strtoupper()`, no
     * después -- el patrón es sensible a mayúsculas, así que cualquier
     * letra minúscula del input se descarta en vez de preservarse y
     * mayuscularse. "cohorte-2027" (todo minúscula) queda solo "2027".
     */
    public function testNombreCohorteConMinusculasLasDescarta(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/cohortes/crear', [
            'json' => [
                'id_carrera' => 1,
                'nombre_cohorte' => 'cohorte-2027',
                'fecha_inicio' => '2027-01-01',
                'fecha_fin' => '2028-06-30',
                'estado' => 'Pendiente',
            ],
        ]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertSame('2027', $respuesta['json']['datos']['nombre_cohorte']);
    }

    public function testConCamposFaltantesDevuelve400(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/cohortes/crear', [
            'json' => ['id_carrera' => 1],
        ]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Complete correctamente todos los campos.', $respuesta['json']['mensaje']);
    }

    public function testConFechaFinAnteriorAFechaInicioDevuelve400(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/cohortes/crear', [
            'json' => [
                'id_carrera' => 1,
                'nombre_cohorte' => 'D2028',
                'fecha_inicio' => '2028-06-30',
                'fecha_fin' => '2027-01-01',
                'estado' => 'Pendiente',
            ],
        ]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame(
            'La fecha final no puede ser anterior a la fecha inicial.',
            $respuesta['json']['mensaje'],
        );
    }

    public function testConCarreraInexistenteDevuelve404(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/cohortes/crear', [
            'json' => [
                'id_carrera' => 9999,
                'nombre_cohorte' => 'E2029',
                'fecha_inicio' => '2029-01-01',
                'fecha_fin' => '2030-06-30',
                'estado' => 'Pendiente',
            ],
        ]);

        $this->assertSame(404, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('La carrera seleccionada no existe.', $respuesta['json']['mensaje']);
    }

    public function testConMetodoGetDevuelve405(): void
    {
        $respuesta = $this->peticion('GET', '/administracion/cohortes/crear');

        $this->assertSame(405, $respuesta['status']);
    }
}
