import { ChevronDown } from 'lucide-react';

import EvidenceHeader from '../../../shared/components/EvidenceHeader';
import Breadcrumb from '../../../shared/components/Breadcrumb';

import BtnGroup from '../components/BtnGroup';

import { COHORT_OPTIONS, MATERIAS_BY_PAO_MODULE } from '../../../shared/data/academic';

import type { IndicatorDef } from '../../../types/index';

export default function StepConfigSyllabus({
  indicator,
  preselectedIndicatorId,
  onBack,
  onBackToSelectIndicator,
  cohort,
  setCohort,
  pao,
  setPao,
  module,
  setModule,
  materia,
  setMateria,
  onContinue,
}: {
  indicator: IndicatorDef | undefined;
  preselectedIndicatorId?: string;
  onBack: () => void;
  onBackToSelectIndicator: () => void;
  cohort: string;
  setCohort: (v: string) => void;
  pao: string;
  setPao: (v: string) => void;
  module: string;
  setModule: (v: string) => void;
  materia: string;
  setMateria: (v: string) => void;
  onContinue: () => void;
}) {
  const canContinue = cohort && pao && module && materia;
  const materias = pao && module ? (MATERIAS_BY_PAO_MODULE[pao]?.[module] ?? []) : [];
  const selectCls =
    'w-full px-3 py-2 rounded-xl text-sm border outline-none appearance-none cursor-pointer';
  const selectStyle = {
    background: '#F4F7FB',
    borderColor: 'rgba(27,58,107,0.2)',
    color: '#0F1E3C',
  };

  return (
    <div
      className="h-screen flex flex-col overflow-hidden"
      style={{ background: '#EEF2F7', fontFamily: "'Plus Jakarta Sans',sans-serif" }}
    >
      <EvidenceHeader
        title={indicator?.name || ''}
        subtitle="Configurar período"
        backLabel="Indicadores"
        onBackClick={() => (preselectedIndicatorId ? onBack() : onBackToSelectIndicator())}
      />
      <div className="flex-1 flex items-center justify-center px-6">
        <div className="w-full max-w-lg">
          <Breadcrumb
            items={['Seleccionar indicador', indicator?.name || '', 'Configurar período']}
          />
          <h2
            className="text-lg font-bold mb-1"
            style={{ fontFamily: "'Libre Baskerville',serif", color: '#0F1E3C' }}
          >
            Seleccionar período
          </h2>
          <p className="text-sm mb-5" style={{ color: '#5A7295' }}>
            Indique el período, módulo y materia correspondientes a los sílabos que va a cargar.
          </p>

          <div
            className="bg-white rounded-2xl p-5 space-y-4"
            style={{ border: '1px solid rgba(27,58,107,0.09)' }}
          >
            {/* Cohorte dropdown */}
            <div>
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
                  className={selectCls}
                  style={selectStyle}
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

            {/* PAO buttons */}
            <BtnGroup
              label="Período (PAO)"
              options={['PAO 1', 'PAO 2', 'PAO 3']}
              value={pao}
              onChange={setPao}
            />

            {/* Module buttons */}
            <BtnGroup
              label="Módulo"
              options={['A', 'B', 'C']}
              value={module}
              onChange={(v) => {
                setModule(v);
                setMateria('');
              }}
            />

            {/* Materia */}
            <div style={{ opacity: module ? 1 : 0.45, pointerEvents: module ? 'auto' : 'none' }}>
              <label
                className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                style={{ color: '#5A7295' }}
              >
                Materia
              </label>
              <div className="relative">
                <select
                  value={materia}
                  onChange={(e) => setMateria(e.target.value)}
                  className={selectCls}
                  style={selectStyle}
                >
                  <option value="">— Seleccionar materia —</option>
                  {materias.map((mat) => (
                    <option key={mat} value={mat}>
                      {mat}
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
          </div>

          <button
            onClick={() => canContinue && onContinue()}
            disabled={!canContinue}
            className="w-full mt-4 py-3 rounded-xl font-bold text-sm transition-all"
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
