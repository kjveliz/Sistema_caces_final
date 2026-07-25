<?php

declare(strict_types=1);

namespace App\DTOs;

/**
 * Resultado de I3 (Tutorías Académicas) para UNA asignatura.
 * Espejo exacto de la forma que devolvía calcularResultadoAsignaturaTutorias()
 * en el api/tutorias_academicas/_calculo.php original — mismo JSON de salida,
 * ahora con forma tipada en vez de un array asociativo suelto (hallazgo
 * 1.2.4 del Plan de Mejora).
 *
 * @phpstan-type EfResultado array{
 *   label: string,
 *   peso: float,
 *   pct: float|null,
 *   estado: 'ok'|'sin_datos',
 *   cumplidos: int,
 *   total_puntos: int,
 *   detalle_puntos: array<int, array{nombre: string, cumplido: bool, valor: string|null}>
 * }
 */
final class ResultadoAsignaturaTutoriasDTO
{
    /**
     * @param array<string, array{
     *   label: string,
     *   peso: float,
     *   pct: float|null,
     *   estado: string,
     *   cumplidos: int,
     *   total_puntos: int,
     *   detalle_puntos: array<int, array{nombre: string, cumplido: bool, valor: string|null}>
     * }> $efs Claves EF1..EF4, mismo formato que el original.
     */
    public function __construct(
        public readonly int $idAsignatura,
        public readonly string $nombreAsignatura,
        public readonly ?float $valoracionGeneral,
        public readonly string $estadoGeneral,
        public readonly ?string $escala,
        public readonly ?string $colorEscala,
        public readonly array $efs,
    ) {
    }

    /** @return array<string, mixed> Misma forma exacta que el JSON original de _calculo.php. */
    public function toArray(): array
    {
        return [
            'id_asignatura' => $this->idAsignatura,
            'nombre_asignatura' => $this->nombreAsignatura,
            'valoracion_general' => $this->valoracionGeneral,
            'estado_general' => $this->estadoGeneral,
            'escala' => $this->escala,
            'color_escala' => $this->colorEscala,
            'efs' => $this->efs,
        ];
    }
}
