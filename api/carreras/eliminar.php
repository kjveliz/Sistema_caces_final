<?php

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Headers: Content-Type, Accept");
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

if (($_SESSION["rol"] ?? "") !== "administrador") {
    http_response_code(403);

    echo json_encode([
        "ok" => false,
        "mensaje" => "No tiene permisos para eliminar carreras."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

require_once __DIR__ . "/../conexion.php";

$datos = json_decode(
    file_get_contents("php://input"),
    true
);

$idCarrera = isset($datos["id_carrera"])
    ? (int) $datos["id_carrera"]
    : 0;

if ($idCarrera <= 0) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" => "El identificador de la carrera no es válido."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$sqlBuscar = "
    SELECT id_carrera, nombre
    FROM Carreras
    WHERE id_carrera = ?
    LIMIT 1
";

$stmtBuscar = $conexion->prepare($sqlBuscar);

if (!$stmtBuscar) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo preparar la consulta de la carrera.",
        "detalle" => $conexion->error
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$stmtBuscar->bind_param("i", $idCarrera);
$stmtBuscar->execute();

$resultadoBuscar = $stmtBuscar->get_result();
$carrera = $resultadoBuscar->fetch_assoc();

$stmtBuscar->close();

if (!$carrera) {
    http_response_code(404);

    echo json_encode([
        "ok" => false,
        "mensaje" => "La carrera no existe."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

/*
 * Protección de integridad:
 * si la carrera ya tiene evaluaciones académicas, no se elimina todavía.
 * Esto evita borrar evidencias, cálculos y demás información institucional
 * de manera accidental.
 */
$sqlEvaluaciones = "
    SELECT COUNT(*) AS total
    FROM Evaluaciones
    WHERE id_carrera = ?
";

$stmtEvaluaciones = $conexion->prepare($sqlEvaluaciones);

if (!$stmtEvaluaciones) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo verificar si la carrera tiene evaluaciones.",
        "detalle" => $conexion->error
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$stmtEvaluaciones->bind_param("i", $idCarrera);
$stmtEvaluaciones->execute();

$resultadoEvaluaciones = $stmtEvaluaciones->get_result();
$filaEvaluaciones = $resultadoEvaluaciones->fetch_assoc();
$totalEvaluaciones = (int) ($filaEvaluaciones["total"] ?? 0);

$stmtEvaluaciones->close();

if ($totalEvaluaciones > 0) {
    http_response_code(409);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "La carrera tiene evaluaciones académicas relacionadas y no puede eliminarse automáticamente.",
        "detalle" =>
            "Primero deben eliminarse o trasladarse sus evaluaciones y evidencias para evitar pérdida accidental de información."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$conexion->begin_transaction();

try {
    /*
     * Eliminar primero la malla curricular relacionada.
     */
    $sqlMalla = "
        DELETE FROM Mallas_Curriculares
        WHERE id_carrera = ?
    ";

    $stmtMalla = $conexion->prepare($sqlMalla);

    if (!$stmtMalla) {
        throw new Exception(
            "No se pudo preparar la eliminación de la malla curricular: " .
            $conexion->error
        );
    }

    $stmtMalla->bind_param("i", $idCarrera);

    if (!$stmtMalla->execute()) {
        throw new Exception(
            "No se pudo eliminar la malla curricular: " .
            $stmtMalla->error
        );
    }

    $stmtMalla->close();

    /*
     * Eliminación física de la carrera.
     */
    $sqlCarrera = "
        DELETE FROM Carreras
        WHERE id_carrera = ?
    ";

    $stmtCarrera = $conexion->prepare($sqlCarrera);

    if (!$stmtCarrera) {
        throw new Exception(
            "No se pudo preparar la eliminación física de la carrera: " .
            $conexion->error
        );
    }

    $stmtCarrera->bind_param("i", $idCarrera);

    if (!$stmtCarrera->execute()) {
        throw new Exception(
            "No se pudo eliminar la carrera: " .
            $stmtCarrera->error
        );
    }

    if ($stmtCarrera->affected_rows !== 1) {
        throw new Exception(
            "La carrera no fue eliminada de la base de datos."
        );
    }

    $stmtCarrera->close();

    $conexion->commit();

    echo json_encode([
        "ok" => true,
        "mensaje" => "Carrera eliminada permanentemente.",
        "datos" => [
            "id_carrera" => $idCarrera,
            "nombre" => $carrera["nombre"]
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $error) {
    $conexion->rollback();

    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo eliminar permanentemente la carrera.",
        "detalle" => $error->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}