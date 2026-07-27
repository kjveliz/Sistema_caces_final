<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Contrato común para el guardado de evidencia (I2/I3 por ahora — ver
 * plan_interruptor_almacenamiento.txt §5), sin importar el destino final
 * (Google Drive o almacenamiento local). Implementado por
 * GoogleDriveService (destino 'drive') y AlmacenamientoLocalService
 * (destino 'local'), para que los controllers y EvidenciaResolverService
 * puedan intercambiarlos sin condicionales de "if modo === drive" fuera
 * de este paquete de servicios.
 *
 * Mismas firmas que ya usaba GoogleDriveService antes de este cambio —
 * no se renombra nada de lo existente, solo se declara el contrato.
 */
interface EvidenciaStorageInterface
{
    /**
     * Sube (o reemplaza si ya existe) un archivo dentro del árbol
     * Carrera / Cohorte / PAO / Asignatura del destino que corresponda.
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
    ): array;

    /**
     * Descarga el contenido de un archivo ya subido, a partir del
     * url_archivo guardado en BD. Devuelve null si el origen no se pudo
     * resolver (mismo criterio de degradación que ya usa
     * GoogleDriveService::descargarContenidoDrive() para EncuestaEvidenciaService).
     */
    public function descargarContenido(string $urlArchivo): ?string;

    /** Validación de PDF (extensión + MIME real + tamaño máximo). */
    public function validarArchivoSubido(array $archivo): ?string;

    /** Validación de CSV (extensión + MIME real + tamaño máximo). */
    public function validarCsv(array $archivo): ?string;
}
