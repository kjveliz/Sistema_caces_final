<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Accept");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

require_once __DIR__ . "/../conexion.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "ok" => false,
        "mensaje" => "Método no permitido."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$contenido = file_get_contents("php://input");
$datos = json_decode($contenido, true);

if (!is_array($datos)) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" => "Los datos enviados no son válidos."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$codigo = strtoupper(trim($datos["codigo"] ?? ""));
$nombre = trim($datos["nombre"] ?? "");
$areaConocimiento = trim($datos["area_conocimiento"] ?? "");
$modalidad = trim($datos["modalidad"] ?? "");

$modalidadesPermitidas = [
    "Presencial",
    "En línea",
    "Híbrida"
];

if (
    $codigo === "" ||
    $nombre === "" ||
    $areaConocimiento === "" ||
    $modalidad === ""
) {
    http_response_code(422);

    echo json_encode([
        "ok" => false,
        "mensaje" => "Todos los campos de la carrera son obligatorios."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (!in_array($modalidad, $modalidadesPermitidas, true)) {
    http_response_code(422);

    echo json_encode([
        "ok" => false,
        "mensaje" => "La modalidad seleccionada no es válida."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (strlen($codigo) > 15) {
    http_response_code(422);

    echo json_encode([
        "ok" => false,
        "mensaje" => "El código institucional no puede superar los 15 caracteres."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/*
 * Comprobar si ya existe una carrera con el mismo código.
 */
$sqlCodigo = "
    SELECT id_carrera, activo
    FROM carreras
    WHERE codigo = ?
    LIMIT 1
";

$stmtCodigo = $conexion->prepare($sqlCodigo);

if (!$stmtCodigo) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo preparar la validación del código.",
        "detalle" => $conexion->error
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$stmtCodigo->bind_param("s", $codigo);
$stmtCodigo->execute();

$resultadoCodigo = $stmtCodigo->get_result();
$carreraCodigo = $resultadoCodigo->fetch_assoc();

$stmtCodigo->close();

if ($carreraCodigo) {
    http_response_code(409);

    echo json_encode([
        "ok" => false,
        "mensaje" => "Ya existe una carrera registrada con ese código institucional."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/*
 * Comprobar si ya existe una carrera con el mismo nombre.
 */
$sqlNombre = "
    SELECT id_carrera
    FROM carreras
    WHERE LOWER(nombre) = LOWER(?)
    LIMIT 1
";

$stmtNombre = $conexion->prepare($sqlNombre);

if (!$stmtNombre) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo preparar la validación del nombre.",
        "detalle" => $conexion->error
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$stmtNombre->bind_param("s", $nombre);
$stmtNombre->execute();

$resultadoNombre = $stmtNombre->get_result();
$carreraNombre = $resultadoNombre->fetch_assoc();

$stmtNombre->close();

if ($carreraNombre) {
    http_response_code(409);

    echo json_encode([
        "ok" => false,
        "mensaje" => "Ya existe una carrera registrada con ese nombre."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/*
 * Guardar la carrera.
 */
$sqlInsertar = "
    INSERT INTO carreras (
        codigo,
        nombre,
        area_conocimiento,
        modalidad,
        activo
    )
    VALUES (?, ?, ?, ?, 1)
";

$stmtInsertar = $conexion->prepare($sqlInsertar);

if (!$stmtInsertar) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo preparar el registro de la carrera.",
        "detalle" => $conexion->error
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$stmtInsertar->bind_param(
    "ssss",
    $codigo,
    $nombre,
    $areaConocimiento,
    $modalidad
);

if (!$stmtInsertar->execute()) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo registrar la carrera.",
        "detalle" => $stmtInsertar->error
    ], JSON_UNESCAPED_UNICODE);

    $stmtInsertar->close();
    exit;
}

$idCarrera = $stmtInsertar->insert_id;

$stmtInsertar->close();

echo json_encode([
    "ok" => true,
    "mensaje" => "Carrera registrada correctamente.",
    "datos" => [
        "id_carrera" => $idCarrera,
        "codigo" => $codigo,
        "nombre" => $nombre,
        "area_conocimiento" => $areaConocimiento,
        "modalidad" => $modalidad
    ]
], JSON_UNESCAPED_UNICODE);