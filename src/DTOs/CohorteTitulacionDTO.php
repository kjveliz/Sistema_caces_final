<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Una fila de datos_tasa_titulacion para una evaluación, tal como la
 * devuelve GET /tasa-titulacion/obtener. Espejo exacto de la forma que
 * armaba api/tasa_titulacion/obtener.php original (y de la interfaz
 * `CohorteTitulacion` en frontend/src/services/evidencias.ts).
 */
final class CohorteTitulacionDTO
{
    public function __construct(
        public readonly int $idEvaluacion,
        public readonly string $cohorte,
        public readonly ?int $matriculados,
        public readonly ?int $graduados,
        public readonly ?float $tasa,
        public readonly string $fechaActualizacion,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id_evaluacion' => $this->idEvaluacion,
            'cohorte' => $this->cohorte,
            'matriculados' => $this->matriculados,
            'graduados' => $this->graduados,
            'tasa' => $this->tasa,
            'fecha_actualizacion' => $this->fechaActualizacion,
        ];
    }
}
