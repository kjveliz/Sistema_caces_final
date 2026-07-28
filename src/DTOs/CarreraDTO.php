<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Fila de `carreras` (con su malla curricular asociada, si tiene) tal como
 * la devuelve GET /carreras/listar. Espejo exacto de la forma que armaba
 * api/carreras/listar.php original (y de la interfaz `CarreraBD` en
 * frontend/src/shared/services/carreras.ts).
 */
final class CarreraDTO
{
    public function __construct(
        public readonly int $idCarrera,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $areaConocimiento,
        public readonly string $modalidad,
        public readonly ?string $nombreMalla,
        public readonly ?string $idDrive,
        public readonly ?string $urlMalla,
        public readonly string $modoAlmacenamiento,
        public readonly ?string $rutaAlmacenamientoLocal,
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
            'nombre_malla' => $this->nombreMalla,
            'id_drive' => $this->idDrive,
            'url_malla' => $this->urlMalla,
            'modo_almacenamiento' => $this->modoAlmacenamiento,
            'ruta_almacenamiento_local' => $this->rutaAlmacenamientoLocal,
        ];
    }
}
