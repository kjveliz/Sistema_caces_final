<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Resultado de guardar (crear/actualizar uno de los 3 datos y recalcular
 * la tasa) un dato de deserción, tal como lo devuelve
 * POST /tasa-desercion/guardar, incluida la advertencia opcional de
 * inconsistencia entre los 3 valores. Espejo exacto de la forma que armaba
 * api/tasa_desercion/guardar.php original (y de la interfaz
 * `DatoDesercionGuardado` en frontend/src/services/evidencias.ts).
 */
final class DatoDesercionGuardadoDTO
{
    public function __construct(
        public readonly int $idEvaluacion,
        public readonly string $cohorte,
        public readonly ?int $iniciaronPrimerNivel,
        public readonly ?int $matriculadosSegundoAnio,
        public readonly ?int $noContinuaron,
        public readonly ?float $tasa,
        public readonly ?string $advertencia,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id_evaluacion' => $this->idEvaluacion,
            'cohorte' => $this->cohorte,
            'iniciaron_primer_nivel' => $this->iniciaronPrimerNivel,
            'matriculados_segundo_anio' => $this->matriculadosSegundoAnio,
            'no_continuaron' => $this->noContinuaron,
            'tasa' => $this->tasa,
        ];
    }
}
