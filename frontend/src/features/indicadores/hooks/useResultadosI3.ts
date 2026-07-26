import { useEffect, useState } from 'react';

import { obtenerEvaluacion } from '../../../shared/services/evidencias';
import { obtenerPeriodos } from '../../../shared/services/seguimientoSyllabus';
import {
  obtenerResultadoCohorteTutorias,
  type ResultadoCohorteTutorias,
  type ResultadoAsignaturaTutorias,
} from '../../../shared/services/tutoriasAcademicas';
import { getStatus } from '../../../shared/utils/evaluation';
import type { Career } from '../../../types/index';

// Igual patrón que useResultadosI2: solo lectura, trae de la API real el
// resultado ya calculado por api/tutorias_academicas/_calculo.php (pesos,
// % por EF, escala) -- no se recalcula nada en el frontend.
export function useResultadosI3({
  career,
  cohort,
  pao,
  onAsignaturaChange,
}: {
  career: Career | null;
  cohort: string;
  pao: number;
  onAsignaturaChange?: (idAsignatura: number | null, nombreAsignatura?: string | null) => void;
}) {
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [resultadoCohorte, setResultadoCohorte] = useState<ResultadoCohorteTutorias | null>(null);
  const [selectedIdx, setSelectedIdx] = useState(0);

  const detalle = resultadoCohorte?.detalle_asignaturas ?? [];
  const materia: ResultadoAsignaturaTutorias | undefined =
    detalle[Math.min(selectedIdx, detalle.length - 1)];

  // Notificar al padre cuando cambie la asignatura seleccionada -- misma
  // señal que usa TabEvidences para I2, necesaria para que I3 también
  // pueda mostrar/subir evidencia de la materia correcta en esa pestaña.
  useEffect(() => {
    const id = materia?.id_asignatura ?? null;
    onAsignaturaChange?.(id, materia?.nombre_asignatura ?? null);
  }, [selectedIdx, resultadoCohorte]);

  useEffect(() => {
    if (!career) return;
    let cancelado = false;
    setCargando(true);
    setError(null);

    (async () => {
      try {
        const evaluacion = await obtenerEvaluacion(career.code, cohort);
        const periodos = await obtenerPeriodos(evaluacion.id_cohorte);
        const periodo = periodos.find((p) => p.orden === pao);
        if (!periodo) {
          throw new Error(`No existe el PAO ${pao} para esta cohorte.`);
        }
        const rc = await obtenerResultadoCohorteTutorias(
          evaluacion.id_cohorte,
          evaluacion.id_evaluacion,
          periodo.id_periodoacademico,
        );
        if (cancelado) return;
        setResultadoCohorte(rc);
        setSelectedIdx(0);
      } catch (e) {
        if (!cancelado)
          setError(e instanceof Error ? e.message : 'No se pudo cargar la información.');
      } finally {
        if (!cancelado) setCargando(false);
      }
    })();

    return () => {
      cancelado = true;
    };
  }, [career, cohort, pao]);

  const paoScore = resultadoCohorte?.valoracion_general ?? null;
  const paoSt = getStatus(Math.round(paoScore ?? 0));
  const materiaScore = materia?.valoracion_general ?? null;
  const materiaCompleta = materia?.estado_general === 'completo';

  return {
    cargando,
    error,
    detalle,
    materia,
    selectedIdx,
    setSelectedIdx,
    paoScore,
    paoSt,
    materiaScore,
    materiaCompleta,
  };
}
