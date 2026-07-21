<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: GET, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    http_response_code(405);

    echo json_encode([
        "ok" => false,
        "mensaje" => "Método no permitido."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

session_start();

if (!isset($_SESSION["id_usuario"])) {
    http_response_code(401);

    echo json_encode([
        "ok" => false,
        "mensaje" => "La sesión no está activa."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (
    ($_SESSION["rol"] ?? "") !==
    "administrador"
) {
    http_response_code(403);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No tiene permisos para consultar usuarios."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

require_once __DIR__ . "/../../conexion.php";

$sql = "
    SELECT
        id_usuario,
        nombres,
        apellidos,
        correo,
        rol,
        activo
    FROM usuarios
    ORDER BY apellidos, nombres
";

$resultado = $conexion->query($sql);

if (!$resultado) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No se pudieron consultar los usuarios.",
        "detalle" => $conexion->error
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$usuarios = [];

while ($fila = $resultado->fetch_assoc()) {
    $usuarios[] = [
        "id_usuario" =>
            intval($fila["id_usuario"]),
        "nombres" =>
            $fila["nombres"],
        "apellidos" =>
            $fila["apellidos"],
        "correo" =>
            $fila["correo"],
        "rol" =>
            $fila["rol"],
        "activo" =>
            intval($fila["activo"]),
    ];
}

echo json_encode([
    "ok" => true,
    "datos" => $usuarios
], JSON_UNESCAPED_UNICODE);