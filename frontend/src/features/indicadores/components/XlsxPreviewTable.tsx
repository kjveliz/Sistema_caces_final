import { useEffect, useState } from 'react';
import { AlertCircle } from 'lucide-react';
import { read, utils as xlsxUtils, type WorkSheet } from 'xlsx';

interface HojaHtml {
  nombre: string;
  html: string;
}

/** Calcula el rango real con contenido de la hoja (celdas con texto, o cubiertas por un
 * merge), para no arrastrar filas/columnas vacías sueltas que a veces quedan en el
 * `!ref` de un Excel exportado y que inflarían la tabla con espacio en blanco. */
function calcularRangoConContenido(hoja: WorkSheet): string | null {
  const rango = xlsxUtils.decode_range(hoja['!ref'] ?? 'A1');
  const merges = hoja['!merges'] ?? [];

  let minR = Infinity;
  let maxR = -Infinity;
  let minC = Infinity;
  let maxC = -Infinity;

  for (let r = rango.s.r; r <= rango.e.r; r++) {
    for (let c = rango.s.c; c <= rango.e.c; c++) {
      const celda = hoja[xlsxUtils.encode_cell({ r, c })];
      const valor = celda ? String(celda.w ?? celda.v ?? '').trim() : '';
      if (valor !== '') {
        if (r < minR) minR = r;
        if (r > maxR) maxR = r;
        if (c < minC) minC = c;
        if (c > maxC) maxC = c;
      }
    }
  }

  // Una celda combinada cuenta entera aunque su contenido viva solo en la celda
  // superior-izquierda (ya contemplada arriba): hay que asegurar que el rango cubra
  // el merge completo, para no cortar una celda combinada a la mitad.
  for (const merge of merges) {
    if (merge.s.r < minR) minR = merge.s.r;
    if (merge.e.r > maxR) maxR = merge.e.r;
    if (merge.s.c < minC) minC = merge.s.c;
    if (merge.e.c > maxC) maxC = merge.e.c;
  }

  if (!isFinite(minR)) return null;

  return xlsxUtils.encode_range({ s: { r: minR, c: minC }, e: { r: maxR, c: maxC } });
}

/** Marca con una clase los <td> que vienen de una celda combinada (rowspan/colspan > 1)
 * para poder destacarlos visualmente (títulos de período, nombre de módulo, etc.) sin
 * tener que reimplementar el parseo de merges a mano. */
function resaltarCeldasCombinadas(html: string): string {
  return html.replace(/<td rowspan=/g, '<td class="xlsx-merge-cell" rowspan=').replace(
    /<td colspan=/g,
    '<td class="xlsx-merge-cell" colspan=',
  );
}

/**
 * Vista previa real de un Excel (.xlsx/.xls) como tabla, en vez del
 * mensaje genérico de "vista previa no disponible". El navegador no tiene
 * visor nativo para incrustar Office dentro de un <iframe> (a diferencia
 * de PDF), así que se parsea el archivo del lado del cliente con `xlsx`
 * (SheetJS) — la misma librería que ya usa `shared/utils/mallaCurricular.ts`
 * para leer la malla al crear una carrera.
 *
 * Usa `XLSX.utils.sheet_to_html()`, la utilidad propia de SheetJS para
 * convertir una hoja a HTML: reconstruye colspan/rowspan de las celdas
 * combinadas exactamente como los ve Excel (períodos lado a lado, módulo
 * abarcando varias filas, etc.), en vez de una reconstrucción manual. Si
 * el workbook tiene más de una hoja, se muestran pestañas para cambiar
 * entre ellas. No reconstruye colores ni fórmulas — es una vista previa
 * de datos y estructura, no un visor de Excel completo; para eso está
 * "Abrir documento".
 */
export default function XlsxPreviewTable({ url }: { url: string }) {
  const [hojas, setHojas] = useState<HojaHtml[] | null>(null);
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

        const hojasParseadas: HojaHtml[] = libro.SheetNames.map((nombre) => {
          const hojaOriginal = libro.Sheets[nombre];
          const rangoConContenido = calcularRangoConContenido(hojaOriginal);
          if (!rangoConContenido) return { nombre, html: '' };

          const hojaTrim: WorkSheet = { ...hojaOriginal, '!ref': rangoConContenido };
          const htmlCrudo = xlsxUtils.sheet_to_html(hojaTrim, { header: '', footer: '' });
          return { nombre, html: resaltarCeldasCombinadas(htmlCrudo) };
        }).filter((hoja) => hoja.html !== '');

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

  return (
    <div className="w-full h-full flex flex-col overflow-hidden">
      <style>{`
        .xlsx-preview-table-wrapper table {
          border-collapse: collapse;
          font-size: 0.75rem;
          width: max-content;
          min-width: 100%;
        }
        .xlsx-preview-table-wrapper td {
          border: 1px solid rgba(27, 58, 107, 0.18);
          padding: 6px 10px;
          color: #334155;
          white-space: nowrap;
          vertical-align: middle;
          background: #FFFFFF;
        }
        .xlsx-preview-table-wrapper td.xlsx-merge-cell {
          font-weight: 700;
          color: #0F1E3C;
          background: #DCE6F5;
          border: 1px solid rgba(27, 58, 107, 0.35);
          text-align: center;
          white-space: normal;
        }
      `}</style>

      {hojas.length > 1 && (
        <div
          className="flex-shrink-0 flex gap-1 px-3 pt-2 overflow-x-auto"
          style={{ borderBottom: '1px solid rgba(27,58,107,0.08)' }}
        >
          {hojas.map((hoja, i) => (
            <button
              key={hoja.nombre}
              type="button"
              onClick={() => setHojaActiva(i)}
              className="px-3 py-1.5 text-xs font-semibold rounded-t-lg whitespace-nowrap transition-colors"
              style={{
                color: i === hojaActiva ? '#1B3A6B' : '#5A7295',
                background: i === hojaActiva ? '#F8FAFD' : 'transparent',
                borderBottom: i === hojaActiva ? '2px solid #1B3A6B' : '2px solid transparent',
              }}
            >
              {hoja.nombre}
            </button>
          ))}
        </div>
      )}

      <div className="flex-1 min-h-0 overflow-auto">
        {/* HTML generado por SheetJS a partir del propio archivo .xlsx del usuario (no
            input arbitrario de terceros), mismo origen ya confiado que el resto del
            visor de evidencias. */}
        <div
          className="xlsx-preview-table-wrapper"
          dangerouslySetInnerHTML={{ __html: hojas[hojaActiva].html }}
        />
      </div>
    </div>
  );
}
