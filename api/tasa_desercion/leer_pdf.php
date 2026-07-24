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
        "mensaje" => "No se recibió ningún archivo PDF."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$archivo = $_FILES["archivo"];
$tipoDato = trim($_POST["tipo_dato"] ?? "");

$tiposPermitidos = [
    "primer_nivel",
    "segundo_anio",
    "no_continuaron"
];

if (!in_array($tipoDato, $tiposPermitidos, true)) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "El tipo de dato no es válido."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

if ($archivo["error"] !== UPLOAD_ERR_OK) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" => "No se pudo recibir el archivo."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$extension = strtolower(
    pathinfo($archivo["name"], PATHINFO_EXTENSION)
);

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($archivo["tmp_name"]);

if (
    $extension !== "pdf" ||
    $mime !== "application/pdf"
) {
    http_response_code(400);

    echo json_encode([
        "ok" => false,
        "mensaje" => "El archivo debe ser un PDF válido."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

require_once __DIR__ . "/../../vendor/autoload.php";
require_once __DIR__ . "/_calculo.php";

try {
    $parser = new Smalot\PdfParser\Parser();

    $pdf = $parser->parseFile(
        $archivo["tmp_name"]
    );

    $texto = $pdf->getText();

    $datos = extraerDatosDesercion($texto);
    $datos = array_merge(["tipo_dato" => $tipoDato], $datos);

    echo json_encode([
        "ok" => true,
        "mensaje" => "El PDF fue leído correctamente.",
        "datos" => $datos
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $error) {
    http_response_code(500);

    echo json_encode([
        "ok" => false,
        "mensaje" =>
            "No se pudo leer la información del PDF.",
        "detalle" => $error->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
