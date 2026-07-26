// Mapeo de sourceNum de I2 a tipo en evidencia_asignatura. 'encuesta_csv'
// (slot 5) se agrega a partir de la migración
// sql/migracion_i2_encuesta_csv_por_asignatura.sql (ver MEMORIA v18,
// reemplaza lo decidido en v17 sección 41: el CSV ya no es evaluation-wide).
export const I2_SOURCE_NUM_TO_TIPO: Record<number, string> = {
  1: 'syllabus',
  3: 'acta_ajuste_curricular',
  4: 'evidencia_difusion',
  5: 'encuesta_csv',
};

// I3 (Tutorías Académicas): mismo mecanismo por-asignatura que I2, sin
// slots evaluation-wide -- los 4 slots del wizard mapean 1:1 a los 4 tipos
// reales de evidencia_asignatura para este indicador.
export const I3_SOURCE_NUM_TO_TIPO: Record<number, string> = {
  1: 'plan_tutorias',
  2: 'registro_tutorias',
  3: 'informe_tutorias',
  4: 'evidencia_atencion',
};

/**
 * I2 e I3 comparten el mismo mecanismo por-asignatura: un item de
 * evidencia_asignatura (o su equivalente de tutorías, misma forma) que
 * indica si ya se subió el archivo de un tipo dado. Ambos construyen el
 * mismo objeto `file` del slot a partir de ese item -- extraído acá para
 * no duplicarlo entre useEvidenciasIndicador.ts (I2/I3) y, más adelante,
 * cualquier otro lugar que necesite la misma resolución (Fase 4).
 */
export interface ItemEvidenciaPorTipo {
  tipo: string;
  subida: boolean;
  archivo: {
    nombre_archivo: string;
    url_archivo: string;
  } | null;
}

export function resolverArchivoPorTipo(
  items: ItemEvidenciaPorTipo[] | null | undefined,
  tipo: string,
):
  | {
      originalName: string;
      fileName: string;
      url: string;
      serverUrl: string;
      size: number;
    }
  | undefined {
  const item = items?.find((e) => e.tipo === tipo);

  if (!item || !item.subida || !item.archivo) {
    return undefined;
  }

  return {
    originalName: item.archivo.nombre_archivo,
    fileName: item.archivo.nombre_archivo,
    url: item.archivo.url_archivo,
    serverUrl: item.archivo.url_archivo,
    size: 0,
  };
}
