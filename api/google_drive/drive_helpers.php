<?php

function escaparConsultaDrive(string $texto): string
{
    return str_replace(
        ["\\", "'"],
        ["\\\\", "\\'"],
        $texto
    );
}

function obtenerOCrearCarpeta(
    Google\Service\Drive $drive,
    string $nombre,
    ?string $idPadre = null
): string {
    $nombreSeguro = escaparConsultaDrive($nombre);

    $partesConsulta = [
        "name = '{$nombreSeguro}'",
        "mimeType = 'application/vnd.google-apps.folder'",
        "trashed = false",
    ];

    if ($idPadre !== null && $idPadre !== "") {
        $idPadreSeguro = escaparConsultaDrive($idPadre);
        $partesConsulta[] = "'{$idPadreSeguro}' in parents";
    }

    $resultado = $drive->files->listFiles([
        "q" => implode(" and ", $partesConsulta),
        "spaces" => "drive",
        "fields" => "files(id,name)",
        "pageSize" => 10,
    ]);

    $carpetas = $resultado->getFiles();

    if (count($carpetas) > 0) {
        return $carpetas[0]->getId();
    }

    $metadata = [
        "name" => $nombre,
        "mimeType" => "application/vnd.google-apps.folder",
    ];

    if ($idPadre !== null && $idPadre !== "") {
        $metadata["parents"] = [$idPadre];
    }

    $carpeta = $drive->files->create(
        new Google\Service\Drive\DriveFile($metadata),
        [
            "fields" => "id",
        ]
    );

    return $carpeta->getId();
}

function obtenerEstructuraCaces(
    Google\Service\Drive $drive,
    string $nombreCarrera,
    string $cohorte
): array {
    $idRaiz = obtenerOCrearCarpeta(
        $drive,
        "Sistema CACES"
    );

    $idCarrera = obtenerOCrearCarpeta(
        $drive,
        $nombreCarrera,
        $idRaiz
    );

    $idCohorte = obtenerOCrearCarpeta(
        $drive,
        $cohorte,
        $idCarrera
    );

    return [
        "raiz" => $idRaiz,
        "carrera" => $idCarrera,
        "cohorte" => $idCohorte,
    ];
}