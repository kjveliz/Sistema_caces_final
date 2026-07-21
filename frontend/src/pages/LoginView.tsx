import {
  iniciarSesion,
  type UsuarioSesion,
} from "../services/auth";

import { useState } from "react";
import {
  Eye,
  EyeOff,
  User,
} from "lucide-react";

interface LoginViewProps {
  onLogin: (usuario: UsuarioSesion) => void;
}

export default function LoginView({
  onLogin,
}: LoginViewProps) {
  const [email, setEmail] = useState("");
  const [pass, setPass] = useState("");
  const [show, setShow] = useState(false);
  const [err, setErr] = useState("");
  const [loading, setLoading] = useState(false);

  async function submit(
  e: React.FormEvent<HTMLFormElement>,
) {
  e.preventDefault();
  setErr("");
  setLoading(true);

  try {
    const respuesta = await iniciarSesion(
      email.trim(),
      pass,
    );

    if (!respuesta.ok) {
      setErr(
        respuesta.mensaje ||
          "No se pudo iniciar sesión.",
      );
      return;
    }

    onLogin(respuesta.usuario);
  } catch (error) {
    setErr(
      error instanceof Error
        ? error.message
        : "No se pudo conectar con el servidor.",
    );
  } finally {
    setLoading(false);
  }
}

  return (
    <div
      className="min-h-screen flex"
      style={{
        fontFamily: "'Plus Jakarta Sans',sans-serif",
      }}
    >
      <div
        className="hidden lg:flex flex-col justify-between w-5/12 p-12 text-white"
        style={{
          background:
            "linear-gradient(150deg,#0F2556 0%,#1B3A6B 55%,#1E4A8A 100%)",
        }}
      >
       <div>
  <div className="flex justify-center mb-10">
    <img
      src="/LOGO-UCSG-TEC-FULL-COLOR.png"
      alt="UCSG TEC"
      className="w-40 h-auto object-contain"
    />
  </div> 

          <h1
            className="text-4xl font-bold leading-tight mb-4"
            style={{
              fontFamily: "'Libre Baskerville',serif",
            }}
          >
            Sistema de
            <br />
            Evaluación
            <br />
            Institucional
          </h1>

          <p className="text-blue-200 text-sm leading-relaxed max-w-xs">
            Modelo de evaluación para Unidades Académicas de
            Formación Técnica y Tecnológica - CACES 
          </p>
        </div>

        <div className="space-y-3">
          {[
            "Módulo de Docencia",
            "Módulo de Investigación",
            "Módulo de Vinculación",
            "Módulo de Gestión",
          ].map((modulo, index) => (
            <div
              key={modulo}
              className="flex items-center gap-3"
              style={{
                opacity: index === 0 ? 1 : 0.4,
              }}
            >
              <div
                className="w-2 h-2 rounded-full"
                style={{
                  background:
                    index === 0
                      ? "#60A5FA"
                      : "rgba(255,255,255,0.35)",
                }}
              />

              <span className="text-sm">
                {modulo}
              </span>

              {index === 0 && (
                <span
                  className="ml-auto text-xs px-2 py-0.5 rounded-full"
                  style={{
                    background: "rgba(96,165,250,0.2)",
                    color: "#93C5FD",
                  }}
                >
                  Activo
                </span>
              )}
            </div>
          ))}
        </div>
      </div>

      <div
        className="flex-1 flex items-center justify-center p-8"
        style={{
          background: "#EEF2F7",
        }}
      >
        <div className="w-full max-w-md">
          <div
            className="bg-white rounded-2xl shadow-lg p-10 border"
            style={{
              borderColor: "rgba(27,58,107,0.08)",
            }}
          >
            <div className="mb-8">
              <div className="flex items-center gap-2 mb-1">
                <User
                  size={14}
                  className="text-blue-600"
                />

                <span
                  className="text-xs font-bold uppercase tracking-widest"
                  style={{
                    color: "#2563EB",
                  }}
                >
                  Acceso al sistema
                </span>
              </div>

              <h2
                className="text-2xl font-bold"
                style={{
                  fontFamily: "'Libre Baskerville',serif",
                  color: "#0F1E3C",
                }}
              >
                Iniciar sesión
              </h2>

              <p
                className="text-sm mt-1"
                style={{
                  color: "#5A7295",
                }}
              >
                Ingrese sus credenciales institucionales
              </p>
            </div>

            <form
              onSubmit={submit}
              className="space-y-5"
            >
              <div>
                <label
                  className="block text-sm font-medium mb-1.5"
                  style={{
                    color: "#0F1E3C",
                  }}
                >
                  Correo institucional
                </label>

                <input
                  type="email"
                  value={email}
                  onChange={(e) => {
                    setEmail(e.target.value);
                    setErr("");
                  }}
                  placeholder="usuario@uaftt.edu.ec"
                  className="w-full px-4 py-3 rounded-xl text-sm border outline-none"
                  style={{
                    background: "#F4F7FB",
                    borderColor: "rgba(27,58,107,0.15)",
                    color: "#0F1E3C",
                  }}
                  required
                />
              </div>

              <div>
                <label
                  className="block text-sm font-medium mb-1.5"
                  style={{
                    color: "#0F1E3C",
                  }}
                >
                  Contraseña
                </label>

                <div className="relative">
                  <input
                    type={show ? "text" : "password"}
                    value={pass}
                    onChange={(e) => {
                      setPass(e.target.value);
                      setErr("");
                    }}
                    placeholder="••••••••"
                    className="w-full px-4 py-3 rounded-xl text-sm border outline-none pr-11"
                    style={{
                      background: "#F4F7FB",
                      borderColor: "rgba(27,58,107,0.15)",
                      color: "#0F1E3C",
                    }}
                    required
                  />

                  <button
                    type="button"
                    onClick={() => setShow(!show)}
                    className="absolute right-3 top-1/2 -translate-y-1/2"
                    style={{
                      color: "#5A7295",
                    }}
                  >
                    {show ? (
                      <EyeOff size={16} />
                    ) : (
                      <Eye size={16} />
                    )}
                  </button>
                </div>
              </div>

              {err && (
                <p
                  className="text-xs px-3 py-2 rounded-lg"
                  style={{
                    background: "#FEE2E2",
                    color: "#DC2626",
                  }}
                >
                  {err}
                </p>
              )}

              <button
                type="submit"
                disabled={loading}
                className="w-full py-3 rounded-xl font-semibold text-sm transition-all hover:opacity-90 active:scale-[0.98]"
                style={{
                  background: loading
                    ? "#94A3B8"
                    : "#1B3A6B",
                color: "#fff",
                cursor: loading
                    ? "not-allowed"
                    : "pointer",
                }}
              >
                {loading
                  ? "Verificando..."
                  : "Ingresar al sistema"}
            </button>
            </form>

            <p
              className="text-center text-xs mt-6"
              style={{
                color: "#5A7295",
              }}
            >
              Acceso exclusivo para personal autorizado de la Universidad Católica de Santiago de Guayaquil.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}