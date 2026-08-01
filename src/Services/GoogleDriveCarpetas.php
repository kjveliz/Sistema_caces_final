<?php

declare(strict_types=1);

namespace App\Services;

use Google\Service\Drive;
use Google\Service\Drive\DriveFile;

/**
 * Helpers de carpetas de Google Drive, compartidos por
 * `App\Services\GoogleDriveService` (I2/I3, en producción desde la Fase 3) y
 * por `api/google_drive/subir_archivo.php` (Parte 21, todavía sin migrar).
 * Puerto 1:1 de `api/google_drive/drive_helpers.php` (Parte 18 del plan de
 * migración slim-legacy) -- mismas 3 funciones (mismas queries, mismo
 * criterio de escape), ahora como métodos estáticos en vez de funciones
 * globales. `drive_helpers.php` queda borrado del repo con esta Parte.
 */
final class GoogleDriveCarpetas
{
    /**
     * Escapa backslashes y comillas simples para usar de forma segura un
     * texto dentro de una consulta `q` de la API de Drive.
     */
    public static function escaparConsultaDrive(string $texto): string
    {
        return str_replace(
            ['\\', "'"],
            ['\\\\', "\\'"],
            $texto,
        );
    }

    /**
     * Busca una carpeta por nombre (y padre opcional); si no existe, la
     * crea. Devuelve el id de Drive de la carpeta (existente o recién
     * creada).
     */
    public static function obtenerOCrearCarpeta(
        Drive $drive,
        string $nombre,
        ?string $idPadre = null,
    ): string {
        $nombreSeguro = self::escaparConsultaDrive($nombre);

        $partesConsulta = [
            "name = '{$nombreSeguro}'",
            "mimeType = 'application/vnd.google-apps.folder'",
            'trashed = false',
        ];

        if ($idPadre !== null && $idPadre !== '') {
            $idPadreSeguro = self::escaparConsultaDrive($idPadre);
            $partesConsulta[] = "'{$idPadreSeguro}' in parents";
        }

        $resultado = $drive->files->listFiles([
            'q' => implode(' and ', $partesConsulta),
            'spaces' => 'drive',
            'fields' => 'files(id,name)',
            'pageSize' => 10,
        ]);

        $carpetas = $resultado->getFiles();

        if (count($carpetas) > 0) {
            return $carpetas[0]->getId();
        }

        $metadata = [
            'name' => $nombre,
            'mimeType' => 'application/vnd.google-apps.folder',
        ];

        if ($idPadre !== null && $idPadre !== '') {
            $metadata['parents'] = [$idPadre];
        }

        $carpeta = $drive->files->create(
            new DriveFile($metadata),
            ['fields' => 'id'],
        );

        return $carpeta->getId();
    }

    /**
     * Arma (u obtiene, si ya existe) el árbol de carpetas
     * "Sistema CACES / <carrera> / <cohorte>" y devuelve los 3 ids.
     *
     * @return array{raiz: string, carrera: string, cohorte: string}
     */
    public static function obtenerEstructuraCaces(
        Drive $drive,
        string $nombreCarrera,
        string $cohorte,
    ): array {
        $idRaiz = self::obtenerOCrearCarpeta($drive, 'Sistema CACES');
        $idCarrera = self::obtenerOCrearCarpeta($drive, $nombreCarrera, $idRaiz);
        $idCohorte = self::obtenerOCrearCarpeta($drive, $cohorte, $idCarrera);

        return [
            'raiz' => $idRaiz,
            'carrera' => $idCarrera,
            'cohorte' => $idCohorte,
        ];
    }
}
