<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "ok" => false,
        "mensaje" => "Método no permitido."
    ], JSON_UNESCAPED_UNICODE);

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

require_once __DIR__ . "/../conexion.php";

$datos = json_decode(
    file_get_contents("php://input"),
    true
);

$idCatalogo = intval($datos["id_catalogo"] ?? 0);
$idEvaluacion = intval($datos["id_evaluacion"] ?? 0);
$codigoEvidencia = trim($datos["codigo_evidencia"] ?? "");
$descripcion = trim($datos["descripcion"] ?? "");
$nombreArchivo = trim($datos["nombre_archivo"] ?? "");
$tipo = trim($datos["tipo"] ?? "");
$urlArchivo = trim($datos["url_archivo"] ?? "");

$idUsuario = intval($_SESSION["id_usuario"]);

if (
    $idCatalogo <= 0 ||
    $idEvaluacion <= 0 ||
    $codigoEvidencia === "" ||
    $descripcion === "" ||
    $nombreArchivo === "" ||
    $tipo === "" ||
    $urlArchivo === ""
) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" => "Faltan datos obligatorios para registrar la evidencia."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$conexion->begin_transaction();

try {
    /*
        Inserta la evidencia si no existe.
        Si ya existe para la misma evaluación y catálogo,
        actualiza el registro.
    */
    $sqlEvidencia = "
        INSERT INTO Evidencias (
            id_catalogo,
            id_evaluacion,
            codigo_evidencia,
            descripcion,
            nombre_archivo,
            tipo,
            url_archivo,
            fecha_subida,
            id_usuario
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)

        ON DUPLICATE KEY UPDATE
            codigo_evidencia = VALUES(codigo_evidencia),
            descripcion = VALUES(descripcion),
            nombre_archivo = VALUES(nombre_archivo),
            tipo = VALUES(tipo),
            url_archivo = VALUES(url_archivo),
            fecha_subida = NOW(),
            id_usuario = VALUES(id_usuario),
            id_evidencia = LAST_INSERT_ID(id_evidencia)
    ";

    $stmtEvidencia = $conexion->prepare($sqlEvidencia);

    if (!$stmtEvidencia) {
        throw new Exception(
            "No se pudo preparar el registro de la evidencia: " .
            $conexion->error
        );
    }

    $stmtEvidencia->bind_param(
        "iisssssi",
        $idCatalogo,
        $idEvaluacion,
        $codigoEvidencia,
        $descripcion,
        $nombreArchivo,
        $tipo,
        $urlArchivo,
        $idUsuario
    );

    if (!$stmtEvidencia->execute()) {
        throw new Exception(
            "No se pudo guardar la evidencia: " .
            $stmtEvidencia->error
        );
    }

    $idEvidencia = intval($stmtEvidencia->insert_id);

    /*
        Relacionar la evidencia con su indicador de origen,
        obtenido desde Catalogo_Evidencias.
    */
    $sqlOrigen = "
        INSERT IGNORE INTO Indicador_Evidencia (
            id_indicador,
            id_evidencia
        )
        SELECT
            id_indicador,
            ?
        FROM Catalogo_Evidencias
        WHERE id_catalogo = ?
          AND activo = 1
    ";

    $stmtOrigen = $conexion->prepare($sqlOrigen);

    if (!$stmtOrigen) {
        throw new Exception(
            "No se pudo preparar la relación con el indicador de origen: " .
            $conexion->error
        );
    }

    $stmtOrigen->bind_param(
        "ii",
        $idEvidencia,
        $idCatalogo
    );

    if (!$stmtOrigen->execute()) {
        throw new Exception(
            "No se pudo relacionar la evidencia con su indicador de origen: " .
            $stmtOrigen->error
        );
    }

    /*
        Buscar reglas activas de compartición y relacionar
        automáticamente la misma evidencia con otros indicadores.
    */
    $sqlCompartidas = "
        INSERT IGNORE INTO Indicador_Evidencia (
            id_indicador,
            id_evidencia
        )
        SELECT
            id_indicador_destino,
            ?
        FROM Compartir_Catalogo
        WHERE id_catalogo_origen = ?
          AND activo = 1
    ";

    $stmtCompartidas = $conexion->prepare($sqlCompartidas);

    if (!$stmtCompartidas) {
        throw new Exception(
            "No se pudo preparar la compartición automática: " .
            $conexion->error
        );
    }

    $stmtCompartidas->bind_param(
        "ii",
        $idEvidencia,
        $idCatalogo
    );

    if (!$stmtCompartidas->execute()) {
        throw new Exception(
            "No se pudo compartir la evidencia automáticamente: " .
            $stmtCompartidas->error
        );
    }

    $relacionesCompartidas =
        intval($stmtCompartidas->affected_rows);

    $conexion->commit();

    echo json_encode([
        "ok" => true,
        "mensaje" => "Evidencia guardada correctamente.",
        "id_evidencia" => $idEvidencia,
        "relaciones_compartidas" => $relacionesCompartidas
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $error) {
    $conexion->rollback();

    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No se pudo completar el registro de la evidencia.",
        "detalle" => $error->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}