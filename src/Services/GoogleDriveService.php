<?php

declare(strict_types=1);

namespace App\Services;

use Google\Service\Drive;
use Google\Service\Drive\DriveFile as GoogleDriveFile;
use Google\Service\Drive\Permission as GoogleDrivePermission;
use Google\Service\Exception as GoogleServiceException;
use RuntimeException;
use Throwable;

/**
 * Integración real con Google Drive, compartida entre I2 (Seguimiento
 * Syllabus) y I3 (Tutorías Académicas).
 *
 * Hasta la migración de I2 (Fase 3), esta clase era un envoltorio delgado
 * que hacía `require_once` de api/seguimiento_syllabus/_google_drive.php
 * (ver MEMORIA v63/§40): la lógica real vivía ahí porque I2 todavía no se
 * había migrado y mover el archivo entero hubiera sido tocar código fuera
 * del alcance de la sesión de I3. Ahora que I2 SÍ se migra, la lógica de
 * ese archivo se trae 1:1 (mismas queries, mismo seam de testing, mismo
 * criterio de validación) directamente a esta clase, y `_google_drive.php`
 * se elimina. `api/google_drive/drive_helpers.php` (obtenerEstructuraCaces,
 * obtenerOCrearCarpeta, escaparConsultaDrive) y
 * `api/google_drive/cliente_autorizado.php` NO se tocan -- son la
 * integración base de Drive, compartida con más de estos dos indicadores,
 * y quedan fuera de alcance de esta migración.
 *
 * Métodos nuevos respecto a la versión usada solo por I3:
 * `validarCsv()` (I2 sube el CSV de la encuesta, tipo 'encuesta_csv') y
 * `descargarContenidoDrive()` (I2 necesita leer de vuelta ese CSV para
 * calcular EF1/EF4 -- ver EncuestaEvidenciaService). Los métodos que ya
 * usaba I3 (`subirArchivo`, `validarArchivoSubido`) mantienen exactamente
 * la misma firma y comportamiento.
 */
final class GoogleDriveService
{
    public function __construct()
    {
        require_once __DIR__ . '/../../api/google_drive/drive_helpers.php';
    }

    /**
     * Sube (o reemplaza si ya existe) un archivo dentro de:
     *   Sistema CACES / <carrera> / <cohorte> / <pao> / <asignatura> / archivo.*
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
        // Seam de testing (Fase 5): en APP_ENV=testing se evita el llamado
        // real a Google Drive -- no hay credenciales de prueba disponibles.
        if ((getenv('APP_ENV') ?: '') === 'testing') {
            return [
                'id_archivo' => 'fake-drive-id-' . bin2hex(random_bytes(4)),
                'nombre_archivo' => $nombreArchivo,
                'url_archivo' => 'https://drive.google.com/fake-test-double/' . rawurlencode($nombreArchivo),
            ];
        }

        $cliente = require __DIR__ . '/../../api/google_drive/cliente_autorizado.php';
        $drive = new Drive($cliente);

        $estructura = obtenerEstructuraCaces($drive, $nombreCarrera, $cohorte);
        $idCarpetaPao = obtenerOCrearCarpeta($drive, $pao, $estructura['cohorte']);
        $idCarpetaAsignatura = obtenerOCrearCarpeta($drive, $asignatura, $idCarpetaPao);

        $nombreSeguro = escaparConsultaDrive($nombreArchivo);
        $idCarpetaSeguro = escaparConsultaDrive($idCarpetaAsignatura);

        $consulta = sprintf(
            "name = '%s' and '%s' in parents and trashed = false",
            $nombreSeguro,
            $idCarpetaSeguro,
        );

        $existentes = $drive->files->listFiles([
            'q' => $consulta,
            'spaces' => 'drive',
            'fields' => 'files(id,name)',
            'pageSize' => 10,
        ])->getFiles();

        $contenido = file_get_contents($rutaTemporal);
        if ($contenido === false) {
            throw new RuntimeException('No se pudo leer el archivo temporal.');
        }

        if (count($existentes) > 0) {
            $idArchivo = $existentes[0]->getId();
            $archivoDrive = $drive->files->update(
                $idArchivo,
                new GoogleDriveFile(['name' => $nombreArchivo]),
                ['data' => $contenido, 'mimeType' => $mimeType, 'uploadType' => 'multipart', 'fields' => 'id,name,webViewLink,webContentLink'],
            );
        } else {
            $metadata = new GoogleDriveFile([
                'name' => $nombreArchivo,
                'parents' => [$idCarpetaAsignatura],
            ]);
            $archivoDrive = $drive->files->create(
                $metadata,
                ['data' => $contenido, 'mimeType' => $mimeType, 'uploadType' => 'multipart', 'fields' => 'id,name,webViewLink,webContentLink'],
            );
        }

        $idArchivoDrive = $archivoDrive->getId();

        try {
            $drive->permissions->create(
                $idArchivoDrive,
                new GoogleDrivePermission(['type' => 'anyone', 'role' => 'reader']),
                ['fields' => 'id'],
            );
        } catch (GoogleServiceException $errorPermiso) {
            if (intval($errorPermiso->getCode()) !== 409) {
                throw $errorPermiso;
            }
        }

        $archivoDrive = $drive->files->get($idArchivoDrive, ['fields' => 'id,name,webViewLink,webContentLink']);

        return [
            'id_archivo' => $archivoDrive->getId(),
            'nombre_archivo' => $archivoDrive->getName(),
            'url_archivo' => $archivoDrive->getWebViewLink(),
        ];
    }

    /**
     * Descarga el contenido de un archivo de Drive dado su webViewLink
     * (https://drive.google.com/file/d/{ID}/view...). Puerto 1:1 de
     * `_descargarContenidoDrive()` (api/seguimiento_syllabus/_encuesta.php).
     * Devuelve null si la URL no tiene la forma esperada; deja propagar
     * cualquier excepción del cliente de Drive (token vencido, etc.) para
     * que el llamador decida cómo degradar (ver EncuestaEvidenciaService).
     */
    public function descargarContenidoDrive(string $urlArchivo): ?string
    {
        if (!preg_match('#/d/([a-zA-Z0-9_-]+)#', $urlArchivo, $m)) {
            error_log("GoogleDriveService: no se pudo extraer el id de Drive de '{$urlArchivo}'.");

            return null;
        }
        $idArchivo = $m[1];

        $cliente = require __DIR__ . '/../../api/google_drive/cliente_autorizado.php';
        $drive = new Drive($cliente);

        $respuesta = $drive->files->get($idArchivo, ['alt' => 'media']);

        return $respuesta->getBody()->getContents();
    }

    /** Validación de PDF: extensión + MIME real + 25MB. */
    public function validarArchivoSubido(array $archivo): ?string
    {
        return $this->validarPdf($archivo);
    }

    private function validarPdf(array $archivo): ?string
    {
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            return 'Ocurrió un error al recibir el archivo.';
        }
        $tamanoMaximo = 25 * 1024 * 1024;
        if ($archivo['size'] > $tamanoMaximo) {
            return 'El archivo no debe superar los 25 MB.';
        }
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($archivo['tmp_name']);
        if ($extension !== 'pdf' || $mime !== 'application/pdf') {
            return 'Solo se aceptan archivos PDF válidos.';
        }

        return null;
    }

    /**
     * Validación de CSV para el slot 'encuesta_csv' de I2 (ver MEMORIA
     * v18): mismo límite de tamaño que validarPdf, pero exige extensión
     * .csv y un MIME real de texto plano/csv.
     */
    public function validarCsv(array $archivo): ?string
    {
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            return 'Ocurrió un error al recibir el archivo.';
        }
        $tamanoMaximo = 25 * 1024 * 1024;
        if ($archivo['size'] > $tamanoMaximo) {
            return 'El archivo no debe superar los 25 MB.';
        }
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if ($extension !== 'csv') {
            return 'Solo se aceptan archivos CSV.';
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($archivo['tmp_name']);
        $mimesValidos = ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'];
        if (!in_array($mime, $mimesValidos, true)) {
            return 'Solo se aceptan archivos CSV válidos.';
        }

        return null;
    }
}
