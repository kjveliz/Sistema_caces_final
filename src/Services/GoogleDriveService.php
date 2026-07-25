<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Envoltorio de clase sobre api/seguimiento_syllabus/_google_drive.php.
 *
 * Decisión deliberada de esta migración: NO se reescribe ni se duplica la
 * integración con Google Drive (eso es un cambio mucho más grande, fuera
 * del alcance de este POC de Fase 3). subirArchivoDrive() y validarPdf() ya
 * son compartidas hoy entre I2 y I3 — este wrapper solo les da una interfaz
 * de clase para que el nuevo TutoriasAcademicasController no dependa de
 * funciones globales sueltas, sin tocar una sola línea de la lógica real de
 * Drive.
 */
final class GoogleDriveService
{
    public function __construct()
    {
        require_once __DIR__ . '/../../api/seguimiento_syllabus/_google_drive.php';
    }

    /**
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
        return subirArchivoDrive($rutaTemporal, $nombreArchivo, $nombreCarrera, $cohorte, $pao, $asignatura, $mimeType);
    }

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $archivo
     */
    public function validarArchivoSubido(array $archivo): ?string
    {
        return validarPdf($archivo);
    }
}
