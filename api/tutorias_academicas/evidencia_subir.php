<?php
require_once __DIR__ . '/../seguimiento_syllabus/_helpers.php';
iniciarEndpoint(['POST'], requiereSesion: true);
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/../seguimiento_syllabus/_google_drive.php'; // subirArchivoDrive() + validarPdf()
require_once __DIR__ . '/_calculo.php';
require_once __DIR__ . '/_validacion_pdf.php';

$idAsignatura = isset($_POST['id_asignatura']) ? (int) $_POST['id_asignatura'] : 0;
$tipo = trim($_POST['tipo'] ?? '');

if ($idAsignatura <= 0 || !in_array($tipo, TIPOS_TUTORIAS, true)) {
    responderJson(false, 'id_asignatura y tipo (válido) son requeridos.', [], 400);
}

if (!isset($_FILES['archivo'])) {
    responderJson(false, 'No se recibió ningún archivo.', [], 400);
}

$errorValidacion = validarPdf($_FILES['archivo']);
if ($errorValidacion !== null) {
    responderJson(false, $errorValidacion, [], 400);
}

$ef = tipoAEf()[$tipo];

// Contexto (carrera/cohorte/PAO) para la jerarquía de carpetas en Drive — igual que I2.
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

// EF2 se topa a 100% si las horas evidenciadas >= horas planeadas en EF1.
// Buscamos el valor numérico que quedó guardado al validar el EF1 vigente.
$horasEf1Previas = null;
if ($ef === 'EF2') {
    $stmtHoras = $conexion->prepare(
        "SELECT v.valor_extraido
         FROM evidencia_validacion_pdf v
         JOIN evidencia_asignatura e ON e.id_evidencia_asig = v.id_evidencia_asig
         WHERE e.id_asignatura = ? AND e.vigente = 1 AND v.ef = 'EF1' AND v.punto_nombre = 'horas'
         ORDER BY v.fecha_validacion DESC LIMIT 1"
    );
    $stmtHoras->bind_param('i', $idAsignatura);
    $stmtHoras->execute();
    $filaHoras = $stmtHoras->get_result()->fetch_assoc();
    if ($filaHoras && $filaHoras['valor_extraido'] !== null && preg_match('/(\d+(?:[.,]\d+)?)/', $filaHoras['valor_extraido'], $m)) {
        $horasEf1Previas = (float) str_replace(',', '.', $m[1]);
    }
}

// Validación del PDF ANTES de subir a Drive (usa tmp_name, que existe durante todo el request).
try {
    $resultadoValidacion = validarPdfTutorias($_FILES['archivo']['tmp_name'], $ef, $horasEf1Previas);
} catch (Throwable $e) {
    responderJson(false, 'No se pudo leer el contenido del PDF.', ['detalle' => $e->getMessage()], 422);
}

$nombreArchivoDrive = sprintf('%s_%s_%s.pdf', $tipo, preg_replace('/[^A-Za-z0-9]+/', '_', $contexto['asignatura']), date('Ymd_His'));

try {
    $subida = subirArchivoDrive(
        $_FILES['archivo']['tmp_name'],
        $nombreArchivoDrive,
        $contexto['carrera'],
        $contexto['cohorte'],
        $contexto['pao'],
        $contexto['asignatura']
    );
} catch (Throwable $e) {
    responderJson(false, 'No se pudo subir el PDF a Google Drive.', ['detalle' => $e->getMessage()], 502);
}

$idUsuario = intval($_SESSION['id_usuario']);

$conexion->begin_transaction();
try {
    // Igual que I2: la evidencia anterior del mismo tipo/asignatura pasa a
    // vigente=0 (se conserva como historial), la nueva se marca vigente=1.
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
    $idEvidenciaAsig = (int) $stmtNuevo->insert_id;

    foreach ($resultadoValidacion['puntos'] as $orden => $punto) {
        $ordenSql = $orden + 1;
        $cumplidoInt = $punto['cumplido'] ? 1 : 0;
        $valorExtraido = $punto['valor']; // puede ser null; bind_param lo maneja bien.
        $stmtPunto = $conexion->prepare(
            'INSERT INTO evidencia_validacion_pdf (id_evidencia_asig, ef, punto_orden, punto_nombre, cumplido, valor_extraido)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        // Tipos: i(id_evidencia_asig) s(ef) i(punto_orden) s(punto_nombre) i(cumplido) s(valor_extraido) = "isisis".
        $stmtPunto->bind_param('isisis', $idEvidenciaAsig, $ef, $ordenSql, $punto['nombre'], $cumplidoInt, $valorExtraido);
        $stmtPunto->execute();
    }

    $conexion->commit();
} catch (Throwable $e) {
    $conexion->rollback();
    responderJson(false, 'No se pudo guardar la evidencia o su validación.', ['detalle' => $e->getMessage()], 500);
}

responderJson(true, 'Evidencia subida y validada correctamente.', [
    'datos' => [
        'id_evidencia_asig' => $idEvidenciaAsig,
        'url_archivo' => $subida['url_archivo'],
        'ef' => $ef,
        'puntos' => $resultadoValidacion['puntos'],
        'cumplidos' => count(array_filter($resultadoValidacion['puntos'], fn($p) => $p['cumplido'])),
        'total_puntos' => count($resultadoValidacion['puntos']),
    ],
]);