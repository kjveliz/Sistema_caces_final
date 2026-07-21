<?php
require_once __DIR__ . '/_helpers.php';
iniciarEndpoint(['GET']);
require_once __DIR__ . '/_encuesta.php';
require_once __DIR__ . '/../conexion.php';

$idAsignatura = isset($_GET['id_asignatura']) ? (int) $_GET['id_asignatura'] : 0;
$idEvaluacion = isset($_GET['id_evaluacion']) ? (int) $_GET['id_evaluacion'] : 0;

// id_evaluacion se sigue aceptando por compatibilidad con el frontend
// (services/seguimientoSyllabus.ts sigue mandando ambos), pero ya no se usa
// para buscar el CSV -- el CSV de la encuesta vive directamente ligado a la
// asignatura (tipo 'encuesta_csv' en evidencia_asignatura, ver MEMORIA v18).
if ($idAsignatura <= 0) {
    responderJson(false, 'Parámetro id_asignatura es requerido.', [], 400);
}

$stmt = $conexion->prepare('SELECT nombre FROM asignatura WHERE id_asignatura = ?');
$stmt->bind_param('i', $idAsignatura);
$stmt->execute();
$fila = $stmt->get_result()->fetch_assoc();
if (!$fila) {
    responderJson(false, 'Asignatura no encontrada.', [], 404);
}

$detalle = obtenerDetalleEncuesta($conexion, $idAsignatura);

if ($detalle === null) {
    responderJson(false, 'Esta asignatura todavía no tiene un CSV de encuesta subido.', [], 502);
}

responderJson(true, null, ['datos' => $detalle]);
