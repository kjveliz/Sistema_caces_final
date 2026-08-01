<?php

declare(strict_types=1);

namespace Tests\Integration\GoogleDrive;

use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de GET /google-drive/conectar (Parte 22 del plan de
 * migración de PHP suelto a Slim -- ver plan_migracion_slim_legacy_v3.txt
 * §3; reemplaza a api/google_drive/conectar.php).
 *
 * A diferencia del resto de los endpoints del Grupo D, este no devuelve
 * JSON: la respuesta de éxito es un 302 con header Location hacia la
 * pantalla de consentimiento de Google (plan §2 punto 3). El seam de
 * testing de GoogleDriveController::conectar() (activo porque
 * IntegrationTestCase levanta el servidor con APP_ENV=testing) evita
 * necesitar credenciales.json reales -- ver el mismo criterio ya usado por
 * GoogleDriveService::{subirArchivo,descargarContenidoDrive}().
 */
final class ConectarTest extends IntegrationTestCase
{
    private const RUTA = '/google-drive/conectar';

    public function testDevuelve302ConLocationHaciaGoogleViaElSeamDeTesting(): void
    {
        $respuesta = $this->peticion('GET', self::RUTA);

        $this->assertSame(302, $respuesta['status']);
        $this->assertSame(
            'https://accounts.google.com/o/oauth2/fake-test-double',
            $respuesta['headers']['location'] ?? null,
        );
    }

    public function testNoRequiereSesion(): void
    {
        // Sin loguearComo() previo -- el original tampoco validaba sesión
        // (se abre manualmente desde el navegador del administrador, ver
        // docblock de GoogleDriveController::conectar()). Si tuviera
        // SessionAuthMiddleware acá devolvería 401 en vez de 302.
        $respuesta = $this->peticion('GET', self::RUTA);

        $this->assertSame(302, $respuesta['status']);
    }

    public function testConMetodoPostDevuelve405(): void
    {
        $respuesta = $this->peticion('POST', self::RUTA);

        $this->assertSame(405, $respuesta['status']);
    }
}
