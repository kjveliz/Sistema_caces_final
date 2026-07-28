import { ChevronDown, Upload, X } from 'lucide-react';
import type { FormEvent, RefObject } from 'react';

import type { CareerArea } from '../../types/index';
import type { CrearCohorteParams } from '../../shared/services/cohortes';

export default function NewCareerModal({
  open,
  onClose,
  areas,
  newCareerName,
  onNameChange,
  newCareerCode,
  onCodeChange,
  newCareerArea,
  onAreaChange,
  newCareerModalidad,
  onModalidadChange,
  newCareerFile,
  onFileChange,
  fileRef,
  newCohorteNombre,
  onCohorteNombreChange,
  newCohorteFechaInicio,
  onCohorteFechaInicioChange,
  newCohorteFechaFin,
  onCohorteFechaFinChange,
  newCohorteEstado,
  onCohorteEstadoChange,
  savingCareer,
  onSubmit,
}: {
  open: boolean;
  onClose: () => void;
  areas: CareerArea[];
  newCareerName: string;
  onNameChange: (value: string) => void;
  newCareerCode: string;
  onCodeChange: (value: string) => void;
  newCareerArea: string;
  onAreaChange: (value: string) => void;
  newCareerModalidad: string;
  onModalidadChange: (value: string) => void;
  newCareerFile: File | null;
  onFileChange: (file: File | null) => void;
  fileRef: RefObject<HTMLInputElement>;
  newCohorteNombre: string;
  onCohorteNombreChange: (value: string) => void;
  newCohorteFechaInicio: string;
  onCohorteFechaInicioChange: (value: string) => void;
  newCohorteFechaFin: string;
  onCohorteFechaFinChange: (value: string) => void;
  newCohorteEstado: CrearCohorteParams['estado'];
  onCohorteEstadoChange: (value: CrearCohorteParams['estado']) => void;
  savingCareer: boolean;
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
        className="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 flex flex-col"
        style={{
          border: '1px solid rgba(27,58,107,0.12)',
          maxHeight: '90vh',
        }}
      >
        <div
          className="flex items-center justify-between px-6 py-4 border-b flex-shrink-0"
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
            Nueva Carrera
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

        <form onSubmit={onSubmit} className="px-6 py-5 space-y-4 overflow-y-auto">
          <div>
            <label
              className="block text-xs font-bold uppercase tracking-widest mb-1.5"
              style={{
                color: '#5A7295',
              }}
            >
              Nombre de la carrera
            </label>

            <input
              type="text"
              value={newCareerName}
              onChange={(event) => onNameChange(event.target.value)}
              placeholder="Ej: Administración de Empresas"
              required
              className="w-full px-3 py-2 rounded-xl text-sm border outline-none"
              style={{
                background: '#F4F7FB',
                borderColor: 'rgba(27,58,107,0.2)',
                color: '#0F1E3C',
              }}
            />
          </div>
          <div>
            <label
              className="block text-xs font-bold uppercase tracking-widest mb-1.5"
              style={{
                color: '#5A7295',
              }}
            >
              Código institucional
            </label>

            <input
              type="text"
              value={newCareerCode}
              onChange={(event) => onCodeChange(event.target.value)}
              placeholder="Ej: DESSOF"
              required
              maxLength={15}
              className="w-full px-3 py-2 rounded-xl text-sm border outline-none"
              style={{
                background: '#F4F7FB',
                borderColor: 'rgba(27,58,107,0.2)',
                color: '#0F1E3C',
              }}
            />

            <p
              className="text-xs mt-1"
              style={{
                color: '#9CA3AF',
              }}
            >
              Se guardará en mayúsculas y sin espacios.
            </p>
          </div>

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
                value={newCareerArea}
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
          <div>
            <label
              className="block text-xs font-bold uppercase tracking-widest mb-1.5"
              style={{
                color: '#5A7295',
              }}
            >
              Modalidad
            </label>

            <div className="relative">
              <select
                value={newCareerModalidad}
                onChange={(event) => onModalidadChange(event.target.value)}
                required
                className="w-full px-3 py-2 rounded-xl text-sm border outline-none appearance-none cursor-pointer"
                style={{
                  background: '#F4F7FB',
                  borderColor: 'rgba(27,58,107,0.2)',
                  color: '#0F1E3C',
                }}
              >
                <option value="">— Seleccionar modalidad —</option>

                <option value="Presencial">Presencial</option>

                <option value="En línea">En línea</option>

                <option value="Híbrida">Híbrida</option>
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
          <div>
            <label
              className="block text-xs font-bold uppercase tracking-widest mb-1.5"
              style={{
                color: '#5A7295',
              }}
            >
              Malla Curricular (XLSX) *
            </label>

            <div className="flex items-center gap-3">
              <button
                type="button"
                onClick={() => fileRef.current?.click()}
                className="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold border transition-all hover:bg-blue-50"
                style={{
                  borderColor: 'rgba(27,58,107,0.2)',
                  color: '#1B3A6B',
                }}
              >
                <Upload size={12} />

                {newCareerFile ? 'Cambiar archivo' : 'Subir Excel'}
              </button>

              {newCareerFile && (
                <span
                  className="text-xs truncate max-w-40"
                  style={{
                    color: '#16A34A',
                  }}
                >
                  {newCareerFile.name}
                </span>
              )}
            </div>

            <input
              ref={fileRef}
              type="file"
              accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
              className="hidden"
              onChange={(event) => onFileChange(event.target.files?.[0] ?? null)}
            />
          </div>

          <div>
            <label
              className="block text-xs font-bold uppercase tracking-widest mb-1.5"
              style={{
                color: '#5A7295',
              }}
            >
              Nombre de la cohorte
            </label>

            <input
              type="text"
              value={newCohorteNombre}
              onChange={(event) => onCohorteNombreChange(event.target.value)}
              placeholder="A2026"
              required
              className="w-full px-3 py-2 rounded-xl text-sm border outline-none"
              style={{
                background: '#F4F7FB',
                borderColor: 'rgba(27,58,107,0.2)',
                color: '#0F1E3C',
              }}
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label
                className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                style={{
                  color: '#5A7295',
                }}
              >
                Inicio
              </label>

              <input
                type="date"
                value={newCohorteFechaInicio}
                onChange={(event) => onCohorteFechaInicioChange(event.target.value)}
                required
                className="w-full px-3 py-2 rounded-xl text-sm border outline-none"
                style={{
                  background: '#F4F7FB',
                  borderColor: 'rgba(27,58,107,0.2)',
                  color: '#0F1E3C',
                }}
              />
            </div>

            <div>
              <label
                className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                style={{
                  color: '#5A7295',
                }}
              >
                Fin
              </label>

              <input
                type="date"
                value={newCohorteFechaFin}
                onChange={(event) => onCohorteFechaFinChange(event.target.value)}
                required
                className="w-full px-3 py-2 rounded-xl text-sm border outline-none"
                style={{
                  background: '#F4F7FB',
                  borderColor: 'rgba(27,58,107,0.2)',
                  color: '#0F1E3C',
                }}
              />
            </div>
          </div>

          <div>
            <label
              className="block text-xs font-bold uppercase tracking-widest mb-1.5"
              style={{
                color: '#5A7295',
              }}
            >
              Estado
            </label>

            <div className="relative">
              <select
                value={newCohorteEstado}
                onChange={(event) =>
                  onCohorteEstadoChange(event.target.value as CrearCohorteParams['estado'])
                }
                className="w-full px-3 py-2 rounded-xl text-sm border outline-none appearance-none cursor-pointer"
                style={{
                  background: '#F4F7FB',
                  borderColor: 'rgba(27,58,107,0.2)',
                  color: '#0F1E3C',
                }}
              >
                <option value="Activa">Activa</option>
                <option value="Pendiente">Pendiente</option>
                <option value="Cerrada">Cerrada</option>
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

          <div className="flex gap-3 pt-1">
            <button
              type="submit"
              disabled={savingCareer}
              className="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all hover:opacity-90 disabled:opacity-60"
              style={{
                background: '#1B3A6B',
                color: '#fff',
                cursor: savingCareer ? 'not-allowed' : 'pointer',
              }}
            >
              {savingCareer ? 'Guardando...' : 'Guardar'}
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
