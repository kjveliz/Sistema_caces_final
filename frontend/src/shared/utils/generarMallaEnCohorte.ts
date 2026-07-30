import { crearAsignatura, crearPeriodo, obtenerPeriodos } from '../services/seguimientoSyllabus';
import { parsearMallaCurricular } from './mallaCurricular';

/**
 * Genera los PAO + asignaturas de una cohorte ya existente a partir de un
 * Excel de malla curricular. Extraído de la lógica que antes vivía en
 * `useEditCareerForm.ts` (flujo "Editar carrera + subir Excel", que creaba
 * una cohorte nueva cada vez) para reutilizarla ahora desde la columna
 * "Malla" de "Gestión de cohortes" -- decisión acordada con el usuario el
 * 30 jul 2026, que reemplaza aquel flujo: ya no hace falta crear una
 * cohorte nueva para cargar malla, se carga directo sobre la fila
 * existente.
 *
 * Mismo chequeo defensivo que tenía useEditCareerForm: crearPeriodo() no
 * es get-or-create (a diferencia de crearAsignatura), así que si la
 * cohorte ya tuviera períodos cargados se bloquea en vez de duplicarlos.
 *
 * Nota de alcance: a diferencia del flujo viejo (donde la cohorte era
 * recién creada y se podía borrar entera si algo fallaba a mitad de
 * camino), acá la cohorte YA EXISTÍA de antes con su propia evaluación --
 * borrarla completa como rollback sería destructivo y no corresponde. Si
 * la carga falla después de crear algunos períodos, se informa cuántos se
 * alcanzaron a crear para que el usuario los revise manualmente (no hay
 * endpoint para borrar un período suelto sin borrar toda la cohorte).
 */
export async function generarMallaEnCohorte(idCohorte: number, archivo: File): Promise<void> {
  const buffer = await archivo.arrayBuffer();
  const malla = parsearMallaCurricular(buffer);

  const periodosExistentes = await obtenerPeriodos(idCohorte);
  if (periodosExistentes.length > 0) {
    throw new Error('Esa cohorte ya tiene malla curricular cargada.');
  }

  let periodosCreados = 0;

  try {
    for (const pao of malla.paos) {
      const idPeriodo = await crearPeriodo({
        idCohorte,
        nombre: pao.nombre,
        orden: pao.orden,
      });
      periodosCreados += 1;

      for (const asignatura of pao.asignaturas) {
        await crearAsignatura({
          idPeriodo,
          nombre: asignatura.nombre,
          modulo: asignatura.modulo,
        });
      }
    }
  } catch (error) {
    const base = error instanceof Error ? error.message : 'No se pudo generar la malla curricular.';
    const detalle =
      periodosCreados > 0
        ? ` Se alcanzaron a crear ${periodosCreados} período(s) antes del error -- revíselos manualmente.`
        : '';
    throw new Error(base + detalle, { cause: error });
  }
}
