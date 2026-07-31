import { createContext, useContext, useEffect, useMemo, useState } from 'react';
import type { ReactNode } from 'react';

import { cerrarSesion, verificarSesion, type UsuarioSesion } from '../shared/services/auth';

interface AuthContextValue {
  usuario: UsuarioSesion | null;
  /** Deriva de usuario.rol: administrador y coordinador pueden cargar evidencias. */
  puedeCargar: boolean;
  /**
   * true mientras se resuelve, al montar la app, si la cookie de sesión que
   * ya pueda tener el navegador todavía corresponde a un usuario logueado
   * (ver verificarSesion()). Mientras es true no se sabe todavía si hay
   * sesión o no, así que no hay que redirigir a /login todavía.
   */
  cargando: boolean;
  login: (usuario: UsuarioSesion) => void;
  logout: () => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [usuario, setUsuario] = useState<UsuarioSesion | null>(null);
  const [cargando, setCargando] = useState(true);

  // Al montar la app (primera carga o refresh de página), recupera la
  // sesión desde el backend en vez de arrancar siempre en null: el usuario
  // sigue en memoria de React, pero la fuente de verdad de si la sesión
  // sigue activa es la cookie PHPSESSID que ya maneja el backend (ver
  // verificarSesion() en shared/services/auth.ts, GET /auth/me), no
  // localStorage/sessionStorage.
  useEffect(() => {
    let cancelado = false;

    async function recuperarSesion() {
      const usuarioRecuperado = await verificarSesion();

      if (!cancelado) {
        setUsuario(usuarioRecuperado);
        setCargando(false);
      }
    }

    void recuperarSesion();

    return () => {
      cancelado = true;
    };
  }, []);

  const value = useMemo<AuthContextValue>(() => {
    const puedeCargar = usuario?.rol === 'administrador' || usuario?.rol === 'coordinador';

    return {
      usuario,
      puedeCargar,
      cargando,
      login: (usuarioAutenticado: UsuarioSesion) => setUsuario(usuarioAutenticado),
      logout: () => {
        setUsuario(null);
        void cerrarSesion();
      },
    };
  }, [usuario, cargando]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

/**
 * Hook de acceso al usuario logueado. Reemplaza el prop-drilling de
 * `usuario`/`onLogout`/`puedeCargar` que antes bajaba desde App.tsx a cada
 * pantalla (Fase 4 del Plan de Mejora, activación de AuthContext).
 */
export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth debe usarse dentro de un <AuthProvider>.');
  }

  return context;
}
