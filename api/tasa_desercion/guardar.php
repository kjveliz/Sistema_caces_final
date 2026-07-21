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

$tienePrimerNivel = array_key_exists(
    "iniciaron_primer_nivel",
    $datos
);

$tieneSegundoAnio = array_key_exists(
    "matriculados_segundo_anio",
    $datos
);

$tieneNoContinuaron = array_key_exists(
    "no_continuaron",
    $datos
);

$primerNivel = $tienePrimerNivel
    ? intval($datos["iniciaron_primer_nivel"])
    : null;

$segundoAnio = $tieneSegundoAnio
    ? intval($datos["matriculados_segundo_anio"])
    : null;

$noContinuaron = $tieneNoContinuaron
    ? intval($datos["no_continuaron"])
    : null;

if (
    $idEvaluacion <= 0 ||
    $cohorte === "" ||
    (
        !$tienePrimerNivel &&
        !$tieneSegundoAnio &&
        !$tieneNoContinuaron
    )
) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "Faltan datos para actualizar la tasa de deserción."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (
    ($tienePrimerNivel && $primerNivel <= 0) ||
    ($tieneSegundoAnio && $segundoAnio < 0) ||
    ($tieneNoContinuaron && $noContinuaron < 0)
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
    $sqlInsertar = "
        INSERT IGNORE INTO datos_tasa_desercion (
            id_evaluacion,
            cohorte,
            iniciaron_primer_nivel,
            matriculados_segundo_anio,
            no_continuaron,
            tasa,
            fecha_actualizacion
        )
        VALUES (?, ?, NULL, NULL, NULL, NULL, NOW())
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

    if (!$stmtInsertar->execute()) {
        throw new RuntimeException(
            $stmtInsertar->error
        );
    }

    if ($tienePrimerNivel) {
        $sqlActualizar = "
            UPDATE datos_tasa_desercion
            SET iniciaron_primer_nivel = ?,
                fecha_actualizacion = NOW()
            WHERE id_evaluacion = ?
              AND cohorte = ?
        ";

        $stmtActualizar =
            $conexion->prepare($sqlActualizar);

        $stmtActualizar->bind_param(
            "iis",
            $primerNivel,
            $idEvaluacion,
            $cohorte
        );
    } elseif ($tieneSegundoAnio) {
        $sqlActualizar = "
            UPDATE datos_tasa_desercion
            SET matriculados_segundo_anio = ?,
                fecha_actualizacion = NOW()
            WHERE id_evaluacion = ?
              AND cohorte = ?
        ";

        $stmtActualizar =
            $conexion->prepare($sqlActualizar);

        $stmtActualizar->bind_param(
            "iis",
            $segundoAnio,
            $idEvaluacion,
            $cohorte
        );
    } else {
        $sqlActualizar = "
            UPDATE datos_tasa_desercion
            SET no_continuaron = ?,
                fecha_actualizacion = NOW()
            WHERE id_evaluacion = ?
              AND cohorte = ?
        ";

        $stmtActualizar =
            $conexion->prepare($sqlActualizar);

        $stmtActualizar->bind_param(
            "iis",
            $noContinuaron,
            $idEvaluacion,
            $cohorte
        );
    }

    if (!$stmtActualizar->execute()) {
        throw new RuntimeException(
            $stmtActualizar->error
        );
    }

    $sqlCalcular = "
        UPDATE datos_tasa_desercion
        SET tasa =
            CASE
                WHEN iniciaron_primer_nivel IS NOT NULL
                 AND iniciaron_primer_nivel > 0
                 AND no_continuaron IS NOT NULL
                THEN ROUND(
                    (
                        no_continuaron /
                        iniciaron_primer_nivel
                    ) * 100,
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

    if (!$stmtCalcular->execute()) {
        throw new RuntimeException(
            $stmtCalcular->error
        );
    }

    $sqlResultado = "
        SELECT
            iniciaron_primer_nivel,
            matriculados_segundo_anio,
            no_continuaron,
            tasa
        FROM datos_tasa_desercion
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

    $advertencia = null;

    if (
        $resultado["iniciaron_primer_nivel"] !== null &&
        $resultado["matriculados_segundo_anio"] !== null &&
        $resultado["no_continuaron"] !== null
    ) {
        $continuaronCalculados =
            intval($resultado["iniciaron_primer_nivel"]) -
            intval($resultado["no_continuaron"]);

        if (
            $continuaronCalculados !==
            intval($resultado["matriculados_segundo_anio"])
        ) {
            $advertencia =
                "Los datos no coinciden: primer nivel menos no continuaron " .
                "debería ser igual a matriculados de segundo año.";
        }
    }

    $conexion->commit();

    echo json_encode([
        "ok" => true,
        "mensaje" =>
            "Datos de deserción actualizados correctamente.",
        "advertencia" => $advertencia,
        "datos" => [
            "id_evaluacion" => $idEvaluacion,
            "cohorte" => $cohorte,
            "iniciaron_primer_nivel" =>
                $resultado["iniciaron_primer_nivel"] !== null
                    ? intval($resultado["iniciaron_primer_nivel"])
                    : null,
            "matriculados_segundo_anio" =>
                $resultado["matriculados_segundo_anio"] !== null
                    ? intval($resultado["matriculados_segundo_anio"])
                    : null,
            "no_continuaron" =>
                $resultado["no_continuaron"] !== null
                    ? intval($resultado["no_continuaron"])
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
            "No se pudieron actualizar los datos de deserción.",
        "detalle" => $error->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
