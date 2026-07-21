<?php

header("Content-Type: text/html; charset=utf-8");

require_once __DIR__ . "/config.php";

if (isset($_GET["error"])) {
    exit(
        "<h2>Google Drive no fue autorizado.</h2>" .
        "<p>" . htmlspecialchars($_GET["error"]) . "</p>"
    );
}

$codigo = trim($_GET["code"] ?? "");

if ($codigo === "") {
    http_response_code(400);

    exit(
        "<h2>No se recibió el código de autorización.</h2>" .
        "<p>Vuelva a iniciar la conexión con Google Drive.</p>"
    );
}

$token = $cliente->fetchAccessTokenWithAuthCode($codigo);

if (isset($token["error"])) {
    http_response_code(400);

    $detalle = $token["error_description"]
        ?? $token["error"];

    exit(
        "<h2>No se pudo obtener el token.</h2>" .
        "<p>" . htmlspecialchars($detalle) . "</p>"
    );
}

/*
 * Google puede omitir el refresh_token cuando la cuenta
 * ya autorizó anteriormente la aplicación.
 * En ese caso conservamos el refresh_token anterior.
 */
$rutaToken = __DIR__ . "/token.json";

if (file_exists($rutaToken)) {
    $tokenAnterior = json_decode(
        file_get_contents($rutaToken),
        true
    );

    if (
        !isset($token["refresh_token"]) &&
        isset($tokenAnterior["refresh_token"])
    ) {
        $token["refresh_token"] =
            $tokenAnterior["refresh_token"];
    }
}

$contenidoToken = json_encode(
    $token,
    JSON_PRETTY_PRINT |
    JSON_UNESCAPED_SLASHES
);

if (
    file_put_contents(
        $rutaToken,
        $contenidoToken,
        LOCK_EX
    ) === false
) {
    http_response_code(500);

    exit(
        "<h2>No se pudo guardar token.json.</h2>" .
        "<p>Revise los permisos de la carpeta google_drive.</p>"
    );
}

echo "
<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <title>Google Drive conectado</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #eef2f7;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .card {
            width: 420px;
            background: white;
            border-radius: 16px;
            padding: 32px;
            text-align: center;
            box-shadow: 0 12px 35px rgba(0,0,0,.10);
        }

        h1 {
            color: #1b3a6b;
            font-size: 24px;
        }

        p {
            color: #5a7295;
            line-height: 1.5;
        }

        a {
            display: inline-block;
            margin-top: 16px;
            background: #1b3a6b;
            color: white;
            text-decoration: none;
            padding: 11px 20px;
            border-radius: 10px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class='card'>
        <h1>Google Drive conectado</h1>
        <p>
            La cuenta autorizó correctamente al Sistema CACES.
            El token quedó almacenado de forma local.
        </p>
        <a href='http://localhost:5173'>
            Volver al sistema
        </a>
    </div>
</body>
</html>
";