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

try {
    $parser = new Smalot\PdfParser\Parser();

    $pdf = $parser->parseFile(
        $archivo["tmp_name"]
    );

    $texto = $pdf->getText();

    $textoNormalizado = preg_replace(
        "/[ \t]+/",
        " ",
        $texto
    );

    $textoNormalizado = preg_replace(
        "/\r\n|\r/",
        "\n",
        $textoNormalizado
    );

    $patronesTotal = [
        "/Total\s+alumnos\s+por\s+ciclo\s*:\s*(\d+)/iu",
        "/Total\s+de\s+alumnos\s*:\s*(\d+)/iu",
        "/Total\s+alumnos\s*:\s*(\d+)/iu",
        "/Total\s+matriculados\s*:\s*(\d+)/iu",
        "/Total\s+estudiantes\s*:\s*(\d+)/iu",
        "/Total\s+que\s+no\s+continuaron\s*:\s*(\d+)/iu",
        "/Total\s+no\s+continuaron\s*:\s*(\d+)/iu",
        "/Total\s+desertados\s*:\s*(\d+)/iu",
    ];

    $total = null;
    $metodo = null;

    foreach ($patronesTotal as $patron) {
        if (
            preg_match(
                $patron,
                $textoNormalizado,
                $coincidencia
            )
        ) {
            $total = intval($coincidencia[1]);
            $metodo = "total_reportado";
            break;
        }
    }

    $identificaciones = [];

    if ($total === null) {
        preg_match_all(
            "/(?<!\d)\d{10}(?!\d)/",
            $textoNormalizado,
            $coincidenciasIdentificacion
        );

        $identificaciones = array_values(
            array_unique(
                $coincidenciasIdentificacion[0]
            )
        );

        $total = count($identificaciones);
        $metodo = "identificaciones_unicas";
    }

    if ($total <= 0) {
        throw new RuntimeException(
            "No se pudo detectar ningún estudiante en el PDF."
        );
    }

    $cohorteDetectada = null;

    if (
        preg_match(
            "/\b([AB])\s*(20\d{2})\b/iu",
            $textoNormalizado,
            $coincidenciaCohorte
        )
    ) {
        $cohorteDetectada =
            strtoupper($coincidenciaCohorte[1]) .
            $coincidenciaCohorte[2];
    }

    $periodoDetectado = null;

    if (
        preg_match(
            "/Periodo\s*:\s*(.+?)(?:Fecha\s+Inicio|Fecha\s*:|\n)/iu",
            $textoNormalizado,
            $coincidenciaPeriodo
        )
    ) {
        $periodoDetectado = trim(
            preg_replace(
                "/\s+/",
                " ",
                $coincidenciaPeriodo[1]
            )
        );
    }

    echo json_encode([
        "ok" => true,
        "mensaje" => "El PDF fue leído correctamente.",
        "datos" => [
            "tipo_dato" => $tipoDato,
            "total" => $total,
            "metodo" => $metodo,
            "cohorte_detectada" => $cohorteDetectada,
            "periodo_detectado" => $periodoDetectado,
            "identificaciones_detectadas" =>
                count($identificaciones),
        ]
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
