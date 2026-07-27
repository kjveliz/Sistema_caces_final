// Fase 3 del Plan de Mejora: I3 ya no se sirve como archivos .php sueltos
// (api/tutorias_academicas/*.php) sino a través del router de Slim en
// public/index.php — ver src/Controllers/TutoriasAcademicasController.php.
const BASE = 'http://localhost/sistemacaces/public/tutorias-academicas';

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

// ── Evidencia por asignatura (Indicador 11.3 — Tutorías Académicas) ─────
// Los 4 documentos de I3 son, igual que I2 desde c4fa5e15, por-asignatura
// (tabla evidencia_asignatura). A diferencia de I2, cada uno se valida
// automáticamente al subir y la evaluación es CUALITATIVA por puntos
// dentro de cada EF, no cuantitativa vía encuesta como I2. Desde v86:
// EF1/EF2/EF3 (Planeación/Cumplimiento/Seguimiento académico) se validan
// leyendo un CSV real (TutoriasCsvParserService); solo EF4 (Normativas)
// sigue leyendo un PDF (TutoriasValidacionPdfService). Esta capa de
// servicio NO cambia esa lógica: solo llama a los endpoints reales ya
// implementados en el backend, que decide CSV vs PDF según el campo
// `tipo` enviado (no según la extensión real del archivo subido).
export type TipoEvidenciaTutorias =
  'plan_tutorias' | 'registro_tutorias' | 'informe_tutorias' | 'evidencia_atencion';

export type EfTutorias = 'EF1' | 'EF2' | 'EF3' | 'EF4';

export interface PuntoValidacionTutorias {
  nombre: string;
  cumplido: boolean;
  valor: string | null;
}

export interface ValidacionEfTutorias {
  puntos: PuntoValidacionTutorias[];
  total_puntos: number;
  cumplidos: number;
}

export interface EvidenciaTutoriasItem {
  tipo: TipoEvidenciaTutorias;
  ef: EfTutorias;
  subida: boolean;
  archivo: {
    id_evidencia_asig: number;
    nombre_archivo: string;
    url_archivo: string;
    subido_por: string | null;
    fecha_subida: string;
  } | null;
  validacion: ValidacionEfTutorias | null;
}

export function obtenerEvidenciaTutorias(idAsignatura: number): Promise<EvidenciaTutoriasItem[]> {
  return getJson(`${BASE}/evidencia-listar?id_asignatura=${idAsignatura}`);
}

export async function subirEvidenciaTutorias(params: {
  idAsignatura: number;
  tipo: TipoEvidenciaTutorias;
  archivo: File;
}): Promise<{
  id_evidencia_asig: number;
  url_archivo: string;
  ef: EfTutorias;
  puntos: PuntoValidacionTutorias[];
  cumplidos: number;
  total_puntos: number;
}> {
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

// ── Resultado EF1-EF4 (evaluación cualitativa por puntos) ───────────────
// Espejo exacto de lo que devuelve api/tutorias_academicas/_calculo.php
// (pesosEf, etiquetasEfTutorias, porcentajePorPuntos): no se recalcula nada
// en el frontend, solo se muestra lo que ya viene calculado del backend.
export interface EfResultadoTutorias {
  label: string;
  peso: number;
  pct: number | null;
  estado: 'ok' | 'sin_datos';
  cumplidos: number;
  total_puntos: number;
  detalle_puntos: PuntoValidacionTutorias[];
}

export interface ResultadoAsignaturaTutorias {
  id_asignatura: number;
  nombre_asignatura: string;
  valoracion_general: number | null;
  estado_general: 'completo' | 'parcial';
  escala: string | null;
  color_escala: string | null;
  efs: Record<EfTutorias, EfResultadoTutorias>;
}

export interface ResultadoCohorteTutorias {
  valoracion_general: number | null;
  estado_general: 'completo' | 'parcial' | 'sin_datos';
  escala: string | null;
  color_escala: string | null;
  detalle_asignaturas: ResultadoAsignaturaTutorias[];
}

export function obtenerResultadoAsignaturaTutorias(
  idAsignatura: number,
  idEvaluacion: number,
): Promise<ResultadoAsignaturaTutorias> {
  return getJson(
    `${BASE}/resultado-asignatura?id_asignatura=${idAsignatura}&id_evaluacion=${idEvaluacion}`,
  );
}

export function obtenerResultadoCohorteTutorias(
  idCohorte: number,
  idEvaluacion: number,
  idPeriodo?: number,
): Promise<ResultadoCohorteTutorias> {
  const params = new URLSearchParams({
    id_cohorte: String(idCohorte),
    id_evaluacion: String(idEvaluacion),
  });
  if (idPeriodo) params.set('id_periodo', String(idPeriodo));
  return getJson(`${BASE}/resultado-cohorte?${params.toString()}`);
}
