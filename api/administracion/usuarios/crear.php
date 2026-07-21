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
            "Solo un administrador puede crear usuarios."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

require_once __DIR__ . "/../../conexion.php";

$datos = json_decode(
    file_get_contents("php://input"),
    true
);

$nombres = trim($datos["nombres"] ?? "");
$apellidos = trim($datos["apellidos"] ?? "");
$correo = strtolower(
    trim($datos["correo"] ?? "")
);
$contrasena = $datos["contrasena"] ?? "";
$rol = strtolower(
    trim($datos["rol"] ?? "")
);
$activo = isset($datos["activo"])
    ? intval($datos["activo"])
    : 1;

$rolesPermitidos = [
    "administrador",
    "coordinador",
    "evaluador"
];

if (
    $nombres === "" ||
    $apellidos === "" ||
    $correo === "" ||
    $contrasena === "" ||
    !in_array(
        $rol,
        $rolesPermitidos,
        true
    )
) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "Complete correctamente todos los campos."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "El correo electrónico no es válido."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (strlen($contrasena) < 8) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "La contraseña debe tener al menos 8 caracteres."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$hash = password_hash(
    $contrasena,
    PASSWORD_DEFAULT
);

$sql = "
    INSERT INTO usuarios (
        nombres,
        apellidos,
        correo,
        contrasena,
        rol,
        activo
    )
    VALUES (?, ?, ?, ?, ?, ?)
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No se pudo preparar el registro.",
        "detalle" =>
            $conexion->error
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$stmt->bind_param(
    "sssssi",
    $nombres,
    $apellidos,
    $correo,
    $hash,
    $rol,
    $activo
);

if (!$stmt->execute()) {
    if ($conexion->errno === 1062) {
        http_response_code(409);

        echo json_encode([
            "ok" => false,
            "mensaje" =>
                "Ya existe un usuario con ese correo."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }

    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No se pudo crear el usuario.",
        "detalle" =>
            $stmt->error
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

echo json_encode([
    "ok" => true,
    "mensaje" =>
        "Usuario creado correctamente.",
    "datos" => [
        "id_usuario" =>
            intval($stmt->insert_id),
        "nombres" => $nombres,
        "apellidos" => $apellidos,
        "correo" => $correo,
        "rol" => $rol,
        "activo" => $activo
    ]
], JSON_UNESCAPED_UNICODE);