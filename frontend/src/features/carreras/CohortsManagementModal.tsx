import { useEffect, useMemo, useState } from 'react';
import { AlertCircle, CalendarDays, Loader2, Plus, RefreshCw, Trash2, X } from 'lucide-react';
import { toast } from 'sonner';

import {
  cambiarEstadoEvaluacion,
  crearCohorteEvaluacion,
  listarCohortesEvaluaciones,
  type CohorteEvaluacion,
} from '../../shared/services/cohortes';
import {
  CohorteConEvidenciaError,
  eliminarCohorteForzada,
  eliminarCohorteSeguimiento,
} from '../../shared/services/seguimientoSyllabus';

interface CohortsManagementModalProps {
  open: boolean;
  onClose: () => void;
  carreras: {
    id: number;
    nombre: string;
  }[];
}

type EstadoEvaluacion = 'Activa' | 'Pendiente' | 'Cerrada';

export default function CohortsManagementModal({
  open,
  onClose,
  carreras,
}: CohortsManagementModalProps) {
  const [datos, setDatos] = useState<CohorteEvaluacion[]>([]);
  const [cargando, setCargando] = useState(false);
  const [guardando, setGuardando] = useState(false);
  const [cambiandoId, setCambiandoId] = useState<number | null>(null);

  const [idCarrera, setIdCarrera] = useState('');
  const [nombreCohorte, setNombreCohorte] = useState('');
  const [fechaInicio, setFechaInicio] = useState('');
  const [fechaFin, setFechaFin] = useState('');
  const [estado, setEstado] = useState<EstadoEvaluacion>('Activa');

  // Eliminar cohorte (Parte A del plan de malla curricular xlsx, endpoint
  // DELETE /seguimiento-syllabus/cohortes/{id} ya pensado desde el
  // backend también como "acción manual de limpieza en Gestión de
  // cohortes" -- ver comentario de SeguimientoSyllabusController::
  // cohorteEliminar()). Requiere escribir el nombre exacto de la cohorte
  // para confirmar, mismo patrón que DeleteCareerModal.
  const [idCohorteAEliminar, setIdCohorteAEliminar] = useState('');
  const [confirmacionEliminar, setConfirmacionEliminar] = useState('');
  const [eliminandoCohorte, setEliminandoCohorte] = useState(false);
  // Cuando eliminarCohorteSeguimiento() responde 409 (evidencia real
  // subida), se ofrece un segundo paso de "forzar eliminación" -- mismo
  // patrón que CarreraConEvaluacionesError + eliminarCarreraForzada() en
  // DeleteCareerModal, pero acá inline en el mismo panel en vez de un
  // modal aparte. Exige escribir "FORZAR" (no el nombre de la cohorte de
  // nuevo) para dejar clara la diferencia con la confirmación normal.
  const [requiereForzarEliminacion, setRequiereForzarEliminacion] = useState(false);
  const [confirmacionForzar, setConfirmacionForzar] = useState('');
  const [forzandoEliminacion, setForzandoEliminacion] = useState(false);

  useEffect(() => {
    if (open) void cargar();
  }, [open]);

  const totalActivas = useMemo(
    () => datos.filter((item) => item.estado === 'Activa').length,
    [datos],
  );

  async function cargar() {
    try {
      setCargando(true);
      setDatos(await listarCohortesEvaluaciones());
    } catch (error) {
      toast.error('No se pudieron cargar las cohortes', {
        description: error instanceof Error ? error.message : 'Ocurrió un error inesperado.',
      });
    } finally {
      setCargando(false);
    }
  }

  function limpiar() {
    setIdCarrera('');
    setNombreCohorte('');
    setFechaInicio('');
    setFechaFin('');
    setEstado('Activa');
  }

  async function guardar(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();

    try {
      setGuardando(true);

      await crearCohorteEvaluacion({
        idCarrera: Number(idCarrera),
        nombreCohorte,
        fechaInicio,
        fechaFin,
        estado,
      });

      toast.success('Cohorte y evaluación creadas');
      limpiar();
      await cargar();
    } catch (error) {
      toast.error('No se pudo crear la cohorte', {
        description: error instanceof Error ? error.message : 'Ocurrió un error inesperado.',
      });
    } finally {
      setGuardando(false);
    }
  }

  async function actualizarEstado(item: CohorteEvaluacion, nuevoEstado: EstadoEvaluacion) {
    if (!item.id_evaluacion) return;

    try {
      setCambiandoId(item.id_evaluacion);
      await cambiarEstadoEvaluacion(item.id_evaluacion, nuevoEstado);

      setDatos((actuales) =>
        actuales.map((registro) =>
          registro.id_evaluacion === item.id_evaluacion
            ? { ...registro, estado: nuevoEstado }
            : registro,
        ),
      );

      toast.success('Estado actualizado');
    } catch (error) {
      toast.error('No se pudo cambiar el estado', {
        description: error instanceof Error ? error.message : 'Ocurrió un error inesperado.',
      });
    } finally {
      setCambiandoId(null);
    }
  }

  const cohorteAEliminar = datos.find(
    (item) => String(item.id_cohorte) === idCohorteAEliminar,
  );
  const confirmacionListaParaEliminar =
    Boolean(cohorteAEliminar) &&
    confirmacionEliminar.trim().toUpperCase() === cohorteAEliminar?.nombre_cohorte.toUpperCase();

  async function eliminarCohorte() {
    if (!cohorteAEliminar || !confirmacionListaParaEliminar) return;

    try {
      setEliminandoCohorte(true);
      setRequiereForzarEliminacion(false);

      const resumen = await eliminarCohorteSeguimiento(cohorteAEliminar.id_cohorte);

      toast.success('Cohorte eliminada', {
        description: `${resumen.periodos_borrados} período(s) y ${resumen.asignaturas_borradas} asignatura(s) borrados.`,
      });

      setIdCohorteAEliminar('');
      setConfirmacionEliminar('');
      await cargar();
    } catch (error) {
      if (error instanceof CohorteConEvidenciaError) {
        // No es un error genérico: la cohorte tiene evidencia real y el
        // borrado normal la protege a propósito. Se ofrece un segundo paso
        // explícito de "forzar" en vez de solo mostrar el toast de error.
        setRequiereForzarEliminacion(true);
        toast.error('No se pudo eliminar la cohorte', { description: error.message });
      } else {
        toast.error('No se pudo eliminar la cohorte', {
          description: error instanceof Error ? error.message : 'Ocurrió un error inesperado.',
        });
      }
    } finally {
      setEliminandoCohorte(false);
    }
  }

  async function forzarEliminacionCohorte() {
    if (!cohorteAEliminar || confirmacionForzar.trim().toUpperCase() !== 'FORZAR') return;

    try {
      setForzandoEliminacion(true);

      const resumen = await eliminarCohorteForzada(cohorteAEliminar.id_cohorte);

      toast.success('Cohorte eliminada (forzado)', {
        description: `${resumen.periodos_borrados} período(s), ${resumen.asignaturas_borradas} asignatura(s) y ${resumen.evidencias_borradas} evidencia(s) borrados permanentemente.`,
      });

      setIdCohorteAEliminar('');
      setConfirmacionEliminar('');
      setRequiereForzarEliminacion(false);
      setConfirmacionForzar('');
      await cargar();
    } catch (error) {
      toast.error('No se pudo forzar la eliminación', {
        description: error instanceof Error ? error.message : 'Ocurrió un error inesperado.',
      });
    } finally {
      setForzandoEliminacion(false);
    }
  }

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-[70] flex items-center justify-center p-4"
      style={{ background: 'rgba(15,30,60,0.48)' }}
    >
      <div
        className="bg-white rounded-2xl shadow-2xl w-full max-w-6xl overflow-hidden flex flex-col"
        style={{
          maxHeight: '92vh',
          border: '1px solid rgba(27,58,107,0.12)',
        }}
      >
        <div
          className="px-6 py-4 flex items-center justify-between"
          style={{
            borderBottom: '1px solid rgba(27,58,107,0.1)',
            background: '#F8FAFD',
          }}
        >
          <div className="flex items-center gap-3">
            <div
              className="w-9 h-9 rounded-xl flex items-center justify-center"
              style={{ background: '#1B3A6B', color: '#fff' }}
            >
              <CalendarDays size={17} />
            </div>

            <div>
              <h2
                className="text-base font-bold"
                style={{
                  color: '#0F1E3C',
                  fontFamily: "'Libre Baskerville',serif",
                }}
              >
                Gestión de cohortes
              </h2>
              <p className="text-xs mt-0.5" style={{ color: '#5A7295' }}>
                {datos.length} registradas · {totalActivas} activas
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => void cargar()}
              className="p-2 rounded-lg hover:bg-blue-50"
            >
              <RefreshCw
                size={15}
                className={cargando ? 'animate-spin' : ''}
                style={{ color: '#1B3A6B' }}
              />
            </button>
            <button type="button" onClick={onClose} className="p-2 rounded-lg hover:bg-gray-100">
              <X size={16} style={{ color: '#5A7295' }} />
            </button>
          </div>
        </div>

        <div className="flex flex-col lg:flex-row flex-1 min-h-0 overflow-hidden">
          <div
            className="w-full lg:w-[360px] lg:flex-shrink-0 overflow-y-auto"
            style={{
              borderRight: '1px solid rgba(27,58,107,0.08)',
              background: '#FBFCFE',
            }}
          >
          <form
            onSubmit={guardar}
            className="p-5 space-y-4"
          >
            <h3 className="text-sm font-bold" style={{ color: '#0F1E3C' }}>
              Nueva cohorte
            </h3>

            <div>
              <label
                className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                style={{ color: '#5A7295' }}
              >
                Carrera
              </label>
              <select
                value={idCarrera}
                onChange={(e) => setIdCarrera(e.target.value)}
                required
                className="w-full px-3 py-2.5 rounded-xl text-sm border outline-none"
              >
                <option value="">— Seleccionar —</option>
                {carreras.map((carrera) => (
                  <option key={carrera.id} value={carrera.id}>
                    {carrera.nombre}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label
                className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                style={{ color: '#5A7295' }}
              >
                Nombre
              </label>
              <input
                value={nombreCohorte}
                onChange={(e) => setNombreCohorte(e.target.value)}
                placeholder="A2026"
                required
                className="w-full px-3 py-2.5 rounded-xl text-sm border outline-none"
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
                  value={fechaInicio}
                  onChange={(e) => setFechaInicio(e.target.value)}
                  required
                  className="w-full px-3 py-2.5 rounded-xl text-sm border outline-none"
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
                  value={fechaFin}
                  onChange={(e) => setFechaFin(e.target.value)}
                  required
                  className="w-full px-3 py-2.5 rounded-xl text-sm border outline-none"
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
              <select
                value={estado}
                onChange={(e) => setEstado(e.target.value as EstadoEvaluacion)}
                className="w-full px-3 py-2.5 rounded-xl text-sm border outline-none"
              >
                <option value="Activa">Activa</option>
                <option value="Pendiente">Pendiente</option>
                <option value="Cerrada">Cerrada</option>
              </select>
            </div>

            <button
              type="submit"
              disabled={guardando}
              className="w-full py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2"
              style={{
                background: guardando ? '#94A3B8' : '#1B3A6B',
                color: '#fff',
              }}
            >
              {guardando ? <Loader2 size={14} className="animate-spin" /> : <Plus size={14} />}
              {guardando ? 'Guardando...' : 'Crear cohorte'}
            </button>
          </form>

          <div
            className="p-5 space-y-4"
            style={{
              borderTop: '1px solid rgba(27,58,107,0.08)',
              background: '#FBFCFE',
            }}
          >
            <h3 className="text-sm font-bold flex items-center gap-2" style={{ color: '#DC2626' }}>
              <Trash2 size={14} />
              Eliminar cohorte
            </h3>

            <div>
              <label
                className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                style={{ color: '#5A7295' }}
              >
                Cohorte
              </label>
              <select
                value={idCohorteAEliminar}
                onChange={(e) => {
                  setIdCohorteAEliminar(e.target.value);
                  setConfirmacionEliminar('');
                  setRequiereForzarEliminacion(false);
                  setConfirmacionForzar('');
                }}
                className="w-full px-3 py-2.5 rounded-xl text-sm border outline-none"
              >
                <option value="">— Seleccionar —</option>
                {datos.map((item) => (
                  <option key={item.id_cohorte} value={item.id_cohorte}>
                    {item.nombre_cohorte} — {item.carrera}
                  </option>
                ))}
              </select>
            </div>

            {cohorteAEliminar && (
              <>
                <div
                  className="rounded-xl px-3 py-2.5 flex items-start gap-2"
                  style={{
                    background: '#FEE2E2',
                    border: '1px solid #DC262630',
                  }}
                >
                  <AlertCircle size={13} style={{ color: '#DC2626', flexShrink: 0, marginTop: 1 }} />
                  <p className="text-xs" style={{ color: '#991B1B' }}>
                    Se borrará la cohorte, su evaluación, sus períodos (PAO), sus asignaturas y la
                    evidencia asociada. Escriba{' '}
                    <strong>{cohorteAEliminar.nombre_cohorte}</strong> para confirmar.
                  </p>
                </div>

                <input
                  type="text"
                  value={confirmacionEliminar}
                  onChange={(e) => setConfirmacionEliminar(e.target.value)}
                  placeholder={`Escriba "${cohorteAEliminar.nombre_cohorte}" para confirmar`}
                  className="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                  style={{
                    background: '#fff',
                    borderColor: '#DC262640',
                    color: '#0F1E3C',
                  }}
                />

                <button
                  type="button"
                  onClick={() => void eliminarCohorte()}
                  disabled={!confirmacionListaParaEliminar || eliminandoCohorte}
                  className="w-full py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 transition-all"
                  style={{
                    background:
                      confirmacionListaParaEliminar && !eliminandoCohorte ? '#DC2626' : '#E5E7EB',
                    color: confirmacionListaParaEliminar && !eliminandoCohorte ? '#fff' : '#9CA3AF',
                    cursor: confirmacionListaParaEliminar ? 'pointer' : 'not-allowed',
                  }}
                >
                  {eliminandoCohorte ? (
                    <Loader2 size={14} className="animate-spin" />
                  ) : (
                    <Trash2 size={14} />
                  )}
                  {eliminandoCohorte ? 'Eliminando...' : 'Eliminar cohorte'}
                </button>

                {requiereForzarEliminacion && (
                  <div
                    className="rounded-xl p-3 space-y-2.5"
                    style={{ background: '#FEF2F2', border: '1px solid #DC262650' }}
                  >
                    <p className="text-xs font-bold" style={{ color: '#7F1D1D' }}>
                      Esta cohorte tiene evidencia real subida. Forzar la eliminación la borra
                      permanentemente junto con todo lo demás — no se puede deshacer.
                    </p>
                    <input
                      type="text"
                      value={confirmacionForzar}
                      onChange={(e) => setConfirmacionForzar(e.target.value)}
                      placeholder='Escriba "FORZAR" para confirmar'
                      className="w-full px-3 py-2 rounded-lg text-sm border outline-none"
                      style={{ background: '#fff', borderColor: '#DC262660', color: '#0F1E3C' }}
                    />
                    <button
                      type="button"
                      onClick={() => void forzarEliminacionCohorte()}
                      disabled={confirmacionForzar.trim().toUpperCase() !== 'FORZAR' || forzandoEliminacion}
                      className="w-full py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 transition-all"
                      style={{
                        background:
                          confirmacionForzar.trim().toUpperCase() === 'FORZAR' && !forzandoEliminacion
                            ? '#7F1D1D'
                            : '#E5E7EB',
                        color:
                          confirmacionForzar.trim().toUpperCase() === 'FORZAR' && !forzandoEliminacion
                            ? '#fff'
                            : '#9CA3AF',
                        cursor: confirmacionForzar.trim().toUpperCase() === 'FORZAR' ? 'pointer' : 'not-allowed',
                      }}
                    >
                      {forzandoEliminacion ? (
                        <Loader2 size={14} className="animate-spin" />
                      ) : (
                        <AlertCircle size={14} />
                      )}
                      {forzandoEliminacion ? 'Forzando...' : 'Forzar eliminación (borra evidencia)'}
                    </button>
                  </div>
                )}
              </>
            )}
          </div>
          </div>

          <div className="flex-1 flex flex-col min-h-0 overflow-hidden">
            <div
              className="grid grid-cols-[1fr_1.4fr_0.9fr_0.9fr] gap-3 px-5 py-3 flex-shrink-0"
              style={{
                background: '#F8FAFD',
                borderBottom: '1px solid rgba(27,58,107,0.08)',
              }}
            >
              {['Cohorte', 'Carrera', 'Periodo', 'Estado'].map((titulo) => (
                <span
                  key={titulo}
                  className="text-xs font-bold uppercase tracking-widest"
                  style={{ color: '#5A7295' }}
                >
                  {titulo}
                </span>
              ))}
            </div>

            <div className="flex-1 min-h-0 overflow-y-auto">
              {cargando ? (
                <div className="h-full flex items-center justify-center">
                  <Loader2 size={24} className="animate-spin" />
                </div>
              ) : (
                datos.map((item) => (
                  <div
                    key={item.id_cohorte}
                    className="grid grid-cols-[1fr_1.4fr_0.9fr_0.9fr] gap-3 items-center px-5 py-3"
                    style={{
                      borderBottom: '1px solid rgba(27,58,107,0.06)',
                    }}
                  >
                    <span className="text-sm font-bold" style={{ color: '#0F1E3C' }}>
                      {item.nombre_cohorte}
                    </span>
                    <span className="text-xs" style={{ color: '#5A7295' }}>
                      {item.carrera}
                    </span>
                    <span className="text-xs" style={{ color: '#5A7295' }}>
                      {item.fecha_inicio ?? '—'}
                      <br />
                      {item.fecha_fin ?? '—'}
                    </span>
                    <select
                      value={(item.estado ?? 'Pendiente') as EstadoEvaluacion}
                      disabled={!item.id_evaluacion || cambiandoId === item.id_evaluacion}
                      onChange={(e) =>
                        void actualizarEstado(item, e.target.value as EstadoEvaluacion)
                      }
                      className="px-2 py-1.5 rounded-lg text-xs border"
                    >
                      <option value="Activa">Activa</option>
                      <option value="Pendiente">Pendiente</option>
                      <option value="Cerrada">Cerrada</option>
                    </select>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
