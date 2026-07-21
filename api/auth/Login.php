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

require_once __DIR__ . "/../conexion.php";

$datos = json_decode(
    file_get_contents("php://input"),
    true
);

$correo = trim($datos["correo"] ?? "");
$contrasena = $datos["contrasena"] ?? "";

if ($correo === "" || $contrasena === "") {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "Ingrese el correo y la contraseña."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$sql = "
    SELECT
        id_usuario,
        nombres,
        apellidos,
        correo,
        contrasena,
        rol,
        activo
    FROM usuarios
    WHERE correo = ?
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

$stmt->bind_param("s", $correo);
$stmt->execute();

$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();

if (
    !$usuario ||
    !password_verify(
        $contrasena,
        $usuario["contrasena"]
    )
) {
    http_response_code(401);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "Usuario o contraseña incorrectos."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (intval($usuario["activo"]) !== 1) {
    http_response_code(403);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "La cuenta se encuentra desactivada."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

session_start();

session_regenerate_id(true);

$_SESSION["id_usuario"] =
    intval($usuario["id_usuario"]);

$_SESSION["correo"] =
    $usuario["correo"];

$_SESSION["rol"] =
    $usuario["rol"];

echo json_encode([
    "ok" => true,
    "mensaje" =>
        "Inicio de sesión correcto.",
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