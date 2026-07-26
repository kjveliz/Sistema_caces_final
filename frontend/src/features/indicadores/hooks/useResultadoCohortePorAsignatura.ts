import { useEffect, useState } from 'react';

import { obtenerEvaluacion } from '../../../shared/services/evidencias';
import { obtenerPeriodos } from '../../../shared/services/seguimientoSyllabus';
import type { Career } from '../../../types/index';

/**
 * I2 (Resultados de Encuesta) y I3 (Tutorías Académicas) resuelven su
 * resultado de la misma forma: evaluación (id_evaluacion, id_cohorte) →
 * período académico del PAO pedido → resultado ya calculado de esa
 * cohorte+período, con el detalle por asignatura. Ambos son de solo
 * lectura -- no se recalcula nada en el frontend, el backend ya devuelve
 * el resultado final (ver useResultadosI2.ts/useResultadosI3.ts).
 *
 * Este hook aísla esa resolución común -- career+cohort+pao → resultado de
 * cohorte -- y deja que cada indicador le pase su propia función de fetch
 * (`obtenerResultadoCohorte` para I2, `obtenerResultadoCohorteTutorias` para
 * I3) y su propio tipo de resultado. Las dos derivaciones específicas de
 * cada indicador (radar/EF de I2, semáforo de I3) siguen viviendo en cada
 * hook, no acá.
 */
export function useResultadoCohortePorAsignatura<
  TAsignatura extends { id_asignatura: number; nombre_asignatura: string },
  TCohorte extends { detalle_asignaturas: TAsignatura[] },
>({
  career,
  cohort,
  pao,
  onAsignaturaChange,
  fetchResultadoCohorte,
}: {
  career: Career | null;
  cohort: string;
  pao: number;
  onAsignaturaChange?: (idAsignatura: number | null, nombreAsignatura?: string | null) => void;
  /**
   * Debe ser una referencia estable (p. ej. una función exportada a nivel de
   * módulo, como `obtenerResultadoCohorte`/`obtenerResultadoCohorteTutorias`)
   * y no un closure creado en cada render, para no volver a disparar el
   * fetch en cada render.
   */
  fetchResultadoCohorte: (
    idCohorte: number,
    idEvaluacion: number,
    idPeriodo: number,
  ) => Promise<TCohorte>;
}) {
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [resultadoCohorte, setResultadoCohorte] = useState<TCohorte | null>(null);
  const [selectedIdx, setSelectedIdx] = useState(0);

  const detalle = resultadoCohorte?.detalle_asignaturas ?? [];
  const seleccionado: TAsignatura | undefined = detalle[Math.min(selectedIdx, detalle.length - 1)];

  // Notificar al padre cuando cambie la asignatura seleccionada.
  useEffect(() => {
    const id = seleccionado?.id_asignatura ?? null;
    onAsignaturaChange?.(id, seleccionado?.nombre_asignatura ?? null);
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
        const rc = await fetchResultadoCohorte(
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
  }, [career, cohort, pao, fetchResultadoCohorte]);

  return {
    cargando,
    error,
    resultadoCohorte,
    detalle,
    seleccionado,
    selectedIdx,
    setSelectedIdx,
  };
}
