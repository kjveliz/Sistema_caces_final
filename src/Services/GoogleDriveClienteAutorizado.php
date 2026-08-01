<?php

declare(strict_types=1);

namespace App\Services;

use Google\Client as GoogleClient;
use RuntimeException;

/**
 * Obtiene un cliente de Google Drive ya autorizado (token cargado desde
 * disco, renovado automáticamente si venció). Puerto 1:1 de
 * `api/google_drive/cliente_autorizado.php` (Parte 19 del plan de
 * migración slim-legacy) -- misma lógica exacta (leer token.json, validar
 * que tenga access_token, renovar con el refresh token si expiró,
 * persistir el token renovado), ahora como método estático en vez de
 * script que hacía `return $cliente;`.
 *
 * Consumidores tras esta Parte: `App\Services\GoogleDriveService`
 * (`subirArchivo()`, `descargarContenidoDrive()`, `eliminarArchivo()`),
 * `scripts/diagnostico_evidencia_drive.php`, y
 * `App\Controllers\GoogleDriveController::verArchivo()` (Parte 20, vía
 * `GoogleDriveService::descargarContenidoDrive()` -- ver
 * EvidenciaStorageResolver). El endpoint legacy que todavía sirve bytes
 * de Drive directamente -- `api/google_drive/subir_archivo.php` (Parte
 * 21, pendiente) -- sigue usando este método directo.
 * `cliente_autorizado.php` queda borrado del repo con esta Parte.
 */
final class GoogleDriveClienteAutorizado
{
    private const RUTA_TOKEN = __DIR__ . '/../../api/google_drive/token.json';

    /**
     * @param GoogleClient|null $cliente Seam de testing: permite inyectar un
     *   cliente ya construido (p. ej. un mock) en vez de que el método arme
     *   uno real vía GoogleDriveClienteFactory::crear(). Los llamadores
     *   reales siempre invocan sin este argumento.
     * @param string|null $rutaToken Seam de testing: permite inyectar una
     *   ruta de token.json de prueba. Los llamadores reales siempre invocan
     *   sin este argumento, usando la ruta real de RUTA_TOKEN.
     */
    public static function obtener(
        ?GoogleClient $cliente = null,
        ?string $rutaToken = null,
    ): GoogleClient {
        $cliente ??= GoogleDriveClienteFactory::crear();
        $rutaToken ??= self::RUTA_TOKEN;

        if (!file_exists($rutaToken)) {
            throw new RuntimeException(
                'Google Drive no está conectado. Primero ejecute conectar.php.',
            );
        }

        $token = json_decode(
            file_get_contents($rutaToken),
            true,
        );

        if (
            !is_array($token) ||
            !isset($token['access_token'])
        ) {
            throw new RuntimeException(
                'El archivo token.json no contiene un token válido.',
            );
        }

        $cliente->setAccessToken($token);

        /*
         * Si el token de acceso venció, se renueva mediante
         * el refresh token guardado durante la autorización.
         */
        if ($cliente->isAccessTokenExpired()) {
            $refreshToken =
                $cliente->getRefreshToken()
                ?? ($token['refresh_token'] ?? null);

            if (!$refreshToken) {
                throw new RuntimeException(
                    'No existe un refresh token. Vuelva a conectar Google Drive.',
                );
            }

            $tokenRenovado =
                $cliente->fetchAccessTokenWithRefreshToken(
                    $refreshToken,
                );

            if (isset($tokenRenovado['error'])) {
                throw new RuntimeException(
                    $tokenRenovado['error_description']
                    ?? $tokenRenovado['error'],
                );
            }

            /*
             * Algunas renovaciones no devuelven nuevamente
             * el refresh_token, por eso lo conservamos.
             */
            $tokenRenovado['refresh_token'] =
                $refreshToken;

            if (
                file_put_contents(
                    $rutaToken,
                    json_encode(
                        $tokenRenovado,
                        JSON_PRETTY_PRINT |
                        JSON_UNESCAPED_SLASHES,
                    ),
                    LOCK_EX,
                ) === false
            ) {
                throw new RuntimeException(
                    'No se pudo actualizar token.json.',
                );
            }

            $cliente->setAccessToken($tokenRenovado);
        }

        return $cliente;
    }
}
