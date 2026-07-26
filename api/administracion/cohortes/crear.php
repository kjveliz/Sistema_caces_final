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
     * Registrar automáticamente la malla curricular como evidencia
     * DOC.SYL.01 de la evaluación recién creada.
     *
     * Si la carrera todavía no tiene una malla activa, la cohorte y la
     * evaluación se crean normalmente sin registrar esta evidencia.
     */
    $mallaRegistrada = false;
    $idEvidenciaMalla = null;

    $sqlMalla = "
        SELECT
            nombre_archivo,
            url_drive
        FROM mallas_curriculares
        WHERE id_carrera = ?
          AND activo = 1
        ORDER BY id_malla DESC
        LIMIT 1
    ";

    $stmtMalla = $conexion->prepare($sqlMalla);

    if (!$stmtMalla) {
        throw new RuntimeException(
            "No se pudo preparar la consulta de la malla curricular: " .
            $conexion->error
        );
    }

    $stmtMalla->bind_param("i", $idCarrera);
    $stmtMalla->execute();

    $malla = $stmtMalla
        ->get_result()
        ->fetch_assoc();

    $stmtMalla->close();

    if ($malla) {
        /*
         * Se obtiene el catálogo real por código para no depender de un
         * id_catalogo fijo que pueda cambiar entre bases de datos.
         */
        $codigoMalla = "DOC.SYL.01";

        $sqlCatalogoMalla = "
            SELECT
                id_catalogo,
                codigo_evidencia,
                descripcion
            FROM catalogo_evidencias
            WHERE codigo_evidencia = ?
              AND activo = 1
            LIMIT 1
        ";

        $stmtCatalogoMalla = $conexion->prepare($sqlCatalogoMalla);

        if (!$stmtCatalogoMalla) {
            throw new RuntimeException(
                "No se pudo preparar la consulta del catálogo de la malla: " .
                $conexion->error
            );
        }

        $stmtCatalogoMalla->bind_param("s", $codigoMalla);
        $stmtCatalogoMalla->execute();

        $catalogoMalla = $stmtCatalogoMalla
            ->get_result()
            ->fetch_assoc();

        $stmtCatalogoMalla->close();

        if (!$catalogoMalla) {
            throw new RuntimeException(
                "No existe una evidencia activa con el código DOC.SYL.01."
            );
        }

        $idCatalogoMalla = intval($catalogoMalla["id_catalogo"]);
        $descripcionMalla = trim(
            (string) ($catalogoMalla["descripcion"] ?? "")
        );

        if ($descripcionMalla === "") {
            $descripcionMalla = "Malla curricular de la carrera.";
        }

        $nombreArchivoMalla = trim(
            (string) $malla["nombre_archivo"]
        );
        $urlArchivoMalla = trim(
            (string) $malla["url_drive"]
        );
        $tipoMalla = "application/pdf";

        $sqlEvidenciaMalla = "
            INSERT INTO evidencias (
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

        $stmtEvidenciaMalla = $conexion->prepare($sqlEvidenciaMalla);

        if (!$stmtEvidenciaMalla) {
            throw new RuntimeException(
                "No se pudo preparar el registro automático de la malla: " .
                $conexion->error
            );
        }

        $stmtEvidenciaMalla->bind_param(
            "iisssssi",
            $idCatalogoMalla,
            $idEvaluacion,
            $codigoMalla,
            $descripcionMalla,
            $nombreArchivoMalla,
            $tipoMalla,
            $urlArchivoMalla,
            $idUsuario
        );

        if (!$stmtEvidenciaMalla->execute()) {
            throw new RuntimeException(
                "No se pudo registrar automáticamente la malla: " .
                $stmtEvidenciaMalla->error
            );
        }

        $idEvidenciaMalla = intval(
            $stmtEvidenciaMalla->insert_id
        );

        $stmtEvidenciaMalla->close();

        /*
         * Relación con el indicador de origen de DOC.SYL.01.
         */
        $sqlOrigenMalla = "
            INSERT IGNORE INTO indicador_evidencia (
                id_indicador,
                id_evidencia
            )
            SELECT
                id_indicador,
                ?
            FROM catalogo_evidencias
            WHERE id_catalogo = ?
              AND activo = 1
        ";

        $stmtOrigenMalla = $conexion->prepare($sqlOrigenMalla);

        if (!$stmtOrigenMalla) {
            throw new RuntimeException(
                "No se pudo preparar la relación de la malla con su indicador: " .
                $conexion->error
            );
        }

        $stmtOrigenMalla->bind_param(
            "ii",
            $idEvidenciaMalla,
            $idCatalogoMalla
        );

        if (!$stmtOrigenMalla->execute()) {
            throw new RuntimeException(
                "No se pudo relacionar la malla con su indicador: " .
                $stmtOrigenMalla->error
            );
        }

        $stmtOrigenMalla->close();

        /*
         * Compartición con los demás indicadores configurados en
         * compartir_catalogo.
         */
        $sqlCompartirMalla = "
            INSERT IGNORE INTO indicador_evidencia (
                id_indicador,
                id_evidencia
            )
            SELECT
                id_indicador_destino,
                ?
            FROM compartir_catalogo
            WHERE id_catalogo_origen = ?
              AND activo = 1
        ";

        $stmtCompartirMalla = $conexion->prepare($sqlCompartirMalla);

        if (!$stmtCompartirMalla) {
            throw new RuntimeException(
                "No se pudo preparar la compartición automática de la malla: " .
                $conexion->error
            );
        }

        $stmtCompartirMalla->bind_param(
            "ii",
            $idEvidenciaMalla,
            $idCatalogoMalla
        );

        if (!$stmtCompartirMalla->execute()) {
            throw new RuntimeException(
                "No se pudo compartir automáticamente la malla: " .
                $stmtCompartirMalla->error
            );
        }

        $stmtCompartirMalla->close();
        $mallaRegistrada = true;
    }

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