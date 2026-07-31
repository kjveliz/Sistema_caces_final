<?php

declare(strict_types=1);

namespace Tests\Integration\Administracion;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de GET /administracion/usuarios/listar -- Parte 14
 * del plan de migración slim-legacy (ver plan_migracion_slim_legacy_v3.txt
 * §3 Grupo E, primera Parte del subgrupo de usuarios), reemplaza a
 * api/administracion/usuarios/listar.php.
 *
 * A diferencia de cohortesListar() (Parte 11, solo exige sesión activa),
 * este endpoint SÍ valida además el rol administrador -- ver
 * AdministracionController::usuariosListar().
 *
 * Datos de referencia sembrados por UsuariosSeeder (los 3 roles del
 * sistema, mismo apellido "Demo" para los 3 -> el ORDER BY apellidos,
 * nombres queda determinado por nombres en orden alfabético):
 *   - id_usuario=1, Administrador Demo, administrador@demo.local, rol=administrador
 *   - id_usuario=3, Coordinador Demo, coordinador@demo.local, rol=coordinador
 *   - id_usuario=2, Evaluador Demo, evaluador@demo.local, rol=evaluador
 *   Orden esperado por nombres (apellidos empatados en "Demo"):
 *   Administrador, Coordinador, Evaluador.
 */
final class UsuariosListarTest extends IntegrationTestCase
{
    public function testSinSesionActivaDevuelve401(): void
    {
        $respuesta = $this->peticion('GET', '/administracion/usuarios/listar');

        $this->assertSame(401, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testConRolNoAdministradorDevuelve403(): void
    {
        $login = $this->loguearComo('coordinador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('GET', '/administracion/usuarios/listar');

        $this->assertSame(403, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('No tiene permisos para consultar usuarios.', $respuesta['json']['mensaje']);
    }

    public function testConRolEvaluadorDevuelve403(): void
    {
        // El original valida el rol exacto 'administrador', no solo
        // "distinto de evaluador" -- se cubre con un segundo rol no-admin
        // aparte de coordinador, para no asumir que el chequeo sea una
        // lista blanca de un solo rol excluido.
        $login = $this->loguearComo('evaluador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('GET', '/administracion/usuarios/listar');

        $this->assertSame(403, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testConRolAdministradorDevuelveLosUsuariosOrdenadosPorApellidoYNombre(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('GET', '/administracion/usuarios/listar');

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);

        $datos = $respuesta['json']['datos'];
        $this->assertCount(3, $datos);

        // Orden exacto esperado (apellidos empatados en "Demo" para los 3,
        // desempatado por nombres ASC): Administrador, Coordinador, Evaluador.
        $this->assertSame('Administrador', $datos[0]['nombres']);
        $this->assertSame(1, $datos[0]['id_usuario']);
        $this->assertSame('Coordinador', $datos[1]['nombres']);
        $this->assertSame(3, $datos[1]['id_usuario']);
        $this->assertSame('Evaluador', $datos[2]['nombres']);
        $this->assertSame(2, $datos[2]['id_usuario']);

        foreach ($datos as $fila) {
            $this->assertSame('Demo', $fila['apellidos']);
            $this->assertArrayHasKey('correo', $fila);
            $this->assertArrayHasKey('rol', $fila);
            $this->assertArrayHasKey('activo', $fila);
            // La contraseña (hash) nunca debe exponerse en el listado.
            $this->assertArrayNotHasKey('contrasena', $fila);
        }

        $this->assertSame('administrador@demo.local', $datos[0]['correo']);
        $this->assertSame('administrador', $datos[0]['rol']);
        $this->assertSame(1, $datos[0]['activo']);
    }

    public function testConMetodoPostDevuelve405(): void
    {
        $respuesta = $this->peticion('POST', '/administracion/usuarios/listar');

        $this->assertSame(405, $respuesta['status']);
    }
}
