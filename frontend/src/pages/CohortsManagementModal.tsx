import { useEffect, useMemo, useState } from "react";
import {
  CalendarDays,
  Loader2,
  Plus,
  RefreshCw,
  X,
} from "lucide-react";
import { toast } from "sonner";

import {
  cambiarEstadoEvaluacion,
  crearCohorteEvaluacion,
  listarCohortesEvaluaciones,
  type CohorteEvaluacion,
} from "../services/cohortes";

interface CohortsManagementModalProps {
  open: boolean;
  onClose: () => void;
  carreras: {
    id: number;
    nombre: string;
  }[];
}

type EstadoEvaluacion =
  | "Activa"
  | "Pendiente"
  | "Cerrada";

export default function CohortsManagementModal({
  open,
  onClose,
  carreras,
}: CohortsManagementModalProps) {
  const [datos, setDatos] = useState<CohorteEvaluacion[]>([]);
  const [cargando, setCargando] = useState(false);
  const [guardando, setGuardando] = useState(false);
  const [cambiandoId, setCambiandoId] = useState<number | null>(null);

  const [idCarrera, setIdCarrera] = useState("");
  const [nombreCohorte, setNombreCohorte] = useState("");
  const [fechaInicio, setFechaInicio] = useState("");
  const [fechaFin, setFechaFin] = useState("");
  const [estado, setEstado] =
    useState<EstadoEvaluacion>("Activa");

  useEffect(() => {
    if (open) void cargar();
  }, [open]);

  const totalActivas = useMemo(
    () => datos.filter((item) => item.estado === "Activa").length,
    [datos],
  );

  async function cargar() {
    try {
      setCargando(true);
      setDatos(await listarCohortesEvaluaciones());
    } catch (error) {
      toast.error("No se pudieron cargar las cohortes", {
        description:
          error instanceof Error
            ? error.message
            : "Ocurrió un error inesperado.",
      });
    } finally {
      setCargando(false);
    }
  }

  function limpiar() {
    setIdCarrera("");
    setNombreCohorte("");
    setFechaInicio("");
    setFechaFin("");
    setEstado("Activa");
  }

  async function guardar(
    event: React.FormEvent<HTMLFormElement>,
  ) {
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

      toast.success("Cohorte y evaluación creadas");
      limpiar();
      await cargar();
    } catch (error) {
      toast.error("No se pudo crear la cohorte", {
        description:
          error instanceof Error
            ? error.message
            : "Ocurrió un error inesperado.",
      });
    } finally {
      setGuardando(false);
    }
  }

  async function actualizarEstado(
    item: CohorteEvaluacion,
    nuevoEstado: EstadoEvaluacion,
  ) {
    if (!item.id_evaluacion) return;

    try {
      setCambiandoId(item.id_evaluacion);
      await cambiarEstadoEvaluacion(
        item.id_evaluacion,
        nuevoEstado,
      );

      setDatos((actuales) =>
        actuales.map((registro) =>
          registro.id_evaluacion === item.id_evaluacion
            ? { ...registro, estado: nuevoEstado }
            : registro,
        ),
      );

      toast.success("Estado actualizado");
    } catch (error) {
      toast.error("No se pudo cambiar el estado", {
        description:
          error instanceof Error
            ? error.message
            : "Ocurrió un error inesperado.",
      });
    } finally {
      setCambiandoId(null);
    }
  }

  if (!open) return null;

  return (
    <div
      className="fixed inset-0 z-[70] flex items-center justify-center p-4"
      style={{ background: "rgba(15,30,60,0.48)" }}
    >
      <div
        className="bg-white rounded-2xl shadow-2xl w-full max-w-6xl overflow-hidden flex flex-col"
        style={{
          maxHeight: "92vh",
          border: "1px solid rgba(27,58,107,0.12)",
        }}
      >
        <div
          className="px-6 py-4 flex items-center justify-between"
          style={{
            borderBottom:
              "1px solid rgba(27,58,107,0.1)",
            background: "#F8FAFD",
          }}
        >
          <div className="flex items-center gap-3">
            <div
              className="w-9 h-9 rounded-xl flex items-center justify-center"
              style={{ background: "#1B3A6B", color: "#fff" }}
            >
              <CalendarDays size={17} />
            </div>

            <div>
              <h2
                className="text-base font-bold"
                style={{
                  color: "#0F1E3C",
                  fontFamily: "'Libre Baskerville',serif",
                }}
              >
                Gestión de cohortes
              </h2>
              <p className="text-xs mt-0.5" style={{ color: "#5A7295" }}>
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
                className={cargando ? "animate-spin" : ""}
                style={{ color: "#1B3A6B" }}
              />
            </button>
            <button
              type="button"
              onClick={onClose}
              className="p-2 rounded-lg hover:bg-gray-100"
            >
              <X size={16} style={{ color: "#5A7295" }} />
            </button>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-[360px_1fr] flex-1 min-h-0 overflow-hidden">
          <form
            onSubmit={guardar}
            className="p-5 space-y-4 overflow-auto"
            style={{
              borderRight:
                "1px solid rgba(27,58,107,0.08)",
              background: "#FBFCFE",
            }}
          >
            <h3 className="text-sm font-bold" style={{ color: "#0F1E3C" }}>
              Nueva cohorte
            </h3>

            <div>
              <label className="block text-xs font-bold uppercase tracking-widest mb-1.5" style={{ color: "#5A7295" }}>
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
              <label className="block text-xs font-bold uppercase tracking-widest mb-1.5" style={{ color: "#5A7295" }}>
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
                <label className="block text-xs font-bold uppercase tracking-widest mb-1.5" style={{ color: "#5A7295" }}>
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
                <label className="block text-xs font-bold uppercase tracking-widest mb-1.5" style={{ color: "#5A7295" }}>
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
              <label className="block text-xs font-bold uppercase tracking-widest mb-1.5" style={{ color: "#5A7295" }}>
                Estado
              </label>
              <select
                value={estado}
                onChange={(e) =>
                  setEstado(e.target.value as EstadoEvaluacion)
                }
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
                background: guardando ? "#94A3B8" : "#1B3A6B",
                color: "#fff",
              }}
            >
              {guardando ? (
                <Loader2 size={14} className="animate-spin" />
              ) : (
                <Plus size={14} />
              )}
              {guardando ? "Guardando..." : "Crear cohorte"}
            </button>
          </form>

          <div className="flex flex-col min-h-0">
            <div
              className="grid grid-cols-[1fr_1.4fr_0.9fr_0.9fr] gap-3 px-5 py-3"
              style={{
                background: "#F8FAFD",
                borderBottom:
                  "1px solid rgba(27,58,107,0.08)",
              }}
            >
              {["Cohorte", "Carrera", "Periodo", "Estado"].map((titulo) => (
                <span
                  key={titulo}
                  className="text-xs font-bold uppercase tracking-widest"
                  style={{ color: "#5A7295" }}
                >
                  {titulo}
                </span>
              ))}
            </div>

            <div className="flex-1 overflow-auto">
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
                      borderBottom:
                        "1px solid rgba(27,58,107,0.06)",
                    }}
                  >
                    <span className="text-sm font-bold" style={{ color: "#0F1E3C" }}>
                      {item.nombre_cohorte}
                    </span>
                    <span className="text-xs" style={{ color: "#5A7295" }}>
                      {item.carrera}
                    </span>
                    <span className="text-xs" style={{ color: "#5A7295" }}>
                      {item.fecha_inicio ?? "—"}<br />
                      {item.fecha_fin ?? "—"}
                    </span>
                    <select
                      value={(item.estado ?? "Pendiente") as EstadoEvaluacion}
                      disabled={
                        !item.id_evaluacion ||
                        cambiandoId === item.id_evaluacion
                      }
                      onChange={(e) =>
                        void actualizarEstado(
                          item,
                          e.target.value as EstadoEvaluacion,
                        )
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
