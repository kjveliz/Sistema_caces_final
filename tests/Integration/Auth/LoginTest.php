<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de api/auth/Login.php contra la BD de prueba
 * sembrada por los seeders de la Fase 2 (usuarios demo, contraseña
 * "CacesDemo2026!" -- ver db/seeds/UsuariosSeeder.php).
 */
final class LoginTest extends IntegrationTestCase
{
    public function testLoginConCredencialesValidasDevuelveUsuarioYSesion(): void
    {
        $respuesta = $this->loguearComo('evaluador@demo.local');

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
        $this->assertSame('evaluador@demo.local', $respuesta['json']['usuario']['correo']);
        $this->assertSame('evaluador', $respuesta['json']['usuario']['rol']);
        $this->assertArrayNotHasKey('contrasena', $respuesta['json']['usuario']);
    }

    public function testLoginConContrasenaIncorrectaDevuelve401(): void
    {
        $respuesta = $this->loguearComo('evaluador@demo.local', 'contrasena-equivocada');

        $this->assertSame(401, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testLoginConCorreoInexistenteDevuelve401(): void
    {
        $respuesta = $this->loguearComo('no-existe@demo.local', 'CacesDemo2026!');

        $this->assertSame(401, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testLoginSinCorreoNiContrasenaDevuelve400(): void
    {
        $respuesta = $this->peticion('POST', '/api/auth/Login.php', ['json' => []]);

        $this->assertSame(400, $respuesta['status']);
        $this->assertFalse($respuesta['json']['ok']);
    }

    public function testLoginConMetodoGetDevuelve405(): void
    {
        $respuesta = $this->peticion('GET', '/api/auth/Login.php');

        $this->assertSame(405, $respuesta['status']);
    }
}
