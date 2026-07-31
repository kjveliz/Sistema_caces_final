export interface PrepararPdfResponse {
  ok: boolean;
  mensaje: string;
  datos?: {
    id_catalogo: number;
    codigo_evidencia: string;
    descripcion: string;
    nombre_original: string;
    nombre_generado: string;
    tipo: string;
    tamano: number;
    url_archivo: string;
  };
}

interface PrepararPdfParams {
  archivo: File;
  idCatalogo: number;
  codigoCarrera: string;
  cohorte: string;
  criterio: number;
  indicador: number;
}

// Ruta de Slim (Parte 8 del plan de migración de PHP suelto -- ver
// plan_migracion_slim_legacy_v3.txt §3), reemplaza a
// api/evidencias/preparar_pdf.php. Quinta y última Parte del Grupo B.
export async function prepararPdf({
  archivo,
  idCatalogo,
  codigoCarrera,
  cohorte,
  criterio,
  indicador,
}: PrepararPdfParams): Promise<PrepararPdfResponse> {
  const formulario = new FormData();

  formulario.append('archivo', archivo);
  formulario.append('id_catalogo', String(idCatalogo));
  formulario.append('codigo_carrera', codigoCarrera);
  formulario.append('cohorte', cohorte);
  formulario.append('criterio', String(criterio));
  formulario.append('indicador', String(indicador));

  const respuesta = await fetch('http://localhost/sistemacaces/public/evidencias/preparar-pdf', {
    method: 'POST',
    credentials: 'include',
    body: formulario,
  });

  const datos = (await respuesta.json()) as PrepararPdfResponse;

  if (!respuesta.ok || !datos.ok) {
    throw new Error(datos.mensaje || 'No se pudo procesar el archivo.');
  }

  return datos;
}

export interface CatalogoEvidencia {
  id_catalogo: number;
  codigo_evidencia: string;
  titulo_corto: string;
  descripcion: string;
  nombre_archivo_base: string;
  orden: number;
}

interface CatalogoResponse {
  ok: boolean;
  mensaje?: string;
  datos: CatalogoEvidencia[];
}

export async function obtenerCatalogoEvidencias(idIndicador: number): Promise<CatalogoEvidencia[]> {
  // Ruta de Slim (Parte 9 del plan de migración de PHP suelto -- ver
  // plan_migracion_slim_legacy_v3.txt §3), reemplaza a
  // api/catalogo/obtener_evidencias.php.
  const respuesta = await fetch(
    `http://localhost/sistemacaces/public/catalogo/obtener-evidencias?id_indicador=${idIndicador}`,
    {
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    },
  );

  const datos = (await respuesta.json()) as CatalogoResponse;

  if (!respuesta.ok || !datos.ok) {
    throw new Error(datos.mensaje || 'No se pudo cargar el catálogo de evidencias.');
  }

  return datos.datos;
}

export interface EvaluacionResponse {
  id_evaluacion: number;
  nombre_evaluacion: string;
  estado: string;
  fecha_inicio: string | null;
  fecha_fin: string | null;
  id_carrera: number;
  codigo_carrera: string;
  carrera: string;
  id_cohorte: number;
  nombre_cohorte: string;
}

interface ObtenerEvaluacionResponse {
  ok: boolean;
  mensaje?: string;
  datos?: EvaluacionResponse;
}

export async function obtenerEvaluacion(
  codigoCarrera: string,
  cohorte: string,
): Promise<EvaluacionResponse> {
  const parametros = new URLSearchParams({
    codigo_carrera: codigoCarrera,
    cohorte,
  });

  const respuesta = await fetch(
    `http://localhost/sistemacaces/api/evaluaciones/obtener_evaluacion.php?${parametros.toString()}`,
    {
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    },
  );

  const datos = (await respuesta.json()) as ObtenerEvaluacionResponse;

  if (!respuesta.ok || !datos.ok || !datos.datos) {
    throw new Error(datos.mensaje || 'No se pudo obtener la evaluación.');
  }

  return datos.datos;
}

interface GuardarEvidenciaParams {
  idCatalogo: number;
  idEvaluacion: number;
  codigoEvidencia: string;
  descripcion: string;
  nombreArchivo: string;
  tipo: string;
  urlArchivo: string;
}

interface GuardarEvidenciaResponse {
  ok: boolean;
  mensaje: string;
  id_evidencia?: number;
  relaciones_compartidas?: number;
}

// Ruta de Slim (Parte 7 del plan de migración de PHP suelto -- ver
// plan_migracion_slim_legacy_v3.txt §3), reemplaza a
// api/evidencias/guardar_evidencia.php. Cuarta y última Parte del Grupo B.
export async function guardarEvidencia({
  idCatalogo,
  idEvaluacion,
  codigoEvidencia,
  descripcion,
  nombreArchivo,
  tipo,
  urlArchivo,
}: GuardarEvidenciaParams): Promise<GuardarEvidenciaResponse> {
  const respuesta = await fetch(
    'http://localhost/sistemacaces/public/evidencias/guardar',
    {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        id_catalogo: idCatalogo,
        id_evaluacion: idEvaluacion,
        codigo_evidencia: codigoEvidencia,
        descripcion,
        nombre_archivo: nombreArchivo,
        tipo,
        url_archivo: urlArchivo,
      }),
    },
  );

  const datos = (await respuesta.json()) as GuardarEvidenciaResponse;

  if (!respuesta.ok || !datos.ok) {
    throw new Error(datos.mensaje || 'No se pudo guardar la evidencia.');
  }

  return datos;
}

export interface EvidenciaGuardada {
  id_evidencia: number;
  id_catalogo: number;
  id_evaluacion: number;
  codigo_evidencia: string;
  descripcion: string;
  nombre_archivo: string;
  tipo: string;
  url_archivo: string;
  fecha_subida: string;
  titulo_corto: string;
  nombre_archivo_base: string;
  orden: number;
}

interface EvidenciasGuardadasResponse {
  ok: boolean;
  mensaje?: string;
  datos: EvidenciaGuardada[];
}

export async function obtenerEvidenciasGuardadas(
  idEvaluacion: number,
  idIndicador: number,
): Promise<EvidenciaGuardada[]> {
  const parametros = new URLSearchParams({
    id_evaluacion: String(idEvaluacion),
    id_indicador: String(idIndicador),
  });

  // Ruta de Slim (Parte 4 del plan de migración de PHP suelto -- ver
  // plan_migracion_slim_legacy_v3.txt §3), reemplaza a
  // api/evidencias/obtener_evidencias_guardadas.php.
  const respuesta = await fetch(
    `http://localhost/sistemacaces/public/evidencias/guardadas?${parametros.toString()}`,
    {
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    },
  );

  const datos = (await respuesta.json()) as EvidenciasGuardadasResponse;

  if (!respuesta.ok || !datos.ok) {
    throw new Error(datos.mensaje || 'No se pudieron consultar las evidencias guardadas.');
  }

  return datos.datos;
}

export interface EvidenciaCompartida {
  id_evidencia: number;
  id_catalogo: number;
  id_evaluacion: number;
  codigo_evidencia: string;
  descripcion: string;
  nombre_archivo: string;
  tipo: string;
  url_archivo: string;
  fecha_subida: string;
  titulo_corto: string;
  nombre_archivo_base: string;
  orden: number;
  id_indicador_origen: number;
  indicador_origen: string;
}

interface EvidenciasCompartidasResponse {
  ok: boolean;
  mensaje?: string;
  datos: EvidenciaCompartida[];
}

export async function obtenerEvidenciasCompartidas(
  idEvaluacion: number,
  idIndicadorDestino: number,
): Promise<EvidenciaCompartida[]> {
  const parametros = new URLSearchParams({
    id_evaluacion: String(idEvaluacion),
    id_indicador_destino: String(idIndicadorDestino),
  });

  const respuesta = await fetch(
    `http://localhost/sistemacaces/public/evidencias/compartidas?${parametros.toString()}`,
    {
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    },
  );

  const datos = (await respuesta.json()) as EvidenciasCompartidasResponse;

  if (!respuesta.ok || !datos.ok) {
    throw new Error(datos.mensaje || 'No se pudieron consultar las evidencias compartidas.');
  }

  return datos.datos;
}

// GET /api/google_drive/ver_archivo.php?id_evidencia= — visor real para
// I1/I4/I5 (tabla `evidencias`, evaluation-wide). El backend ya ramifica
// Drive/local desde el paso 5 del plan de interruptor de almacenamiento,
// pero hasta ahora nada en el frontend lo llamaba: estos slots seguían
// abriendo `url_archivo` directo, que se rompe si la carrera está en modo
// 'local' porque esa URL pasa a ser una ruta de filesystem (no abrible
// desde el navegador) — ver plan_interruptor_almacenamiento.txt §4.5/§4.6
// y MEMORIA (bug reportado tras migrar una carrera a local).
// Mismo criterio que urlVisorEvidenciaAsignatura (evidenciaAsignaturaVisor.ts):
// no devuelve JSON, así que solo se necesita la URL para usarla como `src`
// de un <iframe>, destino de window.open(), o fetch() de CsvPreviewTable.
export function urlVisorEvidenciaLegacy(idEvidencia: number): string {
  return `http://localhost/sistemacaces/api/google_drive/ver_archivo.php?id_evidencia=${idEvidencia}`;
}

export interface SubirDriveResponse {
  ok: boolean;
  mensaje: string;
  datos?: {
    id_archivo: string;
    nombre_archivo: string;
    url_archivo: string;
    url_descarga?: string | null;
    id_carpeta: string;
    codigo_carrera: string;
    nombre_carrera: string;
    cohorte: string;
    indicador: number;
  };
}

interface SubirDriveParams {
  archivo: File;
  // Necesario para que el backend resuelva el interruptor de almacenamiento
  // de la carrera (EvidenciaStorageResolver) -- antes esta ruta subía
  // siempre a Google Drive sin mirar el flag para nada (ver MEMORIA v100/§76).
  idCarrera: number;
  codigoCarrera: string;
  nombreCarrera: string;
  cohorte: string;
  indicador: number;
  nombreArchivo: string;
  // "pdf" (default, retrocompatible con todos los slots existentes) o "csv"
  // para el slot de resultados de encuesta.
  tipoEsperado?: 'pdf' | 'csv';
}

export async function subirPdfGoogleDrive({
  archivo,
  idCarrera,
  codigoCarrera,
  nombreCarrera,
  cohorte,
  indicador,
  nombreArchivo,
  tipoEsperado = 'pdf',
}: SubirDriveParams): Promise<SubirDriveResponse> {
  const formulario = new FormData();

  formulario.append('archivo', archivo);
  formulario.append('id_carrera', String(idCarrera));
  formulario.append('codigo_carrera', codigoCarrera);
  formulario.append('nombre_carrera', nombreCarrera);
  formulario.append('cohorte', cohorte);
  formulario.append('indicador', String(indicador));
  formulario.append('nombre_archivo', nombreArchivo);
  formulario.append('tipo_esperado', tipoEsperado);

  const respuesta = await fetch(
    'http://localhost/sistemacaces/api/google_drive/subir_archivo.php',
    {
      method: 'POST',
      credentials: 'include',
      body: formulario,
    },
  );

  const datos = (await respuesta.json()) as SubirDriveResponse;

  if (!respuesta.ok || !datos.ok || !datos.datos) {
    throw new Error(datos.mensaje || 'No se pudo subir el PDF.');
  }

  return datos;
}

export interface LecturaMatriculadosResponse {
  ok: boolean;
  mensaje: string;
  datos?: {
    matriculados: number;
    periodo: string | null;
    cohorte_detectada: string | null;
  };
}

export async function leerMatriculadosPdf(archivo: File): Promise<LecturaMatriculadosResponse> {
  const formulario = new FormData();

  formulario.append('archivo', archivo);

  // Ruta de Slim (Parte 6 del plan de migración de PHP suelto -- ver
  // plan_migracion_slim_legacy_v3.txt §3), reemplaza a
  // api/evidencias/leer_matriculados.php.
  const respuesta = await fetch(
    'http://localhost/sistemacaces/public/evidencias/leer-matriculados',
    {
      method: 'POST',
      credentials: 'include',
      body: formulario,
    },
  );

  const datos = (await respuesta.json()) as LecturaMatriculadosResponse;

  if (!respuesta.ok || !datos.ok || !datos.datos) {
    throw new Error(datos.mensaje || 'No se pudo leer el PDF de matriculados.');
  }

  return datos;
}

export interface CohorteTitulacion {
  cohorte: string;

  matriculados: number;

  graduados: number;

  tasa: number;
}

export async function obtenerDatosTasa(idEvaluacion: number): Promise<CohorteTitulacion[]> {
  const respuesta = await fetch(
    `http://localhost/sistemacaces/public/tasa-titulacion/obtener?id_evaluacion=${idEvaluacion}`,
    {
      method: 'GET',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    },
  );

  const datos = await respuesta.json();

  if (!respuesta.ok || !datos.ok) {
    throw new Error(datos.mensaje || 'No se pudieron consultar los datos.');
  }

  return datos.datos ?? [];
}

export type TipoDatoTitulacion = 'matriculados' | 'graduados';

export interface LecturaPdfTitulacion {
  tipo_dato: TipoDatoTitulacion;
  total: number;
  metodo: 'total_reportado' | 'identificaciones_unicas';
  cohorte_detectada: string | null;
  periodo_detectado: string | null;
  identificaciones_detectadas: number;
}

interface LeerPdfTitulacionResponse {
  ok: boolean;
  mensaje: string;
  datos?: LecturaPdfTitulacion;
}

export async function leerPdfTitulacion(
  archivo: File,
  tipoDato: TipoDatoTitulacion,
): Promise<LecturaPdfTitulacion> {
  const formulario = new FormData();

  formulario.append('archivo', archivo);
  formulario.append('tipo_dato', tipoDato);

  const respuesta = await fetch('http://localhost/sistemacaces/public/tasa-titulacion/leer-pdf', {
    method: 'POST',
    credentials: 'include',
    body: formulario,
  });

  const datos = (await respuesta.json()) as LeerPdfTitulacionResponse;

  if (!respuesta.ok || !datos.ok || !datos.datos) {
    throw new Error(datos.mensaje || 'No se pudo leer el PDF.');
  }

  return datos.datos;
}

interface GuardarDatoTitulacionParams {
  idEvaluacion: number;
  cohorte: string;
  matriculados?: number;
  graduados?: number;
}

export interface DatoTitulacionGuardado {
  id_evaluacion: number;
  cohorte: string;
  matriculados: number | null;
  graduados: number | null;
  tasa: number | null;
}

interface GuardarDatoTitulacionResponse {
  ok: boolean;
  mensaje: string;
  datos?: DatoTitulacionGuardado;
}

export async function guardarDatoTitulacion({
  idEvaluacion,
  cohorte,
  matriculados,
  graduados,
}: GuardarDatoTitulacionParams): Promise<DatoTitulacionGuardado> {
  const cuerpo: Record<string, string | number> = {
    id_evaluacion: idEvaluacion,
    cohorte,
  };

  if (matriculados !== undefined) {
    cuerpo.matriculados = matriculados;
  }

  if (graduados !== undefined) {
    cuerpo.graduados = graduados;
  }

  const respuesta = await fetch('http://localhost/sistemacaces/public/tasa-titulacion/guardar', {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify(cuerpo),
  });

  const datos = (await respuesta.json()) as GuardarDatoTitulacionResponse;

  if (!respuesta.ok || !datos.ok || !datos.datos) {
    throw new Error(datos.mensaje || 'No se pudo guardar el cálculo.');
  }

  return datos.datos;
}

//Tasa de Deserción
export type TipoDatoDesercion = 'primer_nivel' | 'segundo_anio' | 'no_continuaron';

export interface LecturaPdfDesercion {
  tipo_dato: TipoDatoDesercion;
  total: number;
  metodo: 'total_reportado' | 'identificaciones_unicas';
  cohorte_detectada: string | null;
  periodo_detectado: string | null;
  identificaciones_detectadas: number;
}

interface LeerPdfDesercionResponse {
  ok: boolean;
  mensaje: string;
  datos?: LecturaPdfDesercion;
}

export async function leerPdfDesercion(
  archivo: File,
  tipoDato: TipoDatoDesercion,
): Promise<LecturaPdfDesercion> {
  const formulario = new FormData();

  formulario.append('archivo', archivo);
  formulario.append('tipo_dato', tipoDato);

  const respuesta = await fetch('http://localhost/sistemacaces/public/tasa-desercion/leer-pdf', {
    method: 'POST',
    credentials: 'include',
    body: formulario,
  });

  const datos = (await respuesta.json()) as LeerPdfDesercionResponse;

  if (!respuesta.ok || !datos.ok || !datos.datos) {
    throw new Error(datos.mensaje || 'No se pudo leer el PDF de deserción.');
  }

  return datos.datos;
}

interface GuardarDatoDesercionParams {
  idEvaluacion: number;
  cohorte: string;
  iniciaronPrimerNivel?: number;
  matriculadosSegundoAnio?: number;
  noContinuaron?: number;
}

export interface DatoDesercionGuardado {
  id_evaluacion: number;
  cohorte: string;
  iniciaron_primer_nivel: number | null;
  matriculados_segundo_anio: number | null;
  no_continuaron: number | null;
  tasa: number | null;
}

interface GuardarDatoDesercionResponse {
  ok: boolean;
  mensaje: string;
  advertencia?: string | null;
  datos?: DatoDesercionGuardado;
}

export async function guardarDatoDesercion({
  idEvaluacion,
  cohorte,
  iniciaronPrimerNivel,
  matriculadosSegundoAnio,
  noContinuaron,
}: GuardarDatoDesercionParams): Promise<DatoDesercionGuardado> {
  const cuerpo: Record<string, string | number> = {
    id_evaluacion: idEvaluacion,
    cohorte,
  };

  if (iniciaronPrimerNivel !== undefined) {
    cuerpo.iniciaron_primer_nivel = iniciaronPrimerNivel;
  }

  if (matriculadosSegundoAnio !== undefined) {
    cuerpo.matriculados_segundo_anio = matriculadosSegundoAnio;
  }

  if (noContinuaron !== undefined) {
    cuerpo.no_continuaron = noContinuaron;
  }

  const respuesta = await fetch('http://localhost/sistemacaces/public/tasa-desercion/guardar', {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify(cuerpo),
  });

  const datos = (await respuesta.json()) as GuardarDatoDesercionResponse;

  if (!respuesta.ok || !datos.ok || !datos.datos) {
    throw new Error(datos.mensaje || 'No se pudo guardar el cálculo de deserción.');
  }

  return datos.datos;
}

export interface CohorteDesercion {
  cohorte: string;
  iniciaron_primer_nivel: number | null;
  matriculados_segundo_anio: number | null;
  no_continuaron: number | null;
  tasa: number | null;
}

interface ObtenerDatosDesercionResponse {
  ok: boolean;
  mensaje?: string;
  datos?: CohorteDesercion[];
}

export async function obtenerDatosDesercion(idEvaluacion: number): Promise<CohorteDesercion[]> {
  const respuesta = await fetch(
    `http://localhost/sistemacaces/public/tasa-desercion/obtener?id_evaluacion=${idEvaluacion}`,
    {
      method: 'GET',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
      },
    },
  );

  const datos = (await respuesta.json()) as ObtenerDatosDesercionResponse;

  if (!respuesta.ok || !datos.ok) {
    throw new Error(datos.mensaje || 'No se pudieron consultar los datos de deserción.');
  }

  return datos.datos ?? [];
}

export interface MallaCurricularCarrera {
  id_malla: number;
  id_carrera: number;
  nombre_archivo: string;
  id_drive: string;
  url_drive: string;
  fecha_subida: string;
  fecha_actualizacion: string;
}

interface ObtenerMallaCurricularResponse {
  ok: boolean;
  mensaje?: string;
  datos?: MallaCurricularCarrera | null;
  detalle?: string;
}

export async function obtenerMallaCurricular(
  codigoCarrera: string,
): Promise<MallaCurricularCarrera | null> {
  const parametros = new URLSearchParams({
    codigo_carrera: codigoCarrera,
  });

  const respuesta = await fetch(
    `http://localhost/sistemacaces/public/malla-curricular/obtener?${parametros.toString()}`,
    {
      method: 'GET',
      credentials: 'include',
      cache: 'no-store',
      headers: {
        Accept: 'application/json',
      },
    },
  );

  let datos: ObtenerMallaCurricularResponse;

  try {
    datos = (await respuesta.json()) as ObtenerMallaCurricularResponse;
  } catch {
    throw new Error(
      'El servidor no devolvió una respuesta válida al consultar la malla curricular.',
    );
  }

  if (!respuesta.ok || !datos.ok) {
    throw new Error(datos.mensaje ?? datos.detalle ?? 'No se pudo consultar la malla curricular.');
  }

  return datos.datos ?? null;
}
