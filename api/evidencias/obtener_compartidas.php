<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");

require_once __DIR__ . "/../conexion.php";

$idEvaluacion = intval($_GET["id_evaluacion"] ?? 0);
$idIndicadorDestino = intval(
    $_GET["id_indicador_destino"] ?? 0
);

if (
    $idEvaluacion <= 0 ||
    $idIndicadorDestino <= 0
) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "Debe enviar la evaluación y el indicador destino."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$sql = "
    SELECT
        e.id_evidencia,
        e.id_catalogo,
        e.id_evaluacion,
        e.codigo_evidencia,
        e.descripcion,
        e.nombre_archivo,
        e.tipo,
        e.url_archivo,
        e.fecha_subida,

        ce.titulo_corto,
        ce.nombre_archivo_base,
        ce.orden,

        indicador_origen.id_indicador
            AS id_indicador_origen,

        indicador_origen.nombre
            AS indicador_origen

    FROM Indicador_Evidencia relacion_destino

    INNER JOIN Evidencias e
        ON e.id_evidencia =
           relacion_destino.id_evidencia

    INNER JOIN Catalogo_Evidencias ce
        ON ce.id_catalogo = e.id_catalogo

    INNER JOIN Indicadores indicador_origen
        ON indicador_origen.id_indicador =
           ce.id_indicador

    WHERE e.id_evaluacion = ?
      AND relacion_destino.id_indicador = ?
      AND ce.id_indicador <> ?

    ORDER BY ce.orden ASC
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
    "iii",
    $idEvaluacion,
    $idIndicadorDestino,
    $idIndicadorDestino
);

$stmt->execute();

$resultado = $stmt->get_result();
$evidencias = [];

while ($fila = $resultado->fetch_assoc()) {
    $fila["id_evidencia"] =
        (int) $fila["id_evidencia"];

    $fila["id_catalogo"] =
        (int) $fila["id_catalogo"];

    $fila["id_evaluacion"] =
        (int) $fila["id_evaluacion"];

    $fila["orden"] =
        (int) $fila["orden"];

    $fila["id_indicador_origen"] =
        (int) $fila["id_indicador_origen"];

    $evidencias[] = $fila;
}

echo json_encode([
    "ok" => true,
    "datos" => $evidencias
], JSON_UNESCAPED_UNICODE);