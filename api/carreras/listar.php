<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . "/../conexion.php";

$sql = "
    SELECT
        c.id_carrera,
        c.codigo,
        c.nombre,
        c.area_conocimiento,
        c.modalidad,
        m.nombre_archivo AS nombre_malla,
        m.id_drive,
        m.url_drive AS url_malla
    FROM Carreras c
    LEFT JOIN Mallas_Curriculares m
        ON m.id_carrera = c.id_carrera
       AND m.activo = 1
    WHERE c.activo = 1
    ORDER BY
        c.area_conocimiento,
        c.nombre
";

$resultado = $conexion->query($sql);

if (!$resultado) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudieron consultar las carreras.",
        "detalle" => $conexion->error
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$datos = [];

while ($fila = $resultado->fetch_assoc()) {
    $fila["id_carrera"] = (int) $fila["id_carrera"];
    $datos[] = $fila;
}

echo json_encode([
    "ok" => true,
    "datos" => $datos
], JSON_UNESCAPED_UNICODE);