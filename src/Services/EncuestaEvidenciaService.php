<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SeguimientoSyllabusRepository;
use Throwable;

/**
 * Descarga (con caché en disco) el CSV de la encuesta de heteroevaluación
 * propio de una asignatura, y delega el cálculo puro a
 * EncuestaCalculoService. Puerto 1:1 de la parte con I/O de
 * api/seguimiento_syllabus/_encuesta.php (descargarCsvEncuesta,
 * calcularEfDesdeCsv, obtenerDetalleEncuesta) — misma ruta de caché, mismo
 * TTL, mismo criterio de degradación si la descarga falla.
 *
 * Desde el interruptor de almacenamiento por carrera (ver
 * plan_interruptor_almacenamiento.txt): la descarga ya no asume Drive
 * directamente, resuelve vía EvidenciaStorageResolver::resolverParaDescarga()
 * según la forma de la URL guardada (Drive o local).
 */
final class EncuestaEvidenciaService
{
    private const TIPO_EVIDENCIA_ENCUESTA = 'encuesta_csv';

    /** TTL del caché en disco, en segundos. */
    private const TTL_CACHE_CSV_SEGUNDOS = 60;

    /** Memoización por petición (una descarga por asignatura, aunque se llame varias veces). */
    private array $cache = [];

    public function __construct(
        private readonly SeguimientoSyllabusRepository $repositorio,
        private readonly EvidenciaStorageResolver $storageResolver,
    ) {
    }

    private function rutaCacheCsv(int $idAsignatura): string
    {
        return sys_get_temp_dir() . '/seguimiento_syllabus_encuesta_cache_asignatura_' . $idAsignatura . '.csv';
    }

    /** @return array{filas: array<int, array<int, string>>, degradado: bool}|null */
    public function descargarCsvEncuesta(int $idAsignatura): ?array
    {
        if (array_key_exists($idAsignatura, $this->cache)) {
            return $this->cache[$idAsignatura];
        }

        $rutaCache = $this->rutaCacheCsv($idAsignatura);

        if (is_readable($rutaCache) && (time() - filemtime($rutaCache)) < self::TTL_CACHE_CSV_SEGUNDOS) {
            $cacheContenido = file_get_contents($rutaCache);
            if ($cacheContenido !== false) {
                $this->cache[$idAsignatura] = ['filas' => EncuestaCalculoService::parseCsvString($cacheContenido), 'degradado' => false];

                return $this->cache[$idAsignatura];
            }
        }

        $urlArchivo = $this->repositorio->buscarUrlCsvEncuestaVigente($idAsignatura, self::TIPO_EVIDENCIA_ENCUESTA);

        if ($urlArchivo === null) {
            // Esta asignatura todavía no tiene CSV de encuesta propio
            // subido -- no es un error.
            $this->cache[$idAsignatura] = null;

            return null;
        }

        try {
            $contenido = $this->storageResolver->resolverParaDescarga($urlArchivo)->descargarContenido($urlArchivo);
        } catch (Throwable $e) {
            error_log('EncuestaEvidenciaService: no se pudo descargar el CSV de encuesta: ' . $e->getMessage());
            $contenido = null;
        }

        if ($contenido !== null) {
            @file_put_contents($rutaCache, $contenido);
            $this->cache[$idAsignatura] = ['filas' => EncuestaCalculoService::parseCsvString($contenido), 'degradado' => false];

            return $this->cache[$idAsignatura];
        }

        // Descarga fallida (p.ej. Drive desconectado): como último recurso
        // se usa el caché en disco aunque esté vencido.
        if (is_readable($rutaCache)) {
            $cacheContenido = file_get_contents($rutaCache);
            if ($cacheContenido !== false) {
                $this->cache[$idAsignatura] = ['filas' => EncuestaCalculoService::parseCsvString($cacheContenido), 'degradado' => true];

                return $this->cache[$idAsignatura];
            }
        }

        $this->cache[$idAsignatura] = null;

        return null;
    }

    /** @return array{ef1: float, ef4: float, respuestas: int, promedio_general: float, degradado: bool}|null */
    public function calcularEfDesdeCsv(int $idAsignatura): ?array
    {
        $csv = $this->descargarCsvEncuesta($idAsignatura);
        if ($csv === null) {
            return null;
        }

        return EncuestaCalculoService::calcularEfDesdeFilas($csv['filas'], $csv['degradado']);
    }

    /** @return array{respuestas_totales_materia: int, preguntas: array<int, array{numero: int, texto: string|null, es_ef1: bool, es_ef4: bool, conteos: array<string, int>, total: int}>}|null */
    public function obtenerDetalleEncuesta(int $idAsignatura): ?array
    {
        $csv = $this->descargarCsvEncuesta($idAsignatura);
        if ($csv === null) {
            return null;
        }

        return EncuestaCalculoService::detalleDesdeFilas($csv['filas']);
    }
}
