<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Resultado de guardar (crear/actualizar matriculados o graduados y
 * recalcular la tasa) un dato de titulación, tal como lo devuelve
 * POST /tasa-titulacion/guardar. Espejo exacto de la forma que armaba
 * api/tasa_titulacion/guardar.php original (y de la interfaz
 * `DatoTitulacionGuardado` en frontend/src/services/evidencias.ts).
 */
final class DatoTitulacionGuardadoDTO
{
    public function __construct(
        public readonly int $idEvaluacion,
        public readonly string $cohorte,
        public readonly ?int $matriculados,
        public readonly ?int $graduados,
        public readonly ?float $tasa,
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
        ];
    }
}
