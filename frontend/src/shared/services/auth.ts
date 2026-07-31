export type RolUsuario = 'administrador' | 'coordinador' | 'evaluador';

export interface UsuarioSesion {
  id_usuario: number;
  nombres: string;
  apellidos: string;
  correo: string;
  rol: RolUsuario;
}

export interface LoginResponse {
  ok: boolean;
  mensaje: string;
  usuario?: UsuarioSesion;
}

interface MeResponse {
  ok: boolean;
  mensaje?: string;
  usuario?: UsuarioSesion;
}

/**
 * Le pregunta al backend, a partir de la cookie de sesión (`PHPSESSID`) que el
 * navegador ya manda solo, si hay un usuario logueado. Se usa al montar la
 * app para recuperar la sesión tras un refresh, sin depender de guardar nada
 * en localStorage/sessionStorage: la fuente de verdad sigue siendo la sesión
 * real de PHP.
 *
 * Devuelve el usuario si la sesión sigue activa, o `null` si no hay sesión
 * (401) o si no se pudo contactar al servidor — en ambos casos el efecto
 * práctico es el mismo: mandar al login.
 */
export async function verificarSesion(): Promise<UsuarioSesion | null> {
  try {
    const respuesta = await fetch('http://localhost/sistemacaces/api/auth/Me.php', {
      method: 'GET',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    });

    if (!respuesta.ok) {
      return null;
    }

    const datos = (await respuesta.json()) as MeResponse;

    return datos.ok && datos.usuario ? datos.usuario : null;
  } catch {
    return null;
  }
}

/**
 * Destruye la sesión real del lado del servidor (no solo el estado en
 * memoria del frontend). Si el pedido falla por lo que sea (servidor caído,
 * red), no se propaga el error: cerrar sesión en el navegador debe poder
 * seguir adelante igual, ya que de todos modos el usuario deja de poder usar
 * la app con esa sesión desde el frontend.
 */
export async function cerrarSesion(): Promise<void> {
  try {
    // Ruta de Slim (Parte 2 del plan de migración de PHP suelto -- ver
    // plan_migracion_slim_legacy_v3.txt §3), reemplaza a
    // api/auth/Logout.php.
    await fetch('http://localhost/sistemacaces/public/auth/logout', {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    });
  } catch {
    // Ignorado a propósito: ver comentario de la función.
  }
}

export async function iniciarSesion(
  correo: string,
  contrasena: string,
): Promise<LoginResponse & { usuario: UsuarioSesion }> {
  // Ruta de Slim (Parte 1 del plan de migración de PHP suelto -- ver
  // plan_migracion_slim_legacy_v3.txt §3), reemplaza a
  // api/auth/login.php. cerrarSesion() de arriba ya apunta también a Slim
  // (Parte 2); verificarSesion() sigue en el legacy hasta que Me.php se
  // migre en la Parte 3.
  const respuesta = await fetch('http://localhost/sistemacaces/public/auth/login', {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({
      correo,
      contrasena,
    }),
  });

  const datos = (await respuesta.json()) as LoginResponse;

  if (!respuesta.ok) {
    throw new Error(datos.mensaje || 'No se pudo iniciar sesión.');
  }

  if (!datos.ok || !datos.usuario) {
    throw new Error(datos.mensaje || 'El servidor no devolvió los datos del usuario.');
  }

  return datos as LoginResponse & { usuario: UsuarioSesion };
}
