import { ChevronDown, Upload, X } from 'lucide-react';
import type { FormEvent, RefObject } from 'react';

import type { CareerArea } from '../../types/index';
import type { CrearCohorteParams } from '../../shared/services/cohortes';
import type { CarreraBD } from './hooks/useCareers';

export default function EditCareerModal({
  open,
  onClose,
  carrerasBD,
  areas,
  editCareerId,
  onSelectCareer,
  editCareerName,
  onNameChange,
  editCareerCode,
  onCodeChange,
  editCareerArea,
  onAreaChange,
  editCareerModalidad,
  onModalidadChange,
  updatingCareer,
  editCareerFile,
  onFileChange,
  fileRef,
  editCohorteNombre,
  onCohorteNombreChange,
  editCohorteFechaInicio,
  onCohorteFechaInicioChange,
  editCohorteFechaFin,
  onCohorteFechaFinChange,
  editCohorteEstado,
  onCohorteEstadoChange,
  onSubmit,
}: {
  open: boolean;
  onClose: () => void;
  carrerasBD: CarreraBD[];
  areas: CareerArea[];
  editCareerId: string;
  onSelectCareer: (id: string) => void;
  editCareerName: string;
  onNameChange: (value: string) => void;
  editCareerCode: string;
  onCodeChange: (value: string) => void;
  editCareerArea: string;
  onAreaChange: (value: string) => void;
  editCareerModalidad: string;
  onModalidadChange: (value: string) => void;
  updatingCareer: boolean;
  editCareerFile: File | null;
  onFileChange: (file: File | null) => void;
  fileRef: RefObject<HTMLInputElement>;
  editCohorteNombre: string;
  onCohorteNombreChange: (value: string) => void;
  editCohorteFechaInicio: string;
  onCohorteFechaInicioChange: (value: string) => void;
  editCohorteFechaFin: string;
  onCohorteFechaFinChange: (value: string) => void;
  editCohorteEstado: CrearCohorteParams['estado'];
  onCohorteEstadoChange: (value: CrearCohorteParams['estado']) => void;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}) {
  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center"
      style={{ background: 'rgba(0,0,0,0.4)' }}
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
            Editar Carrera
          </h2>

          <button
            type="button"
            onClick={onClose}
            disabled={updatingCareer}
            className="p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
          >
            <X size={15} style={{ color: '#5A7295' }} />
          </button>
        </div>

        <form onSubmit={onSubmit} className="px-6 py-5 space-y-4 overflow-y-auto">
          <div>
            <label
              className="block text-xs font-bold uppercase tracking-widest mb-1.5"
              style={{ color: '#5A7295' }}
            >
              Carrera que desea editar
            </label>

            <div className="relative">
              <select
                value={editCareerId}
                onChange={(event) => onSelectCareer(event.target.value)}
                required
                disabled={updatingCareer}
                className="w-full px-3 py-2 rounded-xl text-sm border outline-none appearance-none cursor-pointer"
                style={{
                  background: '#F4F7FB',
                  borderColor: 'rgba(27,58,107,0.2)',
                  color: '#0F1E3C',
                }}
              >
                <option value="">— Seleccionar carrera —</option>

                {carrerasBD.map((carrera) => (
                  <option key={carrera.id_carrera} value={carrera.id_carrera}>
                    {carrera.nombre}
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

          <div
            className="space-y-4"
            style={{
              opacity: editCareerId ? 1 : 0.45,
              pointerEvents: editCareerId ? 'auto' : 'none',
            }}
          >
            <div>
              <label
                className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                style={{ color: '#5A7295' }}
              >
                Nombre
              </label>
              <input
                type="text"
                value={editCareerName}
                onChange={(event) => onNameChange(event.target.value)}
                required
                disabled={updatingCareer}
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
                style={{ color: '#5A7295' }}
              >
                Código institucional
              </label>
              <input
                type="text"
                value={editCareerCode}
                onChange={(event) => onCodeChange(event.target.value)}
                required
                maxLength={15}
                disabled={updatingCareer}
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
                style={{ color: '#5A7295' }}
              >
                Área de conocimiento
              </label>
              <div className="relative">
                <select
                  value={editCareerArea}
                  onChange={(event) => onAreaChange(event.target.value)}
                  required
                  disabled={updatingCareer}
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
                  style={{ color: '#5A7295' }}
                />
              </div>
            </div>

            <div>
              <label
                className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                style={{ color: '#5A7295' }}
              >
                Modalidad
              </label>
              <div className="relative">
                <select
                  value={editCareerModalidad}
                  onChange={(event) => onModalidadChange(event.target.value)}
                  required
                  disabled={updatingCareer}
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
                  style={{ color: '#5A7295' }}
                />
              </div>
            </div>

            <div>
              <label
                className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                style={{ color: '#5A7295' }}
              >
                Generar malla curricular (opcional)
              </label>

              <p className="text-xs mb-2" style={{ color: '#9CA3AF' }}>
                Subí un Excel (.xlsx) para crear una cohorte nueva con sus PAO y asignaturas. No
                reemplaza la malla ya registrada de la carrera.
              </p>

              <div className="flex items-center gap-3">
                <button
                  type="button"
                  onClick={() => fileRef.current?.click()}
                  disabled={updatingCareer}
                  className="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold border transition-all hover:bg-blue-50"
                  style={{
                    borderColor: 'rgba(27,58,107,0.2)',
                    color: '#1B3A6B',
                  }}
                >
                  <Upload size={12} />
                  {editCareerFile ? 'Cambiar archivo' : 'Subir Excel'}
                </button>

                {editCareerFile && (
                  <span
                    className="text-xs truncate max-w-40"
                    style={{ color: '#16A34A' }}
                  >
                    {editCareerFile.name}
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

            {editCareerFile && (
              <div className="space-y-4 pl-3 border-l-2" style={{ borderColor: 'rgba(27,58,107,0.15)' }}>
                <div>
                  <label
                    className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                    style={{ color: '#5A7295' }}
                  >
                    Nombre de la cohorte
                  </label>

                  <input
                    type="text"
                    value={editCohorteNombre}
                    onChange={(event) => onCohorteNombreChange(event.target.value)}
                    placeholder="A2026"
                    required
                    disabled={updatingCareer}
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
                      style={{ color: '#5A7295' }}
                    >
                      Inicio
                    </label>

                    <input
                      type="date"
                      value={editCohorteFechaInicio}
                      onChange={(event) => onCohorteFechaInicioChange(event.target.value)}
                      required
                      disabled={updatingCareer}
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
                      style={{ color: '#5A7295' }}
                    >
                      Fin
                    </label>

                    <input
                      type="date"
                      value={editCohorteFechaFin}
                      onChange={(event) => onCohorteFechaFinChange(event.target.value)}
                      required
                      disabled={updatingCareer}
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
                    style={{ color: '#5A7295' }}
                  >
                    Estado
                  </label>

                  <div className="relative">
                    <select
                      value={editCohorteEstado}
                      onChange={(event) =>
                        onCohorteEstadoChange(event.target.value as CrearCohorteParams['estado'])
                      }
                      disabled={updatingCareer}
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
                      style={{ color: '#5A7295' }}
                    />
                  </div>
                </div>
              </div>
            )}
          </div>

          <div className="flex gap-3 pt-1">
            <button
              type="submit"
              disabled={!editCareerId || updatingCareer}
              className="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all hover:opacity-90"
              style={{
                background: editCareerId && !updatingCareer ? '#1B3A6B' : '#E5E7EB',
                color: editCareerId && !updatingCareer ? '#fff' : '#9CA3AF',
                cursor: editCareerId && !updatingCareer ? 'pointer' : 'not-allowed',
              }}
            >
              {updatingCareer ? 'Actualizando...' : 'Guardar cambios'}
            </button>

            <button
              type="button"
              onClick={onClose}
              disabled={updatingCareer}
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
