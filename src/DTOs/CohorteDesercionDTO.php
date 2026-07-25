<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Una fila de datos_tasa_desercion para una evaluación, tal como la
 * devuelve GET /tasa-desercion/obtener. Espejo exacto de la forma que
 * armaba api/tasa_desercion/obtener.php original (y de la interfaz
 * `CohorteDesercion` en frontend/src/services/evidencias.ts).
 */
final class CohorteDesercionDTO
{
    public function __construct(
        public readonly int $idDato,
        public readonly int $idEvaluacion,
        public readonly string $cohorte,
        public readonly ?int $iniciaronPrimerNivel,
        public readonly ?int $matriculadosSegundoAnio,
        public readonly ?int $noContinuaron,
        public readonly ?float $tasa,
        public readonly string $fechaActualizacion,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id_dato' => $this->idDato,
            'id_evaluacion' => $this->idEvaluacion,
            'cohorte' => $this->cohorte,
            'iniciaron_primer_nivel' => $this->iniciaronPrimerNivel,
            'matriculados_segundo_anio' => $this->matriculadosSegundoAnio,
            'no_continuaron' => $this->noContinuaron,
            'tasa' => $this->tasa,
            'fecha_actualizacion' => $this->fechaActualizacion,
        ];
    }
}
