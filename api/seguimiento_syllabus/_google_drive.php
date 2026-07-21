<?php
/**
 * Conecta con la integración REAL de Google Drive que ya existe en
 * api/google_drive/ (drive_helpers.php + cliente_autorizado.php + vendor de
 * google/apiclient vía Composer). No se duplica autenticación ni carpetas
 * raíz: se reusa exactamente la misma jerarquía "Sistema CACES / Carrera /
 * Cohorte" que ya crea obtenerEstructuraCaces(), extendida acá con 2
 * niveles más (PAO / Asignatura) usando la misma obtenerOCrearCarpeta()
 * genérica que ya trae drive_helpers.php.
 */

require_once __DIR__ . '/../google_drive/drive_helpers.php';

/**
 * Sube (o reemplaza si ya existe) un archivo dentro de:
 *   Sistema CACES / <carrera> / <cohorte> / <pao> / <asignatura> / archivo.*
 *
 * $mimeType por defecto sigue siendo PDF (todos los llamadores existentes
 * son PDF); el slot de CSV de encuesta (tipo 'encuesta_csv', ver MEMORIA
 * v18) es el único que pasa 'text/csv' explícitamente.
 *
 * @return array{nombre_archivo:string, url_archivo:string, id_archivo:string}
 */
function subirArchivoDrive(
    string $rutaTemporal,
    string $nombreArchivo,
    string $nombreCarrera,
    string $cohorte,
    string $pao,
    string $asignatura,
    string $mimeType = 'application/pdf'
): array {
    $cliente = require __DIR__ . '/../google_drive/cliente_autorizado.php';
    $drive = new Google\Service\Drive($cliente);

    $estructura = obtenerEstructuraCaces($drive, $nombreCarrera, $cohorte);
    $idCarpetaPao = obtenerOCrearCarpeta($drive, $pao, $estructura['cohorte']);
    $idCarpetaAsignatura = obtenerOCrearCarpeta($drive, $asignatura, $idCarpetaPao);

    $nombreSeguro = escaparConsultaDrive($nombreArchivo);
    $idCarpetaSeguro = escaparConsultaDrive($idCarpetaAsignatura);

    $consulta = sprintf(
        "name = '%s' and '%s' in parents and trashed = false",
        $nombreSeguro,
        $idCarpetaSeguro
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
            new Google\Service\Drive\DriveFile(['name' => $nombreArchivo]),
            ['data' => $contenido, 'mimeType' => $mimeType, 'uploadType' => 'multipart', 'fields' => 'id,name,webViewLink,webContentLink']
        );
    } else {
        $metadata = new Google\Service\Drive\DriveFile([
            'name' => $nombreArchivo,
            'parents' => [$idCarpetaAsignatura],
        ]);
        $archivoDrive = $drive->files->create(
            $metadata,
            ['data' => $contenido, 'mimeType' => $mimeType, 'uploadType' => 'multipart', 'fields' => 'id,name,webViewLink,webContentLink']
        );
    }

    $idArchivoDrive = $archivoDrive->getId();

    try {
        $drive->permissions->create(
            $idArchivoDrive,
            new Google\Service\Drive\Permission(['type' => 'anyone', 'role' => 'reader']),
            ['fields' => 'id']
        );
    } catch (Google\Service\Exception $errorPermiso) {
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

/** Validación de PDF, mismo criterio que google_drive/subir_archivo.php (extensión + MIME real + 25MB). */
function validarPdf(array $archivo): ?string
{
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return 'Ocurrió un error al recibir el archivo.';
    }
    $tamanoMaximo = 25 * 1024 * 1024;
    if ($archivo['size'] > $tamanoMaximo) {
        return 'El archivo no debe superar los 25 MB.';
    }
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($archivo['tmp_name']);
    if ($extension !== 'pdf' || $mime !== 'application/pdf') {
        return 'Solo se aceptan archivos PDF válidos.';
    }
    return null;
}

/**
 * Validación de CSV para el slot 'encuesta_csv' (ver MEMORIA v18): mismo
 * límite de tamaño que validarPdf, pero exige extensión .csv y un MIME real
 * de texto plano/csv (finfo suele reportar CSV como text/plain o text/csv
 * según el contenido, nunca application/pdf ni binarios).
 */
function validarCsv(array $archivo): ?string
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
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($archivo['tmp_name']);
    $mimesValidos = ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'];
    if (!in_array($mime, $mimesValidos, true)) {
        return 'Solo se aceptan archivos CSV válidos.';
    }
    return null;
}