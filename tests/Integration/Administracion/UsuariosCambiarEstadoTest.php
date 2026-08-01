<?php

declare(strict_types=1);

namespace Tests\Integration\Administracion;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de POST /administracion/usuarios/cambiar-estado --
 * Parte 16 del plan de migración slim-legacy (ver
 * plan_migracion_slim_legacy_v3.txt §3 Grupo E), reemplaza a
 * api/administracion/usuarios/cambiar_estado.php. Última Parte del
 * subgrupo de usuarios y del Grupo E completo.
 *
 * Usuarios de referencia sembrados por UsuariosSeeder: id_usuario=1
 * (administrador@demo.local, rol administrador), id_usuario=2
 * (evaluador@demo.local, rol evaluador), ambos activo=1 al arrancar.
 */
final class UsuariosCambiarEstadoTest extends IntegrationTestCase
{
    public function testSinSesionActivaDevuelve401(): void
    {
        $respuesta = $this->peticion('POST', '/administracion/usuarios/cambiar-estado', [
            'json' => ['id_usuario' => 2, 'activo' => 0],
        ]);

        $this->assertSame(401, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testConRolNoAdministradorDevuelve403(): void
    {
        $login = $this->loguearComo('coordinador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/usuarios/cambiar-estado', [
            'json' => ['id_usuario' => 2, 'activo' => 0],
        ]);

        $this->assertSame(403, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('No tiene permisos para modificar usuarios.', $respuesta['json']['mensaje']);
    }

    public function testDesactivaOtroUsuario(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/usuarios/cambiar-estado', [
            'json' => ['id_usuario' => 2, 'activo' => 0],
        ]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame('Usuario desactivado correctamente.', $respuesta['json']['mensaje']);

        $conexion = self::conexionBd();
        $fila = $conexion->query('SELECT activo FROM usuarios WHERE id_usuario = 2')->fetch_assoc();
        $this->assertSame(0, (int) $fila['activo']);
    }

    public function testReactivaOtroUsuario(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        // Deja a id_usuario=2 desactivado primero, para probar la reactivación.
        $this->peticion('POST', '/administracion/usuarios/cambiar-estado', [
            'json' => ['id_usuario' => 2, 'activo' => 0],
        ]);

        $respuesta = $this->peticion('POST', '/administracion/usuarios/cambiar-estado', [
            'json' => ['id_usuario' => 2, 'activo' => 1],
        ]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame('Usuario activado correctamente.', $respuesta['json']['mensaje']);

        $conexion = self::conexionBd();
        $fila = $conexion->query('SELECT activo FROM usuarios WHERE id_usuario = 2')->fetch_assoc();
        $this->assertSame(1, (int) $fila['activo']);
    }

    /**
     * Regla de negocio del original, preservada tal cual: un administrador
     * no puede desactivar su propia cuenta (evita que se bloquee a sí
     * mismo sin nadie más con acceso). Chequeada antes de tocar la BD.
     */
    public function testNoPuedeDesactivarSuPropiaCuenta(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        // id_usuario=1 es administrador@demo.local, el mismo que logueó.
        $respuesta = $this->peticion('POST', '/administracion/usuarios/cambiar-estado', [
            'json' => ['id_usuario' => 1, 'activo' => 0],
        ]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('No puede desactivar su propia cuenta.', $respuesta['json']['mensaje']);

        // No debe haber tocado la BD.
        $conexion = self::conexionBd();
        $fila = $conexion->query('SELECT activo FROM usuarios WHERE id_usuario = 1')->fetch_assoc();
        $this->assertSame(1, (int) $fila['activo']);
    }

    /**
     * A diferencia del caso anterior, activarse a sí mismo (activo=1) sí
     * está permitido -- la regla del original solo bloquea la
     * autodesactivación, no cualquier cambio sobre la propia cuenta.
     */
    public function testPuedeReactivarseASiMismo(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/usuarios/cambiar-estado', [
            'json' => ['id_usuario' => 1, 'activo' => 1],
        ]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
    }

    public function testConActivoInvalidoDevuelve400(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/usuarios/cambiar-estado', [
            'json' => ['id_usuario' => 2, 'activo' => 2],
        ]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
        $this->assertSame('Los datos recibidos no son válidos.', $respuesta['json']['mensaje']);
    }

    public function testConIdUsuarioInvalidoDevuelve400(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/usuarios/cambiar-estado', [
            'json' => ['id_usuario' => 0, 'activo' => 1],
        ]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    /**
     * Documenta el mismo comportamiento del original (no corregido, mismo
     * criterio que UsuariosRepository::cambiarEstado() /
     * SeguimientoSyllabusRepository::cambiarEstadoEvaluacion()): un
     * id_usuario inexistente no da 404 -- el UPDATE afecta 0 filas y mysqli
     * lo sigue considerando éxito.
     */
    public function testConIdUsuarioInexistenteIgualDevuelveOk(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('POST', '/administracion/usuarios/cambiar-estado', [
            'json' => ['id_usuario' => 9999, 'activo' => 0],
        ]);

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
    }

    public function testConMetodoGetDevuelve405(): void
    {
        $respuesta = $this->peticion('GET', '/administracion/usuarios/cambiar-estado');

        $this->assertSame(405, $respuesta['status']);
    }
}
