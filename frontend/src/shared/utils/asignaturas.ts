import type { AsignaturaReal } from '../services/seguimientoSyllabus';

/**
 * Resuelve el id_asignatura real a partir del nombre de materia elegido en
 * el selector del wizard (lista mock, Title Case, ver data/academic.ts)
 * contra la lista real de asignaturas de un período académico (sentence
 * case en varias filas de la BD).
 *
 * Comparación case-insensitive y con espacios al borde recortados. Antes de
 * este fix (ver MEMORIA del proyecto, commit 9438cc31) la comparación era
 * exacta (===) y fallaba en silencio para materias que sí existían en la BD
 * pero con distinto casing, dejando asignaturaId en null y bloqueando la
 * subida con "No se pudo determinar la asignatura" (ver EvidenceUploadView.tsx,
 * resolverAsignatura()).
 *
 * Extraída como función pura en la Fase 5 (testing) para poder cubrirla con
 * Vitest sin necesidad de montar el componente ni mockear fetch.
 */
export function resolverAsignaturaPorNombre(
  asignaturas: AsignaturaReal[],
  materia: string,
): number | null {
  const materiaNorm = materia.trim().toLowerCase();
  const match = asignaturas.find((a) => a.nombre.trim().toLowerCase() === materiaNorm);
  return match?.id_asignatura ?? null;
}
