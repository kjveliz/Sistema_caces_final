<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Resultado de eliminar permanentemente una carrera, tal como lo devuelve
 * POST /carreras/eliminar. Espejo exacto de la forma que armaba
 * api/carreras/eliminar.php original.
 */
final class CarreraEliminadaDTO
{
    public function __construct(
        public readonly int $idCarrera,
        public readonly string $nombre,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id_carrera' => $this->idCarrera,
            'nombre' => $this->nombre,
        ];
    }
}
