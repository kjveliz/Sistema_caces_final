<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Fila de Mallas_Curriculares para una carrera, tal como la devuelve
 * GET /malla-curricular/obtener. Espejo exacto de la forma que armaba
 * api/carreras/obtener_malla.php original (y de la interfaz
 * `MallaCurricularCarrera` en frontend/src/services/evidencias.ts).
 */
final class MallaCurricularDTO
{
    public function __construct(
        public readonly int $idMalla,
        public readonly int $idCarrera,
        public readonly string $nombreArchivo,
        public readonly string $idDrive,
        public readonly string $urlDrive,
        public readonly string $fechaSubida,
        public readonly string $fechaActualizacion,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id_malla' => $this->idMalla,
            'id_carrera' => $this->idCarrera,
            'nombre_archivo' => $this->nombreArchivo,
            'id_drive' => $this->idDrive,
            'url_drive' => $this->urlDrive,
            'fecha_subida' => $this->fechaSubida,
            'fecha_actualizacion' => $this->fechaActualizacion,
        ];
    }
}
