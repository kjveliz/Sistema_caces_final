<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Resultado agregado de I3 (Tutorías Académicas) para un PAO/cohorte.
 * Espejo exacto de calcularResultadoGeneralTutorias() del _calculo.php original.
 */
final class ResultadoCohorteTutoriasDTO
{
    /** @param ResultadoAsignaturaTutoriasDTO[] $detalleAsignaturas */
    public function __construct(
        public readonly ?float $valoracionGeneral,
        public readonly string $estadoGeneral,
        public readonly ?string $escala,
        public readonly ?string $colorEscala,
        public readonly array $detalleAsignaturas,
    ) {
    }

    /** @return array<string, mixed> Misma forma exacta que el JSON original de _calculo.php. */
    public function toArray(): array
    {
        return [
            'valoracion_general' => $this->valoracionGeneral,
            'estado_general' => $this->estadoGeneral,
            'escala' => $this->escala,
            'color_escala' => $this->colorEscala,
            'detalle_asignaturas' => array_map(
                fn (ResultadoAsignaturaTutoriasDTO $r) => $r->toArray(),
                $this->detalleAsignaturas,
            ),
        ];
    }
}
