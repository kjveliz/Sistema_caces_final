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

require_once __DIR__ . "/../conexion.php";

$idCatalogo = intval(
    $_POST["id_catalogo"] ?? 0
);

$codigoCarrera = strtoupper(
    preg_replace(
        "/[^A-Z0-9]/",
        "",
        trim($_POST["codigo_carrera"] ?? "")
    )
);

$cohorte = strtoupper(
    preg_replace(
        "/[^A-Z0-9]/",
        "",
        trim($_POST["cohorte"] ?? "")
    )
);

$criterio = intval(
    $_POST["criterio"] ?? 0
);

$indicador = intval(
    $_POST["indicador"] ?? 0
);

if (
    $idCatalogo <= 0 ||
    $codigoCarrera === "" ||
    $cohorte === "" ||
    $criterio <= 0 ||
    $indicador <= 0
) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "Faltan datos para procesar el archivo."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if (!isset($_FILES["archivo"])) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No se recibió ningún archivo."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$archivo = $_FILES["archivo"];

if ($archivo["error"] !== UPLOAD_ERR_OK) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "Ocurrió un error al recibir el archivo."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$tamanoMaximo = 25 * 1024 * 1024;

if ($archivo["size"] > $tamanoMaximo) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "El archivo no debe superar los 25 MB."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$sql = "
    SELECT
        codigo_evidencia,
        titulo_corto,
        descripcion,
        nombre_archivo_base,
        orden
    FROM Catalogo_Evidencias
    WHERE id_catalogo = ?
      AND activo = 1
    LIMIT 1
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No se pudo preparar la consulta.",
        "detalle" =>
            $conexion->error
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$stmt->bind_param(
    "i",
    $idCatalogo
);

$stmt->execute();

$resultado = $stmt->get_result();

$catalogo = $resultado->fetch_assoc();

if (!$catalogo) {
    http_response_code(404);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "La evidencia seleccionada no existe o está inactiva."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Único slot que hoy espera CSV en vez de PDF. Se identifica por
// codigo_evidencia (dato de servidor, no lo que mande el cliente) para no
// depender de que el frontend mande el tipo correcto.
$esCsv = $catalogo["codigo_evidencia"] === "DOC.SEG.05";

$extension = strtolower(
    pathinfo(
        $archivo["name"],
        PATHINFO_EXTENSION
    )
);

$finfo = new finfo(FILEINFO_MIME_TYPE);

$tipoMime = $finfo->file(
    $archivo["tmp_name"]
);

if ($esCsv) {
    $mimeCsvValidos = [
        "text/csv",
        "application/csv",
        "application/vnd.ms-excel",
        "text/plain",
    ];

    if ($extension !== "csv" || !in_array($tipoMime, $mimeCsvValidos, true)) {
        http_response_code(400);

        echo json_encode([
            "ok" => false,
            "mensaje" =>
                "Solo se aceptan archivos CSV válidos."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
} else {
    if ($extension !== "pdf" || $tipoMime !== "application/pdf") {
        http_response_code(400);

        echo json_encode([
            "ok" => false,
            "mensaje" =>
                "Solo se aceptan archivos PDF válidos."
        ], JSON_UNESCAPED_UNICODE);

        exit;
    }
}

$nombreBase = preg_replace(
    "/[^A-Za-z0-9_]/",
    "_",
    $catalogo["nombre_archivo_base"]
);

$nombreBase = preg_replace(
    "/_+/",
    "_",
    $nombreBase
);

$nombreBase = trim(
    $nombreBase,
    "_"
);

$numeroEvidencia = intval(
    $catalogo["orden"]
);

$nombreGenerado = sprintf(
    "%s.%s.C%d.%d.%d.%s.%s",
    $codigoCarrera,
    $cohorte,
    $criterio,
    $indicador,
    $numeroEvidencia,
    $nombreBase,
    $esCsv ? "csv" : "pdf"
);

echo json_encode([
    "ok" => true,
    "mensaje" =>
        ($esCsv ? "CSV" : "PDF") . " validado y nombre generado correctamente.",
    "datos" => [
        "id_catalogo" =>
            $idCatalogo,

        "codigo_evidencia" =>
            $catalogo["codigo_evidencia"],

        "titulo_corto" =>
            $catalogo["titulo_corto"],

        "descripcion" =>
            $catalogo["descripcion"],

        "nombre_original" =>
            $archivo["name"],

        "nombre_generado" =>
            $nombreGenerado,

        "tipo" =>
            $tipoMime,

        "tamano" =>
            intval($archivo["size"])
    ]
], JSON_UNESCAPED_UNICODE);