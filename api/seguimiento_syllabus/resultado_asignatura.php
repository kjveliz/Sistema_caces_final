<?php
require_once __DIR__ . '/_helpers.php';
iniciarEndpoint(['GET']);
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/_calculo.php';

$idAsignatura = isset($_GET['id_asignatura']) ? (int) $_GET['id_asignatura'] : 0;
$idEvaluacion = isset($_GET['id_evaluacion']) ? (int) $_GET['id_evaluacion'] : 0;

if ($idAsignatura <= 0 || $idEvaluacion <= 0) {
    responderJson(false, 'Parámetros id_asignatura e id_evaluacion son requeridos.', [], 400);
}

$stmt = $conexion->prepare('SELECT id_asignatura, nombre FROM asignatura WHERE id_asignatura = ?');
$stmt->bind_param('i', $idAsignatura);
$stmt->execute();
$asignatura = $stmt->get_result()->fetch_assoc();

if (!$asignatura) {
    responderJson(false, 'Asignatura no encontrada.', [], 404);
}

$resultado = calcularResultadoAsignatura($conexion, $idAsignatura, $asignatura['nombre'], $idEvaluacion);
guardarSnapshotSeguimiento($conexion, $idAsignatura, $idEvaluacion, $resultado);

responderJson(true, null, ['datos' => $resultado]);