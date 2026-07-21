<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . "/../conexion.php";

$codigoCarrera = strtoupper(
    trim($_GET["codigo_carrera"] ?? "")
);

$cohorte = strtoupper(
    preg_replace(
        "/\s+/",
        "",
        trim($_GET["cohorte"] ?? "")
    )
);

if ($codigoCarrera === "" || $cohorte === "") {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "Debe enviar el código de la carrera y la cohorte."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$sql = "
    SELECT
        e.id_evaluacion,
        e.nombre_evaluacion,
        e.estado,
        e.fecha_inicio,
        e.fecha_fin,
        c.id_carrera,
        c.codigo AS codigo_carrera,
        c.nombre AS carrera,
        co.id_cohorte,
        co.nombre_cohorte
    FROM Evaluaciones e
    INNER JOIN Carreras c
        ON c.id_carrera = e.id_carrera
    INNER JOIN Cohortes co
        ON co.id_cohorte = e.id_cohorte
    WHERE c.codigo = ?
      AND REPLACE(co.nombre_cohorte, ' ', '') = ?
    ORDER BY e.id_evaluacion DESC
    LIMIT 1
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo preparar la consulta.",
        "detalle" => $conexion->error
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$stmt->bind_param(
    "ss",
    $codigoCarrera,
    $cohorte
);

$stmt->execute();

$resultado = $stmt->get_result();
$evaluacion = $resultado->fetch_assoc();

if (!$evaluacion) {
    http_response_code(404);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No existe una evaluación para la carrera y cohorte seleccionadas."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$evaluacion["id_evaluacion"] =
    (int) $evaluacion["id_evaluacion"];

$evaluacion["id_carrera"] =
    (int) $evaluacion["id_carrera"];

$evaluacion["id_cohorte"] =
    (int) $evaluacion["id_cohorte"];

echo json_encode([
    "ok" => true,
    "datos" => $evaluacion
], JSON_UNESCAPED_UNICODE);