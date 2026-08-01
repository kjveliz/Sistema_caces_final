<?php

declare(strict_types=1);

namespace Tests\Unit\GoogleDrive;

use App\Services\GoogleDriveClienteFactory;
use Google\Service\Drive;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Tests de GoogleDriveClienteFactory (Parte 17 del plan de migración
 * slim-legacy -- puerto de api/google_drive/config.php). Usa un archivo de
 * credenciales de prueba (fixture, formato mínimo válido para
 * Google\Client::setAuthConfig) inyectado vía el parámetro opcional de
 * crear(), nunca las credenciales reales del proyecto.
 */
final class GoogleDriveClienteFactoryTest extends TestCase
{
    private string $rutaCredencialesPrueba;

    protected function setUp(): void
    {
        $this->rutaCredencialesPrueba = tempnam(sys_get_temp_dir(), 'credenciales_drive_');
        file_put_contents($this->rutaCredencialesPrueba, json_encode([
            'installed' => [
                'client_id' => 'fake-client-id.apps.googleusercontent.com',
                'client_secret' => 'fake-client-secret',
                'redirect_uris' => ['http://localhost/no-deberia-quedar-esta'],
            ],
        ]));
    }

    protected function tearDown(): void
    {
        unlink($this->rutaCredencialesPrueba);
    }

    public function testCrearConfiguraElScopeDriveFile(): void
    {
        $cliente = GoogleDriveClienteFactory::crear($this->rutaCredencialesPrueba);

        $this->assertSame([Drive::DRIVE_FILE], $cliente->getScopes());
    }

    public function testCrearConfiguraElRedirectUriReal(): void
    {
        $cliente = GoogleDriveClienteFactory::crear($this->rutaCredencialesPrueba);

        // El redirect URI explícito debe prevalecer sobre el que trae el
        // archivo de credenciales (ver orden de llamadas en la clase: el
        // setRedirectUri propio va después de setAuthConfig).
        $this->assertSame(
            'http://localhost/sistemacaces/api/google_drive/callback.php',
            $cliente->getRedirectUri(),
        );
    }

    public function testCrearLanzaExcepcionSiElArchivoDeCredencialesNoExiste(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GoogleDriveClienteFactory::crear('/ruta/que/no/existe/credenciales.json');
    }
}
