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
        "mensaje" => "No tiene permisos para consultar cohortes."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . "/../../conexion.php";

$sql = "
    SELECT
        co.id_cohorte,
        co.nombre_cohorte,
        co.fecha_inicio,
        co.fecha_fin,
        co.id_carrera,
        ca.nombre AS carrera,
        ca.codigo AS codigo_carrera,
        ev.id_evaluacion,
        ev.nombre_evaluacion,
        ev.estado
    FROM cohortes co
    INNER JOIN carreras ca
        ON ca.id_carrera = co.id_carrera
    LEFT JOIN evaluaciones ev
        ON ev.id_cohorte = co.id_cohorte
       AND ev.id_carrera = co.id_carrera
    ORDER BY co.fecha_inicio DESC, ca.nombre ASC
";

$resultado = $conexion->query($sql);

if (!$resultado) {
    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudieron consultar las cohortes.",
        "detalle" => $conexion->error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$datos = [];

while ($fila = $resultado->fetch_assoc()) {
    $datos[] = [
        "id_cohorte" => intval($fila["id_cohorte"]),
        "nombre_cohorte" => $fila["nombre_cohorte"],
        "fecha_inicio" => $fila["fecha_inicio"],
        "fecha_fin" => $fila["fecha_fin"],
        "id_carrera" => intval($fila["id_carrera"]),
        "carrera" => $fila["carrera"],
        "codigo_carrera" => $fila["codigo_carrera"],
        "id_evaluacion" =>
            $fila["id_evaluacion"] !== null
                ? intval($fila["id_evaluacion"])
                : null,
        "nombre_evaluacion" => $fila["nombre_evaluacion"],
        "estado" => $fila["estado"],
    ];
}

echo json_encode([
    "ok" => true,
    "datos" => $datos
], JSON_UNESCAPED_UNICODE);
