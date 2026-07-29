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

if (($_SESSION["rol"] ?? "") !== "administrador") {
    http_response_code(403);
    echo json_encode([
        "ok" => false,
        "mensaje" => "Solo un administrador puede crear cohortes."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . "/../../conexion.php";

$datos = json_decode(
    file_get_contents("php://input"),
    true
);

$idCarrera = intval($datos["id_carrera"] ?? 0);
$nombreCohorte = strtoupper(
    preg_replace(
        "/[^A-Z0-9]/",
        "",
        trim($datos["nombre_cohorte"] ?? "")
    )
);
$fechaInicio = trim($datos["fecha_inicio"] ?? "");
$fechaFin = trim($datos["fecha_fin"] ?? "");
$estado = trim($datos["estado"] ?? "Pendiente");

$estadosPermitidos = [
    "Activa",
    "Pendiente",
    "Cerrada"
];

if (
    $idCarrera <= 0 ||
    $nombreCohorte === "" ||
    $fechaInicio === "" ||
    $fechaFin === "" ||
    !in_array($estado, $estadosPermitidos, true)
) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "mensaje" => "Complete correctamente todos los campos."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($fechaFin < $fechaInicio) {
    http_response_code(400);
    echo json_encode([
        "ok" => false,
        "mensaje" => "La fecha final no puede ser anterior a la fecha inicial."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$conexion->begin_transaction();

try {
    $sqlCarrera = "
        SELECT nombre, codigo
        FROM carreras
        WHERE id_carrera = ?
        LIMIT 1
    ";

    $stmtCarrera = $conexion->prepare($sqlCarrera);
    $stmtCarrera->bind_param("i", $idCarrera);
    $stmtCarrera->execute();

    $carrera = $stmtCarrera
        ->get_result()
        ->fetch_assoc();

    if (!$carrera) {
        throw new RuntimeException("La carrera seleccionada no existe.");
    }

    $sqlCohorte = "
        INSERT INTO cohortes (
            nombre_cohorte,
            fecha_inicio,
            fecha_fin,
            id_carrera
        )
        VALUES (?, ?, ?, ?)
    ";

    $stmtCohorte = $conexion->prepare($sqlCohorte);
    $stmtCohorte->bind_param(
        "sssi",
        $nombreCohorte,
        $fechaInicio,
        $fechaFin,
        $idCarrera
    );

    if (!$stmtCohorte->execute()) {
        throw new RuntimeException($stmtCohorte->error);
    }

    $idCohorte = intval($stmtCohorte->insert_id);
    $idUsuario = intval($_SESSION["id_usuario"]);
    $nombreEvaluacion =
        "Evaluación " .
        $carrera["nombre"] .
        " " .
        $nombreCohorte;

    $sqlEvaluacion = "
        INSERT INTO evaluaciones (
            nombre_evaluacion,
            id_cohorte,
            fecha_inicio,
            fecha_fin,
            estado,
            id_usuario,
            id_carrera
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ";

    $stmtEvaluacion = $conexion->prepare($sqlEvaluacion);
    $stmtEvaluacion->bind_param(
        "sisssii",
        $nombreEvaluacion,
        $idCohorte,
        $fechaInicio,
        $fechaFin,
        $estado,
        $idUsuario,
        $idCarrera
    );

    if (!$stmtEvaluacion->execute()) {
        throw new RuntimeException($stmtEvaluacion->error);
    }

    $idEvaluacion = intval($stmtEvaluacion->insert_id);

    /*
     * El .xlsx de la malla curricular (tabla `mallas_curriculares`) ya NO se
     * auto-registra como evidencia DOC.SYL.01. Ese archivo sirve únicamente
     * para parsear cohorte/PAOs/asignaturas al crear la carrera (paso previo,
     * ver useNewCareerForm.ts); la evidencia DOC.SYL.01 real (compartida con
     * I1/I2/I5) ahora se sube a mano como PDF, con el flujo genérico que ya
     * existe para el resto de evidencias (mismo PdfZone que ya valida y solo
     * acepta .pdf) -- antes esto quedaba enmascarado porque este bloque
     * marcaba el slot como "ya lleno" con el propio Excel, forzando además
     * `tipo = "application/pdf"` sobre un archivo que no lo era.
     *
     * `eliminarCohorteEnCascada()` y `tiposCarreraVigentes()` ya toleraban
     * la ausencia de esta evidencia (siempre la buscaban con "si la hay"),
     * así que no requieren cambios.
     */
    $mallaRegistrada = false;
    $idEvidenciaMalla = null;

    $conexion->commit();

    echo json_encode([
        "ok" => true,
        "mensaje" => "Cohorte y evaluación creadas correctamente.",
        "datos" => [
            "id_cohorte" => $idCohorte,
            "nombre_cohorte" => $nombreCohorte,
            "fecha_inicio" => $fechaInicio,
            "fecha_fin" => $fechaFin,
            "id_carrera" => $idCarrera,
            "carrera" => $carrera["nombre"],
            "codigo_carrera" => $carrera["codigo"],
            "id_evaluacion" => $idEvaluacion,
            "nombre_evaluacion" => $nombreEvaluacion,
            "estado" => $estado,
            "malla_registrada_como_evidencia" => $mallaRegistrada,
            "id_evidencia_malla" => $idEvidenciaMalla
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $error) {
    $conexion->rollback();

    $codigo = $conexion->errno === 1062 ? 409 : 500;
    http_response_code($codigo);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            $codigo === 409
                ? "Ya existe esa cohorte para la carrera seleccionada."
                : "No se pudo crear la cohorte.",
        "detalle" => $error->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}