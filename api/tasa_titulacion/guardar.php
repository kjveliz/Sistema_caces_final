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

$idEvaluacion = intval(
    $datos["id_evaluacion"] ?? 0
);

$cohorte = strtoupper(
    preg_replace(
        "/[^A-Z0-9]/",
        "",
        trim($datos["cohorte"] ?? "")
    )
);

$tieneMatriculados = array_key_exists(
    "matriculados",
    $datos
);

$tieneGraduados = array_key_exists(
    "graduados",
    $datos
);

$matriculados = $tieneMatriculados
    ? intval($datos["matriculados"])
    : null;

$graduados = $tieneGraduados
    ? intval($datos["graduados"])
    : null;

if (
    $idEvaluacion <= 0 ||
    $cohorte === "" ||
    (!$tieneMatriculados && !$tieneGraduados)
) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "Faltan datos para actualizar la tasa."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (
    ($tieneMatriculados && $matriculados <= 0) ||
    ($tieneGraduados && $graduados < 0)
) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "Las cantidades recibidas no son válidas."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$conexion->begin_transaction();

try {
    /*
     * Crear la fila si aún no existe.
     */
    $sqlInsertar = "
        INSERT IGNORE INTO datos_tasa_titulacion (
            id_evaluacion,
            cohorte,
            matriculados,
            graduados,
            tasa,
            fecha_actualizacion
        )
        VALUES (?, ?, NULL, NULL, NULL, NOW())
    ";

    $stmtInsertar =
        $conexion->prepare($sqlInsertar);

    if (!$stmtInsertar) {
        throw new RuntimeException(
            $conexion->error
        );
    }

    $stmtInsertar->bind_param(
        "is",
        $idEvaluacion,
        $cohorte
    );

    $stmtInsertar->execute();

    /*
     * Actualizar solamente el dato recibido.
     */
    if ($tieneMatriculados) {
        $sqlActualizar = "
            UPDATE datos_tasa_titulacion
            SET matriculados = ?,
                fecha_actualizacion = NOW()
            WHERE id_evaluacion = ?
              AND cohorte = ?
        ";

        $stmtActualizar =
            $conexion->prepare($sqlActualizar);

        $stmtActualizar->bind_param(
            "iis",
            $matriculados,
            $idEvaluacion,
            $cohorte
        );
    } else {
        $sqlActualizar = "
            UPDATE datos_tasa_titulacion
            SET graduados = ?,
                fecha_actualizacion = NOW()
            WHERE id_evaluacion = ?
              AND cohorte = ?
        ";

        $stmtActualizar =
            $conexion->prepare($sqlActualizar);

        $stmtActualizar->bind_param(
            "iis",
            $graduados,
            $idEvaluacion,
            $cohorte
        );
    }

    if (!$stmtActualizar->execute()) {
        throw new RuntimeException(
            $stmtActualizar->error
        );
    }

    /*
     * Recalcular solamente cuando existan
     * matriculados y graduados.
     */
    $sqlCalcular = "
        UPDATE datos_tasa_titulacion
        SET tasa =
            CASE
                WHEN matriculados IS NOT NULL
                 AND matriculados > 0
                 AND graduados IS NOT NULL
                THEN ROUND(
                    (graduados / matriculados) * 100,
                    2
                )
                ELSE NULL
            END,
            fecha_actualizacion = NOW()
        WHERE id_evaluacion = ?
          AND cohorte = ?
    ";

    $stmtCalcular =
        $conexion->prepare($sqlCalcular);

    $stmtCalcular->bind_param(
        "is",
        $idEvaluacion,
        $cohorte
    );

    $stmtCalcular->execute();

    $sqlResultado = "
        SELECT
            matriculados,
            graduados,
            tasa
        FROM datos_tasa_titulacion
        WHERE id_evaluacion = ?
          AND cohorte = ?
        LIMIT 1
    ";

    $stmtResultado =
        $conexion->prepare($sqlResultado);

    $stmtResultado->bind_param(
        "is",
        $idEvaluacion,
        $cohorte
    );

    $stmtResultado->execute();

    $resultado =
        $stmtResultado->get_result()->fetch_assoc();

    $conexion->commit();

    echo json_encode([
        "ok" => true,
        "mensaje" =>
            "Datos de titulación actualizados correctamente.",
        "datos" => [
            "id_evaluacion" => $idEvaluacion,
            "cohorte" => $cohorte,
            "matriculados" =>
                $resultado["matriculados"] !== null
                    ? intval($resultado["matriculados"])
                    : null,
            "graduados" =>
                $resultado["graduados"] !== null
                    ? intval($resultado["graduados"])
                    : null,
            "tasa" =>
                $resultado["tasa"] !== null
                    ? floatval($resultado["tasa"])
                    : null,
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $error) {
    $conexion->rollback();

    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No se pudieron actualizar los datos de titulación.",
        "detalle" => $error->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}