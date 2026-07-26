<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Resultado de registrar o actualizar una carrera, tal como lo devuelven
 * POST /carreras/crear y POST /carreras/actualizar. Espejo exacto de la
 * forma que armaban api/carreras/{crear,actualizar}.php originales (y de
 * la interfaz `CarreraBD` en frontend/src/shared/services/carreras.ts).
 */
final class CarreraGuardadaDTO
{
    public function __construct(
        public readonly int $idCarrera,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $areaConocimiento,
        public readonly string $modalidad,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id_carrera' => $this->idCarrera,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'area_conocimiento' => $this->areaConocimiento,
            'modalidad' => $this->modalidad,
        ];
    }
}
