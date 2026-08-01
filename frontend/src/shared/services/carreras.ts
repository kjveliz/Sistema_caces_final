import { AREAS } from '../data/careers';
import type { Career } from '../../types/index';

export type ModoAlmacenamiento = 'drive' | 'local';

export interface CarreraBD {
  id_carrera: number;
  codigo: string;
  nombre: string;
  area_conocimiento: string;
  modalidad: string;
  nombre_malla?: string | null;
  id_drive?: string | null;
  url_malla?: string | null;
  modo_almacenamiento: ModoAlmacenamiento;
  ruta_almacenamiento_local?: string | null;
}

interface RespuestaCarreras {
  ok: boolean;
  datos?: CarreraBD[];
  mensaje?: string;
  detalle?: string;
}

const API_BASE = 'http://localhost/sistemacaces/public/carreras';

async function leerRespuestaJson<T>(respuesta: Response, mensajeInvalido: string): Promise<T> {
  try {
    return (await respuesta.json()) as T;
  } catch {
    throw new Error(mensajeInvalido);
  }
}

export async function obtenerCarreras(): Promise<CarreraBD[]> {
  const respuesta = await fetch(`${API_BASE}/listar`, {
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
  const respuesta = await fetch(`${API_BASE}/crear`, {
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

/**
 * Se lanza específicamente cuando /carreras/eliminar responde 409 porque la
 * carrera tiene evaluaciones académicas relacionadas (contarEvaluaciones() > 0
 * en el backend). Permite a la UI distinguir este caso puntual (para ofrecer
 * la eliminación forzada) de cualquier otro error genérico.
 */
export class CarreraConEvaluacionesError extends Error {}

export async function eliminarCarrera(idCarrera: number): Promise<void> {
  if (!Number.isInteger(idCarrera) || idCarrera <= 0) {
    throw new Error('El identificador de la carrera no es válido.');
  }

  const respuesta = await fetch(`${API_BASE}/eliminar`, {
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
    const mensaje = resultado.mensaje ?? resultado.detalle ?? 'No se pudo eliminar la carrera.';

    if (respuesta.status === 409) {
      throw new CarreraConEvaluacionesError(mensaje);
    }

    throw new Error(mensaje);
  }
}

export interface ResultadoEliminacionForzada {
  id_carrera: number;
  nombre: string;
  cohortes_borradas: number;
  evaluaciones_borradas: number;
  periodos_borrados: number;
  asignaturas_borradas: number;
  evidencias_borradas: number;
}

interface RespuestaEliminarCarreraForzada {
  ok: boolean;
  mensaje?: string;
  datos?: ResultadoEliminacionForzada;
  detalle?: string;
}

/**
 * Herramienta de desarrollo/pruebas: borra una carrera y TODA su cadena
 * relacionada (evaluaciones, cohortes, períodos, asignaturas, evidencia en
 * BD), sin el bloqueo de contarEvaluaciones() que aplica eliminarCarrera().
 * Solo borra filas de la base de datos -- los archivos ya subidos a Drive o
 * almacenamiento local quedan huérfanos, a propósito. Requiere confirmación
 * extra en la UI antes de llamar a esta función.
 */
export async function eliminarCarreraForzada(idCarrera: number): Promise<ResultadoEliminacionForzada> {
  if (!Number.isInteger(idCarrera) || idCarrera <= 0) {
    throw new Error('El identificador de la carrera no es válido.');
  }

  const respuesta = await fetch(`${API_BASE}/eliminar-forzada`, {
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

  const resultado = await leerRespuestaJson<RespuestaEliminarCarreraForzada>(
    respuesta,
    'El servidor no devolvió una respuesta válida al eliminar forzadamente la carrera.',
  );

  if (!respuesta.ok || !resultado.ok || !resultado.datos) {
    throw new Error(
      resultado.mensaje ?? resultado.detalle ?? 'No se pudo eliminar forzadamente la carrera.',
    );
  }

  return resultado.datos;
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

  const respuesta = await fetch(`${API_BASE}/actualizar`, {
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
  // Parte E del plan de malla curricular xlsx: mismo criterio que ya usaba
  // el validador de PDF (chequear mime type O extensión, no exigir ambos),
  // porque el navegador no siempre setea el mime correcto para xlsx --
  // mismo mime real que valida subir_archivo.php del lado del servidor.
  const mimeXlsx = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

  if (archivo.type !== mimeXlsx && !archivo.name.toLowerCase().endsWith('.xlsx')) {
    throw new Error('La malla curricular debe ser un archivo Excel (.xlsx).');
  }

  if (archivo.size > 25 * 1024 * 1024) {
    throw new Error('El archivo no debe superar los 25 MB.');
  }

  const nombreArchivo = `Malla_Curricular_${carrera.codigo}.xlsx`;

  const formulario = new FormData();
  formulario.append('archivo', archivo);
  // Requerido desde que subir_archivo.php resuelve el interruptor de
  // almacenamiento por carrera (ver MEMORIA v100/§76 y sesión de fix de
  // I1/I4/I5): sin esto, la subida de malla fallaba con "Faltan datos"
  // para CUALQUIER carrera, sin importar el modo -- regresión detectada y
  // corregida en la misma sesión en que se reportó.
  formulario.append('id_carrera', String(carrera.id_carrera));
  formulario.append('codigo_carrera', carrera.codigo);
  formulario.append('nombre_carrera', carrera.nombre);
  formulario.append('cohorte', 'GENERAL');
  formulario.append('indicador', '1');
  formulario.append('nombre_archivo', nombreArchivo);
  formulario.append('tipo_esperado', 'xlsx');

  // Parte 21 del plan de migración de PHP suelto a Slim
  // (plan_migracion_slim_legacy_v3.txt §3): reemplaza a
  // api/google_drive/subir_archivo.php, ya borrado del repo.
  const respuestaDrive = await fetch(
    'http://localhost/sistemacaces/public/google-drive/subir-archivo',
    {
      method: 'POST',
      credentials: 'include',
      body: formulario,
    },
  );

  const drive = await leerRespuestaJson<SubirMallaDriveResponse>(
    respuestaDrive,
    'El servidor no devolvió una respuesta válida al subir la malla curricular.',
  );

  if (!respuestaDrive.ok || !drive.ok || !drive.datos?.id_archivo || !drive.datos.url_archivo) {
    throw new Error(drive.mensaje ?? drive.detalle ?? 'No se pudo subir la malla curricular.');
  }

  const respuestaGuardar = await fetch('http://localhost/sistemacaces/public/malla-curricular/guardar', {
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
        'La malla se subió, pero no pudo registrarse en MySQL.',
    );
  }

  return guardado.datos;
}

export interface CambioAlmacenamientoCarrera {
  id_carrera: number;
  modo_almacenamiento: ModoAlmacenamiento;
  ruta_local?: string | null;
}

export interface AlmacenamientoMigrado {
  total_migrados: number;
  modo_anterior: ModoAlmacenamiento;
  modo_nuevo: ModoAlmacenamiento;
}

interface RespuestaAlmacenamientoCarrera {
  ok: boolean;
  mensaje?: string;
  datos?: AlmacenamientoMigrado;
  detalle?: string;
}

/**
 * PUT /carreras/{id}/almacenamiento — mueve el interruptor Drive/local de
 * una carrera y dispara la migración síncrona y todo-o-nada de
 * `EvidenciaMigradorService` (ver plan_interruptor_almacenamiento.txt §4.4
 * y CarrerasAlmacenamientoController). La llamada solo se resuelve cuando
 * el backend termina de migrar (o revertir), así que quien la use debe
 * mostrar un loading bloqueante mientras dura — no hay progreso parcial
 * que reportar.
 *
 * Puede tardar (llamadas reales a Drive por archivo), así que no se le
 * pone un AbortController con timeout corto: cortar la petición a mitad
 * de camino no cancela la migración del lado del servidor, solo nos deja
 * sin saber si terminó bien o no.
 */
export async function actualizarAlmacenamientoCarrera({
  id_carrera,
  modo_almacenamiento,
  ruta_local,
}: CambioAlmacenamientoCarrera): Promise<AlmacenamientoMigrado> {
  if (!Number.isInteger(id_carrera) || id_carrera <= 0) {
    throw new Error('El identificador de la carrera no es válido.');
  }

  const respuesta = await fetch(`${API_BASE}/${id_carrera}/almacenamiento`, {
    method: 'PUT',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({
      modo_almacenamiento,
      ruta_local: ruta_local?.trim() || null,
    }),
  });

  const resultado = await leerRespuestaJson<RespuestaAlmacenamientoCarrera>(
    respuesta,
    'El servidor no devolvió una respuesta válida al cambiar el almacenamiento.',
  );

  if (!respuesta.ok || !resultado.ok || !resultado.datos) {
    // A diferencia del resto de endpoints de este archivo, acá el backend
    // (CarrerasAlmacenamientoController::almacenamiento) manda siempre el
    // mismo `mensaje` genérico ("No se pudo cambiar el almacenamiento de
    // la carrera.") y deja la causa real en `detalle` (mensaje de la
    // excepción original: archivo que falló al migrar, ruta no
    // escribible, error de Drive/BD, etc.). Priorizar `detalle` acá es lo
    // único que le permite al usuario ver por qué falló en vez de un
    // texto siempre igual.
    throw new Error(
      resultado.detalle ?? resultado.mensaje ?? 'No se pudo cambiar el almacenamiento de la carrera.',
    );
  }

  return resultado.datos;
}

/**
 * Reconstruye un `Career` completo a partir de solo su código, consultando
 * la base real de carreras. Necesario para que las rutas de react-router
 * (`/carreras/:code/...`) funcionen con una entrada directa por URL o un
 * refresh de página, sin depender de que el usuario haya pasado antes por
 * el listado de CareersView (que ya tiene el objeto Career en memoria).
 *
 * Replica la misma regla de merge que `organizarCarrerasPorArea` en
 * CareersView.tsx: `criterionNum` se conserva del AREAS estático si la
 * carrera ya existía ahí (por defecto 4), y `clickable` siempre es `true`
 * para cualquier carrera activa que venga de MySQL.
 */
export async function resolverCarreraPorCodigo(codigo: string): Promise<Career | null> {
  const carrerasBD = await obtenerCarreras();

  const carrera = carrerasBD.find(
    (item) => item.codigo.trim().toUpperCase() === codigo.trim().toUpperCase(),
  );

  if (!carrera) {
    return null;
  }

  const careerEstatico = AREAS.flatMap((area) => area.careers).find(
    (item) => item.code === carrera.codigo,
  );

  return {
    name: carrera.nombre,
    code: carrera.codigo,
    criterionNum: careerEstatico?.criterionNum ?? 4,
    clickable: true,
  };
}
