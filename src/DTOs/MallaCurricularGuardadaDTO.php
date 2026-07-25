<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Resultado de registrar (crear/actualizar) la malla curricular de una
 * carrera, tal como lo devuelve POST /malla-curricular/guardar. Espejo
 * exacto de la forma que armaba api/carreras/guardar_malla.php original (y
 * de la interfaz `MallaCurricularRegistrada` en
 * frontend/src/services/carreras.ts).
 */
final class MallaCurricularGuardadaDTO
{
    public function __construct(
        public readonly int $idCarrera,
        public readonly string $nombreArchivo,
        public readonly string $idDrive,
        public readonly string $urlDrive,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id_carrera' => $this->idCarrera,
            'nombre_archivo' => $this->nombreArchivo,
            'id_drive' => $this->idDrive,
            'url_drive' => $this->urlDrive,
        ];
    }
}
