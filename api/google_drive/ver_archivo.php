<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    http_response_code(401);
    exit("La sesión no está activa.");
}

require_once __DIR__ . "/../conexion.php";

$idEvidencia = intval(
    $_GET["id_evidencia"] ?? 0
);

if ($idEvidencia <= 0) {
    http_response_code(400);
    exit("Identificador de evidencia inválido.");
}

/*
 * Consultar la evidencia desde MySQL.
 */
$sql = "
    SELECT
        nombre_archivo,
        tipo,
        url_archivo
    FROM Evidencias
    WHERE id_evidencia = ?
    LIMIT 1
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    exit("No se pudo preparar la consulta.");
}

$stmt->bind_param(
    "i",
    $idEvidencia
);

$stmt->execute();

$resultado = $stmt->get_result();
$evidencia = $resultado->fetch_assoc();

if (!$evidencia) {
    http_response_code(404);
    exit("La evidencia no existe.");
}

$urlArchivo = trim(
    $evidencia["url_archivo"] ?? ""
);

/*
 * Extraer el ID desde enlaces como:
 * https://drive.google.com/file/d/ID/view
 */
if (
    !preg_match(
        "#drive\.google\.com/file/d/([^/]+)#",
        $urlArchivo,
        $coincidencias
    )
) {
    http_response_code(400);
    exit(
        "La evidencia no contiene una URL válida de Google Drive."
    );
}

$idArchivoDrive = $coincidencias[1];

try {
    $cliente = require __DIR__ .
        "/cliente_autorizado.php";

    $drive = new Google\Service\Drive(
        $cliente
    );

    /*
     * Descargar el PDF desde Drive usando
     * las credenciales privadas del sistema.
     */
    $respuesta = $drive->files->get(
        $idArchivoDrive,
        [
            "alt" => "media",
        ]
    );

    $contenido =
        $respuesta->getBody()->getContents();

    if ($contenido === "") {
        throw new RuntimeException(
            "Google Drive devolvió un archivo vacío."
        );
    }

    $nombreArchivo =
        $evidencia["nombre_archivo"] ??
        "evidencia.pdf";

    header(
        "Content-Type: application/pdf"
    );

    header(
        "Content-Disposition: inline; filename=\"" .
        basename($nombreArchivo) .
        "\""
    );

    header(
        "Content-Length: " .
        strlen($contenido)
    );

    header(
        "Cache-Control: private, max-age=0, must-revalidate"
    );

    echo $contenido;

} catch (Throwable $error) {
    http_response_code(500);

    echo "No se pudo mostrar el documento: " .
        htmlspecialchars(
            $error->getMessage(),
            ENT_QUOTES,
            "UTF-8"
        );
}