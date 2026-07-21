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
        "mensaje" => "No tiene permisos para editar carreras."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . "/../conexion.php";

$contenido = file_get_contents("php://input");
$datos = json_decode($contenido, true);

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

$codigo = strtoupper(trim((string) ($datos["codigo"] ?? "")));
$nombre = trim((string) ($datos["nombre"] ?? ""));
$areaConocimiento = trim((string) ($datos["area_conocimiento"] ?? ""));
$modalidad = trim((string) ($datos["modalidad"] ?? ""));

$codigo = preg_replace("/[^A-Z0-9]/", "", $codigo);

if (
    $idCarrera <= 0 ||
    $codigo === "" ||
    $nombre === "" ||
    $areaConocimiento === "" ||
    $modalidad === ""
) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "mensaje" => "Complete todos los campos obligatorios."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (strlen($codigo) > 15) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "mensaje" => "El código no puede superar los 15 caracteres."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$sqlExiste = "
    SELECT id_carrera
    FROM Carreras
    WHERE id_carrera = ?
      AND activo = 1
    LIMIT 1
";

$stmtExiste = $conexion->prepare($sqlExiste);

if (!$stmtExiste) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo verificar la carrera.",
        "detalle" => $conexion->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmtExiste->bind_param("i", $idCarrera);
$stmtExiste->execute();
$resultadoExiste = $stmtExiste->get_result();
$carreraExiste = $resultadoExiste->fetch_assoc();
$stmtExiste->close();

if (!$carreraExiste) {
    http_response_code(404);
    echo json_encode([
        "ok" => false,
        "mensaje" => "La carrera no existe o se encuentra desactivada."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$sqlCodigo = "
    SELECT id_carrera
    FROM Carreras
    WHERE codigo = ?
      AND id_carrera <> ?
      AND activo = 1
    LIMIT 1
";

$stmtCodigo = $conexion->prepare($sqlCodigo);

if (!$stmtCodigo) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo validar el código de la carrera.",
        "detalle" => $conexion->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmtCodigo->bind_param("si", $codigo, $idCarrera);
$stmtCodigo->execute();
$resultadoCodigo = $stmtCodigo->get_result();
$codigoDuplicado = $resultadoCodigo->fetch_assoc();
$stmtCodigo->close();

if ($codigoDuplicado) {
    http_response_code(409);
    echo json_encode([
        "ok" => false,
        "mensaje" => "Ya existe otra carrera con ese código."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$sqlActualizar = "
    UPDATE Carreras
    SET
        codigo = ?,
        nombre = ?,
        area_conocimiento = ?,
        modalidad = ?
    WHERE id_carrera = ?
      AND activo = 1
";

$stmtActualizar = $conexion->prepare($sqlActualizar);

if (!$stmtActualizar) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo preparar la actualización.",
        "detalle" => $conexion->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmtActualizar->bind_param(
    "ssssi",
    $codigo,
    $nombre,
    $areaConocimiento,
    $modalidad,
    $idCarrera
);

if (!$stmtActualizar->execute()) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo actualizar la carrera.",
        "detalle" => $stmtActualizar->error
    ], JSON_UNESCAPED_UNICODE);
    $stmtActualizar->close();
    exit;
}

$stmtActualizar->close();

echo json_encode([
    "ok" => true,
    "mensaje" => "Carrera actualizada correctamente.",
    "datos" => [
        "id_carrera" => $idCarrera,
        "codigo" => $codigo,
        "nombre" => $nombre,
        "area_conocimiento" => $areaConocimiento,
        "modalidad" => $modalidad
    ]
], JSON_UNESCAPED_UNICODE);