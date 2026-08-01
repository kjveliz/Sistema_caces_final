<?php

declare(strict_types=1);

namespace Tests\Integration\GoogleDrive;

use App\Services\GoogleDriveClienteAutorizado;
use Tests\Integration\IntegrationTestCase;

require_once __DIR__ . '/../IntegrationTestCase.php';

/**
 * Tests de integración de GET /google-drive/callback (Parte 23 del plan de
 * migración de PHP suelto a Slim -- ver plan_migracion_slim_legacy_v3.txt
 * §3; reemplaza a api/google_drive/callback.php, y cierra el plan entero,
 * 23/23 Partes).
 *
 * Igual que ConectarTest, la respuesta no es JSON: es HTML (plan §2 punto
 * 3). El seam de testing de GoogleDriveController::callback() es más fino
 * que el de conectar(): solo se activa con el valor especial de `code`
 * `codigo-de-prueba-seam-testing` (no hay credenciales.json de prueba
 * disponibles en el sandbox para ejercitar fetchAccessTokenWithAuthCode()
 * real) -- ver docblock del método para el detalle.
 *
 * Backup/restore de un token.json real preexistente: estos tests escriben
 * sobre la ruta real de GoogleDriveClienteAutorizado::RUTA_TOKEN (mismo
 * archivo que usaría una conexión real del usuario en su XAMPP) -- si
 * hubiera un token.json real ahí antes de correr la clase, se respalda y
 * se restaura al terminar, para no pisar una conexión real del usuario que
 * corra estos tests en su propia máquina.
 */
final class CallbackTest extends IntegrationTestCase
{
    private const RUTA = '/google-drive/callback';

    private static ?string $tokenOriginal = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (file_exists(GoogleDriveClienteAutorizado::RUTA_TOKEN)) {
            self::$tokenOriginal = file_get_contents(GoogleDriveClienteAutorizado::RUTA_TOKEN);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$tokenOriginal !== null) {
            file_put_contents(GoogleDriveClienteAutorizado::RUTA_TOKEN, self::$tokenOriginal);
        } elseif (file_exists(GoogleDriveClienteAutorizado::RUTA_TOKEN)) {
            unlink(GoogleDriveClienteAutorizado::RUTA_TOKEN);
        }

        parent::tearDownAfterClass();
    }

    protected function tearDown(): void
    {
        // Cada test que escribe token.json empieza sin el archivo, para no
        // depender del orden de ejecución entre tests de esta clase.
        if (file_exists(GoogleDriveClienteAutorizado::RUTA_TOKEN)) {
            unlink(GoogleDriveClienteAutorizado::RUTA_TOKEN);
        }
    }

    public function testConParametroErrorDevuelve400SinTocarTokenJson(): void
    {
        $respuesta = $this->peticion('GET', self::RUTA . '?error=access_denied');

        $this->assertSame(400, $respuesta['status']);
        $this->assertStringContainsString('no fue autorizado', $respuesta['body']);
        $this->assertFileDoesNotExist(GoogleDriveClienteAutorizado::RUTA_TOKEN);
    }

    public function testSinCodigoDevuelve400(): void
    {
        $respuesta = $this->peticion('GET', self::RUTA);

        $this->assertSame(400, $respuesta['status']);
        $this->assertStringContainsString('No se recibió el código', $respuesta['body']);
    }

    public function testCodigoVacioDevuelve400(): void
    {
        $respuesta = $this->peticion('GET', self::RUTA . '?code=');

        $this->assertSame(400, $respuesta['status']);
    }

    public function testExitoViaElSeamDeTestingEscribeTokenJson(): void
    {
        $respuesta = $this->peticion('GET', self::RUTA . '?code=codigo-de-prueba-seam-testing');

        $this->assertSame(200, $respuesta['status']);
        $this->assertStringContainsString('Google Drive conectado', $respuesta['body']);

        $this->assertFileExists(GoogleDriveClienteAutorizado::RUTA_TOKEN);
        $token = json_decode((string) file_get_contents(GoogleDriveClienteAutorizado::RUTA_TOKEN), true);
        $this->assertSame('token-de-prueba-seam-testing', $token['access_token'] ?? null);
        $this->assertSame('refresh-de-prueba-seam-testing', $token['refresh_token'] ?? null);
    }

    public function testConservaElRefreshTokenAnteriorSiGoogleNoLoRepite(): void
    {
        // Simula una conexión previa real: token.json ya existe con un
        // refresh_token. La variante "sin-refresh" del seam simula el caso
        // real de Google omitiendo refresh_token (cuenta ya autorizada
        // antes) -- la Parte debe conservar el anterior, mismo
        // comportamiento textual que el legacy.
        file_put_contents(
            GoogleDriveClienteAutorizado::RUTA_TOKEN,
            json_encode(['access_token' => 'token-viejo', 'refresh_token' => 'refresh-que-debe-conservarse']),
        );

        $respuesta = $this->peticion('GET', self::RUTA . '?code=codigo-de-prueba-seam-testing-sin-refresh');

        $this->assertSame(200, $respuesta['status']);

        $token = json_decode((string) file_get_contents(GoogleDriveClienteAutorizado::RUTA_TOKEN), true);
        $this->assertSame('token-de-prueba-seam-testing', $token['access_token'] ?? null);
        $this->assertSame('refresh-que-debe-conservarse', $token['refresh_token'] ?? null);
    }

    public function testNoRequiereSesion(): void
    {
        // Sin loguearComo() previo -- el original tampoco validaba sesión
        // (lo dispara el navegador del administrador siguiendo el redirect
        // de Google, no el SPA).
        $respuesta = $this->peticion('GET', self::RUTA . '?code=codigo-de-prueba-seam-testing');

        $this->assertSame(200, $respuesta['status']);
    }

    public function testConMetodoPostDevuelve405(): void
    {
        $respuesta = $this->peticion('POST', self::RUTA);

        $this->assertSame(405, $respuesta['status']);
    }
}
