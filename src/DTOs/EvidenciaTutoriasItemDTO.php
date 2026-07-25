<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Un ítem de la respuesta de evidencia-listar (uno por cada uno de los 4
 * tipos de evidencia de I3: plan_tutorias, registro_tutorias,
 * informe_tutorias, evidencia_atencion). Espejo exacto de la forma que
 * armaba api/tutorias_academicas/evidencia_listar.php original.
 */
final class EvidenciaTutoriasItemDTO
{
    /**
     * @param array{
     *   id_evidencia_asig: int,
     *   nombre_archivo: string,
     *   url_archivo: string,
     *   subido_por: string|null,
     *   fecha_subida: string
     * }|null $archivo
     * @param array{puntos: array<int, array{nombre: string, cumplido: bool, valor: string|null}>, total_puntos: int, cumplidos: int}|null $validacion
     */
    public function __construct(
        public readonly string $tipo,
        public readonly string $ef,
        public readonly bool $subida,
        public readonly ?array $archivo,
        public readonly ?array $validacion,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tipo' => $this->tipo,
            'ef' => $this->ef,
            'subida' => $this->subida,
            'archivo' => $this->archivo,
            'validacion' => $this->validacion,
        ];
    }
}
