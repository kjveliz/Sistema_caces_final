<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . "/../conexion.php";

$codigoCarrera = strtoupper(
    trim($_GET["codigo_carrera"] ?? "")
);

if ($codigoCarrera === "") {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" => "Debe enviar el código de la carrera."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$sql = "
    SELECT
        m.id_malla,
        m.id_carrera,
        m.nombre_archivo,
        m.id_drive,
        m.url_drive,
        m.fecha_subida,
        m.fecha_actualizacion
    FROM Mallas_Curriculares m
    INNER JOIN Carreras c
        ON c.id_carrera = m.id_carrera
    WHERE c.codigo = ?
      AND c.activo = 1
      AND m.activo = 1
    ORDER BY m.id_malla DESC
    LIMIT 1
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo preparar la consulta de la malla curricular.",
        "detalle" => $conexion->error
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$stmt->bind_param("s", $codigoCarrera);
$stmt->execute();

$resultado = $stmt->get_result();
$malla = $resultado->fetch_assoc();

$stmt->close();

if (!$malla) {
    echo json_encode([
        "ok" => true,
        "datos" => null
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$malla["id_malla"] = (int) $malla["id_malla"];
$malla["id_carrera"] = (int) $malla["id_carrera"];

echo json_encode([
    "ok" => true,
    "datos" => $malla
], JSON_UNESCAPED_UNICODE);