<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Resultado de I2 (Seguimiento Syllabus) para UNA asignatura. Espejo exacto
 * de la forma que devolvía calcularResultadoAsignatura() en el
 * api/seguimiento_syllabus/_calculo.php original — mismo JSON de salida,
 * ahora con forma tipada en vez de un array asociativo suelto.
 */
final class ResultadoAsignaturaSeguimientoDTO
{
    /**
     * @param array<string, array{subida: bool, label: string}> $evidenciasInfo
     */
    public function __construct(
        public readonly int $idAsignatura,
        public readonly string $nombreAsignatura,
        public readonly ?string $docente,
        public readonly ?float $valoracionGeneral,
        public readonly string $estadoGeneral,
        public readonly ?string $escala,
        public readonly ?string $colorEscala,
        public readonly string $fuenteResultado,
        public readonly array $evidenciasInfo,
        public readonly int $totalEvidencias,
        public readonly float $pctEvidencias,
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
        public readonly float $efPuntaje,
        public readonly int $respuestas,
        public readonly float $promedioGeneral,
    ) {
    }

    /** @return array<string, mixed> Misma forma exacta que el JSON original de _calculo.php. */
    public function toArray(): array
    {
        return [
            'id_asignatura' => $this->idAsignatura,
            'nombre_asignatura' => $this->nombreAsignatura,
            'docente' => $this->docente,
            'resultado_final' => $this->valoracionGeneral,
            'valoracion_general' => $this->valoracionGeneral,
            'estado_general' => $this->estadoGeneral,
            'escala' => $this->escala,
            'color_escala' => $this->colorEscala,
            'fuente_resultado' => $this->fuenteResultado,
            'evidencias_info' => $this->evidenciasInfo,
            'total_evidencias' => $this->totalEvidencias,
            'pct_evidencias' => $this->pctEvidencias,
            'ef_disponible' => $this->efDisponible,
            'ef1' => $this->ef1,
            'ef1_estado' => $this->ef1Estado,
            'ef2' => $this->ef2,
            'ef2_estado' => $this->ef2Estado,
            'ef3' => $this->ef3,
            'ef3_estado' => $this->ef3Estado,
            'ef4' => $this->ef4,
            'ef4_estado' => $this->ef4Estado,
            'ef5' => $this->ef5,
            'ef5_estado' => $this->ef5Estado,
            'ef_puntaje' => $this->efPuntaje,
            'respuestas' => $this->respuestas,
            'promedio_general' => $this->promedioGeneral,
        ];
    }
}
