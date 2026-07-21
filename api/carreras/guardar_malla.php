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

if (($_SESSION["rol"] ?? "") !== "administrador") {
    http_response_code(403);
    echo json_encode([
        "ok" => false,
        "mensaje" => "No tiene permisos para registrar mallas curriculares."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . "/../conexion.php";

$datos = json_decode(file_get_contents("php://input"), true);

if (!is_array($datos)) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se recibieron datos JSON válidos."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$idCarrera = isset($datos["id_carrera"])
    ? (int) $datos["id_carrera"]
    : 0;

$nombreArchivo = trim((string) ($datos["nombre_archivo"] ?? ""));
$idDrive = trim((string) ($datos["id_drive"] ?? ""));
$urlDrive = trim((string) ($datos["url_drive"] ?? ""));

if (
    $idCarrera <= 0 ||
    $nombreArchivo === "" ||
    $idDrive === "" ||
    $urlDrive === ""
) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "mensaje" => "Faltan datos para registrar la malla curricular."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (
    !str_starts_with($urlDrive, "https://drive.google.com/")
) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "mensaje" => "La URL de Google Drive no es válida."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$sqlCarrera = "
    SELECT id_carrera
    FROM Carreras
    WHERE id_carrera = ?
      AND activo = 1
    LIMIT 1
";

$stmtCarrera = $conexion->prepare($sqlCarrera);

if (!$stmtCarrera) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo verificar la carrera.",
        "detalle" => $conexion->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmtCarrera->bind_param("i", $idCarrera);
$stmtCarrera->execute();
$resultadoCarrera = $stmtCarrera->get_result();
$existeCarrera = $resultadoCarrera->fetch_assoc();
$stmtCarrera->close();

if (!$existeCarrera) {
    http_response_code(404);
    echo json_encode([
        "ok" => false,
        "mensaje" => "La carrera no existe o está desactivada."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "
    INSERT INTO Mallas_Curriculares (
        id_carrera,
        nombre_archivo,
        id_drive,
        url_drive,
        activo
    )
    VALUES (?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE
        nombre_archivo = VALUES(nombre_archivo),
        id_drive = VALUES(id_drive),
        url_drive = VALUES(url_drive),
        activo = 1,
        fecha_actualizacion = CURRENT_TIMESTAMP
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo preparar el registro de la malla.",
        "detalle" => $conexion->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param(
    "isss",
    $idCarrera,
    $nombreArchivo,
    $idDrive,
    $urlDrive
);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo registrar la malla curricular.",
        "detalle" => $stmt->error
    ], JSON_UNESCAPED_UNICODE);
    $stmt->close();
    exit;
}

$stmt->close();

echo json_encode([
    "ok" => true,
    "mensaje" => "Malla curricular registrada correctamente.",
    "datos" => [
        "id_carrera" => $idCarrera,
        "nombre_archivo" => $nombreArchivo,
        "id_drive" => $idDrive,
        "url_drive" => $urlDrive
    ]
], JSON_UNESCAPED_UNICODE);