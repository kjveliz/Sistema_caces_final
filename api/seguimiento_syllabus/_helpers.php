<?php
/**
 * Helpers compartidos para api/seguimiento_syllabus/*.php.
 * Replica EXACTO el patrón ya usado en el resto del backend real
 * (ver evidencias/guardar_evidencia.php, google_drive/subir_archivo.php):
 * mismos headers CORS, misma sesión ($_SESSION["id_usuario"]), mismo
 * formato de respuesta {ok, mensaje, ...}.
 *
 * Uso en cada endpoint:
 *   require_once __DIR__ . "/_helpers.php";
 *   iniciarEndpoint(["POST"]);       // o ["GET"], o ["GET","POST"]
 *   require_once __DIR__ . "/../conexion.php";  // da $conexion (mysqli)
 */

function responderJson(bool $ok, ?string $mensaje, array $extra = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode(array_merge([
        "ok" => $ok,
        "mensaje" => $mensaje,
    ], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * @param string[] $metodosPermitidos ej. ["GET"], ["POST"], ["GET","POST"]
 * @param bool $requiereSesion si true, exige $_SESSION["id_usuario"] (401 si no).
 */
function iniciarEndpoint(array $metodosPermitidos, bool $requiereSesion = false): void
{
    header("Content-Type: application/json; charset=utf-8");
    header("Access-Control-Allow-Origin: http://localhost:5173");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Allow-Methods: " . implode(", ", $metodosPermitidos) . ", OPTIONS");

    if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
        http_response_code(204);
        exit;
    }

    if (!in_array($_SERVER["REQUEST_METHOD"], $metodosPermitidos, true)) {
        responderJson(false, "Método no permitido.", [], 405);
    }

    if ($requiereSesion) {
        session_start();
        if (!isset($_SESSION["id_usuario"])) {
            responderJson(false, "La sesión no está activa.", [], 401);
        }
    }
}

/** Lee el body JSON de un POST (igual que json_decode(file_get_contents("php://input"), true) usado en el resto del backend). */
function leerJsonBody(): array
{
    $datos = json_decode(file_get_contents("php://input"), true);
    return is_array($datos) ? $datos : [];
}