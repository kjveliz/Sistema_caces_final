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

require_once __DIR__ . "/../conexion.php";

$sql = "
    SELECT
        id_usuario,
        nombres,
        apellidos,
        correo,
        rol,
        activo
    FROM usuarios
    WHERE id_usuario = ?
    LIMIT 1
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No se pudo preparar la consulta."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$idSesion = intval($_SESSION["id_usuario"]);

$stmt->bind_param("i", $idSesion);
$stmt->execute();

$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();

// El usuario pudo haber sido eliminado o desactivado después de haber
// iniciado sesión (ej. un administrador lo desactiva mientras la cookie
// del navegador sigue viva). En ambos casos la sesión se invalida acá,
// igual que si nunca se hubiera iniciado.
if (!$usuario || intval($usuario["activo"]) !== 1) {
    session_unset();
    session_destroy();

    http_response_code(401);

    echo json_encode([
        "ok" => false,
        "mensaje" => "La sesión no está activa."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

echo json_encode([
    "ok" => true,
    "usuario" => [
        "id_usuario" =>
            intval($usuario["id_usuario"]),
        "nombres" =>
            $usuario["nombres"],
        "apellidos" =>
            $usuario["apellidos"],
        "correo" =>
            $usuario["correo"],
        "rol" =>
            $usuario["rol"]
    ]
], JSON_UNESCAPED_UNICODE);
