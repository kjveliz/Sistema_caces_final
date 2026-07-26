import { useState } from 'react';
import { toast } from 'sonner';

import { obtenerEvaluacion } from '../../../shared/services/evidencias';
import {
  obtenerResultadoCohorte,
  obtenerEncuestaDetalle,
  type ResultadoAsignatura,
  type ResultadoCohorte,
} from '../../../shared/services/seguimientoSyllabus';
import { exportarPdfIndicador2 } from '../../../shared/lib/exportarPdfIndicador2';
import { EF_META } from '../constants/efMetaI2';
import { useResultadoCohortePorAsignatura } from './useResultadoCohortePorAsignatura';
import type { Career } from '../../../types/index';

// Resuelve evaluación (id_evaluacion, id_cohorte), el PAO real, y trae de una
// sola llamada el resultado EF1-EF5 de todas las asignaturas de ese PAO, más
// la lógica de exportación de PDF -- separado del JSX de TabResultsI2, que
// solo pinta la UI. La resolución evaluación→período→resultado de cohorte es
// común con useResultadosI3 y vive en useResultadoCohortePorAsignatura.ts
// (Fase 4, extracción a shared).
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
  const [exportando, setExportando] = useState(false);

  const {
    cargando,
    error,
    resultadoCohorte,
    detalle,
    seleccionado: asig,
    selectedIdx: selectedAsig,
    setSelectedIdx: setSelectedAsig,
  } = useResultadoCohortePorAsignatura<ResultadoAsignatura, ResultadoCohorte>({
    career,
    cohort,
    pao,
    onAsignaturaChange,
    fetchResultadoCohorte: obtenerResultadoCohorte,
  });

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
