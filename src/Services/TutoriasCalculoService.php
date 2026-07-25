<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ResultadoAsignaturaTutoriasDTO;
use App\DTOs\ResultadoCohorteTutoriasDTO;
use App\Repositories\TutoriasRepository;

/**
 * Cálculo del Indicador 11.3 — Tutorías Académicas.
 * Evaluación CUALITATIVA por puntos de validación dentro de cada EF (a
 * diferencia de I2, que es cuantitativo vía encuesta + evidencia binaria).
 *
 * Migrado 1:1 desde api/tutorias_academicas/_calculo.php (Fase 3 del Plan
 * de Mejora): misma fórmula, mismos pesos, mismo redondeo — la única
 * diferencia es que ahora vive en una clase con la parte de SQL movida al
 * TutoriasRepository (hallazgo 1.2.3) y el resultado envuelto en DTOs
 * tipados (hallazgo 1.2.4) en vez de arrays asociativos sueltos.
 */
final class TutoriasCalculoService
{
    public const TIPOS_TUTORIAS = ['plan_tutorias', 'registro_tutorias', 'informe_tutorias', 'evidencia_atencion'];

    public function __construct(private readonly TutoriasRepository $repositorio)
    {
    }

    /** tipo de evidencia_asignatura -> EF que valida. */
    public static function tipoAEf(): array
    {
        return [
            'plan_tutorias' => 'EF1',
            'registro_tutorias' => 'EF2',
            'informe_tutorias' => 'EF3',
            'evidencia_atencion' => 'EF4',
        ];
    }

    public static function pesosEf(): array
    {
        return ['EF1' => 0.40, 'EF2' => 0.30, 'EF3' => 0.20, 'EF4' => 0.10];
    }

    public static function etiquetasEf(): array
    {
        return [
            'EF1' => 'Planeación de tutorías',
            'EF2' => 'Cumplimiento de tutorías',
            'EF3' => 'Seguimiento académico',
            'EF4' => 'Normativas institucionales',
        ];
    }

    /**
     * Tabla de % oficial: EF1/EF2/EF3 tienen base 3 puntos; EF4 tiene base 4
     * puntos. 0 puntos cumplidos siempre es 0%, sin importar la base.
     */
    public static function porcentajePorPuntos(int $cumplidos, int $totalPuntos): float
    {
        if ($cumplidos <= 0) {
            return 0.0;
        }
        if ($totalPuntos === 3) {
            return match ($cumplidos) {
                3 => 100.0,
                2 => 66.0,
                1 => 33.0,
                default => 0.0,
            };
        }
        if ($totalPuntos === 4) {
            return match ($cumplidos) {
                4 => 100.0,
                3 => 75.0,
                2 => 50.0,
                1 => 25.0,
                default => 0.0,
            };
        }
        // Fallback genérico (no debería usarse con los EF actuales).
        return round($cumplidos / max($totalPuntos, 1) * 100, 1);
    }

    /** @return array{0: string|null, 1: string|null} [escala, color] */
    public static function calcularEscala(?float $valoracion): array
    {
        if ($valoracion === null) {
            return [null, null];
        }
        if ($valoracion >= 75) {
            return ['Satisfactorio', '#15803D'];
        }
        if ($valoracion >= 50) {
            return ['Cuasi Satisfactorio', '#CA8A04'];
        }
        if ($valoracion >= 25) {
            return ['Poco Satisfactorio', '#F97316'];
        }

        return ['Deficiente', '#EF4444'];
    }

    /** Calcula el resultado de I3 para UNA asignatura. */
    public function calcularResultadoAsignatura(int $idAsignatura, string $nombreAsignatura): ResultadoAsignaturaTutoriasDTO
    {
        $pesos = self::pesosEf();
        $etiquetas = self::etiquetasEf();
        $validacionesPorEf = $this->repositorio->obtenerValidacionesPorEf($idAsignatura);

        $efs = [];
        $efPuntaje = 0.0;
        $algunEfConDatos = false;
        $todosLosEf = true;

        foreach (['EF1', 'EF2', 'EF3', 'EF4'] as $ef) {
            $datos = $validacionesPorEf[$ef] ?? null;
            if ($datos === null) {
                $efs[$ef] = [
                    'label' => $etiquetas[$ef],
                    'peso' => $pesos[$ef],
                    'pct' => null,
                    'estado' => 'sin_datos',
                    'cumplidos' => 0,
                    'total_puntos' => $ef === 'EF4' ? 4 : 3,
                    'detalle_puntos' => [],
                ];
                $todosLosEf = false;
                continue;
            }
            $pct = self::porcentajePorPuntos($datos['cumplidos'], $datos['total_puntos']);
            $efs[$ef] = [
                'label' => $etiquetas[$ef],
                'peso' => $pesos[$ef],
                'pct' => $pct,
                'estado' => 'ok',
                'cumplidos' => $datos['cumplidos'],
                'total_puntos' => $datos['total_puntos'],
                'detalle_puntos' => $datos['puntos'],
            ];
            $efPuntaje += ($pct / 100) * $pesos[$ef];
            $algunEfConDatos = true;
        }

        $valoracionGeneral = $algunEfConDatos ? round($efPuntaje * 100, 1) : null;
        $estadoGeneral = $todosLosEf ? 'completo' : 'parcial';
        [$escala, $colorEscala] = self::calcularEscala($valoracionGeneral);

        return new ResultadoAsignaturaTutoriasDTO(
            idAsignatura: $idAsignatura,
            nombreAsignatura: $nombreAsignatura,
            valoracionGeneral: $valoracionGeneral,
            estadoGeneral: $estadoGeneral,
            escala: $escala,
            colorEscala: $colorEscala,
            efs: $efs,
        );
    }

    /** Agrega el resultado de I3 de todas las asignaturas de un PAO/cohorte. */
    public function calcularResultadoGeneral(int $idCohorte, ?int $idPeriodo): ResultadoCohorteTutoriasDTO
    {
        $asignaturas = $this->repositorio->asignaturasPorCohorte($idCohorte, $idPeriodo);

        $resultados = array_map(
            fn (array $a) => $this->calcularResultadoAsignatura($a['id_asignatura'], $a['nombre']),
            $asignaturas,
        );

        $total = count($resultados);
        // Asignatura sin datos cuenta como 0, igual que I2.
        $valoresGenerales = array_map(fn (ResultadoAsignaturaTutoriasDTO $r) => $r->valoracionGeneral ?? 0.0, $resultados);
        $valoracionGeneral = $total > 0 ? round(array_sum($valoresGenerales) / $total, 1) : null;
        $estadoGeneral = ($total > 0 && array_reduce(
            $resultados,
            fn (bool $acc, ResultadoAsignaturaTutoriasDTO $r) => $acc && $r->estadoGeneral === 'completo',
            true,
        )) ? 'completo' : 'parcial';

        [$escala, $colorEscala] = self::calcularEscala($valoracionGeneral);

        return new ResultadoCohorteTutoriasDTO(
            valoracionGeneral: $valoracionGeneral,
            estadoGeneral: $total > 0 ? $estadoGeneral : 'sin_datos',
            escala: $escala,
            colorEscala: $colorEscala,
            detalleAsignaturas: $resultados,
        );
    }

    /** Guarda el snapshot de auditoría de un resultado ya calculado. */
    public function guardarSnapshot(int $idAsignatura, int $idEvaluacion, ResultadoAsignaturaTutoriasDTO $resultado): void
    {
        $this->repositorio->guardarSnapshot($idAsignatura, $idEvaluacion, $resultado->toArray());
    }
}
