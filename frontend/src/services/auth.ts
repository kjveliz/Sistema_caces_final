export type RolUsuario =
  | "administrador"
  | "coordinador"
  | "evaluador";

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

export async function iniciarSesion(
  correo: string,
  contrasena: string,
): Promise<LoginResponse> {
  const respuesta = await fetch(
    "http://localhost/sistemacaces/api/auth/login.php",
    {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        correo,
        contrasena,
      }),
    },
  );

  const datos = (await respuesta.json()) as LoginResponse;

  if (!respuesta.ok) {
    throw new Error(
      datos.mensaje || "No se pudo iniciar sesión.",
    );
  }

  if (!datos.ok || !datos.usuario) {
    throw new Error(
      datos.mensaje ||
        "El servidor no devolvió los datos del usuario.",
    );
  }

  return datos;
}
