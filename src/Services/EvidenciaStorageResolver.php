<?php

declare(strict_types=1);

namespace App\Services;

use mysqli;

/**
 * Decide, para una carrera dada, qué implementación de
 * EvidenciaStorageInterface usar al SUBIR evidencia nueva (Drive o local),
 * leyendo `carreras.modo_almacenamiento`/`ruta_almacenamiento_local` (ver
 * plan_interruptor_almacenamiento.txt §4.3). Así los controllers
 * (TutoriasAcademicasController, SeguimientoSyllabusController) no
 * necesitan un `if modo === 'local'` propio -- piden el servicio ya
 * resuelto y lo usan igual que antes usaban GoogleDriveService
 * directamente.
 *
 * Para DESCARGAR evidencia ya subida no hace falta volver a mirar
 * `carreras` (y es más robusto no hacerlo: si el modo de la carrera
 * cambia después de que se subió un archivo, lo único que importa para
 * poder leerlo de vuelta es dónde quedó guardado realmente). Por eso
 * `resolverParaDescarga()` decide mirando la forma de `url_archivo`
 * (empieza con "http" => Drive; si no, es una ruta local) en vez de
 * consultar la carrera dueña.
 */
final class EvidenciaStorageResolver
{
    public function __construct(
        private readonly mysqli $conexion,
        private readonly GoogleDriveService $driveService,
    ) {
    }

    public function resolver(int $idCarrera): EvidenciaStorageInterface
    {
        $stmt = $this->conexion->prepare(
            'SELECT modo_almacenamiento, ruta_almacenamiento_local FROM carreras WHERE id_carrera = ?'
        );
        $stmt->bind_param('i', $idCarrera);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();

        // Default de la entrega: local. Solo se usa Drive si la carrera
        // tiene el modo 'drive' guardado explícitamente; si no se
        // encontró la carrera (no debería pasar, contextoParaDrive ya la
        // resolvió antes) o el modo es 'local'/desconocido, se usa
        // almacenamiento local.
        if ($fila !== null && ($fila['modo_almacenamiento'] ?? 'local') === 'drive') {
            return $this->driveService;
        }

        $rutaLocal = $fila !== null ? ($fila['ruta_almacenamiento_local'] ?? null) : null;

        return new AlmacenamientoLocalService($rutaLocal !== '' ? $rutaLocal : null);
    }

    public function resolverParaDescarga(string $urlArchivo): EvidenciaStorageInterface
    {
        if (str_starts_with($urlArchivo, 'http://') || str_starts_with($urlArchivo, 'https://')) {
            return $this->driveService;
        }

        // La raíz no importa para leer: url_archivo ya es la ruta absoluta
        // completa del archivo guardado.
        return new AlmacenamientoLocalService();
    }
}
