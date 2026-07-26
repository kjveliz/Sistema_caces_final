import { CheckCircle2 } from 'lucide-react';

import EvidenceHeader from '../../../shared/components/EvidenceHeader';
import Breadcrumb from '../../../shared/components/Breadcrumb';

import type { Career, IndicatorDef } from '../../../types/index';

const COLORS: Record<string, string> = {
  I1: '#2563EB',
  I2: '#7C3AED',
  I3: '#0891B2',
  I4: '#DC2626',
  I5: '#16A34A',
};

export default function StepSelectIndicator({
  career,
  indicators,
  onBack,
  onSelectIndicator,
}: {
  career: Career;
  indicators: IndicatorDef[];
  onBack: () => void;
  onSelectIndicator: (id: string) => void;
}) {
  const INDICATOR_ICONS = ['I1', 'I2', 'I3', 'I4', 'I5'];
  return (
    <div
      className="h-screen flex flex-col overflow-hidden"
      style={{ background: '#EEF2F7', fontFamily: "'Plus Jakarta Sans',sans-serif" }}
    >
      <EvidenceHeader
        title="Carga de Evidencias"
        subtitle={career.name}
        backLabel="Panel principal"
        onBackClick={onBack}
      />
      <div className="flex-1 overflow-auto">
        <div className="max-w-3xl mx-auto px-6 py-8">
          <Breadcrumb items={['Seleccionar indicador']} />
          <h2
            className="text-lg font-bold mb-1"
            style={{ fontFamily: "'Libre Baskerville',serif", color: '#0F1E3C' }}
          >
            ¿Para qué indicador desea cargar evidencias?
          </h2>
          <p className="text-sm mb-6" style={{ color: '#5A7295' }}>
            Seleccione el indicador al que pertenecen los documentos que va a subir.
          </p>
          <div className="grid grid-cols-1 gap-3">
            {indicators.map((ind) => {
              const done = ind.slots.filter((s) => s.file).length;
              const total = ind.slots.length;
              const complete = done === total;
              const color = COLORS[ind.id] || '#1B3A6B';
              return (
                <button
                  key={ind.id}
                  onClick={() => onSelectIndicator(ind.id)}
                  className="bg-white rounded-xl px-4 py-2.5 text-left flex items-center gap-3 transition-all hover:-translate-y-0.5 hover:shadow-lg group"
                  style={{
                    border: `1.5px solid ${complete ? '#16A34A' : 'rgba(27,58,107,0.12)'}`,
                    boxShadow: '0 1px 6px rgba(0,0,0,0.04)',
                  }}
                >
                  <div
                    className="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 font-bold text-xs"
                    style={{ background: `${color}18`, color }}
                  >
                    {ind.code}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p
                      className="font-semibold text-sm"
                      style={{ fontFamily: "'Libre Baskerville',serif", color: '#0F1E3C' }}
                    >
                      {ind.name}
                    </p>
                    <p className="text-xs truncate" style={{ color: '#5A7295' }}>
                      {['I1', 'I2', 'I3'].includes(ind.id)
                        ? 'Requiere selección de período · módulo · materia'
                        : 'Requiere selección de cohorte'}
                    </p>
                  </div>
                  <div className="flex-shrink-0 flex items-center gap-2">
                    <span
                      className="text-xs font-mono px-2 py-0.5 rounded-full"
                      style={{
                        background: complete ? '#DCFCE7' : '#F3F4F6',
                        color: complete ? '#16A34A' : '#6B7280',
                      }}
                    >
                      {done}/{total}
                    </span>
                    {complete && <CheckCircle2 size={14} style={{ color: '#16A34A' }} />}
                    <span className="text-blue-600 text-xs font-semibold group-hover:text-blue-700">
                      →
                    </span>
                  </div>
                </button>
              );
            })}
          </div>
        </div>
      </div>
    </div>
  );
}
