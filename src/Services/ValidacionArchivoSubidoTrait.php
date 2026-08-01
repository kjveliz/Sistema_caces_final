<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Validación de tamaño/extensión/MIME de un archivo subido ($_FILES o su
 * shape equivalente), extraída de GoogleDriveService al agregar
 * AlmacenamientoLocalService (interruptor de almacenamiento por carrera —
 * ver plan_interruptor_almacenamiento.txt §4.2) para no duplicar la misma
 * lógica en ambas implementaciones de EvidenciaStorageInterface. Mismo
 * comportamiento exacto que tenía GoogleDriveService antes de este
 * cambio — no se tocaron límites ni mensajes.
 */
trait ValidacionArchivoSubidoTrait
{
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

    /**
     * Validación de Excel (.xlsx) para el `tipo_esperado=xlsx` de
     * `api/google_drive/subir_archivo.php` (Parte 21 del plan de migración
     * slim-legacy — endpoint genérico compartido por I1/I4/I5, no
     * exclusivo de la generación de PAO/Asignaturas del plan de malla
     * curricular). Puerto 1:1 de la validación inline del original: mismo
     * límite de tamaño que validarPdf/validarCsv, exige extensión .xlsx +
     * MIME real dentro del set aceptado.
     *
     * El set de MIME acepta el real de Office
     * (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)
     * y también `application/zip`/`application/octet-stream`: un .xlsx es
     * un zip por dentro y algunos entornos (sobre todo finfo en
     * Windows/XAMPP) lo detectan así según la versión de libmagic — se
     * valida siempre junto con la extensión .xlsx para no depender solo
     * del mime (mismo comentario que ya traía el original).
     */
    public function validarXlsx(array $archivo): ?string
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
        $mimesValidos = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/octet-stream',
        ];
        if ($extension !== 'xlsx' || !in_array($mime, $mimesValidos, true)) {
            return 'Solo se aceptan archivos Excel (.xlsx) válidos.';
        }

        return null;
    }
}
