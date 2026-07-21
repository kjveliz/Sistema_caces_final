<?php
require_once __DIR__ . '/_helpers.php';
iniciarEndpoint(['GET']);
require_once __DIR__ . '/../conexion.php';
require_once __DIR__ . '/_calculo.php';

$idAsignatura = isset($_GET['id_asignatura']) ? (int) $_GET['id_asignatura'] : 0;
if ($idAsignatura <= 0) {
    responderJson(false, 'Parámetro id_asignatura es requerido.', [], 400);
}

$stmt = $conexion->prepare(
    'SELECT id_evidencia_asig, tipo, nombre_archivo, url_archivo, subido_por, fecha_subida
     FROM evidencia_asignatura
     WHERE id_asignatura = ? AND vigente = 1'
);
$stmt->bind_param('i', $idAsignatura);
$stmt->execute();
$filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$etiquetas = etiquetasEvidencia();
$porTipo = [];
foreach (TIPOS_POR_ASIGNATURA as $tipo) {
    $porTipo[$tipo] = ['tipo' => $tipo, 'label' => $etiquetas[$tipo], 'subida' => false, 'archivo' => null];
}
foreach ($filas as $f) {
    $porTipo[$f['tipo']]['subida'] = true;
    $porTipo[$f['tipo']]['archivo'] = [
        'id_evidencia_asig' => (int) $f['id_evidencia_asig'],
        'nombre_archivo' => $f['nombre_archivo'],
        'url_archivo' => $f['url_archivo'],
        'subido_por' => $f['subido_por'],
        'fecha_subida' => $f['fecha_subida'],
    ];
}

responderJson(true, null, ['datos' => array_values($porTipo)]);