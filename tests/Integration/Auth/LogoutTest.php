<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de POST /auth/logout (Parte 2 del plan de migración
 * de PHP suelto a Slim -- ver plan_migracion_slim_legacy_v3.txt §3;
 * reemplaza a api/auth/Logout.php). Antes de la migración original a
 * endpoint, el "logout" del frontend solo borraba el usuario en memoria de
 * React; la sesión de PHP seguía viva del lado del servidor (ver
 * AuthContext.tsx). Este endpoint la destruye de verdad.
 */
final class LogoutTest extends IntegrationTestCase
{
    public function testLogoutDestruyeLaSesionYMeYaNoReconoceAlUsuario(): void
    {
        $login = $this->loguearComo('administrador@demo.local');
        $this->assertSame(200, $login['status']);

        $antesDeLogout = $this->peticion('GET', '/auth/me');
        $this->assertSame(200, $antesDeLogout['status']);

        $logout = $this->peticion('POST', '/auth/logout');
        $this->assertSame(200, $logout['status']);
        $this->assertTrue($logout['json']['ok']);

        $despuesDeLogout = $this->peticion('GET', '/auth/me');
        $this->assertSame(401, $despuesDeLogout['status']);
    }

    public function testLogoutSinSesionActivaTambienDevuelveOk(): void
    {
        // Llamar a logout sin haber iniciado sesión no debería ser un
        // error: el resultado que le importa al que llama ("ya no hay
        // sesión") se cumple igual.
        $respuesta = $this->peticion('POST', '/auth/logout');

        $this->assertSame(200, $respuesta['status']);
        $this->assertTrue($respuesta['json']['ok']);
    }

    public function testLogoutConMetodoGetDevuelve405(): void
    {
        $respuesta = $this->peticion('GET', '/auth/logout');

        $this->assertSame(405, $respuesta['status']);
    }
}
