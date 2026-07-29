import { useEffect, useState } from 'react';
import { ChevronDown } from 'lucide-react';

import EvidenceHeader from '../../../shared/components/EvidenceHeader';
import Breadcrumb from '../../../shared/components/Breadcrumb';

import { listarCohortesEvaluaciones } from '../../../shared/services/cohortes';

import type { Career, IndicatorDef } from '../../../types/index';

/**
 * Reemplaza el mock `COHORT_OPTIONS` de `shared/data/academic.ts` (siempre
 * las mismas 2 cohortes fijas, 'B 2025' y 'A 2026', sin importar la carrera)
 * por cohortes reales de ESTA carrera, mismo patrón ya usado en
 * StepConfigSyllabus.tsx para I1/I2/I3 (Parte G del plan de malla curricular
 * xlsx) — acá aplicado a I4/I5. `codigo_carrera` es el mismo campo que ya usa
 * CohortsManagementModal.tsx para identificar la carrera de cada cohorte.
 */
interface CohorteReal {
  idCohorte: number;
  nombreCohorte: string;
}

export default function StepConfigTitDes({
  career,
  indicator,
  preselectedIndicatorId,
  onBack,
  onBackToSelectIndicator,
  cohort,
  setCohort,
  onContinue,
}: {
  career: Career;
  indicator: IndicatorDef | undefined;
  preselectedIndicatorId?: string;
  onBack: () => void;
  onBackToSelectIndicator: () => void;
  cohort: string;
  setCohort: (v: string) => void;
  onContinue: () => void;
}) {
  const [cohortesReales, setCohortesReales] = useState<CohorteReal[]>([]);
  const [cargandoCohortes, setCargandoCohortes] = useState(true);

  useEffect(() => {
    let activo = true;
    setCargandoCohortes(true);

    listarCohortesEvaluaciones()
      .then((todas) => {
        if (!activo) return;
        setCohortesReales(
          todas
            .filter((c) => c.codigo_carrera === career.code)
            .map((c) => ({ idCohorte: c.id_cohorte, nombreCohorte: c.nombre_cohorte })),
        );
      })
      .catch(() => {
        if (activo) setCohortesReales([]);
      })
      .finally(() => {
        if (activo) setCargandoCohortes(false);
      });

    return () => {
      activo = false;
    };
  }, [career.code]);

  const canContinue = !!cohort;
  return (
    <div
      className="h-screen flex flex-col overflow-hidden"
      style={{ background: '#EEF2F7', fontFamily: "'Plus Jakarta Sans',sans-serif" }}
    >
      <EvidenceHeader
        title={indicator?.name || ''}
        subtitle="Seleccionar cohorte"
        backLabel="Indicadores"
        onBackClick={() => (preselectedIndicatorId ? onBack() : onBackToSelectIndicator())}
      />
      <div className="flex-1 flex items-center justify-center px-6">
        <div className="w-full max-w-sm">
          <Breadcrumb
            items={['Seleccionar indicador', indicator?.name || '', 'Seleccionar cohorte']}
          />
          <h2
            className="text-lg font-bold mb-1"
            style={{ fontFamily: "'Libre Baskerville',serif", color: '#0F1E3C' }}
          >
            Seleccionar cohorte
          </h2>
          <p className="text-sm mb-5" style={{ color: '#5A7295' }}>
            Seleccione la cohorte a la que pertenecen los documentos que va a cargar.
          </p>

          <div
            className="bg-white rounded-2xl p-5 mb-4"
            style={{ border: '1px solid rgba(27,58,107,0.09)' }}
          >
            <label
              className="block text-xs font-bold uppercase tracking-widest mb-1.5"
              style={{ color: '#5A7295' }}
            >
              Cohorte
            </label>
            <div className="relative">
              <select
                value={cohort}
                onChange={(e) => setCohort(e.target.value)}
                className="w-full px-3 py-2 rounded-xl text-sm border outline-none appearance-none cursor-pointer"
                style={{
                  background: '#F4F7FB',
                  borderColor: 'rgba(27,58,107,0.2)',
                  color: '#0F1E3C',
                }}
                disabled={cargandoCohortes}
              >
                <option value="">
                  {cargandoCohortes ? 'Cargando cohortes…' : '— Seleccionar cohorte —'}
                </option>
                {cohortesReales.map((c) => (
                  <option key={c.idCohorte} value={c.nombreCohorte}>
                    Cohorte {c.nombreCohorte}
                  </option>
                ))}
              </select>
              <ChevronDown
                size={14}
                className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                style={{ color: '#5A7295' }}
              />
            </div>
          </div>

          <button
            onClick={() => canContinue && onContinue()}
            disabled={!canContinue}
            className="w-full py-3 rounded-xl font-bold text-sm transition-all"
            style={{
              background: canContinue ? '#1B3A6B' : '#E5E7EB',
              color: canContinue ? '#fff' : '#9CA3AF',
              cursor: canContinue ? 'pointer' : 'not-allowed',
            }}
          >
            Continuar a carga de archivos →
          </button>
        </div>
      </div>
    </div>
  );
}
