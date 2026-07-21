<?php
require_once __DIR__ . '/_helpers.php';
iniciarEndpoint(['GET', 'POST']);
require_once __DIR__ . '/../conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $idPeriodo = isset($_GET['id_periodo']) ? (int) $_GET['id_periodo'] : 0;
    if ($idPeriodo <= 0) {
        responderJson(false, 'Parámetro id_periodo es requerido.', [], 400);
    }
    $stmt = $conexion->prepare(
        'SELECT id_asignatura, nombre, docente FROM asignatura WHERE id_periodoacademico = ? ORDER BY nombre'
    );
    $stmt->bind_param('i', $idPeriodo);
    $stmt->execute();
    responderJson(true, null, ['datos' => $stmt->get_result()->fetch_all(MYSQLI_ASSOC)]);
}

// POST: crear asignatura (equivalente a get_or_create de Django).
$datos = leerJsonBody();
$idPeriodo = (int) ($datos['id_periodo'] ?? 0);
$nombre = trim($datos['nombre'] ?? '');
$docente = trim($datos['docente'] ?? '');

if ($idPeriodo <= 0 || $nombre === '') {
    responderJson(false, 'id_periodo y nombre son requeridos.', [], 400);
}

$stmt = $conexion->prepare('SELECT id_asignatura FROM asignatura WHERE id_periodoacademico = ? AND nombre = ?');
$stmt->bind_param('is', $idPeriodo, $nombre);
$stmt->execute();
$existente = $stmt->get_result()->fetch_assoc();

if ($existente) {
    responderJson(true, 'La asignatura ya existía.', ['datos' => ['id_asignatura' => (int) $existente['id_asignatura']]]);
}

$stmt = $conexion->prepare(
    'INSERT INTO asignatura (id_periodoacademico, nombre, docente, fecha_creacion) VALUES (?, ?, ?, CURDATE())'
);
$docenteParam = $docente !== '' ? $docente : null;
$stmt->bind_param('iss', $idPeriodo, $nombre, $docenteParam);
$stmt->execute();

responderJson(true, 'Asignatura creada.', ['datos' => ['id_asignatura' => (int) $stmt->insert_id]]);