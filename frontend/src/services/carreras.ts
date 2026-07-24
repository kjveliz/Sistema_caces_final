export interface CarreraBD {
  id_carrera: number;
  codigo: string;
  nombre: string;
  area_conocimiento: string;
  modalidad: string;
  nombre_malla?: string | null;
  id_drive?: string | null;
  url_malla?: string | null;
}

interface RespuestaCarreras {
  ok: boolean;
  datos?: CarreraBD[];
  mensaje?: string;
  detalle?: string;
}

const API_BASE = 'http://localhost/sistemacaces/api/carreras';

async function leerRespuestaJson<T>(respuesta: Response, mensajeInvalido: string): Promise<T> {
  try {
    return (await respuesta.json()) as T;
  } catch {
    throw new Error(mensajeInvalido);
  }
}

export async function obtenerCarreras(): Promise<CarreraBD[]> {
  const respuesta = await fetch(`${API_BASE}/listar.php`, {
    method: 'GET',
    credentials: 'include',
    cache: 'no-store',
    headers: { Accept: 'application/json' },
  });

  const resultado = await leerRespuestaJson<RespuestaCarreras>(
    respuesta,
    'El servidor no devolvió una respuesta válida al consultar las carreras.',
  );

  if (!respuesta.ok || !resultado.ok) {
    throw new Error(
      resultado.mensaje ?? resultado.detalle ?? 'No se pudieron obtener las carreras.',
    );
  }

  return resultado.datos ?? [];
}

export interface NuevaCarrera {
  codigo: string;
  nombre: string;
  area_conocimiento: string;
  modalidad: string;
}

interface RespuestaCrearCarrera {
  ok: boolean;
  mensaje?: string;
  datos?: CarreraBD;
  detalle?: string;
}

export async function crearCarrera(carrera: NuevaCarrera): Promise<CarreraBD> {
  const respuesta = await fetch(`${API_BASE}/crear.php`, {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify(carrera),
  });

  const resultado = await leerRespuestaJson<RespuestaCrearCarrera>(
    respuesta,
    'El servidor no devolvió una respuesta válida al registrar la carrera.',
  );

  if (!respuesta.ok || !resultado.ok || !resultado.datos) {
    throw new Error(resultado.mensaje ?? resultado.detalle ?? 'No se pudo registrar la carrera.');
  }

  return resultado.datos;
}

interface RespuestaEliminarCarrera {
  ok: boolean;
  mensaje?: string;
  datos?: {
    id_carrera: number;
    nombre: string;
  };
  detalle?: string;
}

export async function eliminarCarrera(idCarrera: number): Promise<void> {
  if (!Number.isInteger(idCarrera) || idCarrera <= 0) {
    throw new Error('El identificador de la carrera no es válido.');
  }

  const respuesta = await fetch(`${API_BASE}/eliminar.php`, {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({
      id_carrera: idCarrera,
    }),
  });

  const resultado = await leerRespuestaJson<RespuestaEliminarCarrera>(
    respuesta,
    'El servidor no devolvió una respuesta válida al eliminar la carrera.',
  );

  if (!respuesta.ok || !resultado.ok) {
    throw new Error(resultado.mensaje ?? resultado.detalle ?? 'No se pudo eliminar la carrera.');
  }
}

export interface CarreraActualizada {
  id_carrera: number;
  codigo: string;
  nombre: string;
  area_conocimiento: string;
  modalidad: string;
}

interface RespuestaActualizarCarrera {
  ok: boolean;
  mensaje?: string;
  datos?: CarreraBD;
  detalle?: string;
}

export async function actualizarCarrera(carrera: CarreraActualizada): Promise<CarreraBD> {
  const id = Number(carrera.id_carrera);

  if (!Number.isFinite(id) || id <= 0) {
    throw new Error('El identificador de la carrera no es válido.');
  }

  const respuesta = await fetch(`${API_BASE}/actualizar.php`, {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({
      ...carrera,
      id_carrera: id,
    }),
  });

  const resultado = await leerRespuestaJson<RespuestaActualizarCarrera>(
    respuesta,
    'El servidor no devolvió una respuesta válida al actualizar la carrera.',
  );

  if (!respuesta.ok || !resultado.ok || !resultado.datos) {
    throw new Error(resultado.mensaje ?? resultado.detalle ?? 'No se pudo actualizar la carrera.');
  }

  return resultado.datos;
}

interface SubirMallaDriveResponse {
  ok: boolean;
  mensaje?: string;
  datos?: {
    id_archivo: string;
    nombre_archivo: string;
    url_archivo: string;
    url_descarga?: string | null;
    id_carpeta: string;
  };
  detalle?: string;
}

export interface MallaCurricularRegistrada {
  id_carrera: number;
  nombre_archivo: string;
  id_drive: string;
  url_drive: string;
}

interface GuardarMallaResponse {
  ok: boolean;
  mensaje?: string;
  datos?: MallaCurricularRegistrada;
  detalle?: string;
}

interface SubirMallaParams {
  archivo: File;
  carrera: CarreraBD;
}

export async function subirMallaCurricular({
  archivo,
  carrera,
}: SubirMallaParams): Promise<MallaCurricularRegistrada> {
  if (archivo.type !== 'application/pdf' && !archivo.name.toLowerCase().endsWith('.pdf')) {
    throw new Error('La malla curricular debe ser un archivo PDF.');
  }

  if (archivo.size > 25 * 1024 * 1024) {
    throw new Error('El PDF no debe superar los 25 MB.');
  }

  const nombreArchivo = `Malla_Curricular_${carrera.codigo}.pdf`;

  const formulario = new FormData();
  formulario.append('archivo', archivo);
  formulario.append('codigo_carrera', carrera.codigo);
  formulario.append('nombre_carrera', carrera.nombre);
  formulario.append('cohorte', 'GENERAL');
  formulario.append('indicador', '1');
  formulario.append('nombre_archivo', nombreArchivo);
  formulario.append('tipo_esperado', 'pdf');

  const respuestaDrive = await fetch(
    'http://localhost/sistemacaces/api/google_drive/subir_archivo.php',
    {
      method: 'POST',
      credentials: 'include',
      body: formulario,
    },
  );

  const drive = await leerRespuestaJson<SubirMallaDriveResponse>(
    respuestaDrive,
    'Google Drive no devolvió una respuesta válida.',
  );

  if (!respuestaDrive.ok || !drive.ok || !drive.datos?.id_archivo || !drive.datos.url_archivo) {
    throw new Error(drive.mensaje ?? drive.detalle ?? 'No se pudo subir la malla a Google Drive.');
  }

  const respuestaGuardar = await fetch(`${API_BASE}/guardar_malla.php`, {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({
      id_carrera: carrera.id_carrera,
      nombre_archivo: drive.datos.nombre_archivo || nombreArchivo,
      id_drive: drive.datos.id_archivo,
      url_drive: drive.datos.url_archivo,
    }),
  });

  const guardado = await leerRespuestaJson<GuardarMallaResponse>(
    respuestaGuardar,
    'El servidor no devolvió una respuesta válida al registrar la malla.',
  );

  if (!respuestaGuardar.ok || !guardado.ok || !guardado.datos) {
    throw new Error(
      guardado.mensaje ??
        guardado.detalle ??
        'La malla se subió a Drive, pero no pudo registrarse en MySQL.',
    );
  }

  return guardado.datos;
}
