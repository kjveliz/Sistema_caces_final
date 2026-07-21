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
        "mensaje" => "No tiene permisos para cambiar el estado."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . "/../../conexion.php";

$datos = json_decode(
    file_get_contents("php://input"),
    true
);

$idEvaluacion = intval($datos["id_evaluacion"] ?? 0);
$estado = trim($datos["estado"] ?? "");

$estadosPermitidos = [
    "Activa",
    "Pendiente",
    "Cerrada"
];

if (
    $idEvaluacion <= 0 ||
    !in_array($estado, $estadosPermitidos, true)
) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "mensaje" => "Los datos recibidos no son válidos."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$sql = "
    UPDATE evaluaciones
    SET estado = ?
    WHERE id_evaluacion = ?
";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("si", $estado, $idEvaluacion);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo actualizar la evaluación.",
        "detalle" => $stmt->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    "ok" => true,
    "mensaje" => "Estado actualizado correctamente."
], JSON_UNESCAPED_UNICODE);
