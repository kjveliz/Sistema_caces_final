import { ChevronDown, ExternalLink, HardDrive, Loader2, X } from 'lucide-react';
import type { FormEvent } from 'react';

import type { ModoAlmacenamiento } from '../../shared/services/carreras';
import type { CarreraBD } from './hooks/useCareers';

// GET /google-drive/conectar (GoogleDriveController::conectar(), Parte 22
// del plan de migración slim-legacy — reemplaza a
// api/google_drive/conectar.php) — redirige (302) al consentimiento OAuth
// de Google. Es navegación top-level real, no un endpoint JSON, así que se
// abre con window.open() en vez de llamarse con fetch() -- mismo criterio
// que urlVisorEvidenciaLegacy/urlVisorEvidenciaAsignatura para las URLs de
// google_drive/*.php que tampoco son JSON.
const URL_CONECTAR_DRIVE = 'http://localhost/sistemacaces/public/google-drive/conectar';

/**
 * Interruptor de almacenamiento (Drive/local) por carrera. Visible desde un
 * botón separado del menú "Gestionar" (admin+coordinador, ver decisión
 * tomada con el usuario en esta sesión) — no reutiliza EditCareerModal
 * porque ese modal completo (nombre/código/área/modalidad) sigue siendo
 * solo de administrador.
 */
export default function StorageSettingsModal({
  open,
  onClose,
  carrerasBD,
  storageCareerId,
  onSelectCareer,
  storageModo,
  onModoChange,
  storageRutaLocal,
  onRutaLocalChange,
  carreraSeleccionada,
  migrando,
  onSubmit,
}: {
  open: boolean;
  onClose: () => void;
  carrerasBD: CarreraBD[];
  storageCareerId: string;
  onSelectCareer: (id: string) => void;
  storageModo: ModoAlmacenamiento;
  onModoChange: (modo: ModoAlmacenamiento) => void;
  storageRutaLocal: string;
  onRutaLocalChange: (value: string) => void;
  carreraSeleccionada: CarreraBD | undefined;
  migrando: boolean;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
}) {
  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center"
      style={{ background: 'rgba(0,0,0,0.4)' }}
    >
      <div
        className="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4"
        style={{ border: '1px solid rgba(27,58,107,0.12)' }}
      >
        <div
          className="flex items-center justify-between px-6 py-4 border-b"
          style={{ borderColor: 'rgba(27,58,107,0.1)' }}
        >
          <h2
            className="text-base font-bold flex items-center gap-2"
            style={{ fontFamily: "'Libre Baskerville',serif", color: '#0F1E3C' }}
          >
            <HardDrive size={16} style={{ color: '#1B3A6B' }} />
            Almacenamiento de evidencia
          </h2>

          <button
            type="button"
            onClick={onClose}
            disabled={migrando}
            className="p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
          >
            <X size={15} style={{ color: '#5A7295' }} />
          </button>
        </div>

        <form onSubmit={onSubmit} className="px-6 py-5 space-y-4">
          <div>
            <label
              className="block text-xs font-bold uppercase tracking-widest mb-1.5"
              style={{ color: '#5A7295' }}
            >
              Carrera
            </label>

            <div className="relative">
              <select
                value={storageCareerId}
                onChange={(event) => onSelectCareer(event.target.value)}
                required
                disabled={migrando}
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
              opacity: storageCareerId ? 1 : 0.45,
              pointerEvents: storageCareerId ? 'auto' : 'none',
            }}
          >
            {carreraSeleccionada && (
              <p className="text-xs" style={{ color: '#5A7295' }}>
                Modo actual:{' '}
                <span className="font-bold" style={{ color: '#0F1E3C' }}>
                  {carreraSeleccionada.modo_almacenamiento === 'drive'
                    ? 'Google Drive'
                    : 'Almacenamiento local'}
                </span>
                {carreraSeleccionada.modo_almacenamiento === 'local' &&
                  carreraSeleccionada.ruta_almacenamiento_local && (
                    <> ({carreraSeleccionada.ruta_almacenamiento_local})</>
                  )}
              </p>
            )}

            <div>
              <label
                className="block text-xs font-bold uppercase tracking-widest mb-2"
                style={{ color: '#5A7295' }}
              >
                Nuevo destino
              </label>

              <div className="grid grid-cols-2 gap-2">
                {(['drive', 'local'] as const).map((modo) => (
                  <button
                    key={modo}
                    type="button"
                    disabled={migrando}
                    onClick={() => onModoChange(modo)}
                    className="py-2.5 rounded-xl text-sm font-bold border transition-all"
                    style={{
                      background: storageModo === modo ? '#1B3A6B' : '#F4F7FB',
                      color: storageModo === modo ? '#fff' : '#5A7295',
                      borderColor:
                        storageModo === modo ? '#1B3A6B' : 'rgba(27,58,107,0.2)',
                    }}
                  >
                    {modo === 'drive' ? 'Google Drive' : 'Local'}
                  </button>
                ))}
              </div>
            </div>

            {storageModo === 'drive' && (
              <div
                className="rounded-xl px-3 py-3 space-y-2.5"
                style={{ background: '#F4F7FB', border: '1px solid rgba(27,58,107,0.15)' }}
              >
                <p className="text-xs" style={{ color: '#5A7295' }}>
                  Si todavía no vinculó una cuenta de Google Drive en este servidor, conéctela
                  antes de guardar.
                </p>

                <button
                  type="button"
                  onClick={() =>
                    window.open(URL_CONECTAR_DRIVE, '_blank', 'noopener,noreferrer')
                  }
                  disabled={migrando}
                  className="w-full py-2 rounded-xl text-sm font-bold border flex items-center justify-center gap-2 transition-all hover:bg-white"
                  style={{
                    background: '#fff',
                    borderColor: 'rgba(27,58,107,0.25)',
                    color: '#1B3A6B',
                  }}
                >
                  <ExternalLink size={13} />
                  Conectar Google Drive
                </button>
              </div>
            )}

            {storageModo === 'local' && (
              <div>
                <label
                  className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                  style={{ color: '#5A7295' }}
                >
                  Ruta local (opcional)
                </label>
                <input
                  type="text"
                  value={storageRutaLocal}
                  onChange={(event) => onRutaLocalChange(event.target.value)}
                  disabled={migrando}
                  placeholder="Ej: C:\Users\usuario\Documents\Evidencias"
                  className="w-full px-3 py-2 rounded-xl text-sm border outline-none"
                  style={{
                    background: '#F4F7FB',
                    borderColor: 'rgba(27,58,107,0.2)',
                    color: '#0F1E3C',
                  }}
                />
                <p className="text-xs mt-1.5" style={{ color: '#9CA3AF' }}>
                  Dejar vacío usa la carpeta local por defecto. Para un pendrive o disco
                  externo, indique la ruta absoluta en el servidor.
                </p>
              </div>
            )}

            {migrando && (
              <div
                className="flex items-center gap-2 rounded-xl px-3 py-2.5 text-xs font-semibold"
                style={{ background: '#EFF3FA', color: '#1B3A6B' }}
              >
                <Loader2 size={14} className="animate-spin" />
                Migrando evidencia ya subida al nuevo destino. Esto puede tardar; no cierre
                esta ventana.
              </div>
            )}
          </div>

          <div className="flex gap-3 pt-1">
            <button
              type="submit"
              disabled={!storageCareerId || migrando}
              className="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all hover:opacity-90"
              style={{
                background: storageCareerId && !migrando ? '#1B3A6B' : '#E5E7EB',
                color: storageCareerId && !migrando ? '#fff' : '#9CA3AF',
                cursor: storageCareerId && !migrando ? 'pointer' : 'not-allowed',
              }}
            >
              {migrando ? 'Migrando...' : 'Guardar y migrar'}
            </button>

            <button
              type="button"
              onClick={onClose}
              disabled={migrando}
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
