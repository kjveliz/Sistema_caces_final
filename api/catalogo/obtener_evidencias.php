<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . "/../conexion.php";

$idIndicador = intval($_GET["id_indicador"] ?? 0);

if ($idIndicador <= 0) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" => "Debe enviar el id del indicador."
    ]);

    exit;
}

$sql = "
    SELECT
        id_catalogo,
        codigo_evidencia,
	titulo_corto,
        descripcion,
        nombre_archivo_base,
        orden
    FROM Catalogo_Evidencias
    WHERE id_indicador = ?
      AND activo = 1
    ORDER BY orden ASC
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo preparar la consulta.",
        "detalle" => $conexion->error
    ]);

    exit;
}

$stmt->bind_param("i", $idIndicador);
$stmt->execute();

$resultado = $stmt->get_result();
$evidencias = [];

while ($fila = $resultado->fetch_assoc()) {
    $fila["id_catalogo"] = (int) $fila["id_catalogo"];
    $fila["orden"] = (int) $fila["orden"];

    $evidencias[] = $fila;
}

echo json_encode([
    "ok" => true,
    "datos" => $evidencias
], JSON_UNESCAPED_UNICODE);