const BASE = "http://localhost/sistemacaces/api/seguimiento_syllabus";

async function getJson<T>(url: string): Promise<T> {
  const respuesta = await fetch(url, {
    credentials: "include",
    headers: { Accept: "application/json" },
  });
  const datos = await respuesta.json();
  if (!respuesta.ok || !datos.ok) {
    throw new Error(datos.mensaje || "No se pudo completar la solicitud.");
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
  return getJson(`${BASE}/periodos.php?id_cohorte=${idCohorte}`);
}

// ── Asignaturas ──────────────────────────────────────────────────────────
export interface AsignaturaReal {
  id_asignatura: number;
  nombre: string;
  docente: string | null;
}

export function obtenerAsignaturas(idPeriodo: number): Promise<AsignaturaReal[]> {
  return getJson(`${BASE}/asignaturas.php?id_periodo=${idPeriodo}`);
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
  estado_general: "completo" | "parcial" | "sin_datos";
  escala: string | null;
  color_escala: string | null;
  evidencias_info: Record<string, EvidenciaInfo>;
  ef1: number | null; ef1_estado: string;
  ef2: number | null; ef2_estado: string;
  ef3: number | null; ef3_estado: string;
  ef4: number | null; ef4_estado: string;
  ef5: number | null; ef5_estado: string;
  respuestas: number;
  promedio_general: number;
}

export interface ResultadoCohorte extends Omit<ResultadoAsignatura, "id_asignatura" | "nombre_asignatura" | "evidencias_info"> {
  detalle_asignaturas: ResultadoAsignatura[];
}

export function obtenerResultadoAsignatura(idAsignatura: number, idEvaluacion: number): Promise<ResultadoAsignatura> {
  return getJson(`${BASE}/resultado_asignatura.php?id_asignatura=${idAsignatura}&id_evaluacion=${idEvaluacion}`);
}

export function obtenerResultadoCohorte(idCohorte: number, idEvaluacion: number, idPeriodo?: number): Promise<ResultadoCohorte> {
  const params = new URLSearchParams({
    id_cohorte: String(idCohorte),
    id_evaluacion: String(idEvaluacion),
  });
  if (idPeriodo) params.set("id_periodo", String(idPeriodo));
  return getJson(`${BASE}/resultado_cohorte.php?${params.toString()}`);
}

// ── Evidencia por asignatura (syllabus, actas, difusión, encuesta) ──────
// 'encuesta_csv' (slot 5 de I2) se agrega a partir de la migración
// sql/migracion_i2_encuesta_csv_por_asignatura.sql: el CSV de la encuesta
// ahora se sube por-asignatura igual que los otros 4 documentos, en vez de
// ser un único archivo evaluation-wide (ver MEMORIA v18).
export type TipoEvidenciaAsignatura =
  | "syllabus"
  | "acta_retroalimentacion"
  | "acta_ajuste_curricular"
  | "evidencia_difusion"
  | "encuesta_csv";

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

export function obtenerEvidenciaAsignatura(idAsignatura: number): Promise<EvidenciaAsignaturaItem[]> {
  return getJson(`${BASE}/evidencia_asignatura_listar.php?id_asignatura=${idAsignatura}`);
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

export function obtenerEncuestaDetalle(idAsignatura: number, idEvaluacion: number): Promise<EncuestaDetalle> {
  return getJson(`${BASE}/encuesta_detalle.php?id_asignatura=${idAsignatura}&id_evaluacion=${idEvaluacion}`);
}

export async function subirEvidenciaAsignatura(params: {
  idAsignatura: number;
  tipo: TipoEvidenciaAsignatura;
  archivo: File;
}): Promise<{ id_evidencia_asig: number; url_archivo: string }> {
  const formulario = new FormData();
  formulario.append("id_asignatura", String(params.idAsignatura));
  formulario.append("tipo", params.tipo);
  formulario.append("archivo", params.archivo);

  const respuesta = await fetch(`${BASE}/evidencia_asignatura_subir.php`, {
    method: "POST",
    credentials: "include",
    body: formulario,
  });

  const datos = await respuesta.json();
  if (!respuesta.ok || !datos.ok) {
    throw new Error(datos.mensaje || "No se pudo subir la evidencia.");
  }
  return datos.datos;
}