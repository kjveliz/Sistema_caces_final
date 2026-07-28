import { useEffect, useState } from 'react';
import { AlertCircle } from 'lucide-react';

import { parseCsv } from '../../../shared/utils/csv';

/**
 * Vista previa de un CSV como tabla, en vez de meterlo crudo en un
 * <iframe> (que en Chrome se descarga solo para 'text/csv'/'text/plain'
 * en vez de mostrarse — ver MEMORIA, bug reportado tras paso 6 parte 2b
 * del plan de interruptor de almacenamiento).
 *
 * Hace un fetch propio (con `credentials: 'include'` para mandar la cookie
 * de sesión PHP, igual que el resto de los servicios) en vez de depender
 * de que el navegador renderice el Content-Type — así funciona sin
 * importar qué MIME type devuelva el backend.
 */
export default function CsvPreviewTable({ url }: { url: string }) {
  const [filas, setFilas] = useState<string[][] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [cargando, setCargando] = useState(true);

  useEffect(() => {
    let cancelado = false;

    async function cargar() {
      setCargando(true);
      setError(null);
      setFilas(null);

      try {
        const respuesta = await fetch(url, { credentials: 'include' });

        if (!respuesta.ok) {
          throw new Error(`No se pudo cargar el archivo (HTTP ${respuesta.status}).`);
        }

        const texto = await respuesta.text();

        if (!cancelado) {
          setFilas(parseCsv(texto));
        }
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
          Cargando datos del CSV...
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

  if (!filas || filas.length === 0) {
    return (
      <div className="text-center px-8">
        <p className="text-sm font-medium" style={{ color: '#6B7280' }}>
          El archivo CSV está vacío.
        </p>
      </div>
    );
  }

  const [encabezado, ...cuerpo] = filas;

  return (
    <div className="w-full h-full overflow-auto">
      <table className="min-w-full text-xs border-collapse">
        <thead>
          <tr style={{ background: '#F8FAFD' }}>
            {encabezado.map((columna, i) => (
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
  );
}
