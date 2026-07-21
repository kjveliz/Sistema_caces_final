<?php

require_once __DIR__ . "/conexion.php";

echo json_encode([
    "ok" => true,
    "mensaje" => "Conexión local realizada correctamente."
]);