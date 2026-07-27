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
}
