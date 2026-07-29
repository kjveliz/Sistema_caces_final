import { AlertCircle, Loader2, Trash2, X } from 'lucide-react';
import type { FormEvent } from 'react';

import type { CohorteEvaluacion } from '../../shared/services/cohortes';

/**
 * Modal de "Eliminar cohorte". Mismo lenguaje visual que DeleteCareerModal
 * (overlay + tarjeta blanca + franja roja de advertencia + input de
 * confirmación por nombre), pero más chico y con una sola confirmación: si
 * la cohorte tiene evidencia real (409 de eliminarCohorteSeguimiento), el
 * llamador reintenta automáticamente con eliminarCohorteForzada sin pedir un
 * segundo paso -- ver CohortsManagementModal.tsx.
 */
export default function DeleteCohortModal({
  open,
  onClose,
  cohortes,
  cohorteId,
  onCohorteIdChange,
  confirmacion,
  onConfirmacionChange,
  eliminando,
  onSubmit,
}: {
  open: boolean;
  onClose: () => void;
  cohortes: CohorteEvaluacion[];
  cohorteId: string;
  onCohorteIdChange: (value: string) => void;
  confirmacion: string;
  onConfirmacionChange: (value: string) => void;
  eliminando: boolean;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}) {
  if (!open) return null;

  const cohorteSeleccionada = cohortes.find((item) => String(item.id_cohorte) === cohorteId);
  const confirmacionLista =
    Boolean(cohorteSeleccionada) &&
    confirmacion.trim().toUpperCase() === cohorteSeleccionada?.nombre_cohorte.toUpperCase();

  return (
    <div
      className="fixed inset-0 z-[80] flex items-center justify-center"
      style={{ background: 'rgba(0,0,0,0.4)' }}
    >
      <div
        className="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4"
        style={{ border: '1px solid rgba(27,58,107,0.12)' }}
      >
        <div
          className="flex items-center justify-between px-5 py-3.5 border-b"
          style={{ borderColor: 'rgba(27,58,107,0.1)' }}
        >
          <h2
            className="text-sm font-bold"
            style={{ fontFamily: "'Libre Baskerville',serif", color: '#0F1E3C' }}
          >
            Eliminar cohorte
          </h2>

          <button
            type="button"
            onClick={onClose}
            className="p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
          >
            <X size={14} style={{ color: '#5A7295' }} />
          </button>
        </div>

        <form onSubmit={onSubmit} className="px-5 py-4 space-y-3.5">
          <div>
            <label
              className="block text-xs font-bold uppercase tracking-widest mb-1.5"
              style={{ color: '#5A7295' }}
            >
              Cohorte
            </label>

            <select
              value={cohorteId}
              onChange={(event) => onCohorteIdChange(event.target.value)}
              required
              className="w-full px-3 py-2 rounded-xl text-sm border outline-none"
              style={{
                background: '#F4F7FB',
                borderColor: 'rgba(27,58,107,0.2)',
                color: '#0F1E3C',
              }}
            >
              <option value="">— Seleccionar cohorte —</option>

              {cohortes.map((item) => (
                <option key={item.id_cohorte} value={item.id_cohorte}>
                  {item.nombre_cohorte} — {item.carrera}
                </option>
              ))}
            </select>
          </div>

          {cohorteSeleccionada && (
            <>
              <div
                className="rounded-xl px-3 py-2.5 flex items-start gap-2"
                style={{
                  background: '#FEE2E2',
                  border: '1px solid #DC262630',
                }}
              >
                <AlertCircle
                  size={13}
                  style={{ color: '#DC2626', flexShrink: 0, marginTop: 1 }}
                />

                <p className="text-xs" style={{ color: '#991B1B' }}>
                  Se borrará la cohorte, su evaluación, sus períodos (PAO), sus asignaturas y la
                  evidencia asociada. Esta acción no se puede deshacer. Escriba{' '}
                  <strong>{cohorteSeleccionada.nombre_cohorte}</strong> para confirmar.
                </p>
              </div>

              <input
                type="text"
                value={confirmacion}
                onChange={(event) => onConfirmacionChange(event.target.value)}
                placeholder={`Escriba "${cohorteSeleccionada.nombre_cohorte}" para confirmar`}
                className="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                style={{
                  background: '#fff',
                  borderColor: '#DC262640',
                  color: '#0F1E3C',
                }}
              />
            </>
          )}

          <div className="flex gap-3 pt-1">
            <button
              type="submit"
              disabled={!confirmacionLista || eliminando}
              className="flex-1 py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 transition-all hover:opacity-90"
              style={{
                background: confirmacionLista && !eliminando ? '#DC2626' : '#E5E7EB',
                color: confirmacionLista && !eliminando ? '#fff' : '#9CA3AF',
                cursor: confirmacionLista ? 'pointer' : 'not-allowed',
              }}
            >
              {eliminando ? (
                <Loader2 size={14} className="animate-spin" />
              ) : (
                <Trash2 size={14} />
              )}
              {eliminando ? 'Eliminando...' : 'Eliminar'}
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
