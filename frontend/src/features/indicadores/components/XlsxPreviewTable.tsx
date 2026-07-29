import { useEffect, useState } from 'react';
import { AlertCircle } from 'lucide-react';
import { read, utils as xlsxUtils } from 'xlsx';

interface HojaParseada {
  nombre: string;
  filas: string[][];
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
 * Si el workbook tiene más de una hoja, se muestran pestañas para
 * cambiar entre ellas. No intenta reconstruir el layout visual exacto de
 * Excel (celdas combinadas, colores, fórmulas) — es una vista previa de
 * datos, no un visor de Excel completo; para eso está "Abrir documento".
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

        const hojasParseadas: HojaParseada[] = libro.SheetNames.map((nombre) => ({
          nombre,
          filas: xlsxUtils.sheet_to_json<string[]>(libro.Sheets[nombre], {
            header: 1,
            raw: false,
            defval: '',
          }),
        })).filter((hoja) => hoja.filas.length > 0);

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

  const [encabezado, ...cuerpo] = hojas[hojaActiva].filas;

  return (
    <div className="w-full h-full flex flex-col overflow-hidden">
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
        <table className="min-w-full text-xs border-collapse">
          <thead>
            <tr style={{ background: '#F8FAFD' }}>
              {(encabezado ?? []).map((columna, i) => (
                <th
                  key={i}
                  className="sticky top-0 px-3 py-2 text-left font-bold whitespace-nowrap"
                  style={{
                    color: '#0F1E3C',
                    background: '#F8FAFD',
                    borderBottom: '1px solid rgba(27,58,107,0.12)',
                  }}
                >
                  {columna || `Columna ${i + 1}`}
                </th>
              ))}
            </tr>
          </thead>

          <tbody>
            {cuerpo.map((fila, i) => (
              <tr
                key={i}
                style={{
                  background: i % 2 === 0 ? '#FFFFFF' : '#FAFBFD',
                }}
              >
                {fila.map((valor, j) => (
                  <td
                    key={j}
                    className="px-3 py-1.5 whitespace-nowrap"
                    style={{
                      color: '#334155',
                      borderBottom: '1px solid rgba(27,58,107,0.05)',
                    }}
                  >
                    {valor}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
