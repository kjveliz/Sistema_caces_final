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
 * RUTA_CREDENCIALES (punto 20 pendiente de la memoria, resuelto acá): ahora
 * se resuelve vía resolverRutaCredenciales(), que lee
 * GOOGLE_DRIVE_CREDENTIALS_PATH del entorno (mismo patrón que DB_HOST /
 * DB_USER en Database.php) y cae en el mismo valor por defecto que ya
 * estaba hardcodeado si la variable no está definida -- sin cambio de
 * comportamiento para quien no toque su .env.
 *
 * redirect_uri (Parte 23): cambió de la URL legacy
 * (`http://localhost/sistemacaces/api/google_drive/callback.php`, fuera de
 * `public/`) a la ruta nueva de Slim
 * (`http://localhost/sistemacaces/public/google-drive/callback`). Ese
 * cambio es compartido con `conectar()` (ya en producción desde la Parte
 * 22), así que Google Cloud Console necesitó tener AMBAS URIs de redirect
 * autorizadas (Google permite varias simultáneas) durante la transición --
 * confirmado en vivo por el usuario que el flujo de conexión real funciona
 * con la URL nueva. Con eso confirmado, `api/google_drive/callback.php` ya
 * se borró del repo (commit de cierre aparte, después del commit que
 * agregó la ruta nueva -- ver docblock de `GoogleDriveController::
 * callback()` para el detalle completo de la secuencia). Con esta Parte
 * cerrada, el plan de migración slim-legacy queda completo (23/23).
 */
final class GoogleDriveClienteFactory
{
    /**
     * Ruta relativa (a la raíz del proyecto) usada si GOOGLE_DRIVE_CREDENTIALS_PATH
     * no está definida en el entorno. Mismo valor que ya traía .env.example --
     * mantiene el comportamiento actual sin cambios si nadie toca el .env.
     */
    private const RUTA_CREDENCIALES_POR_DEFECTO = 'api/google_drive/credenciales.json';

    /**
     * @param string|null $rutaCredenciales Seam de testing: permite inyectar
     *   un archivo de credenciales de prueba. Los 3 llamadores reales
     *   (GoogleDriveController::conectar(), GoogleDriveController::callback(),
     *   GoogleDriveClienteAutorizado::obtener()) siempre lo invocan sin
     *   argumentos, usando la ruta real resuelta por resolverRutaCredenciales().
     */
    public static function crear(?string $rutaCredenciales = null): GoogleClient
    {
        $cliente = new GoogleClient();

        $cliente->setAuthConfig($rutaCredenciales ?? self::resolverRutaCredenciales());

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

    /**
     * Resuelve la ruta real del archivo de credenciales: usa
     * GOOGLE_DRIVE_CREDENTIALS_PATH si está definida en el entorno (mismo
     * patrón que el resto del proyecto, ver Database.php y public/index.php
     * -- $_ENV, cargado por Dotenv en public/index.php), y si no, cae en
     * RUTA_CREDENCIALES_POR_DEFECTO (el mismo valor que ya estaba
     * hardcodeado antes de este cambio). Cierra el punto 20 pendiente de la
     * memoria: la variable estaba en .env.example pero nada la leía.
     */
    private static function resolverRutaCredenciales(): string
    {
        $ruta = $_ENV['GOOGLE_DRIVE_CREDENTIALS_PATH'] ?? self::RUTA_CREDENCIALES_POR_DEFECTO;

        // Si viene una ruta absoluta (ej. producción con la ruta completa en
        // el .env), se usa tal cual. Si viene relativa (el caso normal, y el
        // valor por defecto), se resuelve contra la raíz del proyecto.
        $esAbsoluta = str_starts_with($ruta, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $ruta) === 1;

        return $esAbsoluta ? $ruta : __DIR__ . '/../../' . $ruta;
    }
}
