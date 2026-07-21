export interface UsuarioSistema {
  id_usuario: number;
  nombres: string;
  apellidos: string;
  correo: string;
  rol:
    | "administrador"
    | "coordinador"
    | "evaluador";
  activo: number;
}

interface RespuestaUsuarios {
  ok: boolean;
  mensaje?: string;
  datos?: UsuarioSistema[];
}

interface CrearUsuarioParams {
  nombres: string;
  apellidos: string;
  correo: string;
  contrasena: string;
  rol:
    | "administrador"
    | "coordinador"
    | "evaluador";
  activo: number;
}

interface CrearUsuarioResponse {
  ok: boolean;
  mensaje: string;
  datos?: UsuarioSistema;
}

export async function listarUsuarios(): Promise<
  UsuarioSistema[]
> {
  const respuesta = await fetch(
    "http://localhost/sistemacaces/api/administracion/usuarios/listar.php",
    {
      method: "GET",
      credentials: "include",
      headers: {
        Accept: "application/json",
      },
    },
  );

  const datos =
    (await respuesta.json()) as RespuestaUsuarios;

  if (
    !respuesta.ok ||
    !datos.ok ||
    !datos.datos
  ) {
    throw new Error(
      datos.mensaje ||
        "No se pudieron consultar los usuarios.",
    );
  }

  return datos.datos;
}

export async function crearUsuario(
  parametros: CrearUsuarioParams,
): Promise<UsuarioSistema> {
  const respuesta = await fetch(
    "http://localhost/sistemacaces/api/administracion/usuarios/crear.php",
    {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify(parametros),
    },
  );

  const datos =
    (await respuesta.json()) as CrearUsuarioResponse;

  if (
    !respuesta.ok ||
    !datos.ok ||
    !datos.datos
  ) {
    throw new Error(
      datos.mensaje ||
        "No se pudo crear el usuario.",
    );
  }

  return datos.datos;
}

export async function cambiarEstadoUsuario(
  idUsuario: number,
  activo: number,
): Promise<void> {
  const respuesta = await fetch(
    "http://localhost/sistemacaces/api/administracion/usuarios/cambiar_estado.php",
    {
      method: "POST",
      credentials: "include",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        id_usuario: idUsuario,
        activo,
      }),
    },
  );

  const datos = await respuesta.json();

  if (!respuesta.ok || !datos.ok) {
    throw new Error(
      datos.mensaje ||
        "No se pudo actualizar el usuario.",
    );
  }
}