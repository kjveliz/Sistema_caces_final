import { AlertCircle, ChevronDown, X } from 'lucide-react';
import type { FormEvent } from 'react';

import type { Career, CareerArea } from '../../types/index';

export default function DeleteCareerModal({
  open,
  onClose,
  areas,
  deleteArea,
  onAreaChange,
  deleteCareer,
  onCareerChange,
  deletableCareers,
  deletingCareer,
  onSubmit,
}: {
  open: boolean;
  onClose: () => void;
  areas: CareerArea[];
  deleteArea: string;
  onAreaChange: (value: string) => void;
  deleteCareer: string;
  onCareerChange: (value: string) => void;
  deletableCareers: Career[];
  deletingCareer: boolean;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}) {
  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center"
      style={{
        background: 'rgba(0,0,0,0.4)',
      }}
    >
      <div
        className="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4"
        style={{
          border: '1px solid rgba(27,58,107,0.12)',
        }}
      >
        <div
          className="flex items-center justify-between px-6 py-4 border-b"
          style={{
            borderColor: 'rgba(27,58,107,0.1)',
          }}
        >
          <h2
            className="text-base font-bold"
            style={{
              fontFamily: "'Libre Baskerville',serif",
              color: '#0F1E3C',
            }}
          >
            Eliminar Carrera
          </h2>

          <button
            type="button"
            onClick={onClose}
            className="p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
          >
            <X
              size={15}
              style={{
                color: '#5A7295',
              }}
            />
          </button>
        </div>

        <form onSubmit={onSubmit} className="px-6 py-5 space-y-4">
          <p
            className="text-sm"
            style={{
              color: '#5A7295',
            }}
          >
            Seleccione el área y la carrera que desea eliminar del sistema.
          </p>

          <div>
            <label
              className="block text-xs font-bold uppercase tracking-widest mb-1.5"
              style={{
                color: '#5A7295',
              }}
            >
              Área de conocimiento
            </label>

            <div className="relative">
              <select
                value={deleteArea}
                onChange={(event) => onAreaChange(event.target.value)}
                required
                className="w-full px-3 py-2 rounded-xl text-sm border outline-none appearance-none cursor-pointer"
                style={{
                  background: '#F4F7FB',
                  borderColor: 'rgba(27,58,107,0.2)',
                  color: '#0F1E3C',
                }}
              >
                <option value="">— Seleccionar área —</option>

                {areas.map((area) => (
                  <option key={area.name} value={area.name}>
                    {area.name}
                  </option>
                ))}
              </select>

              <ChevronDown
                size={14}
                className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                style={{
                  color: '#5A7295',
                }}
              />
            </div>
          </div>

          <div
            style={{
              opacity: deleteArea ? 1 : 0.45,
              pointerEvents: deleteArea ? 'auto' : 'none',
            }}
          >
            <label
              className="block text-xs font-bold uppercase tracking-widest mb-1.5"
              style={{
                color: '#5A7295',
              }}
            >
              Carrera
            </label>

            <div className="relative">
              <select
                value={deleteCareer}
                onChange={(event) => onCareerChange(event.target.value)}
                required={Boolean(deleteArea)}
                className="w-full px-3 py-2 rounded-xl text-sm border outline-none appearance-none cursor-pointer"
                style={{
                  background: '#F4F7FB',
                  borderColor: 'rgba(27,58,107,0.2)',
                  color: '#0F1E3C',
                }}
              >
                <option value="">— Seleccionar carrera —</option>

                {deletableCareers.map((career) => (
                  <option key={career.code} value={career.code}>
                    {career.name}
                  </option>
                ))}
              </select>

              <ChevronDown
                size={14}
                className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                style={{
                  color: '#5A7295',
                }}
              />
            </div>
          </div>

          {deleteCareer && (
            <div
              className="rounded-xl px-3 py-2.5 flex items-center gap-2"
              style={{
                background: '#FEE2E2',
                border: '1px solid #DC262630',
              }}
            >
              <AlertCircle
                size={13}
                style={{
                  color: '#DC2626',
                  flexShrink: 0,
                }}
              />

              <p
                className="text-xs"
                style={{
                  color: '#991B1B',
                }}
              >
                La carrera se eliminará permanentemente de la base de datos.
              </p>
            </div>
          )}

          <div className="flex gap-3 pt-1">
            <button
              type="submit"
              disabled={!deleteCareer || deletingCareer}
              className="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all hover:opacity-90"
              style={{
                background: deleteCareer && !deletingCareer ? '#DC2626' : '#E5E7EB',
                color: deleteCareer && !deletingCareer ? '#fff' : '#9CA3AF',
                cursor: deleteCareer ? 'pointer' : 'not-allowed',
              }}
            >
              <span>{deletingCareer ? 'Eliminando...' : 'Eliminar'}</span>
            </button>

            <button
              type="button"
              onClick={onClose}
              className="flex-1 py-2.5 rounded-xl text-sm font-bold border transition-all hover:bg-gray-50"
              style={{
                borderColor: 'rgba(27,58,107,0.2)',
                color: '#5A7295',
              }}
            >
              Cancelar
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
