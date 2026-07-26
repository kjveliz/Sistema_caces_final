import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import { obtenerEvaluacion } from '../../../shared/services/evidencias';
import {
  obtenerPeriodos,
  obtenerResultadoCohorte,
  obtenerEncuestaDetalle,
  type ResultadoAsignatura,
  type ResultadoCohorte,
} from '../../../shared/services/seguimientoSyllabus';
import { exportarPdfIndicador2 } from '../../../shared/lib/exportarPdfIndicador2';
import { EF_META } from '../constants/efMetaI2';
import type { Career } from '../../../types/index';

// Resuelve evaluación (id_evaluacion, id_cohorte), el PAO real, y trae de una
// sola llamada el resultado EF1-EF5 de todas las asignaturas de ese PAO, más
// la lógica de exportación de PDF -- separado del JSX de TabResultsI2, que
// solo pinta la UI.
export function useResultadosI2({
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
  const [resultadoCohorte, setResultadoCohorte] = useState<ResultadoCohorte | null>(null);
  const [selectedAsig, setSelectedAsig] = useState(0);
  const [exportando, setExportando] = useState(false);

  const detalle = resultadoCohorte?.detalle_asignaturas ?? [];
  const asig: ResultadoAsignatura | undefined = detalle[Math.min(selectedAsig, detalle.length - 1)];

  // Notificar al padre cuando cambie la asignatura seleccionada
  useEffect(() => {
    const id = asig?.id_asignatura ?? null;
    onAsignaturaChange?.(id, asig?.nombre_asignatura ?? null);
  }, [selectedAsig, resultadoCohorte]);

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
        const rc = await obtenerResultadoCohorte(
          evaluacion.id_cohorte,
          evaluacion.id_evaluacion,
          periodo.id_periodoacademico,
        );
        if (cancelado) return;
        setResultadoCohorte(rc);
        setSelectedAsig(0);
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

  const efScores = EF_META.map((ef) => ({
    ...ef,
    pct: asig?.[ef.key] ?? null,
  }));
  const totalGeneral = resultadoCohorte?.valoracion_general ?? null;
  const totalAsignatura = asig?.valoracion_general ?? null;
  // Nuevo (Pendiente #4, MEMORIA v41/§22.5, cerrado en v47): estado_general ya
  // se calculaba bien en el backend (una vez corregido el Pendiente #4) pero
  // no se mostraba en ningún lado de esta pantalla -- se agrega la etiqueta
  // "Completo"/"Incompleto" junto al % de la materia, igual que ya existe en
  // TabResultsI3 (Tutorías) más abajo en este mismo archivo.
  const materiaCompleta = asig?.estado_general === 'completo';
  const radarData = efScores.map((ef) => ({ subject: ef.id, score: ef.pct ?? 0, fullMark: 100 }));

  async function handleExportarPDF() {
    if (!asig || !career) return;
    setExportando(true);
    try {
      const cohorteNormalizada = cohort.replace(/\s+/g, '').toUpperCase();

      const evaluacion = await obtenerEvaluacion(career.code, cohorteNormalizada);
      const encuestaDetalle = await obtenerEncuestaDetalle(
        asig.id_asignatura,
        evaluacion.id_evaluacion,
      );

      await exportarPdfIndicador2({
        asignatura: asig,
        cohortLabel: cohort,
        paoNumero: pao,
        encuestaDetalle,
      });
    } catch (e) {
      toast.error(e instanceof Error ? e.message : 'No se pudo generar el PDF.');
    } finally {
      setExportando(false);
    }
  }

  return {
    cargando,
    error,
    detalle,
    asig,
    selectedAsig,
    setSelectedAsig,
    efScores,
    totalGeneral,
    totalAsignatura,
    materiaCompleta,
    radarData,
    exportando,
    handleExportarPDF,
  };
}
