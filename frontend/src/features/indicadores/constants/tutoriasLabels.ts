import type { EfTutorias } from '../../../shared/services/tutoriasAcademicas';

// ── Tab Resultados (I3 – Tutorías Académicas) ──────────────────────────────
// Etiquetas legibles para los nombres de puntos de validación que devuelve
// el backend (api/tutorias_academicas/_validacion_pdf.php, puntosPorEf()).
// Solo presentación -- el backend es quien decide cumplido/no cumplido.
export const I3_PUNTO_LABELS: Record<string, string> = {
  encabezado_institucional: 'Encabezado institucional',
  horas: 'Horas',
  firma_docente: 'Firma de docente',
  firma_director: 'Firma de director de carrera',
  reporte_mejora: 'Reporte de mejora académica',
  normativa: 'Normativa institucional vigente',
};

export const I3_EF_ORDEN: EfTutorias[] = ['EF1', 'EF2', 'EF3', 'EF4'];
