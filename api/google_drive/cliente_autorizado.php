<?php

require __DIR__ . "/config.php";

$rutaToken = __DIR__ . "/token.json";

if (!file_exists($rutaToken)) {
    throw new RuntimeException(
        "Google Drive no está conectado. Primero ejecute conectar.php."
    );
}

$token = json_decode(
    file_get_contents($rutaToken),
    true
);

if (
    !is_array($token) ||
    !isset($token["access_token"])
) {
    throw new RuntimeException(
        "El archivo token.json no contiene un token válido."
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
        ?? ($token["refresh_token"] ?? null);

    if (!$refreshToken) {
        throw new RuntimeException(
            "No existe un refresh token. Vuelva a conectar Google Drive."
        );
    }

    $tokenRenovado =
        $cliente->fetchAccessTokenWithRefreshToken(
            $refreshToken
        );

    if (isset($tokenRenovado["error"])) {
        throw new RuntimeException(
            $tokenRenovado["error_description"]
            ?? $tokenRenovado["error"]
        );
    }

    /*
     * Algunas renovaciones no devuelven nuevamente
     * el refresh_token, por eso lo conservamos.
     */
    $tokenRenovado["refresh_token"] =
        $refreshToken;

    if (
        file_put_contents(
            $rutaToken,
            json_encode(
                $tokenRenovado,
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_SLASHES
            ),
            LOCK_EX
        ) === false
    ) {
        throw new RuntimeException(
            "No se pudo actualizar token.json."
        );
    }

    $cliente->setAccessToken($tokenRenovado);
}

return $cliente;