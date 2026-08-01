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
 * Consumidores: `App\Controllers\GoogleDriveController::conectar()` (Parte
 * 22, ya migrada -- reemplazó a `api/google_drive/conectar.php`),
 * `api/google_drive/callback.php` (todavía legacy, sin migrar -- Parte 23,
 * la última del plan), y `App\Services\GoogleDriveClienteAutorizado::
 * obtener()` (Parte 19, ya migrada), que a su vez es la base de
 * `App\Services\GoogleDriveService` (en producción, I2/I3) y de
 * `scripts/diagnostico_evidencia_drive.php`.
 * `config.php` quedó borrado del repo desde la Parte 17.
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
