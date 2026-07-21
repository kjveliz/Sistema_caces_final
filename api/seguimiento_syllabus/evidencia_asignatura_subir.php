<?php
require_once __DIR__ . '/_helpers.php';
iniciarEndpoint(['POST'], requiereSesion: true);
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/_calculo.php';
require_once __DIR__ . '/_google_drive.php';

$idAsignatura = isset($_POST['id_asignatura']) ? (int) $_POST['id_asignatura'] : 0;
$tipo = trim($_POST['tipo'] ?? '');

if ($idAsignatura <= 0 || !in_array($tipo, TIPOS_POR_ASIGNATURA, true)) {
    responderJson(false, 'id_asignatura y tipo (válido) son requeridos.', [], 400);
}

if (!isset($_FILES['archivo'])) {
    responderJson(false, 'No se recibió ningún archivo.', [], 400);
}

// El slot 'encuesta_csv' (CSV de resultados de encuesta, ver MEMORIA v18)
// es CSV; el resto de TIPOS_POR_ASIGNATURA sigue siendo PDF.
$esCsv = $tipo === 'encuesta_csv';
$errorValidacion = $esCsv ? validarCsv($_FILES['archivo']) : validarPdf($_FILES['archivo']);
if ($errorValidacion !== null) {
    responderJson(false, $errorValidacion, [], 400);
}

// Contexto (carrera/cohorte/PAO) para armar la jerarquía de carpetas en Drive.
$stmt = $conexion->prepare(
    'SELECT a.nombre AS asignatura, p.nombre AS pao, co.nombre_cohorte AS cohorte, ca.nombre AS carrera
     FROM asignatura a
     JOIN periodo_academico p ON p.id_periodoacademico = a.id_periodoacademico
     JOIN cohortes co ON co.id_cohorte = p.id_cohorte
     JOIN carreras ca ON ca.id_carrera = co.id_carrera
     WHERE a.id_asignatura = ?'
);
$stmt->bind_param('i', $idAsignatura);
$stmt->execute();
$contexto = $stmt->get_result()->fetch_assoc();

if (!$contexto) {
    responderJson(false, 'Asignatura no encontrada.', [], 404);
}

$extension = $esCsv ? 'csv' : 'pdf';
$nombreArchivoDrive = sprintf('%s_%s_%s.%s', $tipo, preg_replace('/[^A-Za-z0-9]+/', '_', $contexto['asignatura']), date('Ymd_His'), $extension);

try {
    $subida = subirArchivoDrive(
        $_FILES['archivo']['tmp_name'],
        $nombreArchivoDrive,
        $contexto['carrera'],
        $contexto['cohorte'],
        $contexto['pao'],
        $contexto['asignatura'],
        $esCsv ? 'text/csv' : 'application/pdf'
    );
} catch (Throwable $e) {
    responderJson(false, 'No se pudo subir el archivo a Google Drive.', ['detalle' => $e->getMessage()], 502);
}

$idUsuario = intval($_SESSION['id_usuario']);

$conexion->begin_transaction();
try {
    // Igual que Django: la evidencia anterior del mismo tipo/asignatura pasa
    // a vigente=0 (se conserva como historial) en vez de borrarse, y la
    // nueva se fuerza a vigente=1 explícitamente.
    $stmtViejo = $conexion->prepare(
        'UPDATE evidencia_asignatura SET vigente = 0 WHERE id_asignatura = ? AND tipo = ? AND vigente = 1'
    );
    $stmtViejo->bind_param('is', $idAsignatura, $tipo);
    $stmtViejo->execute();

    $stmtNuevo = $conexion->prepare(
        'INSERT INTO evidencia_asignatura (id_asignatura, tipo, nombre_archivo, url_archivo, subido_por, vigente)
         VALUES (?, ?, ?, ?, ?, 1)'
    );
    $subidoPor = (string) $idUsuario;
    $stmtNuevo->bind_param('issss', $idAsignatura, $tipo, $subida['nombre_archivo'], $subida['url_archivo'], $subidoPor);
    $stmtNuevo->execute();

    $conexion->commit();
} catch (Throwable $e) {
    $conexion->rollback();
    responderJson(false, 'No se pudo guardar la evidencia.', ['detalle' => $e->getMessage()], 500);
}

responderJson(true, 'Evidencia subida correctamente.', [
    'datos' => [
        'id_evidencia_asig' => (int) $stmtNuevo->insert_id,
        'url_archivo' => $subida['url_archivo'],
    ],
]);