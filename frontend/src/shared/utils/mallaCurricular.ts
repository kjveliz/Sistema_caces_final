import { read, utils as xlsxUtils, type WorkSheet } from 'xlsx';

/**
 * Parseo de la malla curricular en .xlsx (Parte C del plan de malla
 * curricular xlsx, ver plan_malla_curricular_xlsx.txt §9.5 y MEMORIA del
 * proyecto §85.3). Reemplaza el mock hardcodeado
 * `MATERIAS_BY_PAO_MODULE` de `shared/data/academic.ts` (calcado de
 * DS_MALLA.xlsx, una sola carrera) por el parseo real de la malla de
 * cualquier carrera.
 *
 * Layout real confirmado contra `DS_MALLA.xlsx` (fixture en
 * `__fixtures__/DS_MALLA.xlsx`, misma hoja usada para escribir y probar
 * esta función):
 * - 3 columnas de PAO, cada una encabezada por una celda combinada con el
 *   texto "PERIODO ACADÉMICO N" (N = 1/2/3). El nombre de cada asignatura
 *   vive en esa misma columna, no en una columna fija por posición --
 *   por eso se detecta la columna buscando ese encabezado, en vez de
 *   asumir E/J/O.
 * - Dentro de cada columna de PAO, bloques "MÓDULO A/B/C" (etiqueta en una
 *   columna aparte, ver fixture) que delimitan tramos de filas. Cada
 *   asignatura ocupa una celda combinada (nombre) seguida de 2-3 filas de
 *   metadata ("No CRÉDITOS:", "No HORAS:", "TOTAL HORAS") que no son
 *   asignaturas y se descartan explícitamente (créditos/horas no se usan
 *   para nada de negocio, decidido en MEMORIA v106 §82.1b).
 * - "Práctica Laboral" y "Servicio Comunitario" aparecen como bloques con
 *   el mismo layout que una asignatura real (ocupan una celda de nombre
 *   dentro de un módulo), pero se excluyen explícitamente por no ser
 *   asignaturas evaluables (plan §0).
 * - Una sección de resumen ("CRÉDITOS/PAO:", "DOCENCIA", "HORAS/PAO:",
 *   etc.) cierra la hoja después del último módulo; se usa como límite
 *   para no leerla como si fuera parte del último módulo.
 *
 * Validado contra el fixture real: produce exactamente 22 asignaturas
 * (coincide con la celda "NÚMERO TOTAL DE ASIGNATURAS: 22" del propio
 * Excel) agrupadas en los mismos PAO/módulo que
 * `shared/data/academic.ts` documenta como mock de esta misma carrera.
 */

export interface AsignaturaMalla {
  nombre: string;
  modulo: string;
}

export interface PaoMalla {
  nombre: string;
  orden: number;
  asignaturas: AsignaturaMalla[];
}

export interface MallaCurricularParseada {
  paos: PaoMalla[];
}

interface ColumnaPao {
  columna: number;
  orden: number;
}

interface LimiteModulo {
  fila: number;
  letra: string;
}

const ETIQUETAS_METADATA_ASIGNATURA = [
  /^no\s*creditos\s*:?$/,
  /^no\s*horas\s*:?$/,
  /^total\s*horas$/,
];

const NOMBRES_EXCLUIDOS = [/^practica\s+laboral/, /^servicio\s+comunitario/];

function quitarAcentos(valor: string): string {
  return valor.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function normalizar(valor: string): string {
  return quitarAcentos(valor).trim().toLowerCase();
}

function valorCelda(hoja: WorkSheet, fila: number, columna: number): string | null {
  const celda = hoja[xlsxUtils.encode_cell({ r: fila, c: columna })];
  if (!celda || typeof celda.v !== 'string') {
    return null;
  }
  const valor = celda.v.trim();
  return valor.length > 0 ? valor : null;
}

/**
 * Recorre toda la hoja buscando las 3 columnas de PAO (por el texto de su
 * encabezado, no por posición fija) y las filas donde empieza cada
 * "MÓDULO X". Ambas cosas se ubican por contenido para no depender de que
 * la malla de otra carrera use exactamente las mismas filas/columnas que
 * DS_MALLA.xlsx.
 */
function ubicarEstructura(
  hoja: WorkSheet,
  rango: { filaInicio: number; filaFin: number; colInicio: number; colFin: number },
): { columnasPao: ColumnaPao[]; limitesModulo: LimiteModulo[]; filaCorte: number } {
  const columnasPao: ColumnaPao[] = [];
  const limitesModulo: LimiteModulo[] = [];
  let filaCorte = rango.filaFin + 1;

  for (let fila = rango.filaInicio; fila <= rango.filaFin; fila++) {
    for (let columna = rango.colInicio; columna <= rango.colFin; columna++) {
      const valor = valorCelda(hoja, fila, columna);
      if (!valor) continue;
      const valorNorm = normalizar(valor);

      const matchPao = valorNorm.match(/periodo\s+academico\s*([1-3])\b/);
      if (matchPao) {
        columnasPao.push({ columna, orden: Number(matchPao[1]) });
        continue;
      }

      const matchModulo = valorNorm.match(/^modulo\s+([a-z]+)$/);
      if (matchModulo) {
        limitesModulo.push({ fila, letra: matchModulo[1].toUpperCase() });
        continue;
      }

      if (/creditos\s*\/\s*pao/.test(valorNorm)) {
        filaCorte = Math.min(filaCorte, fila);
      }
    }
  }

  columnasPao.sort((a, b) => a.orden - b.orden);
  limitesModulo.sort((a, b) => a.fila - b.fila);

  return { columnasPao, limitesModulo, filaCorte };
}

/**
 * Parsea una malla curricular real en formato .xlsx (layout confirmado
 * contra DS_MALLA.xlsx, ver comentario de arriba). Recibe el
 * ArrayBuffer del archivo (ej. `await archivo.arrayBuffer()` de un
 * `<input type="file">`).
 *
 * @throws Error si no encuentra la hoja, las 3 columnas de PAO
 * (encabezados "PERIODO ACADÉMICO 1/2/3"), o ningún bloque "MÓDULO X" --
 * en cualquiera de esos casos el archivo no tiene el layout esperado y no
 * hay forma segura de adivinar la estructura.
 */
export function parsearMallaCurricular(buffer: ArrayBuffer): MallaCurricularParseada {
  const libro = read(buffer, { type: 'array' });
  const nombreHoja = libro.SheetNames.find((n) => /malla/i.test(n)) ?? libro.SheetNames[0];
  const hoja = nombreHoja ? libro.Sheets[nombreHoja] : undefined;

  if (!hoja || !hoja['!ref']) {
    throw new Error('El archivo Excel no tiene ninguna hoja con contenido.');
  }

  const rangoHoja = xlsxUtils.decode_range(hoja['!ref']);
  const rango = {
    filaInicio: rangoHoja.s.r,
    filaFin: rangoHoja.e.r,
    colInicio: rangoHoja.s.c,
    colFin: rangoHoja.e.c,
  };

  const { columnasPao, limitesModulo, filaCorte } = ubicarEstructura(hoja, rango);

  if (columnasPao.length !== 3 || !columnasPao.every(({ orden }, i) => orden === i + 1)) {
    throw new Error(
      'No se encontraron las 3 columnas de "PERIODO ACADÉMICO 1/2/3" en el Excel. ' +
        'Verificá que el archivo tenga el formato esperado de malla curricular.',
    );
  }

  if (limitesModulo.length === 0) {
    throw new Error(
      'No se encontró ningún bloque "MÓDULO A/B/C" en el Excel. ' +
        'Verificá que el archivo tenga el formato esperado de malla curricular.',
    );
  }

  const paos: PaoMalla[] = columnasPao.map(({ columna, orden }) => {
    const asignaturas: AsignaturaMalla[] = [];

    limitesModulo.forEach((limite, i) => {
      const filaInicioModulo = limite.fila;
      const filaFinModulo =
        i + 1 < limitesModulo.length ? limitesModulo[i + 1].fila : filaCorte;

      for (let fila = filaInicioModulo; fila < filaFinModulo; fila++) {
        const valor = valorCelda(hoja, fila, columna);
        if (!valor) continue;

        const valorNorm = normalizar(valor);
        if (ETIQUETAS_METADATA_ASIGNATURA.some((re) => re.test(valorNorm))) continue;
        if (NOMBRES_EXCLUIDOS.some((re) => re.test(valorNorm))) continue;

        asignaturas.push({ nombre: valor, modulo: limite.letra });
      }
    });

    return { nombre: `PAO ${orden}`, orden, asignaturas };
  });

  return { paos };
}
