<?php
require_once __DIR__ . '/_helpers.php';
iniciarEndpoint(['GET']);
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/_calculo.php';

$idCohorte = isset($_GET['id_cohorte']) ? (int) $_GET['id_cohorte'] : 0;
$idEvaluacion = isset($_GET['id_evaluacion']) ? (int) $_GET['id_evaluacion'] : 0;
$idPeriodo = isset($_GET['id_periodo']) && $_GET['id_periodo'] !== '' ? (int) $_GET['id_periodo'] : null;

if ($idCohorte <= 0 || $idEvaluacion <= 0) {
    responderJson(false, 'Parámetros id_cohorte e id_evaluacion son requeridos.', [], 400);
}

$resultado = calcularResultadoGeneral($conexion, $idCohorte, $idPeriodo, $idEvaluacion);

foreach ($resultado['detalle_asignaturas'] as $r) {
    guardarSnapshotSeguimiento($conexion, $r['id_asignatura'], $idEvaluacion, $r);
}

responderJson(true, null, ['datos' => $resultado]);