<?php

declare(strict_types=1);

/**
 * Script de diagnóstico — SOLO LECTURA, no modifica nada en BD ni en Drive.
 *
 * Contexto: al migrar el almacenamiento de una carrera de Drive a local
 * (ver plan_interruptor_almacenamiento.txt), la migración es todo-o-nada y
 * se detiene en el PRIMER archivo que falla al descargarse de Drive. Si
 * una carrera tiene varios archivos "huérfanos" (su fila en BD apunta a un
 * id de Drive que ya no existe / no es accesible), descubrirlos uno por
 * uno reintentando la migración es muy lento.
 *
 * Este script recorre TODAS las evidencias de una carrera (tablas
 * `evidencias` e `evidencia_asignatura`) y, para cada una, intenta un
 * `files->get()` liviano (solo pide el campo `id`, no descarga contenido)
 * contra la API real de Drive, usando el mismo cliente ya autorizado que
 * usa el resto del sistema (api/google_drive/cliente_autorizado.php). Al
 * final imprime un resumen: cuántos archivos están OK y cuáles fallaron,
 * con el id de Drive y el error real de cada uno.
 *
 * Uso (desde la raíz del repo, en la máquina real — no en el sandbox, ya
 * que depende de vendor/ + token.json + acceso de red a Drive):
 *
 *   php scripts/diagnostico_evidencia_drive.php <id_carrera>
 *
 * Ejemplo:
 *   php scripts/diagnostico_evidencia_drive.php 1
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Infra\Database;
use Google\Service\Drive;
use Google\Service\Exception as GoogleServiceException;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$idCarrera = isset($argv[1]) ? (int) $argv[1] : 0;

if ($idCarrera <= 0) {
    fwrite(STDERR, "Uso: php scripts/diagnostico_evidencia_drive.php <id_carrera>\n");
    exit(1);
}

$conexion = Database::conectar();

// Mismo cliente autorizado que usa el resto del sistema para subir/leer
// de Drive (respeta token.json + refresh automático si venció).
$cliente = require __DIR__ . '/../api/google_drive/cliente_autorizado.php';
$drive = new Drive($cliente);

/**
 * @return list<array{tabla: string, id: int, nombre_archivo: string, url_archivo: string}>
 */
function recolectarEvidenciaAsignatura(mysqli $conexion, int $idCarrera): array
{
    $stmt = $conexion->prepare(
        'SELECT ea.id_evidencia_asig, ea.nombre_archivo, ea.url_archivo
         FROM evidencia_asignatura ea
         JOIN asignatura a ON a.id_asignatura = ea.id_asignatura
         JOIN periodo_academico p ON p.id_periodoacademico = a.id_periodoacademico
         JOIN cohortes co ON co.id_cohorte = p.id_cohorte
         WHERE co.id_carrera = ?
         ORDER BY ea.id_evidencia_asig',
    );
    $stmt->bind_param('i', $idCarrera);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $items = [];
    while ($fila = $resultado->fetch_assoc()) {
        $items[] = [
            'tabla' => 'evidencia_asignatura',
            'id' => (int) $fila['id_evidencia_asig'],
            'nombre_archivo' => $fila['nombre_archivo'],
            'url_archivo' => $fila['url_archivo'],
        ];
    }

    return $items;
}

/**
 * @return list<array{tabla: string, id: int, nombre_archivo: string, url_archivo: string}>
 */
function recolectarEvidenciasCatalogo(mysqli $conexion, int $idCarrera): array
{
    $stmt = $conexion->prepare(
        'SELECT e.id_evidencia, e.nombre_archivo, e.url_archivo
         FROM evidencias e
         JOIN evaluaciones ev ON ev.id_evaluacion = e.id_evaluacion
         WHERE ev.id_carrera = ?
         ORDER BY e.id_evidencia',
    );
    $stmt->bind_param('i', $idCarrera);
    $stmt->execute();
    $resultado = $stmt->get_result();

    $items = [];
    while ($fila = $resultado->fetch_assoc()) {
        $items[] = [
            'tabla' => 'evidencias',
            'id' => (int) $fila['id_evidencia'],
            'nombre_archivo' => $fila['nombre_archivo'],
            'url_archivo' => $fila['url_archivo'],
        ];
    }

    return $items;
}

function extraerFileId(string $urlArchivo): ?string
{
    if (preg_match('#/file/d/([a-zA-Z0-9_-]+)#', $urlArchivo, $m)) {
        return $m[1];
    }

    // Por si algún url_archivo viejo guarda el id "pelado" en vez de la URL completa.
    if (preg_match('#^[a-zA-Z0-9_-]{10,}$#', $urlArchivo)) {
        return $urlArchivo;
    }

    return null;
}

$items = [
    ...recolectarEvidenciaAsignatura($conexion, $idCarrera),
    ...recolectarEvidenciasCatalogo($conexion, $idCarrera),
];

if ($items === []) {
    echo "No se encontraron evidencias para id_carrera={$idCarrera}.\n";
    exit(0);
}

echo "Verificando " . count($items) . " archivo(s) de Drive para id_carrera={$idCarrera}...\n\n";

$rotos = [];
$ok = 0;

foreach ($items as $item) {
    $fileId = extraerFileId($item['url_archivo']);

    if ($fileId === null) {
        $rotos[] = $item + ['error' => 'No se pudo extraer un id de Drive de la url_archivo guardada.'];
        continue;
    }

    try {
        $drive->files->get($fileId, ['fields' => 'id']);
        $ok++;
    } catch (GoogleServiceException $e) {
        $rotos[] = $item + ['file_id' => $fileId, 'error' => $e->getMessage()];
    } catch (Throwable $e) {
        $rotos[] = $item + ['file_id' => $fileId, 'error' => 'Error inesperado: ' . $e->getMessage()];
    }
}

echo "OK: {$ok} archivo(s) accesibles en Drive.\n";
echo "ROTOS: " . count($rotos) . " archivo(s).\n\n";

foreach ($rotos as $item) {
    echo "- [{$item['tabla']} #{$item['id']}] {$item['nombre_archivo']}\n";
    echo "    fileId: " . ($item['file_id'] ?? '(no extraído)') . "\n";
    echo "    error:  {$item['error']}\n\n";
}
