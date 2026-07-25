import { createContext, useContext, useMemo, useState } from 'react';
import type { ReactNode } from 'react';

import type { UsuarioSesion } from '../services/auth';

interface AuthContextValue {
  usuario: UsuarioSesion | null;
  /** Deriva de usuario.rol: administrador y coordinador pueden cargar evidencias. */
  puedeCargar: boolean;
  login: (usuario: UsuarioSesion) => void;
  logout: () => void;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [usuario, setUsuario] = useState<UsuarioSesion | null>(null);

  const value = useMemo<AuthContextValue>(() => {
    const puedeCargar = usuario?.rol === 'administrador' || usuario?.rol === 'coordinador';

    return {
      usuario,
      puedeCargar,
      login: (usuarioAutenticado: UsuarioSesion) => setUsuario(usuarioAutenticado),
      logout: () => setUsuario(null),
    };
  }, [usuario]);

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
