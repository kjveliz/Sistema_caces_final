import { createContext, useContext } from 'react';

import type { Career, IndicatorDef } from '../types/index';

export interface CareerContextValue {
  career: Career;
  indicators: IndicatorDef[];
  setIndicators: (
    actualizar: IndicatorDef[] | ((actuales: IndicatorDef[]) => IndicatorDef[]),
  ) => void;
  selectedCohort: string;
  /** Cambia de cohorte y reinicia la lista de indicadores desde cero (mismo criterio que antes). */
  onCohortChange: (cohort: string) => void;
  selectedPAO: number;
  setSelectedPAO: (pao: number) => void;
}

export const CareerContext = createContext<CareerContextValue | null>(null);

/**
 * Hook de acceso al contexto de la carrera activa. Solo válido dentro de las
 * rutas anidadas bajo `/carreras/:code` (renderizadas por CareerLayout).
 */
export function useCareerContext(): CareerContextValue {
  const context = useContext(CareerContext);

  if (!context) {
    throw new Error('useCareerContext debe usarse dentro de una ruta anidada de CareerLayout.');
  }

  return context;
}
