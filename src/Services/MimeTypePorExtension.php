<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Resuelve el Content-Type real a partir de la extensión del archivo, para
 * los visores de evidencia (EvidenciaAsignaturaVisorController y
 * api/google_drive/ver_archivo.php).
 *
 * Antes ambos visores mandaban `Content-Type: application/pdf` para
 * cualquier archivo que no fuera `.csv`, sin mirar la extensión real. Eso
 * funcionaba mientras la única evidencia no-CSV era PDF, pero la Parte E
 * del plan de malla curricular xlsx habilitó subir la Malla Curricular
 * (DOC.SYL.01) como `.xlsx` -- el navegador recibía esos bytes reales
 * etiquetados como PDF y su visor nativo de PDF fallaba con "No podemos
 * abrir este archivo" (reportado en vivo durante la Parte F, verificando
 * la Malla Curricular de una carrera de prueba real). El bug estaba en
 * AMBOS visores (local y Google Drive) por igual, así que migrar a Drive
 * no lo resuelve por sí solo -- de ahí este helper único para los dos.
 *
 * Nota aparte: que el Content-Type sea correcto no implica que el
 * navegador pueda previsualizar el archivo inline dentro de un <iframe>
 * (no hay visor nativo para .xlsx/.docx, a diferencia de PDF/imágenes).
 * Ese es un problema de UI distinto, resuelto en el frontend
 * (useEvidenciasIndicador.ts) mostrando un mensaje de "vista previa no
 * disponible" en vez de un iframe roto para esas extensiones.
 */
final class MimeTypePorExtension
{
    private const MAPA = [
        'pdf' => 'application/pdf',
        'csv' => 'text/csv',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'xls' => 'application/vnd.ms-excel',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc' => 'application/msword',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'ppt' => 'application/vnd.ms-powerpoint',
    ];

    public static function resolver(string $nombreArchivo): string
    {
        $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));

        return self::MAPA[$extension] ?? 'application/octet-stream';
    }
}
