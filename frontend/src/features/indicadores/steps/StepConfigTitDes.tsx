import { ChevronDown } from 'lucide-react';

import EvidenceHeader from '../../../shared/components/EvidenceHeader';
import Breadcrumb from '../../../shared/components/Breadcrumb';

import { COHORT_OPTIONS } from '../../../shared/data/academic';

import type { IndicatorDef } from '../../../types/index';

export default function StepConfigTitDes({
  indicator,
  preselectedIndicatorId,
  onBack,
  onBackToSelectIndicator,
  cohort,
  setCohort,
  onContinue,
}: {
  indicator: IndicatorDef | undefined;
  preselectedIndicatorId?: string;
  onBack: () => void;
  onBackToSelectIndicator: () => void;
  cohort: string;
  setCohort: (v: string) => void;
  onContinue: () => void;
}) {
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
              >
                <option value="">— Seleccionar cohorte —</option>
                {COHORT_OPTIONS.map((c) => (
                  <option key={c} value={c}>
                    Cohorte {c}
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
