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
            "No tiene permisos para modificar usuarios."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

require_once __DIR__ . "/../../conexion.php";

$datos = json_decode(
    file_get_contents("php://input"),
    true
);

$idUsuario = intval(
    $datos["id_usuario"] ?? 0
);

$activo = intval(
    $datos["activo"] ?? -1
);

if (
    $idUsuario <= 0 ||
    !in_array($activo, [0, 1], true)
) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "Los datos recibidos no son válidos."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (
    $idUsuario ===
    intval($_SESSION["id_usuario"]) &&
    $activo === 0
) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No puede desactivar su propia cuenta."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$sql = "
    UPDATE usuarios
    SET activo = ?
    WHERE id_usuario = ?
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No se pudo preparar la actualización.",
        "detalle" => $conexion->error
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$stmt->bind_param(
    "ii",
    $activo,
    $idUsuario
);

if (!$stmt->execute()) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No se pudo actualizar el usuario.",
        "detalle" => $stmt->error
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

echo json_encode([
    "ok" => true,
    "mensaje" =>
        $activo === 1
            ? "Usuario activado correctamente."
            : "Usuario desactivado correctamente."
], JSON_UNESCAPED_UNICODE);