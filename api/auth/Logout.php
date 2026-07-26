<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "ok" => false,
        "mensaje" => "Método no permitido."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

session_start();

$_SESSION = [];

// Borra también la cookie del navegador, no solo los datos del lado del
// servidor: sin esto, session_destroy() invalida la sesión en el servidor
// pero el navegador seguiría mandando el mismo PHPSESSID (ya inválido) en
// cada pedido siguiente.
if (ini_get("session.use_cookies")) {
    $parametros = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $parametros["path"],
        $parametros["domain"],
        $parametros["secure"],
        $parametros["httponly"]
    );
}

session_destroy();

echo json_encode([
    "ok" => true,
    "mensaje" => "Sesión cerrada correctamente."
], JSON_UNESCAPED_UNICODE);
