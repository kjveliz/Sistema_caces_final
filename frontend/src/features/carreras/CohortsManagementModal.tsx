import { useEffect, useMemo, useRef, useState } from 'react';
import {
  CalendarDays,
  CheckCircle2,
  Filter,
  Loader2,
  Plus,
  RefreshCw,
  Trash2,
  Upload,
  X,
} from 'lucide-react';
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
import { generarMallaEnCohorte } from '../../shared/utils/generarMallaEnCohorte';
import DeleteCohortModal from './DeleteCohortModal';

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
  // DELETE /seguimiento-syllabus/cohortes/{id}, con fallback automático a
  // POST /cohortes/eliminar-forzada). Vive en un modal aparte
  // (DeleteCohortModal), abierto con el botón "Eliminar cohorte" al lado
  // de "Crear cohorte". Una sola confirmación: se escribe el nombre exacto
  // de la cohorte y, si el borrado normal responde 409 por evidencia real,
  // se reintenta solo con eliminarCohorteForzada() sin pedir un segundo
  // paso -- a diferencia de DeleteCareerModal, que sí tiene 2 pasos.
  const [modalEliminarAbierto, setModalEliminarAbierto] = useState(false);
  const [idCohorteAEliminar, setIdCohorteAEliminar] = useState('');
  const [confirmacionEliminar, setConfirmacionEliminar] = useState('');
  const [eliminandoCohorte, setEliminandoCohorte] = useState(false);
  // Filtro por carrera dentro del modal de "Eliminar cohorte" -- selector
  // aparte del filtro de la lista principal (filtroCarreraId), porque acá
  // además hay que limpiar la cohorte ya elegida si deja de pertenecer a la
  // carrera recién filtrada (ver handleFiltroCarreraEliminarChange).
  const [carreraFiltroEliminar, setCarreraFiltroEliminar] = useState('');

  // Filtro "por carrera" de la lista de cohortes (pedido del usuario el 30
  // jul 2026): a futuro va a haber muchas cohortes de muchas carreras, así
  // que se agrega un selector chico al lado del encabezado de la tabla que
  // reduce la lista a una sola carrera a la vez. '' = sin filtro (todas).
  const [filtroCarreraId, setFiltroCarreraId] = useState('');

  // Columna "Malla" (decisión acordada con el usuario el 30 jul 2026,
  // reemplaza al flujo de subir Excel desde "Editar carrera"): un solo
  // input de archivo oculto, compartido por todas las filas -- se abre
  // marcando primero para qué cohorte es (idCohortePendiente), y recién en
  // el onChange se sabe qué archivo se eligió.
  const mallaFileRef = useRef<HTMLInputElement>(null);
  const [idCohortePendiente, setIdCohortePendiente] = useState<number | null>(null);
  const [subiendoMallaId, setSubiendoMallaId] = useState<number | null>(null);

  useEffect(() => {
    if (open) void cargar();
  }, [open]);

  const totalActivas = useMemo(
    () => datos.filter((item) => item.estado === 'Activa').length,
    [datos],
  );

  const datosFiltrados = useMemo(
    () =>
      filtroCarreraId === ''
        ? datos
        : datos.filter((item) => String(item.id_carrera) === filtroCarreraId),
    [datos, filtroCarreraId],
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

  function abrirSelectorMalla(idCohorte: number) {
    setIdCohortePendiente(idCohorte);
    mallaFileRef.current?.click();
  }

  async function onMallaFileSeleccionado(archivo: File | null) {
    const idCohorte = idCohortePendiente;
    setIdCohortePendiente(null);
    if (mallaFileRef.current) mallaFileRef.current.value = '';
    if (!archivo || !idCohorte) return;

    try {
      setSubiendoMallaId(idCohorte);
      await generarMallaEnCohorte(idCohorte, archivo);

      toast.success('Malla curricular cargada');
      await cargar();
    } catch (error) {
      toast.error('No se pudo cargar la malla curricular', {
        description: error instanceof Error ? error.message : 'Ocurrió un error inesperado.',
      });
    } finally {
      setSubiendoMallaId(null);
    }
  }

  const cohorteAEliminar = datos.find(
    (item) => String(item.id_cohorte) === idCohorteAEliminar,
  );
  const confirmacionListaParaEliminar =
    Boolean(cohorteAEliminar) &&
    confirmacionEliminar.trim().toUpperCase() === cohorteAEliminar?.nombre_cohorte.toUpperCase();

  function abrirModalEliminar() {
    setIdCohorteAEliminar('');
    setConfirmacionEliminar('');
    setCarreraFiltroEliminar('');
    setModalEliminarAbierto(true);
  }

  function cerrarModalEliminar() {
    setModalEliminarAbierto(false);
    setIdCohorteAEliminar('');
    setConfirmacionEliminar('');
    setCarreraFiltroEliminar('');
  }

  // Al cambiar la carrera del filtro dentro del modal de eliminar, si la
  // cohorte ya elegida no pertenece a la carrera nueva, se limpia junto con
  // la confirmación por nombre (que ya no aplicaría a la cohorte anterior).
  function handleFiltroCarreraEliminarChange(value: string) {
    setCarreraFiltroEliminar(value);

    const cohorteActual = datos.find((item) => String(item.id_cohorte) === idCohorteAEliminar);
    if (value !== '' && cohorteActual && String(cohorteActual.id_carrera) !== value) {
      setIdCohorteAEliminar('');
      setConfirmacionEliminar('');
    }
  }

  async function eliminarCohorte(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!cohorteAEliminar || !confirmacionListaParaEliminar) return;

    try {
      setEliminandoCohorte(true);

      try {
        const resumen = await eliminarCohorteSeguimiento(cohorteAEliminar.id_cohorte);

        toast.success('Cohorte eliminada', {
          description: `${resumen.periodos_borrados} período(s) y ${resumen.asignaturas_borradas} asignatura(s) borrados.`,
        });
      } catch (error) {
        if (!(error instanceof CohorteConEvidenciaError)) throw error;

        // La cohorte tiene evidencia real subida: el borrado normal la
        // protege a propósito (409), pero acá se pide una sola
        // confirmación, así que se reintenta directo con la variante
        // forzada en vez de mostrar un segundo paso.
        const resumen = await eliminarCohorteForzada(cohorteAEliminar.id_cohorte);

        toast.success('Cohorte eliminada', {
          description: `Tenía evidencia real subida, se forzó el borrado: ${resumen.periodos_borrados} período(s), ${resumen.asignaturas_borradas} asignatura(s) y ${resumen.evidencias_borradas} evidencia(s) borrados permanentemente.`,
        });
      }

      cerrarModalEliminar();
      await cargar();
    } catch (error) {
      toast.error('No se pudo eliminar la cohorte', {
        description: error instanceof Error ? error.message : 'Ocurrió un error inesperado.',
      });
    } finally {
      setEliminandoCohorte(false);
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

            <button
              type="button"
              onClick={abrirModalEliminar}
              className="w-full py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 transition-all hover:opacity-90"
              style={{
                background: '#DC2626',
                color: '#fff',
              }}
            >
              <Trash2 size={14} />
              Eliminar cohorte
            </button>
          </form>
          </div>

          <div className="flex-1 flex flex-col min-h-0 overflow-hidden">
            <div
              className="flex items-center justify-between gap-3 px-5 py-2.5 flex-shrink-0"
              style={{
                background: '#FBFCFE',
                borderBottom: '1px solid rgba(27,58,107,0.06)',
              }}
            >
              <div
                className="flex items-center gap-1.5 pl-2.5 pr-1.5 py-1.5 rounded-full border transition-colors"
                style={{
                  borderColor: filtroCarreraId ? '#1B3A6B' : 'rgba(27,58,107,0.18)',
                  background: filtroCarreraId ? '#EAF0FA' : '#fff',
                }}
              >
                <Filter size={12} style={{ color: '#1B3A6B' }} />
                <select
                  value={filtroCarreraId}
                  onChange={(e) => setFiltroCarreraId(e.target.value)}
                  className="text-xs font-semibold bg-transparent outline-none pr-1"
                  style={{ color: '#0F1E3C' }}
                >
                  <option value="">Todas las carreras</option>
                  {carreras.map((carrera) => (
                    <option key={carrera.id} value={carrera.id}>
                      {carrera.nombre}
                    </option>
                  ))}
                </select>

                {filtroCarreraId !== '' && (
                  <button
                    type="button"
                    onClick={() => setFiltroCarreraId('')}
                    className="p-0.5 rounded-full hover:bg-blue-100 transition-colors"
                    title="Quitar filtro"
                  >
                    <X size={11} style={{ color: '#1B3A6B' }} />
                  </button>
                )}
              </div>

              <span className="text-xs flex-shrink-0" style={{ color: '#5A7295' }}>
                {datosFiltrados.length} de {datos.length} cohorte(s)
              </span>
            </div>

            <div
              className="grid grid-cols-[1fr_1.3fr_0.8fr_0.8fr_0.9fr] gap-3 px-5 py-3 flex-shrink-0"
              style={{
                background: '#F8FAFD',
                borderBottom: '1px solid rgba(27,58,107,0.08)',
              }}
            >
              {['Cohorte', 'Carrera', 'Periodo', 'Estado', 'Malla'].map((titulo) => (
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
              ) : datosFiltrados.length === 0 ? (
                <div
                  className="h-full flex items-center justify-center text-xs"
                  style={{ color: '#5A7295' }}
                >
                  Ninguna cohorte para esa carrera.
                </div>
              ) : (
                datosFiltrados.map((item) => (
                  <div
                    key={item.id_cohorte}
                    className="grid grid-cols-[1fr_1.3fr_0.8fr_0.8fr_0.9fr] gap-3 items-center px-5 py-3"
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

                    {item.total_periodos > 0 ? (
                      <span
                        className="flex items-center gap-1.5 text-xs font-semibold"
                        style={{ color: '#16A34A' }}
                      >
                        <CheckCircle2 size={13} />
                        Subida
                      </span>
                    ) : subiendoMallaId === item.id_cohorte ? (
                      <span
                        className="flex items-center gap-1.5 text-xs font-semibold"
                        style={{ color: '#5A7295' }}
                      >
                        <Loader2 size={13} className="animate-spin" />
                        Subiendo...
                      </span>
                    ) : (
                      <button
                        type="button"
                        onClick={() => abrirSelectorMalla(item.id_cohorte)}
                        className="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-semibold border transition-all hover:bg-blue-50 w-fit"
                        style={{
                          borderColor: 'rgba(27,58,107,0.2)',
                          color: '#1B3A6B',
                        }}
                      >
                        <Upload size={12} />
                        Subir malla
                      </button>
                    )}
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      </div>

      <input
        ref={mallaFileRef}
        type="file"
        accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        className="hidden"
        onChange={(event) => void onMallaFileSeleccionado(event.target.files?.[0] ?? null)}
      />

      <DeleteCohortModal
        open={modalEliminarAbierto}
        onClose={cerrarModalEliminar}
        carreras={carreras}
        cohortes={datos}
        carreraFiltroId={carreraFiltroEliminar}
        onCarreraFiltroIdChange={handleFiltroCarreraEliminarChange}
        cohorteId={idCohorteAEliminar}
        onCohorteIdChange={setIdCohorteAEliminar}
        confirmacion={confirmacionEliminar}
        onConfirmacionChange={setConfirmacionEliminar}
        eliminando={eliminandoCohorte}
        onSubmit={eliminarCohorte}
      />
    </div>
  );
}
