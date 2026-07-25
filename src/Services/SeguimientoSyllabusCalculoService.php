<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\ResultadoAsignaturaSeguimientoDTO;
use App\DTOs\ResultadoCohorteSeguimientoDTO;
use App\Repositories\SeguimientoSyllabusRepository;

/**
 * Cálculo del Indicador 11.2 — Seguimiento al Syllabus (EF1..EF5).
 * Migrado 1:1 desde api/seguimiento_syllabus/_calculo.php (Fase 3 del Plan
 * de Mejora): misma fórmula, mismos pesos, mismo redondeo — la única
 * diferencia es que ahora vive en una clase con el SQL movido al
 * SeguimientoSyllabusRepository y el resultado envuelto en DTOs tipados en
 * vez de arrays asociativos sueltos (mismo tratamiento que ya recibió I3).
 */
final class SeguimientoSyllabusCalculoService
{
    /**
     * 'reporte_control_siu' y 'reporte_avances_siu' (catálogo DOC.SEG.06/
     * DOC.SEG.07): dos reportes reales del SIU que evidencian EF1, a nivel
     * carrera+cohorte igual que malla_curricular/reglamento_normativa.
     */
    public const TIPOS_POR_CARRERA = ['malla_curricular', 'reglamento_normativa', 'reporte_control_siu', 'reporte_avances_siu'];

    /** 'encuesta_csv' se sube por-asignatura, igual que los otros 3 documentos. */
    public const TIPOS_POR_ASIGNATURA = ['syllabus', 'acta_ajuste_curricular', 'evidencia_difusion', 'encuesta_csv'];

    public function __construct(
        private readonly SeguimientoSyllabusRepository $repositorio,
        private readonly EncuestaEvidenciaService $encuestaService,
    ) {
    }

    /** @return array<string, string> */
    public static function etiquetasEvidencia(): array
    {
        return [
            'malla_curricular' => 'Malla Curricular',
            'syllabus' => 'Syllabus',
            'acta_ajuste_curricular' => 'Acta de Ajuste Curricular (EF2)',
            'evidencia_difusion' => 'Evidencia de Difusión (EF3)',
            'reglamento_normativa' => 'Reglamento / Normativa Institucional (EF5)',
            'encuesta_csv' => 'Resultados de Encuesta (CSV)',
            'reporte_control_siu' => 'Reporte de Control de Seguimiento (SIU)',
            'reporte_avances_siu' => 'Reporte de Avances del Syllabus (SIU)',
        ];
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

    /**
     * Calcula EF1-EF5 para UNA asignatura, combinando su evidencia propia +
     * la de nivel carrera + la encuesta propia de la asignatura.
     */
    public function calcularResultadoAsignatura(int $idAsignatura, string $nombreAsignatura, int $idEvaluacion): ResultadoAsignaturaSeguimientoDTO
    {
        $etiquetas = self::etiquetasEvidencia();
        $evidenciasInfo = [];
        foreach ($etiquetas as $tipo => $label) {
            $evidenciasInfo[$tipo] = ['subida' => false, 'label' => $label];
        }

        foreach ($this->repositorio->tiposAsignaturaVigentes($idAsignatura) as $tipo) {
            $evidenciasInfo[$tipo]['subida'] = true;
        }
        foreach ($this->repositorio->tiposCarreraVigentes($idEvaluacion) as $tipo) {
            $evidenciasInfo[$tipo]['subida'] = true;
        }

        $tieneEf2 = $evidenciasInfo['acta_ajuste_curricular']['subida'];
        $tieneEf3 = $evidenciasInfo['evidencia_difusion']['subida'];
        $tieneEf5 = $evidenciasInfo['reglamento_normativa']['subida'];
        $tieneSyllabus = $evidenciasInfo['syllabus']['subida'];
        $tieneMalla = $evidenciasInfo['malla_curricular']['subida'];
        $tieneReporteControlSiu = $evidenciasInfo['reporte_control_siu']['subida'];
        $tieneReporteAvancesSiu = $evidenciasInfo['reporte_avances_siu']['subida'];

        $datosEf = $this->encuestaService->calcularEfDesdeCsv($idAsignatura);
        $efDisponible = $datosEf !== null && $datosEf['respuestas'] > 0;

        $ef1Encuesta = $efDisponible ? $datosEf['ef1'] : 0.0;
        $ef1Syllabus = $tieneSyllabus ? 1.0 : 0.0;
        $ef1Malla = $tieneMalla ? 1.0 : 0.0;
        $ef1ReporteControlSiu = $tieneReporteControlSiu ? 1.0 : 0.0;
        $ef1ReporteAvancesSiu = $tieneReporteAvancesSiu ? 1.0 : 0.0;
        $ef1 = ($efDisponible || $tieneSyllabus || $tieneMalla || $tieneReporteControlSiu || $tieneReporteAvancesSiu)
            ? round(($ef1Encuesta + $ef1Syllabus + $ef1Malla + $ef1ReporteControlSiu + $ef1ReporteAvancesSiu) / 5, 4)
            : null;

        $ef4 = $efDisponible ? $datosEf['ef4'] : null;
        $respuestas = $efDisponible ? $datosEf['respuestas'] : 0;
        $promedioGeneral = $efDisponible ? $datosEf['promedio_general'] : 0;

        $totalEvidencias = ($ef1 !== null ? 1 : 0) + ($tieneEf2 ? 1 : 0) + ($tieneEf3 ? 1 : 0)
            + ($ef4 !== null ? 1 : 0) + ($tieneEf5 ? 1 : 0);
        $pctEvidencias = $totalEvidencias > 0 ? round($totalEvidencias / 5 * 100, 1) : 0;

        $docenteFinal = $this->repositorio->docenteActual($idAsignatura);

        $ef2 = $tieneEf2 ? 1.0 : null;
        $ef3Doc = $tieneEf3 ? 1.0 : null;
        $ef5 = $tieneEf5 ? 1.0 : null;

        $ef1Val = $ef1 ?? 0.0;
        $ef2Val = $ef2 ?? 0.0;
        $ef3Val = $ef3Doc ?? 0.0;
        $ef4Val = $ef4 ?? 0.0;
        $ef5Val = $ef5 ?? 0.0;

        $efPuntaje = round($ef1Val * 0.33 + $ef2Val * 0.27 + $ef3Val * 0.20 + $ef4Val * 0.13 + $ef5Val * 0.07, 4);
        $valoracionGeneral = round($efPuntaje * 100, 1);

        $todosCompletos = $efDisponible && $tieneSyllabus && $tieneMalla && $tieneReporteControlSiu
            && $tieneReporteAvancesSiu && $tieneEf2 && $tieneEf3 && $tieneEf5;
        $estadoGeneral = $todosCompletos ? 'completo' : 'parcial';

        [$escala, $colorEscala] = self::calcularEscala($valoracionGeneral);

        return new ResultadoAsignaturaSeguimientoDTO(
            idAsignatura: $idAsignatura,
            nombreAsignatura: $nombreAsignatura,
            docente: $docenteFinal,
            valoracionGeneral: $valoracionGeneral,
            estadoGeneral: $estadoGeneral,
            escala: $escala,
            colorEscala: $colorEscala,
            fuenteResultado: $todosCompletos ? 'combinado' : 'parcial',
            evidenciasInfo: $evidenciasInfo,
            totalEvidencias: $totalEvidencias,
            pctEvidencias: $pctEvidencias,
            efDisponible: $efDisponible,
            ef1: $ef1 !== null ? round($ef1 * 100, 1) : null,
            ef1Estado: $ef1 !== null ? 'ok' : 'sin_datos',
            ef2: $ef2 !== null ? round($ef2 * 100, 1) : null,
            ef2Estado: $tieneEf2 ? 'ok' : 'sin_datos',
            ef3: $ef3Doc !== null ? round($ef3Doc * 100, 1) : null,
            ef3Estado: $tieneEf3 ? 'ok' : 'sin_datos',
            ef4: $ef4 !== null ? round($ef4 * 100, 1) : null,
            ef4Estado: $efDisponible ? 'ok' : 'sin_datos',
            ef5: $ef5 !== null ? round($ef5 * 100, 1) : null,
            ef5Estado: $tieneEf5 ? 'ok' : 'sin_datos',
            efPuntaje: $efPuntaje,
            respuestas: $respuestas,
            promedioGeneral: $promedioGeneral,
        );
    }

    /** Agrega el resultado de TODAS las asignaturas de un PAO/cohorte, tratando cada asignatura sin dato como 0. */
    public function calcularResultadoGeneral(int $idCohorte, ?int $idPeriodo, int $idEvaluacion): ResultadoCohorteSeguimientoDTO
    {
        $asignaturas = $this->repositorio->asignaturasPorCohorte($idCohorte, $idPeriodo);

        $resultados = array_map(
            fn (array $a) => $this->calcularResultadoAsignatura($a['id_asignatura'], $a['nombre'], $idEvaluacion),
            $asignaturas,
        );

        $totalResultados = count($resultados);
        $agregados = [];
        $estados = [];
        foreach (['ef1', 'ef2', 'ef3', 'ef4', 'ef5'] as $ef) {
            $getter = 'get' . ucfirst($ef);
            $valores = array_map(fn (ResultadoAsignaturaSeguimientoDTO $r) => $this->valorEf($r, $ef) ?? 0.0, $resultados);
            $agregados[$ef] = $totalResultados > 0 ? round(array_sum($valores) / $totalResultados, 1) : null;
            $tieneAlgunDato = array_reduce($resultados, fn (bool $acc, ResultadoAsignaturaSeguimientoDTO $r) => $acc || $this->valorEf($r, $ef) !== null, false);
            $estados["{$ef}_estado"] = $tieneAlgunDato ? 'ok' : 'sin_datos';
        }

        $efDisponible = array_reduce($resultados, fn (bool $acc, ResultadoAsignaturaSeguimientoDTO $r) => $acc || $r->efDisponible, false);
        $respuestas = array_sum(array_map(fn (ResultadoAsignaturaSeguimientoDTO $r) => $r->respuestas, $resultados));
        $promediosValidos = array_filter($resultados, fn (ResultadoAsignaturaSeguimientoDTO $r) => $r->promedioGeneral !== null);
        $promedioGeneral = !empty($promediosValidos)
            ? round(array_sum(array_map(fn (ResultadoAsignaturaSeguimientoDTO $r) => $r->promedioGeneral, $promediosValidos)) / count($promediosValidos), 1)
            : 0;

        if ($totalResultados > 0) {
            $efPuntaje = round(
                ($agregados['ef1'] / 100) * 0.33 +
                ($agregados['ef2'] / 100) * 0.27 +
                ($agregados['ef3'] / 100) * 0.20 +
                ($agregados['ef4'] / 100) * 0.13 +
                ($agregados['ef5'] / 100) * 0.07,
                4,
            );
            $valoracionGeneral = round($efPuntaje * 100, 1);
            $estadoGeneral = array_reduce($resultados, fn (bool $acc, ResultadoAsignaturaSeguimientoDTO $r) => $acc && $r->estadoGeneral === 'completo', true) ? 'completo' : 'parcial';
        } else {
            $efPuntaje = null;
            $valoracionGeneral = null;
            $estadoGeneral = 'sin_datos';
        }

        [$escala, $colorEscala] = self::calcularEscala($valoracionGeneral);

        return new ResultadoCohorteSeguimientoDTO(
            valoracionGeneral: $valoracionGeneral,
            estadoGeneral: $estadoGeneral,
            escala: $escala,
            colorEscala: $colorEscala,
            efDisponible: $efDisponible,
            ef1: $agregados['ef1'], ef1Estado: $estados['ef1_estado'],
            ef2: $agregados['ef2'], ef2Estado: $estados['ef2_estado'],
            ef3: $agregados['ef3'], ef3Estado: $estados['ef3_estado'],
            ef4: $agregados['ef4'], ef4Estado: $estados['ef4_estado'],
            ef5: $agregados['ef5'], ef5Estado: $estados['ef5_estado'],
            efPuntaje: $efPuntaje,
            respuestas: $respuestas,
            promedioGeneral: $promedioGeneral,
            detalleAsignaturas: $resultados,
        );
    }

    private function valorEf(ResultadoAsignaturaSeguimientoDTO $r, string $ef): ?float
    {
        return match ($ef) {
            'ef1' => $r->ef1,
            'ef2' => $r->ef2,
            'ef3' => $r->ef3,
            'ef4' => $r->ef4,
            'ef5' => $r->ef5,
        };
    }

    /** Guarda el snapshot de auditoría de un resultado ya calculado. */
    public function guardarSnapshot(int $idAsignatura, int $idEvaluacion, ResultadoAsignaturaSeguimientoDTO $resultado): void
    {
        $this->repositorio->guardarSnapshotSeguimiento($idAsignatura, $idEvaluacion, $resultado->toArray());
    }
}
