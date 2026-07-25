<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Resultado agregado de I2 (Seguimiento Syllabus) para un PAO/cohorte.
 * Espejo exacto de calcularResultadoGeneral() del _calculo.php original.
 */
final class ResultadoCohorteSeguimientoDTO
{
    /** @param ResultadoAsignaturaSeguimientoDTO[] $detalleAsignaturas */
    public function __construct(
        public readonly ?float $valoracionGeneral,
        public readonly string $estadoGeneral,
        public readonly ?string $escala,
        public readonly ?string $colorEscala,
        public readonly bool $efDisponible,
        public readonly ?float $ef1,
        public readonly string $ef1Estado,
        public readonly ?float $ef2,
        public readonly string $ef2Estado,
        public readonly ?float $ef3,
        public readonly string $ef3Estado,
        public readonly ?float $ef4,
        public readonly string $ef4Estado,
        public readonly ?float $ef5,
        public readonly string $ef5Estado,
        public readonly ?float $efPuntaje,
        public readonly int $respuestas,
        public readonly float $promedioGeneral,
        public readonly array $detalleAsignaturas,
    ) {
    }

    /** @return array<string, mixed> Misma forma exacta que el JSON original de _calculo.php. */
    public function toArray(): array
    {
        return [
            'resultado_final' => $this->valoracionGeneral,
            'valoracion_general' => $this->valoracionGeneral,
            'estado_general' => $this->estadoGeneral,
            'escala' => $this->escala,
            'color_escala' => $this->colorEscala,
            'fuente_resultado' => 'agregado_por_asignatura',
            'ef_disponible' => $this->efDisponible,
            'ef1' => $this->ef1, 'ef1_estado' => $this->ef1Estado,
            'ef2' => $this->ef2, 'ef2_estado' => $this->ef2Estado,
            'ef3' => $this->ef3, 'ef3_estado' => $this->ef3Estado,
            'ef4' => $this->ef4, 'ef4_estado' => $this->ef4Estado,
            'ef5' => $this->ef5, 'ef5_estado' => $this->ef5Estado,
            'ef_puntaje' => $this->efPuntaje,
            'respuestas' => $this->respuestas,
            'promedio_general' => $this->promedioGeneral,
            'detalle_asignaturas' => array_map(
                fn (ResultadoAsignaturaSeguimientoDTO $r) => $r->toArray(),
                $this->detalleAsignaturas,
            ),
        ];
    }
}
