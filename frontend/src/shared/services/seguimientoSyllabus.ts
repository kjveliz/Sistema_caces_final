// Fase 3 del Plan de Mejora: I2 ya no se sirve como archivos .php sueltos
// (api/seguimiento_syllabus/*.php) sino a través del router de Slim en
// public/index.php — ver src/Controllers/SeguimientoSyllabusController.php.
const BASE = 'http://localhost/sistemacaces/public/seguimiento-syllabus';

async function getJson<T>(url: string): Promise<T> {
  const respuesta = await fetch(url, {
    credentials: 'include',
    headers: { Accept: 'application/json' },
  });
  const datos = await respuesta.json();
  if (!respuesta.ok || !datos.ok) {
    throw new Error(datos.mensaje || 'No se pudo completar la solicitud.');
  }
  return datos.datos as T;
}

// ── Periodos académicos (PAO) ───────────────────────────────────────────
export interface PeriodoAcademico {
  id_periodoacademico: number;
  nombre: string;
  orden: number;
  fecha_inicio: string | null;
  fecha_fin: string | null;
}

export function obtenerPeriodos(idCohorte: number): Promise<PeriodoAcademico[]> {
  return getJson(`${BASE}/periodos?id_cohorte=${idCohorte}`);
}

interface CrearPeriodoResponse {
  ok: boolean;
  mensaje?: string;
  datos?: { id_periodoacademico: number };
}

export interface CrearPeriodoParams {
  idCohorte: number;
  nombre: string;
  orden: number;
  fechaInicio?: string | null;
  fechaFin?: string | null;
}

/**
 * POST /seguimiento-syllabus/periodos (Parte D del plan de malla
 * curricular xlsx, ver plan_malla_curricular_xlsx.txt §9.2 paso 4). El
 * endpoint en sí ya existía desde el backend original de §7 Parte 3; lo
 * único nuevo acá es el wrapper del lado del frontend, sin el cual
 * useNewCareerForm no tenía forma de llamarlo.
 */
export async function crearPeriodo(params: CrearPeriodoParams): Promise<number> {
  const respuesta = await fetch(`${BASE}/periodos`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({
      id_cohorte: params.idCohorte,
      nombre: params.nombre,
      orden: params.orden,
      fecha_inicio: params.fechaInicio || undefined,
      fecha_fin: params.fechaFin || undefined,
    }),
  });

  const datos = (await respuesta.json()) as CrearPeriodoResponse;

  if (!respuesta.ok || !datos.ok || !datos.datos?.id_periodoacademico) {
    throw new Error(datos.mensaje || 'No se pudo crear el período académico.');
  }

  return datos.datos.id_periodoacademico;
}

// ── Asignaturas ──────────────────────────────────────────────────────────
export interface AsignaturaReal {
  id_asignatura: number;
  nombre: string;
  docente: string | null;
  // Agregado en la Parte "3.5" del plan de malla curricular xlsx (ver
  // plan_malla_curricular_xlsx.txt §3.5): agrupador visual A/B/C real, para
  // que StepConfigSyllabus.tsx pueda dejar de usar el mock
  // MATERIAS_BY_PAO_MODULE de shared/data/academic.ts.
  modulo?: string | null;
}

export function obtenerAsignaturas(idPeriodo: number): Promise<AsignaturaReal[]> {
  return getJson(`${BASE}/asignaturas?id_periodo=${idPeriodo}`);
}

interface CrearAsignaturaResponse {
  ok: boolean;
  mensaje?: string;
  datos?: { id_asignatura: number };
}

export interface CrearAsignaturaParams {
  idPeriodo: number;
  nombre: string;
  docente?: string | null;
  modulo?: string | null;
}

/**
 * POST /seguimiento-syllabus/asignaturas (Parte D, plan §9.2 paso 5).
 * Mismo endpoint get-or-create ya usado por I2/I3 antes de este plan
 * (§7 Parte 4 le agregó `modulo`); acá solo se agrega el wrapper del
 * frontend para poder invocarlo desde la orquestación de alta de
 * carrera.
 */
export async function crearAsignatura(params: CrearAsignaturaParams): Promise<number> {
  const respuesta = await fetch(`${BASE}/asignaturas`, {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({
      id_periodo: params.idPeriodo,
      nombre: params.nombre,
      docente: params.docente || undefined,
      modulo: params.modulo || undefined,
    }),
  });

  const datos = (await respuesta.json()) as CrearAsignaturaResponse;

  if (!respuesta.ok || !datos.ok || !datos.datos?.id_asignatura) {
    throw new Error(datos.mensaje || 'No se pudo crear la asignatura.');
  }

  return datos.datos.id_asignatura;
}

// ── Borrado en cascada de cohorte (rollback de la Parte D) ──────────────
export interface ResumenEliminacionCohorte {
  periodos_borrados: number;
  asignaturas_borradas: number;
  evidencia_borrada: boolean;
}

interface EliminarCohorteResponse {
  ok: boolean;
  mensaje?: string;
  datos?: ResumenEliminacionCohorte;
}

/**
 * Se lanza específicamente cuando DELETE /cohortes/{id} responde 409 porque
 * la cohorte tiene evidencia real subida (cohorteTieneEvidenciaAsignaturaReal()
 * en el backend, o una FK RESTRICT de seguimiento_syllabus/syllabus/
 * tutorias_academicas). Mismo patrón que CarreraConEvaluacionesError en
 * carreras.ts: permite a la UI distinguir este caso puntual (para ofrecer la
 * eliminación forzada) de cualquier otro error genérico.
 */
export class CohorteConEvidenciaError extends Error {}

/**
 * DELETE /seguimiento-syllabus/cohortes/{id} (Parte A del plan, ver
 * plan_malla_curricular_xlsx.txt §9.5). Usado por la Parte D como
 * rollback cuando falla la creación de períodos o asignaturas después de
 * que la cohorte ya existe -- ver useNewCareerForm.ts.
 */
export async function eliminarCohorteSeguimiento(
  idCohorte: number,
): Promise<ResumenEliminacionCohorte> {
  const respuesta = await fetch(`${BASE}/cohortes/${idCohorte}`, {
    method: 'DELETE',
    credentials: 'include',
    headers: { Accept: 'application/json' },
  });

  const datos = (await respuesta.json()) as EliminarCohorteResponse;

  if (!respuesta.ok || !datos.ok || !datos.datos) {
    const mensaje = datos.mensaje || 'No se pudo borrar la cohorte.';

    if (respuesta.status === 409) {
      throw new CohorteConEvidenciaError(mensaje);
    }

    throw new Error(mensaje);
  }

  return datos.datos;
}

export interface ResumenEliminacionCohorteForzada {
  evaluaciones_borradas: number;
  periodos_borrados: number;
  asignaturas_borradas: number;
  evidencias_borradas: number;
}

interface EliminarCohorteForzadaResponse {
  ok: boolean;
  mensaje?: string;
  datos?: ResumenEliminacionCohorteForzada;
}

/**
 * POST /seguimiento-syllabus/cohortes/eliminar-forzada. Herramienta de
 * desarrollo/pruebas: borra una cohorte y TODA su cadena relacionada
 * (evaluación, períodos, asignaturas, evidencia en BD), sin el chequeo de
 * cohorteTieneEvidenciaAsignaturaReal() que aplica eliminarCohorteSeguimiento().
 * Mismo criterio que eliminarCarreraForzada() en carreras.ts: solo borra
 * filas de la base de datos, los archivos ya subidos quedan huérfanos a
 * propósito. Requiere confirmación extra en la UI antes de llamar a esta
 * función.
 */
export async function eliminarCohorteForzada(
  idCohorte: number,
): Promise<ResumenEliminacionCohorteForzada> {
  const respuesta = await fetch(`${BASE}/cohortes/eliminar-forzada`, {
    method: 'POST',
    credentials: 'include',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({ id_cohorte: idCohorte }),
  });

  const datos = (await respuesta.json()) as EliminarCohorteForzadaResponse;

  if (!respuesta.ok || !datos.ok || !datos.datos) {
    throw new Error(datos.mensaje || 'No se pudo eliminar forzadamente la cohorte.');
  }

  return datos.datos;
}

// ── Resultado EF1-EF5 ────────────────────────────────────────────────────
export interface EvidenciaInfo {
  subida: boolean;
  label: string;
}

export interface ResultadoAsignatura {
  id_asignatura: number;
  nombre_asignatura: string;
  valoracion_general: number | null;
  estado_general: 'completo' | 'parcial' | 'sin_datos';
  escala: string | null;
  color_escala: string | null;
  evidencias_info: Record<string, EvidenciaInfo>;
  ef1: number | null;
  ef1_estado: string;
  ef2: number | null;
  ef2_estado: string;
  ef3: number | null;
  ef3_estado: string;
  ef4: number | null;
  ef4_estado: string;
  ef5: number | null;
  ef5_estado: string;
  respuestas: number;
  promedio_general: number;
}

export interface ResultadoCohorte extends Omit<
  ResultadoAsignatura,
  'id_asignatura' | 'nombre_asignatura' | 'evidencias_info'
> {
  detalle_asignaturas: ResultadoAsignatura[];
}

export function obtenerResultadoAsignatura(
  idAsignatura: number,
  idEvaluacion: number,
): Promise<ResultadoAsignatura> {
  return getJson(
    `${BASE}/resultado-asignatura?id_asignatura=${idAsignatura}&id_evaluacion=${idEvaluacion}`,
  );
}

export function obtenerResultadoCohorte(
  idCohorte: number,
  idEvaluacion: number,
  idPeriodo?: number,
): Promise<ResultadoCohorte> {
  const params = new URLSearchParams({
    id_cohorte: String(idCohorte),
    id_evaluacion: String(idEvaluacion),
  });
  if (idPeriodo) params.set('id_periodo', String(idPeriodo));
  return getJson(`${BASE}/resultado-cohorte?${params.toString()}`);
}

// ── Evidencia por asignatura (syllabus, actas, difusión, encuesta) ──────
// 'encuesta_csv' (slot 5 de I2) se agrega a partir de la migración
// sql/migracion_i2_encuesta_csv_por_asignatura.sql: el CSV de la encuesta
// ahora se sube por-asignatura igual que los otros 4 documentos, en vez de
// ser un único archivo evaluation-wide (ver MEMORIA v18).
export type TipoEvidenciaAsignatura =
  'syllabus' | 'acta_ajuste_curricular' | 'evidencia_difusion' | 'encuesta_csv';

export interface EvidenciaAsignaturaItem {
  tipo: TipoEvidenciaAsignatura;
  label: string;
  subida: boolean;
  archivo: {
    id_evidencia_asig: number;
    nombre_archivo: string;
    url_archivo: string;
    subido_por: string | null;
    fecha_subida: string;
  } | null;
}

export function obtenerEvidenciaAsignatura(
  idAsignatura: number,
): Promise<EvidenciaAsignaturaItem[]> {
  return getJson(`${BASE}/evidencia-listar?id_asignatura=${idAsignatura}`);
}

// ── Detalle de encuesta de heteroevaluación (23 preguntas) ──────────────
// Alimenta el export de PDF: preguntas EF1/EF4 con conteo de respuestas por
// materia, y el resto de las 23 para el anexo. Desde que el CSV es
// por-asignatura (ver MEMORIA v18), respuestas_totales_materia es
// simplemente el total de filas del CSV propio de esa asignatura -- ya no
// hay "materia_filtrada" porque no se filtra nada.
export interface PreguntaEncuestaDetalle {
  numero: number;
  texto: string | null;
  es_ef1: boolean;
  es_ef4: boolean;
  conteos: Record<string, number>;
  total: number;
}

export interface EncuestaDetalle {
  respuestas_totales_materia: number;
  preguntas: PreguntaEncuestaDetalle[];
}

export function obtenerEncuestaDetalle(
  idAsignatura: number,
  idEvaluacion: number,
): Promise<EncuestaDetalle> {
  return getJson(
    `${BASE}/encuesta-detalle?id_asignatura=${idAsignatura}&id_evaluacion=${idEvaluacion}`,
  );
}

export async function subirEvidenciaAsignatura(params: {
  idAsignatura: number;
  tipo: TipoEvidenciaAsignatura;
  archivo: File;
}): Promise<{ id_evidencia_asig: number; url_archivo: string }> {
  const formulario = new FormData();
  formulario.append('id_asignatura', String(params.idAsignatura));
  formulario.append('tipo', params.tipo);
  formulario.append('archivo', params.archivo);

  const respuesta = await fetch(`${BASE}/evidencia-subir`, {
    method: 'POST',
    credentials: 'include',
    body: formulario,
  });

  const datos = await respuesta.json();
  if (!respuesta.ok || !datos.ok) {
    throw new Error(datos.mensaje || 'No se pudo subir la evidencia.');
  }
  return datos.datos;
}
