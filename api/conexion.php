<?php

header("Content-Type: application/json; charset=utf-8");

$host = "localhost";
$usuario = "root";
$contrasena = "";
$base_datos = "evaluacion_caces";

$conexion = new mysqli(
    $host,
    $usuario,
    $contrasena,
    $base_datos
);

if ($conexion->connect_error) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" => "Error de conexión con la base de datos.",
        "detalle" => $conexion->connect_error
    ]);

    exit;
}

$conexion->set_charset("utf8mb4");