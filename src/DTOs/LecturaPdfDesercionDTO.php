<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Resultado de extraer datos de un PDF de I4 (Tasa de Deserción), tal como
 * lo devuelve POST /tasa-desercion/leer-pdf. Espejo exacto de la forma que
 * armaba extraerDatosDesercion() en api/tasa_desercion/_calculo.php
 * original (y de la interfaz `LecturaPdfDesercion` en
 * frontend/src/services/evidencias.ts).
 */
final class LecturaPdfDesercionDTO
{
    public function __construct(
        public readonly string $tipoDato,
        public readonly int $total,
        public readonly string $metodo,
        public readonly ?string $cohorteDetectada,
        public readonly ?string $periodoDetectado,
        public readonly int $identificacionesDetectadas,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tipo_dato' => $this->tipoDato,
            'total' => $this->total,
            'metodo' => $this->metodo,
            'cohorte_detectada' => $this->cohorteDetectada,
            'periodo_detectado' => $this->periodoDetectado,
            'identificaciones_detectadas' => $this->identificacionesDetectadas,
        ];
    }
}
