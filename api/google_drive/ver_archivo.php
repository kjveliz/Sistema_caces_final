<?php

/*
 * CORS: hasta ahora este script solo se abría con window.open()/<iframe
 * src=...> (navegación top-level, no necesita CORS), pero al conectarlo
 * desde el frontend para I1/I4/I5 (paso 6, ver MEMORIA) también lo puede
 * consumir CsvPreviewTable con fetch(credentials:'include') para los CSV
 * de reporte del SIU (DOC.SEG.06/DOC.SEG.07) -- mismo patrón que el resto
 * de endpoints de api/evidencias/*.php.
 */
header("Access-Control-Allow-Origin: http://localhost:5173");
header("Access-Control-Allow-Credentials: true");

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
    FROM evidencias
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
 * Interruptor de almacenamiento (paso 5, plan_interruptor_almacenamiento.txt):
 * si la carrera de esta evidencia está (o estuvo) en modo 'local', url_archivo
 * es un path absoluto del filesystem, no un link de Drive. Se rama por la
 * FORMA de la URL (igual que EvidenciaStorageResolver::resolverParaDescarga(),
 * no por el modo_almacenamiento actual de la carrera) porque una evidencia ya
 * subida sigue viviendo donde se subió aunque después se cambie el
 * interruptor sin volver a migrar -- ver ese resolver para el mismo criterio.
 */
if (!str_starts_with($urlArchivo, "http://") && !str_starts_with($urlArchivo, "https://")) {
    $almacenamientoLocal = new App\Services\AlmacenamientoLocalService();
    $contenidoLocal = $almacenamientoLocal->descargarContenido($urlArchivo);

    if ($contenidoLocal === null) {
        http_response_code(404);
        exit("El archivo de evidencia no existe en el almacenamiento local.");
    }

    $nombreArchivoLocal = $evidencia["nombre_archivo"] ?? "evidencia.pdf";
    $extensionLocal = strtolower(pathinfo($nombreArchivoLocal, PATHINFO_EXTENSION));
    $mimeTypeLocal = $extensionLocal === "csv" ? "text/csv" : "application/pdf";

    header("Content-Type: {$mimeTypeLocal}");
    header("Content-Disposition: inline; filename=\"" . basename($nombreArchivoLocal) . "\"");
    header("Content-Length: " . strlen($contenidoLocal));
    header("Cache-Control: private, max-age=0, must-revalidate");

    echo $contenidoLocal;
    exit;
}

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