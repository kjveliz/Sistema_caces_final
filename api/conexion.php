<?php

header("Content-Type: application/json; charset=utf-8");

require_once __DIR__ . "/../vendor/autoload.php";

// Carga .env desde la raíz del repo si existe. safeLoad() no falla si el
// archivo no está presente, así que un checkout sin .env sigue funcionando
// con los valores por defecto de abajo (mismo comportamiento que antes).
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/..");
$dotenv->safeLoad();

$host = $_ENV['DB_HOST'] ?? "localhost";
$usuario = $_ENV['DB_USER'] ?? "root";
$contrasena = $_ENV['DB_PASS'] ?? "";
$base_datos = $_ENV['DB_NAME'] ?? "evaluacion_caces";

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