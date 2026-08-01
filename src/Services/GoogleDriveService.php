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
 * se elimina.
 *
 * Nota histórica (ya no vigente): en su momento `api/google_drive/
 * drive_helpers.php` y `api/google_drive/cliente_autorizado.php` quedaban
 * fuera de alcance de la migración de I2/I3, por ser integración base
 * compartida con más indicadores. Ahora sí se migran, como parte del plan
 * de migración slim-legacy (Fase 4, Grupo D): `drive_helpers.php` ya se
 * migró a `GoogleDriveCarpetas` (Parte 18) y `cliente_autorizado.php` a
 * `GoogleDriveClienteAutorizado::obtener()` (Parte 19) -- esta clase usa
 * ese método en vez de `require`-ear el script (ver `subirArchivo()`,
 * `descargarContenidoDrive()`, `eliminarArchivo()`).
 *
 * Métodos nuevos respecto a la versión usada solo por I3:
 * `validarCsv()` (I2 sube el CSV de la encuesta, tipo 'encuesta_csv') y
 * `descargarContenidoDrive()` (I2 necesita leer de vuelta ese CSV para
 * calcular EF1/EF4 -- ver EncuestaEvidenciaService). Los métodos que ya
 * usaba I3 (`subirArchivo`, `validarArchivoSubido`) mantienen exactamente
 * la misma firma y comportamiento.
 *
 * Desde el interruptor de almacenamiento por carrera (ver
 * plan_interruptor_almacenamiento.txt): implementa EvidenciaStorageInterface
 * para poder intercambiarse con AlmacenamientoLocalService detrás del
 * mismo resolver. `descargarContenido()` es el método del contrato común;
 * `descargarContenidoDrive()` se conserva tal cual (mismo nombre, mismo
 * comportamiento) porque EncuestaEvidenciaService ya lo llama así -- es
 * un alias, no una segunda implementación.
 */
final class GoogleDriveService implements EvidenciaStorageInterface
{
    use ValidacionArchivoSubidoTrait;

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

        $cliente = GoogleDriveClienteAutorizado::obtener();
        $drive = new Drive($cliente);

        $estructura = GoogleDriveCarpetas::obtenerEstructuraCaces($drive, $nombreCarrera, $cohorte);
        $idCarpetaPao = GoogleDriveCarpetas::obtenerOCrearCarpeta($drive, $pao, $estructura['cohorte']);
        $idCarpetaAsignatura = GoogleDriveCarpetas::obtenerOCrearCarpeta($drive, $asignatura, $idCarpetaPao);

        $nombreSeguro = GoogleDriveCarpetas::escaparConsultaDrive($nombreArchivo);
        $idCarpetaSeguro = GoogleDriveCarpetas::escaparConsultaDrive($idCarpetaAsignatura);

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
     * Sube (o reemplaza si ya existe) un archivo dentro de:
     *   Sistema CACES / <carrera> / <cohorte> / archivo.*
     *
     * Puerto 1:1 del branch de Drive de `api/google_drive/subir_archivo.php`
     * (Parte 21 del plan de migración slim-legacy) -- endpoint genérico
     * compartido por I1/I4/I5 (y 3 slots evaluation-wide de I2). A
     * diferencia de `subirArchivo()` (usado por I2/I3, 5 niveles:
     * Carrera/Cohorte/PAO/Asignatura), este método NO agrega los niveles
     * de PAO/Asignatura -- el original de este endpoint nunca los tuvo
     * (confirmado en MEMORIA §16.3: I5/I4 siempre fueron 3 niveles en
     * Drive, aun cuando el modo local del mismo endpoint sí usa el árbol
     * de 5 niveles vía `AlmacenamientoLocalService::subirArchivo()` con
     * segmentos fijos "Evaluacion"/"I{indicador}"). Es una discrepancia ya
     * existente entre ambos modos de almacenamiento para este mismo
     * endpoint, no introducida por esta migración -- se documenta acá en
     * vez de "corregirla" de paso (plan §4: no mezclar deuda con el cambio
     * de arquitectura).
     *
     * @return array{nombre_archivo: string, url_archivo: string, id_archivo: string}
     */
    public function subirArchivoCatalogo(
        string $rutaTemporal,
        string $nombreArchivo,
        string $nombreCarrera,
        string $cohorte,
        string $mimeType = 'application/pdf',
    ): array {
        // Mismo seam de testing que subirArchivo() (Fase 5): en
        // APP_ENV=testing se evita el llamado real a Google Drive.
        if ((getenv('APP_ENV') ?: '') === 'testing') {
            return [
                'id_archivo' => 'fake-drive-id-' . bin2hex(random_bytes(4)),
                'nombre_archivo' => $nombreArchivo,
                'url_archivo' => 'https://drive.google.com/fake-test-double/' . rawurlencode($nombreArchivo),
            ];
        }

        $cliente = GoogleDriveClienteAutorizado::obtener();
        $drive = new Drive($cliente);

        $estructura = GoogleDriveCarpetas::obtenerEstructuraCaces($drive, $nombreCarrera, $cohorte);
        $idCarpetaDestino = $estructura['cohorte'];

        $nombreSeguro = GoogleDriveCarpetas::escaparConsultaDrive($nombreArchivo);
        $idCarpetaSeguro = GoogleDriveCarpetas::escaparConsultaDrive($idCarpetaDestino);

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
                'parents' => [$idCarpetaDestino],
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

        // Seam de testing (paso 4, EvidenciaMigradorService): antes solo
        // subirArchivo() lo tenía (ver EncuestaDetalleTest, que rodeaba
        // esto con un caché en disco). El migrador SÍ necesita poder
        // "leer" contenido de Drive en los tests, así que se agrega acá
        // el mismo criterio: bajo APP_ENV=testing, nunca se llama a la
        // API real -- se devuelve contenido determinístico basado en el
        // id extraído de la URL, sin credenciales.
        if ((getenv('APP_ENV') ?: '') === 'testing') {
            return "contenido-fake-de-prueba-drive:{$idArchivo}";
        }

        $cliente = GoogleDriveClienteAutorizado::obtener();
        $drive = new Drive($cliente);

        $respuesta = $drive->files->get($idArchivo, ['alt' => 'media']);

        return $respuesta->getBody()->getContents();
    }

    /** Alias del método del contrato común (EvidenciaStorageInterface). */
    public function descargarContenido(string $urlArchivo): ?string
    {
        return $this->descargarContenidoDrive($urlArchivo);
    }

    /**
     * Elimina de Drive el archivo identificado por su webViewLink. Parte
     * del contrato común (EvidenciaStorageInterface), usada por
     * EvidenciaMigradorService para revertir una migración a medias.
     * Devuelve false (sin lanzar) si la URL no tiene la forma esperada o
     * si la API de Drive rechaza el borrado -- el llamador decide qué
     * hacer con una reversión que no se pudo completar del todo.
     */
    public function eliminarArchivo(string $urlArchivo): bool
    {
        if ((getenv('APP_ENV') ?: '') === 'testing') {
            return true;
        }

        if (!preg_match('#/d/([a-zA-Z0-9_-]+)#', $urlArchivo, $m)) {
            error_log("GoogleDriveService: no se pudo extraer el id de Drive de '{$urlArchivo}' para eliminarlo.");

            return false;
        }

        try {
            $cliente = GoogleDriveClienteAutorizado::obtener();
            $drive = new Drive($cliente);
            $drive->files->delete($m[1]);

            return true;
        } catch (Throwable $e) {
            error_log("GoogleDriveService: no se pudo eliminar el archivo '{$urlArchivo}' de Drive: " . $e->getMessage());

            return false;
        }
    }

    // validarArchivoSubido() y validarCsv() vienen de ValidacionArchivoSubidoTrait
    // (extraídas de esta clase al agregar AlmacenamientoLocalService, mismo
    // comportamiento exacto de antes).
}
