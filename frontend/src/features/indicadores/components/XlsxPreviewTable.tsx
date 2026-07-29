import { useEffect, useState } from 'react';
import { AlertCircle } from 'lucide-react';
import { read, utils as xlsxUtils, type WorkSheet } from 'xlsx';

interface CeldaGrilla {
  valor: string;
  /** Celda "maestra" de un merge: cuántas filas/columnas ocupa (1 si no está combinada). */
  rowSpan: number;
  colSpan: number;
  /** true si esta celda está cubierta por el merge de otra y no debe renderizarse. */
  oculta: boolean;
}

interface HojaParseada {
  nombre: string;
  /** true si la hoja tiene celdas combinadas: en ese caso no se fuerza una fila de
   * encabezado sintética, porque en mallas curriculares y layouts similares la
   * primera fila real de datos no es un header en el sentido de una tabla CSV. */
  tieneMerges: boolean;
  filas: CeldaGrilla[][];
}

/** Arma la grilla completa de una hoja (incluye celdas vacías) respetando su rango real,
 * y aplica las celdas combinadas como rowSpan/colSpan en la celda superior-izquierda de
 * cada merge, marcando el resto de las celdas cubiertas como ocultas. */
function parsearHoja(hoja: WorkSheet): CeldaGrilla[][] {
  const rango = xlsxUtils.decode_range(hoja['!ref'] ?? 'A1');
  const merges = hoja['!merges'] ?? [];

  const grilla: CeldaGrilla[][] = [];
  for (let r = rango.s.r; r <= rango.e.r; r++) {
    const fila: CeldaGrilla[] = [];
    for (let c = rango.s.c; c <= rango.e.c; c++) {
      const celda = hoja[xlsxUtils.encode_cell({ r, c })];
      const valor = celda ? String(celda.w ?? celda.v ?? '') : '';
      fila.push({ valor, rowSpan: 1, colSpan: 1, oculta: false });
    }
    grilla.push(fila);
  }

  for (const merge of merges) {
    const filaBase = merge.s.r - rango.s.r;
    const colBase = merge.s.c - rango.s.c;
    if (!grilla[filaBase]?.[colBase]) continue;

    grilla[filaBase][colBase].rowSpan = merge.e.r - merge.s.r + 1;
    grilla[filaBase][colBase].colSpan = merge.e.c - merge.s.c + 1;

    for (let r = merge.s.r; r <= merge.e.r; r++) {
      for (let c = merge.s.c; c <= merge.e.c; c++) {
        if (r === merge.s.r && c === merge.s.c) continue;
        const fr = r - rango.s.r;
        const fc = c - rango.s.c;
        if (grilla[fr]?.[fc]) grilla[fr][fc].oculta = true;
      }
    }
  }

  return grilla;
}

function filaConContenido(fila: CeldaGrilla[]): boolean {
  return fila.some((celda) => !celda.oculta && celda.valor.trim() !== '');
}

/**
 * Vista previa real de un Excel (.xlsx/.xls) como tabla, en vez del
 * mensaje genérico de "vista previa no disponible". El navegador no tiene
 * visor nativo para incrustar Office dentro de un <iframe> (a diferencia
 * de PDF), así que se parsea el archivo del lado del cliente con `xlsx`
 * (SheetJS) — la misma librería que ya usa `shared/utils/mallaCurricular.ts`
 * para leer la malla al crear una carrera — y se renderiza como tabla,
 * mismo patrón que CsvPreviewTable.tsx para CSV.
 *
 * Respeta las celdas combinadas del Excel original (rowSpan/colSpan), que
 * es el layout típico de una malla curricular (título de período, módulo,
 * etc. ocupando varias columnas). Si el workbook tiene más de una hoja, se
 * muestran pestañas para cambiar entre ellas. No reconstruye colores ni
 * fórmulas — es una vista previa de datos y estructura, no un visor de
 * Excel completo; para eso está "Abrir documento".
 */
export default function XlsxPreviewTable({ url }: { url: string }) {
  const [hojas, setHojas] = useState<HojaParseada[] | null>(null);
  const [hojaActiva, setHojaActiva] = useState(0);
  const [error, setError] = useState<string | null>(null);
  const [cargando, setCargando] = useState(true);

  useEffect(() => {
    let cancelado = false;

    async function cargar() {
      setCargando(true);
      setError(null);
      setHojas(null);
      setHojaActiva(0);

      try {
        const respuesta = await fetch(url, { credentials: 'include' });

        if (!respuesta.ok) {
          throw new Error(`No se pudo cargar el archivo (HTTP ${respuesta.status}).`);
        }

        const buffer = await respuesta.arrayBuffer();
        const libro = read(buffer, { type: 'array' });

        const hojasParseadas: HojaParseada[] = libro.SheetNames.map((nombre) => {
          const hojaOriginal = libro.Sheets[nombre];
          const filas = parsearHoja(hojaOriginal).filter(filaConContenido);
          return {
            nombre,
            tieneMerges: (hojaOriginal['!merges']?.length ?? 0) > 0,
            filas,
          };
        }).filter((hoja) => hoja.filas.length > 0);

        if (cancelado) {
          return;
        }

        if (hojasParseadas.length === 0) {
          throw new Error('El archivo Excel no tiene ninguna hoja con contenido.');
        }

        setHojas(hojasParseadas);
      } catch (e) {
        if (!cancelado) {
          setError(e instanceof Error ? e.message : 'No se pudo cargar el archivo.');
        }
      } finally {
        if (!cancelado) {
          setCargando(false);
        }
      }
    }

    void cargar();

    return () => {
      cancelado = true;
    };
  }, [url]);

  if (cargando) {
    return (
      <div className="text-center px-8">
        <p className="text-sm font-medium" style={{ color: '#6B7280' }}>
          Cargando datos del Excel...
        </p>
      </div>
    );
  }

  if (error) {
    return (
      <div className="text-center px-8">
        <AlertCircle size={40} className="mx-auto mb-3" style={{ color: '#D1D5DB' }} />
        <p className="text-sm font-medium" style={{ color: '#6B7280' }}>
          No se pudo mostrar la vista previa
        </p>
        <p className="text-xs mt-1" style={{ color: '#9CA3AF' }}>
          {error}
        </p>
      </div>
    );
  }

  if (!hojas) {
    return null;
  }

  const hoja = hojas[hojaActiva];
  // Solo se trata la primera fila como "encabezado" (fondo distinto, sticky) cuando la
  // hoja NO tiene celdas combinadas -- una hoja con merges (típico de una malla
  // curricular) no tiene una fila de encabezado real en el sentido de una tabla CSV.
  const primeraFilaEsEncabezado = !hoja.tieneMerges;

  return (
    <div className="w-full h-full flex flex-col overflow-hidden">
      {hojas.length > 1 && (
        <div
          className="flex-shrink-0 flex gap-1 px-3 pt-2 overflow-x-auto"
          style={{ borderBottom: '1px solid rgba(27,58,107,0.08)' }}
        >
          {hojas.map((h, i) => (
            <button
              key={h.nombre}
              type="button"
              onClick={() => setHojaActiva(i)}
              className="px-3 py-1.5 text-xs font-semibold rounded-t-lg whitespace-nowrap transition-colors"
              style={{
                color: i === hojaActiva ? '#1B3A6B' : '#5A7295',
                background: i === hojaActiva ? '#F8FAFD' : 'transparent',
                borderBottom: i === hojaActiva ? '2px solid #1B3A6B' : '2px solid transparent',
              }}
            >
              {h.nombre}
            </button>
          ))}
        </div>
      )}

      <div className="flex-1 min-h-0 overflow-auto">
        <table className="min-w-full text-xs border-collapse">
          <tbody>
            {hoja.filas.map((fila, i) => {
              const esEncabezado = primeraFilaEsEncabezado && i === 0;
              return (
                <tr
                  key={i}
                  style={{
                    background: esEncabezado ? '#F8FAFD' : i % 2 === 0 ? '#FFFFFF' : '#FAFBFD',
                  }}
                >
                  {fila.map((celda, j) => {
                    if (celda.oculta) return null;
                    const Tag = esEncabezado ? 'th' : 'td';
                    return (
                      <Tag
                        key={j}
                        rowSpan={celda.rowSpan > 1 ? celda.rowSpan : undefined}
                        colSpan={celda.colSpan > 1 ? celda.colSpan : undefined}
                        className={`px-3 py-1.5 ${
                          celda.colSpan > 1 ? 'whitespace-normal' : 'whitespace-nowrap'
                        } ${esEncabezado ? 'text-left font-bold sticky top-0' : ''}`}
                        style={{
                          color: esEncabezado ? '#0F1E3C' : '#334155',
                          background: esEncabezado ? '#F8FAFD' : undefined,
                          borderBottom: esEncabezado
                            ? '1px solid rgba(27,58,107,0.12)'
                            : '1px solid rgba(27,58,107,0.05)',
                          textAlign: celda.colSpan > 1 ? 'center' : undefined,
                          verticalAlign: celda.rowSpan > 1 ? 'middle' : undefined,
                        }}
                      >
                        {celda.valor}
                      </Tag>
                    );
                  })}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}
