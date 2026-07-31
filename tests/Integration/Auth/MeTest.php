<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de GET /auth/me (Parte 3 del plan de migración de
 * PHP suelto a Slim -- ver plan_migracion_slim_legacy_v3.txt §3; reemplaza
 * a api/auth/Me.php) -- el endpoint "whoami" que le permite al frontend
 * recuperar la sesión tras un refresh de página (Fase 4 del Plan de
 * Mejora, persistencia de sesión, pendiente desde v73).
 */
final class MeTest extends IntegrationTestCase
{
    public function testMeSinSesionActivaDevuelve401(): void
    {
        $respuesta = $this->peticion('GET', '/auth/me');

        $this->assertSame(401, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testMeConSesionActivaDevuelveElUsuarioLogueado(): void
    {
        $login = $this->loguearComo('coordinador@demo.local');
        $this->assertSame(200, $login['status']);

        $respuesta = $this->peticion('GET', '/auth/me');

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame('coordinador@demo.local', $respuesta['json']['usuario']['correo']);
        $this->assertSame('coordinador', $respuesta['json']['usuario']['rol']);
        $this->assertArrayNotHasKey('contrasena', $respuesta['json']['usuario']);
    }

    public function testMeConUsuarioDesactivadoDespuesDeLoguearseDevuelve401(): void
    {
        $login = $this->loguearComo('evaluador@demo.local');
        $this->assertSame(200, $login['status']);

        // Simula lo mismo que haría un administrador desde
        // api/administracion/usuarios/cambiar_estado.php: desactivar la
        // cuenta mientras la cookie de sesión del navegador sigue viva.
        $conexion = self::conexionBd();
        $conexion->query('UPDATE usuarios SET activo = 0 WHERE correo = "evaluador@demo.local"');

        $respuesta = $this->peticion('GET', '/auth/me');

        $this->assertSame(401, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);

        // Deja la cuenta como estaba, para no afectar otros tests de esta
        // misma clase que reutilizan el seed de usuarios.
        $conexion->query('UPDATE usuarios SET activo = 1 WHERE correo = "evaluador@demo.local"');
    }

    public function testMeConMetodoPostDevuelve405(): void
    {
        $respuesta = $this->peticion('POST', '/auth/me');

        $this->assertSame(405, $respuesta['status']);
    }
}
