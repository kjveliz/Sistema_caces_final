<?php
require_once __DIR__ . '/_helpers.php';
iniciarEndpoint(['GET']);
require_once __DIR__ . '/../conexion.php';

$idCohorte = isset($_GET['id_cohorte']) ? (int) $_GET['id_cohorte'] : 0;
if ($idCohorte <= 0) {
    responderJson(false, 'Parámetro id_cohorte es requerido.', [], 400);
}

$stmt = $conexion->prepare(
    'SELECT id_periodoacademico, nombre, orden, fecha_inicio, fecha_fin
     FROM periodo_academico WHERE id_cohorte = ? ORDER BY orden'
);
$stmt->bind_param('i', $idCohorte);
$stmt->execute();

responderJson(true, null, ['datos' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);