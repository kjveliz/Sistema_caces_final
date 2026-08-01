<?php

declare(strict_types=1);

namespace Tests\Unit\GoogleDrive;

use App\Services\GoogleDriveClienteAutorizado;
use Google\Client as GoogleClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests de GoogleDriveClienteAutorizado (Parte 19 del plan de migración
 * slim-legacy -- puerto de api/google_drive/cliente_autorizado.php). El
 * cliente de Google se mockea (createMock) para no depender de
 * credenciales ni tokens reales; el archivo token.json se inyecta vía el
 * parámetro opcional de obtener(), siempre un archivo temporal de prueba.
 */
final class GoogleDriveClienteAutorizadoTest extends TestCase
{
    private string $rutaTokenPrueba;

    protected function setUp(): void
    {
        $this->rutaTokenPrueba = tempnam(sys_get_temp_dir(), 'token_drive_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->rutaTokenPrueba)) {
            unlink($this->rutaTokenPrueba);
        }
    }

    public function testLanzaExcepcionSiElArchivoDeTokenNoExiste(): void
    {
        unlink($this->rutaTokenPrueba);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Google Drive no está conectado.');

        GoogleDriveClienteAutorizado::obtener(
            $this->createMock(GoogleClient::class),
            $this->rutaTokenPrueba,
        );
    }

    public function testLanzaExcepcionSiElTokenNoTieneAccessToken(): void
    {
        file_put_contents($this->rutaTokenPrueba, json_encode(['algo' => 'sin access_token']));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('token.json no contiene un token válido.');

        GoogleDriveClienteAutorizado::obtener(
            $this->createMock(GoogleClient::class),
            $this->rutaTokenPrueba,
        );
    }

    public function testConTokenVigenteNoRenuevaYDevuelveElMismoCliente(): void
    {
        file_put_contents($this->rutaTokenPrueba, json_encode(['access_token' => 'token-vigente']));

        $cliente = $this->createMock(GoogleClient::class);
        $cliente->expects($this->once())
            ->method('setAccessToken')
            ->with(['access_token' => 'token-vigente']);
        $cliente->method('isAccessTokenExpired')->willReturn(false);
        $cliente->expects($this->never())->method('fetchAccessTokenWithRefreshToken');

        $resultado = GoogleDriveClienteAutorizado::obtener($cliente, $this->rutaTokenPrueba);

        $this->assertSame($cliente, $resultado);
    }

    public function testConTokenVencidoRenuevaConElRefreshTokenYActualizaElArchivo(): void
    {
        file_put_contents($this->rutaTokenPrueba, json_encode([
            'access_token' => 'token-vencido',
            'refresh_token' => 'refresh-guardado',
        ]));

        $cliente = $this->createMock(GoogleClient::class);
        $cliente->method('isAccessTokenExpired')->willReturn(true);
        $cliente->method('getRefreshToken')->willReturn(null);
        $cliente->expects($this->once())
            ->method('fetchAccessTokenWithRefreshToken')
            ->with('refresh-guardado')
            ->willReturn(['access_token' => 'token-nuevo']);
        $cliente->expects($this->exactly(2))->method('setAccessToken');

        GoogleDriveClienteAutorizado::obtener($cliente, $this->rutaTokenPrueba);

        $tokenGuardado = json_decode(file_get_contents($this->rutaTokenPrueba), true);
        $this->assertSame('token-nuevo', $tokenGuardado['access_token']);
        // El refresh_token se conserva aunque la renovación no haya
        // devuelto uno nuevo (mismo criterio del script legacy).
        $this->assertSame('refresh-guardado', $tokenGuardado['refresh_token']);
    }

    public function testTokenVencidoSinRefreshTokenLanzaExcepcion(): void
    {
        file_put_contents($this->rutaTokenPrueba, json_encode(['access_token' => 'token-vencido']));

        $cliente = $this->createMock(GoogleClient::class);
        $cliente->method('isAccessTokenExpired')->willReturn(true);
        $cliente->method('getRefreshToken')->willReturn(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No existe un refresh token.');

        GoogleDriveClienteAutorizado::obtener($cliente, $this->rutaTokenPrueba);
    }

    public function testRenovacionConErrorLanzaExcepcionConElMensajeDeGoogle(): void
    {
        file_put_contents($this->rutaTokenPrueba, json_encode([
            'access_token' => 'token-vencido',
            'refresh_token' => 'refresh-guardado',
        ]));

        $cliente = $this->createMock(GoogleClient::class);
        $cliente->method('isAccessTokenExpired')->willReturn(true);
        $cliente->method('getRefreshToken')->willReturn('refresh-guardado');
        $cliente->method('fetchAccessTokenWithRefreshToken')->willReturn([
            'error' => 'invalid_grant',
            'error_description' => 'El refresh token fue revocado.',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('El refresh token fue revocado.');

        GoogleDriveClienteAutorizado::obtener($cliente, $this->rutaTokenPrueba);
    }
}
