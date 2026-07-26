import { obtenerResultadoCohorteTutorias } from '../../../shared/services/tutoriasAcademicas';
import type {
  ResultadoCohorteTutorias,
  ResultadoAsignaturaTutorias,
} from '../../../shared/services/tutoriasAcademicas';
import { getStatus } from '../../../shared/utils/evaluation';
import { useResultadoCohortePorAsignatura } from './useResultadoCohortePorAsignatura';
import type { Career } from '../../../types/index';

// Igual patrón que useResultadosI2: solo lectura, trae de la API real el
// resultado ya calculado por api/tutorias_academicas/_calculo.php (pesos,
// % por EF, escala) -- no se recalcula nada en el frontend. La resolución
// evaluación→período→resultado de cohorte es común con useResultadosI2 y
// vive en useResultadoCohortePorAsignatura.ts (Fase 4, extracción a shared).
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
  const {
    cargando,
    error,
    resultadoCohorte,
    detalle,
    seleccionado: materia,
    selectedIdx,
    setSelectedIdx,
  } = useResultadoCohortePorAsignatura<ResultadoAsignaturaTutorias, ResultadoCohorteTutorias>({
    career,
    cohort,
    pao,
    onAsignaturaChange,
    fetchResultadoCohorte: obtenerResultadoCohorteTutorias,
  });

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
