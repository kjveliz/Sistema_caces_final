import type { EfTutorias } from '../../../shared/services/tutoriasAcademicas';

// ── Tab Resultados (I3 – Tutorías Académicas) ──────────────────────────────
// Etiquetas legibles para los nombres de puntos de validación que devuelve
// el backend. Solo presentación -- el backend es quien decide cumplido/no
// cumplido. Actualizado en v86 (MEMORIA §63): EF1/EF2/EF3 ya no se validan
// leyendo un PDF por regex (TutoriasValidacionPdfService, ahora acotado a
// EF4) sino un CSV real (TutoriasCsvParserService) -- se quitan los puntos
// que ya no emite el backend (horas, firma_docente, reporte_mejora,
// normativa) y se agregan los nuevos del parser CSV.
export const I3_PUNTO_LABELS: Record<string, string> = {
  // EF1 (Planeación) y EF2 (Cumplimiento) -- CSV.
  horas_asignadas_presente: 'Horas de tutoría asignadas',
  horas_cumplidas_ok: 'Horas de tutoría cumplidas',
  cohorte_coincide: 'Cohorte coincide',
  pao_coincide: 'PAO coincide',
  // EF3 (Seguimiento académico) -- CSV, solo credenciales por ahora (ver
  // nota de calibración pendiente en TutoriasCsvParserService).
  asignatura_coincide: 'Asignatura coincide',
  // EF4 (Normativas) -- sigue siendo PDF, sin cambios.
  encabezado_institucional: 'Encabezado institucional',
  firma_director: 'Firma de director de carrera',
};

export const I3_EF_ORDEN: EfTutorias[] = ['EF1', 'EF2', 'EF3', 'EF4'];
