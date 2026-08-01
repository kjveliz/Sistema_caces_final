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
 * `App\Controllers\GoogleDriveController::callback()` (Parte 23, ya
 * migrada -- reemplazó a `api/google_drive/callback.php`), y
 * `App\Services\GoogleDriveClienteAutorizado::obtener()` (Parte 19, ya
 * migrada), que a su vez es la base de `App\Services\GoogleDriveService`
 * (en producción, I2/I3) y de `scripts/diagnostico_evidencia_drive.php`.
 * `config.php` quedó borrado del repo desde la Parte 17.
 *
 * redirect_uri (Parte 23): cambió de la URL legacy
 * (`http://localhost/sistemacaces/api/google_drive/callback.php`, fuera de
 * `public/`) a la ruta nueva de Slim
 * (`http://localhost/sistemacaces/public/google-drive/callback`). Este
 * cambio es compartido con `conectar()` (ya en producción desde la Parte
 * 22): en cuanto este archivo se aplique, las auth-URLs que arma
 * `conectar()` van a apuntar a la ruta nueva -- por eso Google Cloud
 * Console necesita tener AMBAS URIs de redirect autorizadas (la vieja y la
 * nueva) antes de aplicar este cambio en el entorno real del usuario, no
 * después. Google permite varias URIs autorizadas simultáneas, así que no
 * hace falta borrar la vieja de Console todavía -- ver docblock de
 * `GoogleDriveController::callback()` para el resto de la secuencia seguida
 * esta Parte (por qué el legacy `api/google_drive/callback.php` no se
 * borra todavía del repo, a diferencia de las Partes anteriores).
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
            'http://localhost/sistemacaces/public/google-drive/callback',
        );

        return $cliente;
    }
}
