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

require_once __DIR__ . "/../conexion.php";

$idEvaluacion = intval(
    $_GET["id_evaluacion"] ?? 0
);

if ($idEvaluacion <= 0) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "El identificador de la evaluación no es válido."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$sql = "
    SELECT
        id_evaluacion,
        cohorte,
        matriculados,
        graduados,
        tasa,
        fecha_actualizacion
    FROM datos_tasa_titulacion
    WHERE id_evaluacion = ?
    ORDER BY fecha_actualizacion DESC
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No se pudo preparar la consulta.",
        "detalle" => $conexion->error
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$stmt->bind_param(
    "i",
    $idEvaluacion
);

if (!$stmt->execute()) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No se pudieron consultar los datos.",
        "detalle" => $stmt->error
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$resultado = $stmt->get_result();
$datos = [];

while ($fila = $resultado->fetch_assoc()) {
    $datos[] = [
        "id_evaluacion" =>
            intval($fila["id_evaluacion"]),

        "cohorte" =>
            $fila["cohorte"],

        "matriculados" =>
            $fila["matriculados"] !== null
                ? intval($fila["matriculados"])
                : null,

        "graduados" =>
            $fila["graduados"] !== null
                ? intval($fila["graduados"])
                : null,

        "tasa" =>
            $fila["tasa"] !== null
                ? floatval($fila["tasa"])
                : null,

        "fecha_actualizacion" =>
            $fila["fecha_actualizacion"],
    ];
}

echo json_encode([
    "ok" => true,
    "datos" => $datos
], JSON_UNESCAPED_UNICODE);