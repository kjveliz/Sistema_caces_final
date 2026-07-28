<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Guardado de evidencia en el disco del servidor (o una ruta externa,
 * ej. pendrive/disco montado), como alternativa a GoogleDriveService.
 * Ver plan_interruptor_almacenamiento.txt §4.2.
 *
 * Mismo árbol de carpetas que usa Drive hoy, pero en filesystem:
 *   <raiz>/<Carrera>/<Cohorte>/<PAO>/<Asignatura>/archivo.*
 *
 * <raiz> es `ruta_almacenamiento_local` (columna nueva en `carreras`,
 * migración 20260727010000) si la carrera la definió, o
 * self::raizPorDefecto() si no -- la decisión de cuál raíz usar la toma
 * quien construye esta clase (el resolver por carrera, paso 3 del plan),
 * no esta clase misma.
 */
final class AlmacenamientoLocalService implements EvidenciaStorageInterface
{
    use ValidacionArchivoSubidoTrait;

    private readonly string $raiz;

    public function __construct(?string $raiz = null)
    {
        $raizNormalizada = $raiz !== null ? trim($raiz) : '';
        $this->raiz = rtrim($raizNormalizada !== '' ? $raizNormalizada : self::raizPorDefecto(), '/\\');
    }

    /** Ruta local por defecto del sistema cuando la carrera no definió una propia. */
    public static function raizPorDefecto(): string
    {
        return dirname(__DIR__, 2) . '/storage/evidencias';
    }

    /**
     * Sube (o reemplaza si ya existe) un archivo dentro de:
     *   <raiz> / <carrera> / <cohorte> / <pao> / <asignatura> / archivo.*
     *
     * mimeType se ignora acá (a diferencia de Drive, el filesystem no
     * necesita que se lo indiquemos) -- se mantiene en la firma solo para
     * cumplir EvidenciaStorageInterface.
     *
     * @return array{nombre_archivo: string, url_archivo: string, id_archivo: string}
     */
    public function subirArchivo(
        string $rutaTemporal,
        string $nombreArchivo,
        string $nombreCarrera,
        string $cohorte,
        string $pao,
        string $asignatura,
        string $mimeType = 'application/pdf',
    ): array {
        $carpetaDestino = implode('/', [
            $this->raiz,
            self::sanearSegmento($nombreCarrera),
            self::sanearSegmento($cohorte),
            self::sanearSegmento($pao),
            self::sanearSegmento($asignatura),
        ]);

        if (!is_dir($carpetaDestino) && !mkdir($carpetaDestino, 0775, true) && !is_dir($carpetaDestino)) {
            throw new RuntimeException("No se pudo crear el directorio de almacenamiento local: {$carpetaDestino}");
        }

        $contenido = file_get_contents($rutaTemporal);
        if ($contenido === false) {
            throw new RuntimeException('No se pudo leer el archivo temporal.');
        }

        $rutaDestino = $carpetaDestino . '/' . self::sanearNombreArchivo($nombreArchivo);
        if (file_put_contents($rutaDestino, $contenido) === false) {
            throw new RuntimeException("No se pudo escribir el archivo en almacenamiento local: {$rutaDestino}");
        }

        return [
            // No hay un ID externo como en Drive; se deriva uno estable de
            // la ruta para no dejar el campo vacío en quien consuma este array.
            'id_archivo' => 'local-' . hash('sha256', $rutaDestino),
            'nombre_archivo' => basename($rutaDestino),
            'url_archivo' => $rutaDestino,
        ];
    }

    /**
     * Lee el contenido de un archivo ya guardado localmente, a partir del
     * url_archivo (ruta absoluta) guardado en BD. Devuelve null si la
     * ruta no existe o no es un archivo -- mismo criterio de degradación
     * que GoogleDriveService::descargarContenidoDrive().
     */
    public function descargarContenido(string $urlArchivo): ?string
    {
        if (!is_file($urlArchivo)) {
            error_log("AlmacenamientoLocalService: no existe el archivo local '{$urlArchivo}'.");

            return null;
        }

        $contenido = file_get_contents($urlArchivo);

        return $contenido === false ? null : $contenido;
    }

    /**
     * Elimina un archivo ya guardado localmente, dado su url_archivo (ruta
     * absoluta). Parte del contrato común (EvidenciaStorageInterface),
     * usada por EvidenciaMigradorService para revertir una migración a
     * medias. Devuelve false (sin lanzar) si la ruta no existe o no se
     * pudo borrar -- el llamador decide qué hacer con una reversión que no
     * se pudo completar del todo.
     */
    public function eliminarArchivo(string $urlArchivo): bool
    {
        if (!is_file($urlArchivo)) {
            return false;
        }

        return unlink($urlArchivo);
    }

    /**
     * Evita que un nombre de carrera/cohorte/pao/asignatura con "/", ".."
     * u otros caracteres raros pueda escaparse de <raiz> (path traversal)
     * o crear subcarpetas no intencionadas. Mismo criterio ya usado en
     * TutoriasAcademicasController::evidenciaSubir() para armar nombres
     * de archivo seguros (preg_replace a alfanumérico).
     */
    private static function sanearSegmento(string $segmento): string
    {
        $limpio = trim(preg_replace('/[^A-Za-z0-9 _-]+/', '_', $segmento) ?? '');

        return $limpio !== '' ? $limpio : '_';
    }

    /** Igual que sanearSegmento() pero preservando el punto de la extensión. */
    private static function sanearNombreArchivo(string $nombreArchivo): string
    {
        $limpio = trim(preg_replace('/[^A-Za-z0-9 _.-]+/', '_', $nombreArchivo) ?? '');

        return $limpio !== '' ? $limpio : 'archivo';
    }
}
