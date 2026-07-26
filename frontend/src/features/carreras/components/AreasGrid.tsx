import { AlertCircle } from 'lucide-react';

import CompassIcon from '../../../shared/components/CompassIcon';

import type { Career, CareerArea } from '../../../types/index';

export default function AreasGrid({
  areas,
  loadingCareers,
  careersError,
  onRetry,
  onSelect,
}: {
  areas: CareerArea[];
  loadingCareers: boolean;
  careersError: string;
  onRetry: () => void;
  onSelect: (career: Career) => void;
}) {
  if (loadingCareers) {
    return (
      <div className="flex-1 flex items-center justify-center">
        <p className="text-sm font-semibold" style={{ color: '#5A7295' }}>
          Cargando carreras...
        </p>
      </div>
    );
  }

  if (careersError) {
    return (
      <div className="flex-1 flex flex-col items-center justify-center gap-3">
        <AlertCircle size={28} style={{ color: '#DC2626' }} />
        <p className="text-sm text-center" style={{ color: '#DC2626' }}>
          {careersError}
        </p>
        <button
          type="button"
          onClick={onRetry}
          className="px-4 py-2 rounded-xl text-xs font-bold"
          style={{ background: '#1B3A6B', color: '#fff' }}
        >
          Reintentar
        </button>
      </div>
    );
  }

  return (
    <div className="flex-1 min-h-0 px-6 pb-5 grid grid-cols-3 gap-4 overflow-hidden">
      {areas.map((area) => (
        <div
          key={area.name}
          className="bg-white rounded-xl overflow-hidden flex flex-col border"
          style={{
            borderColor: 'rgba(27,58,107,0.1)',
            boxShadow: '0 1px 8px rgba(0,0,0,0.05)',
          }}
        >
          <div
            className="relative flex-shrink-0"
            style={{
              height: 110,
            }}
          >
            <img src={area.image} alt={area.name} className="w-full h-full object-cover" />

            <div
              className="absolute inset-0"
              style={{
                background: 'linear-gradient(to bottom,rgba(15,30,60,0.1),rgba(15,30,60,0.7))',
              }}
            />

            <div className="absolute bottom-0 left-0 right-0 px-3 pb-2 flex items-center gap-2">
              <CompassIcon size={28} />

              <h2
                className="text-xs font-bold text-white leading-tight"
                style={{
                  fontFamily: "'Libre Baskerville',serif",
                }}
              >
                {area.name}
              </h2>
            </div>
          </div>

          <div
            style={{
              height: 1,
              background: 'rgba(27,58,107,0.08)',
            }}
          />

          <div className="flex-1 flex flex-col justify-start overflow-hidden">
            {area.careers.map((career, index) => (
              <div
                key={career.name}
                onClick={() => {
                  if (career.clickable) {
                    onSelect(career);
                  }
                }}
                className={`flex items-center justify-between px-3 transition-colors ${
                  career.clickable ? 'cursor-pointer hover:bg-blue-50 group' : 'cursor-default'
                }`}
                style={{
                  borderBottom:
                    index < area.careers.length - 1 ? '1px solid rgba(27,58,107,0.06)' : 'none',
                  minHeight: 34,
                  paddingTop: 5,
                  paddingBottom: 5,
                }}
              >
                <span
                  className={`text-xs leading-tight ${
                    career.clickable ? 'font-semibold group-hover:text-blue-700' : ''
                  }`}
                  style={{
                    color: career.clickable ? '#1B3A6B' : '#374151',
                  }}
                >
                  {career.name}
                </span>

                {career.clickable && (
                  <span
                    className="text-xs px-1.5 py-0.5 rounded font-semibold flex-shrink-0 ml-2"
                    style={{
                      background: '#DBEAFE',
                      color: '#1D4ED8',
                    }}
                  >
                    →
                  </span>
                )}
              </div>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
