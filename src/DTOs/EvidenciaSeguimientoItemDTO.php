<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Un ítem de la respuesta de evidencia_asignatura_listar (uno por cada uno
 * de los 4 tipos de evidencia por-asignatura de I2: syllabus,
 * acta_ajuste_curricular, evidencia_difusion, encuesta_csv). Espejo exacto
 * de la forma que armaba api/seguimiento_syllabus/evidencia_asignatura_listar.php
 * original.
 */
final class EvidenciaSeguimientoItemDTO
{
    /**
     * @param array{id_evidencia_asig: int, nombre_archivo: string, url_archivo: string, subido_por: string|null, fecha_subida: string}|null $archivo
     */
    public function __construct(
        public readonly string $tipo,
        public readonly string $label,
        public readonly bool $subida,
        public readonly ?array $archivo,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tipo' => $this->tipo,
            'label' => $this->label,
            'subida' => $this->subida,
            'archivo' => $this->archivo,
        ];
    }
}
