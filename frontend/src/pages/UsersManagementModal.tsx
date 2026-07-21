import { useEffect, useMemo, useState } from "react";
import {
  CheckCircle2,
  Eye,
  EyeOff,
  Loader2,
  RefreshCw,
  ShieldCheck,
  UserPlus,
  Users,
  X,
} from "lucide-react";
import { toast } from "sonner";

import {
  cambiarEstadoUsuario,
  crearUsuario,
  listarUsuarios,
  type UsuarioSistema,
} from "../services/usuarios";

interface UsersManagementModalProps {
  open: boolean;
  onClose: () => void;
}

type RolUsuario =
  | "administrador"
  | "coordinador"
  | "evaluador";

export default function UsersManagementModal({
  open,
  onClose,
}: UsersManagementModalProps) {
  const [usuarios, setUsuarios] = useState<UsuarioSistema[]>([]);
  const [cargando, setCargando] = useState(false);
  const [guardando, setGuardando] = useState(false);
  const [cambiandoId, setCambiandoId] = useState<number | null>(null);

  const [nombres, setNombres] = useState("");
  const [apellidos, setApellidos] = useState("");
  const [correo, setCorreo] = useState("");
  const [contrasena, setContrasena] = useState("");
  const [rol, setRol] = useState<RolUsuario>("evaluador");
  const [activo, setActivo] = useState(1);
  const [mostrarContrasena, setMostrarContrasena] = useState(false);

  useEffect(() => {
    if (open) void cargarUsuarios();
  }, [open]);

  const totalActivos = useMemo(
    () => usuarios.filter((u) => Number(u.activo) === 1).length,
    [usuarios],
  );

  async function cargarUsuarios() {
    try {
      setCargando(true);
      setUsuarios(await listarUsuarios());
    } catch (error) {
      toast.error("No se pudieron cargar los usuarios", {
        description:
          error instanceof Error ? error.message : "Ocurrió un error inesperado.",
      });
    } finally {
      setCargando(false);
    }
  }

  function limpiarFormulario() {
    setNombres("");
    setApellidos("");
    setCorreo("");
    setContrasena("");
    setRol("evaluador");
    setActivo(1);
    setMostrarContrasena(false);
  }

  async function guardarUsuario(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();

    try {
      setGuardando(true);

      await crearUsuario({
        nombres: nombres.trim(),
        apellidos: apellidos.trim(),
        correo: correo.trim().toLowerCase(),
        contrasena,
        rol,
        activo,
      });

      toast.success("Usuario creado correctamente");
      limpiarFormulario();
      await cargarUsuarios();
    } catch (error) {
      toast.error("No se pudo crear el usuario", {
        description:
          error instanceof Error ? error.message : "Ocurrió un error inesperado.",
      });
    } finally {
      setGuardando(false);
    }
  }

  async function cambiarEstado(usuario: UsuarioSistema) {
    const nuevoEstado = Number(usuario.activo) === 1 ? 0 : 1;

    try {
      setCambiandoId(usuario.id_usuario);
      await cambiarEstadoUsuario(usuario.id_usuario, nuevoEstado);

      setUsuarios((actuales) =>
        actuales.map((item) =>
          item.id_usuario === usuario.id_usuario
            ? { ...item, activo: nuevoEstado }
            : item,
        ),
      );

      toast.success(
        nuevoEstado === 1 ? "Usuario activado" : "Usuario desactivado",
      );
    } catch (error) {
      toast.error("No se pudo cambiar el estado", {
        description:
          error instanceof Error ? error.message : "Ocurrió un error inesperado.",
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
          className="px-6 py-4 flex items-center justify-between flex-shrink-0"
          style={{
            borderBottom: "1px solid rgba(27,58,107,0.1)",
            background: "#F8FAFD",
          }}
        >
          <div className="flex items-center gap-3">
            <div
              className="w-9 h-9 rounded-xl flex items-center justify-center"
              style={{ background: "#1B3A6B", color: "#fff" }}
            >
              <Users size={17} />
            </div>

            <div>
              <h2
                className="text-base font-bold"
                style={{
                  color: "#0F1E3C",
                  fontFamily: "'Libre Baskerville',serif",
                }}
              >
                Gestión de usuarios
              </h2>

              <p className="text-xs mt-0.5" style={{ color: "#5A7295" }}>
                {usuarios.length} registrados · {totalActivos} activos
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => void cargarUsuarios()}
              disabled={cargando}
              className="p-2 rounded-lg hover:bg-blue-50 transition-colors"
              title="Actualizar"
              style={{ color: "#1B3A6B" }}
            >
              <RefreshCw size={15} className={cargando ? "animate-spin" : ""} />
            </button>

            <button
              type="button"
              onClick={onClose}
              className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
              title="Cerrar"
            >
              <X size={16} style={{ color: "#5A7295" }} />
            </button>
          </div>
        </div>

        <div className="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-[360px_1fr] overflow-hidden">
          <form
            onSubmit={guardarUsuario}
            autoComplete="off"
            className="p-5 space-y-4 overflow-auto"
            style={{
              borderRight: "1px solid rgba(27,58,107,0.08)",
              background: "#FBFCFE",
            }}
          >
            <div className="flex items-center gap-2">
              <UserPlus size={15} style={{ color: "#2563EB" }} />
              <h3 className="text-sm font-bold" style={{ color: "#0F1E3C" }}>
                Nuevo usuario
              </h3>
            </div>

            {[
              ["Nombres", nombres, setNombres, "text"],
              ["Apellidos", apellidos, setApellidos, "text"],
              ["Correo", correo, setCorreo, "email"],
            ].map(([label, value, setter, type]) => (
              <div key={String(label)}>
                <label
                  className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                  style={{ color: "#5A7295" }}
                >
                  {String(label)}
                </label>
                <input
                  type={String(type)}
                  value={String(value)}
                  onChange={(e) =>
                    (setter as React.Dispatch<React.SetStateAction<string>>)(
                      e.target.value,
                    )
                  }
                  required
                  className="w-full px-3 py-2.5 rounded-xl text-sm border outline-none"
                  style={{
                    background: "#fff",
                    borderColor: "rgba(27,58,107,0.18)",
                    color: "#0F1E3C",
                  }}
                />
              </div>
            ))}

            <div>
              <label
                className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                style={{ color: "#5A7295" }}
              >
                Contraseña
              </label>

              <div className="relative">
                <input
                  type={mostrarContrasena ? "text" : "password"}
                  autoComplete="new-password"
                  value={contrasena}
                  onChange={(e) => setContrasena(e.target.value)}
                  required
                  minLength={8}
                  className="w-full px-3 py-2.5 pr-10 rounded-xl text-sm border outline-none"
                  style={{
                    background: "#fff",
                    borderColor: "rgba(27,58,107,0.18)",
                    color: "#0F1E3C",
                  }}
                />

                <button
                  type="button"
                  onClick={() => setMostrarContrasena(!mostrarContrasena)}
                  className="absolute right-3 top-1/2 -translate-y-1/2"
                  style={{ color: "#5A7295" }}
                >
                  {mostrarContrasena ? <EyeOff size={15} /> : <Eye size={15} />}
                </button>
              </div>
            </div>

            <div>
              <label
                className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                style={{ color: "#5A7295" }}
              >
                Rol
              </label>

              <select
                value={rol}
                onChange={(e) => setRol(e.target.value as RolUsuario)}
                className="w-full px-3 py-2.5 rounded-xl text-sm border outline-none"
                style={{
                  background: "#fff",
                  borderColor: "rgba(27,58,107,0.18)",
                  color: "#0F1E3C",
                }}
              >
                <option value="administrador">Administrador</option>
                <option value="coordinador">Coordinador</option>
                <option value="evaluador">Evaluador</option>
              </select>
            </div>

            <label className="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                checked={activo === 1}
                onChange={(e) => setActivo(e.target.checked ? 1 : 0)}
              />
              <span className="text-sm font-medium" style={{ color: "#374151" }}>
                Cuenta activa
              </span>
            </label>

            <button
              type="submit"
              disabled={guardando}
              className="w-full py-2.5 rounded-xl text-sm font-bold flex items-center justify-center gap-2 transition-all hover:opacity-90"
              style={{
                background: guardando ? "#94A3B8" : "#1B3A6B",
                color: "#fff",
              }}
            >
              {guardando ? (
                <>
                  <Loader2 size={14} className="animate-spin" />
                  Guardando...
                </>
              ) : (
                <>
                  <UserPlus size={14} />
                  Crear usuario
                </>
              )}
            </button>
          </form>

          <div className="flex flex-col min-h-0 overflow-hidden">
            <div
              className="grid grid-cols-[1.2fr_1.5fr_0.8fr_0.7fr] gap-3 px-5 py-3 flex-shrink-0"
              style={{
                background: "#F8FAFD",
                borderBottom: "1px solid rgba(27,58,107,0.08)",
              }}
            >
              {["Usuario", "Correo", "Rol", "Estado"].map((titulo) => (
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
                  <Loader2
                    size={24}
                    className="animate-spin"
                    style={{ color: "#1B3A6B" }}
                  />
                </div>
              ) : usuarios.length === 0 ? (
                <div className="h-full flex items-center justify-center">
                  <p className="text-sm" style={{ color: "#94A3B8" }}>
                    No existen usuarios registrados.
                  </p>
                </div>
              ) : (
                usuarios.map((usuario) => {
                  const estaActivo = Number(usuario.activo) === 1;

                  return (
                    <div
                      key={usuario.id_usuario}
                      className="grid grid-cols-[1.2fr_1.5fr_0.8fr_0.7fr] gap-3 items-center px-5 py-3"
                      style={{
                        borderBottom: "1px solid rgba(27,58,107,0.06)",
                      }}
                    >
                      <p
                        className="text-sm font-semibold truncate"
                        style={{ color: "#0F1E3C" }}
                      >
                        {usuario.nombres} {usuario.apellidos}
                      </p>

                      <p className="text-xs truncate" style={{ color: "#5A7295" }}>
                        {usuario.correo}
                      </p>

                      <div className="flex items-center gap-1.5">
                        <ShieldCheck size={12} style={{ color: "#2563EB" }} />
                        <span
                          className="text-xs font-semibold capitalize"
                          style={{ color: "#374151" }}
                        >
                          {usuario.rol}
                        </span>
                      </div>

                      <button
                        type="button"
                        onClick={() => void cambiarEstado(usuario)}
                        disabled={cambiandoId === usuario.id_usuario}
                        className="justify-self-start flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition-all hover:opacity-80"
                        style={{
                          background: estaActivo ? "#DCFCE7" : "#FEE2E2",
                          color: estaActivo ? "#15803D" : "#B91C1C",
                        }}
                      >
                        {cambiandoId === usuario.id_usuario ? (
                          <Loader2 size={11} className="animate-spin" />
                        ) : estaActivo ? (
                          <CheckCircle2 size={11} />
                        ) : (
                          <X size={11} />
                        )}

                        {estaActivo ? "Activo" : "Inactivo"}
                      </button>
                    </div>
                  );
                })
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
