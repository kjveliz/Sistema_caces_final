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

if (!isset($_FILES["archivo"])) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" => "No se recibió el PDF."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$archivo = $_FILES["archivo"];

if ($archivo["error"] !== UPLOAD_ERR_OK) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo recibir el archivo."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$tipoMime = $finfo->file($archivo["tmp_name"]);

if ($tipoMime !== "application/pdf") {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" => "El archivo debe ser un PDF válido."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

require_once __DIR__ . "/../../vendor/autoload.php";

try {
    $parser = new Smalot\PdfParser\Parser();

    $pdf = $parser->parseFile(
        $archivo["tmp_name"]
    );

    $texto = $pdf->getText();

    /*
     * Acepta:
     * Total alumnos por ciclo: 35
     * Total alumnos: 35
     * Total de alumnos: 35
     */
    $patrones = [
        "/Total\s+alumnos\s+por\s+ciclo\s*:\s*(\d+)/iu",
        "/Total\s+de\s+alumnos\s*:\s*(\d+)/iu",
        "/Total\s+alumnos\s*:\s*(\d+)/iu",
    ];

    $totalMatriculados = null;

    foreach ($patrones as $patron) {
        if (preg_match($patron, $texto, $coincidencia)) {
            $totalMatriculados = intval(
                $coincidencia[1]
            );

            break;
        }
    }

    /*
     * Respaldo: contar números de identificación
     * de diez dígitos si no aparece un total.
     */
    if ($totalMatriculados === null) {
        preg_match_all(
            "/\b\d{10}\b/",
            $texto,
            $identificaciones
        );

        $identificacionesUnicas = array_unique(
            $identificaciones[0]
        );

        $totalMatriculados = count(
            $identificacionesUnicas
        );
    }

    $periodo = null;
    $cohorte = null;

    if (
        preg_match(
            "/Periodo\s*:\s*(.*?)\s+Fecha\s+Inicio/iu",
            $texto,
            $coincidenciaPeriodo
        )
    ) {
        $periodo = trim(
            preg_replace(
                "/\s+/",
                " ",
                $coincidenciaPeriodo[1]
            )
        );
    }

    if (
        preg_match(
            "/\b([AB])\s*(20\d{2})\b/iu",
            $texto,
            $coincidenciaCohorte
        )
    ) {
        $cohorte =
            strtoupper($coincidenciaCohorte[1]) .
            $coincidenciaCohorte[2];
    }

    echo json_encode([
        "ok" => true,
        "mensaje" =>
            "El PDF fue leído correctamente.",
        "datos" => [
            "matriculados" =>
                $totalMatriculados,
            "periodo" =>
                $periodo,
            "cohorte_detectada" =>
                $cohorte,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $error) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No se pudo leer el contenido del PDF.",
        "detalle" =>
            $error->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}