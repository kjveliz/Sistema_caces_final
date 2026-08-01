<?php

declare(strict_types=1);

namespace App\Services;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;

/**
 * Fábrica del cliente de Google Drive configurado (credenciales, scopes,
 * redirect URI). Puerto 1:1 de `api/google_drive/config.php` (Parte 17 del
 * plan de migración slim-legacy) -- misma configuración exacta, ahora como
 * método estático en vez de script que arma una variable global `$cliente`.
 *
 * Consumidores tras esta Parte: `api/google_drive/conectar.php` y
 * `api/google_drive/callback.php` (ambos todavía legacy, sin migrar --
 * Partes 22/23), y `App\Services\GoogleDriveClienteAutorizado::obtener()`
 * (Parte 19, ya migrada), que a su vez es la base de
 * `App\Services\GoogleDriveService` (en producción, I2/I3) y de
 * `scripts/diagnostico_evidencia_drive.php`.
 * `config.php` queda borrado del repo con esta Parte.
 */
final class GoogleDriveClienteFactory
{
    private const RUTA_CREDENCIALES = __DIR__ . '/../../api/google_drive/credenciales.json';

    /**
     * @param string|null $rutaCredenciales Seam de testing: permite inyectar
     *   un archivo de credenciales de prueba. Los 3 llamadores reales
     *   (conectar.php, callback.php, GoogleDriveClienteAutorizado::obtener())
     *   siempre lo invocan sin argumentos, usando la ruta real de
     *   RUTA_CREDENCIALES.
     */
    public static function crear(?string $rutaCredenciales = null): GoogleClient
    {
        $cliente = new GoogleClient();

        $cliente->setAuthConfig($rutaCredenciales ?? self::RUTA_CREDENCIALES);

        $cliente->setApplicationName('Sistema CACES');

        $cliente->setScopes([
            GoogleDrive::DRIVE_FILE,
        ]);

        $cliente->setAccessType('offline');

        $cliente->setPrompt('consent');

        $cliente->setRedirectUri(
            'http://localhost/sistemacaces/api/google_drive/callback.php',
        );

        return $cliente;
    }
}
