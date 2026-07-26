import { AlertCircle, TableProperties } from 'lucide-react';

import { getStatus } from '../../../shared/utils/evaluation';
import type { Career, IndicatorDef } from '../../../types/index';
import { useCohortesIndicador } from '../hooks/useCohortesIndicador';

// ── Tab Cohortes ───────────────────────────────────────────────────────────
export default function TabCohorts({
  ind,
  career,
  cohort,
}: {
  ind: IndicatorDef;
  career: Career | null;
  cohort: string;
}) {
  const {
    datosTitulacion,
    datosDesercion,
    cargando,
    errorCarga,
    esDesercion,
    cantidadRegistros,
    totalPrimerNivel,
    totalSegundoAnio,
    totalDesertados,
    tasaPromedioDesercion,
    totalMatriculados,
    totalGraduados,
    tasaGeneralTitulacion,
    encabezados,
  } = useCohortesIndicador({ ind, career, cohort });

  return (
    <div
      className={`h-full flex flex-col px-6 py-4 mx-auto overflow-hidden ${
        esDesercion ? 'max-w-6xl' : 'max-w-4xl'
      }`}
    >
      <div
        className="bg-white rounded-2xl overflow-hidden flex-1 min-h-0 flex flex-col"
        style={{
          border: '1px solid rgba(27,58,107,0.08)',
        }}
      >
        <div
          className="px-6 py-4 flex-shrink-0"
          style={{
            borderBottom: '1px solid rgba(27,58,107,0.07)',
            background: '#F8FAFD',
          }}
        >
          <h3
            className="text-sm font-semibold"
            style={{
              fontFamily: "'Libre Baskerville',serif",
              color: '#0F1E3C',
            }}
          >
            Historial de datos por cohorte
          </h3>

          <p className="text-xs mt-1" style={{ color: '#5A7295' }}>
            Carrera: {career?.name ?? '—'} · Cohorte seleccionada: {cohort}
          </p>
        </div>

        {cargando ? (
          <div className="flex-1 flex items-center justify-center">
            <p className="text-sm" style={{ color: '#5A7295' }}>
              Cargando datos...
            </p>
          </div>
        ) : errorCarga ? (
          <div className="flex-1 flex flex-col items-center justify-center text-center px-6">
            <AlertCircle size={36} className="mb-3" style={{ color: '#DC2626' }} />

            <p className="text-sm font-semibold" style={{ color: '#DC2626' }}>
              No se pudieron cargar los datos
            </p>

            <p className="text-xs mt-1 max-w-md" style={{ color: '#6B7280' }}>
              {errorCarga}
            </p>
          </div>
        ) : cantidadRegistros === 0 ? (
          <div className="flex-1 flex flex-col items-center justify-center text-center px-6">
            <TableProperties size={36} className="mb-3" style={{ color: '#D1D5DB' }} />

            <p className="text-sm font-medium" style={{ color: '#6B7280' }}>
              Sin datos de cohortes
            </p>

            <p className="text-xs mt-1 max-w-sm" style={{ color: '#9CA3AF' }}>
              {esDesercion
                ? 'Los resultados aparecerán cuando se lean y guarden los PDF de primer nivel, segundo año y estudiantes desertados.'
                : 'Los resultados aparecerán cuando se lean y guarden los PDF de matriculados y graduados.'}
            </p>
          </div>
        ) : (
          <div className="flex-1 overflow-auto">
            <table className="w-full text-sm">
              <thead>
                <tr
                  style={{
                    borderBottom: '1px solid rgba(27,58,107,0.07)',
                  }}
                >
                  {encabezados.map((encabezado) => (
                    <th
                      key={encabezado}
                      className="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider"
                      style={{
                        color: '#5A7295',
                        background: '#F8FAFD',
                      }}
                    >
                      {encabezado}
                    </th>
                  ))}
                </tr>
              </thead>

              <tbody>
                {esDesercion
                  ? datosDesercion.map((item, index) => {
                      const tasa = Number(item.tasa ?? 0);

                      const estado = getStatus(Math.round(tasa));

                      const esActual =
                        item.cohorte.replace(/\s+/g, '').toUpperCase() ===
                        cohort.replace(/\s+/g, '').toUpperCase();

                      return (
                        <tr
                          key={`${item.cohorte}-${index}`}
                          style={{
                            borderBottom: '1px solid rgba(27,58,107,0.05)',
                            background: esActual
                              ? '#EEF5FF'
                              : index % 2 === 0
                                ? '#FFFFFF'
                                : '#FAFBFD',
                          }}
                        >
                          <td className="px-5 py-3">
                            <div className="flex items-center gap-2">
                              <span
                                className="font-bold"
                                style={{
                                  fontFamily: "'DM Mono',monospace",
                                  color: esActual ? '#1B3A6B' : '#0F1E3C',
                                }}
                              >
                                Cohorte {item.cohorte}
                              </span>

                              {esActual && (
                                <span
                                  className="text-xs px-2 py-0.5 rounded-full font-bold"
                                  style={{
                                    background: '#1B3A6B',
                                    color: '#FFFFFF',
                                  }}
                                >
                                  actual
                                </span>
                              )}
                            </div>
                          </td>

                          <td className="px-5 py-3" style={{ color: '#374151' }}>
                            {Number(item.iniciaron_primer_nivel ?? 0)}
                          </td>

                          <td className="px-5 py-3" style={{ color: '#374151' }}>
                            {Number(item.matriculados_segundo_anio ?? 0)}
                          </td>

                          <td className="px-5 py-3" style={{ color: '#374151' }}>
                            {Number(item.no_continuaron ?? 0)}
                          </td>

                          <td className="px-5 py-3">
                            <span
                              className="font-bold px-2.5 py-1 rounded-lg"
                              style={{
                                background: estado.bg,
                                color: estado.color,
                                fontFamily: "'DM Mono',monospace",
                              }}
                            >
                              {tasa.toFixed(2)}%
                            </span>
                          </td>

                          <td className="px-5 py-3">
                            <span
                              className="text-xs px-2 py-0.5 rounded-full font-semibold"
                              style={{
                                background: estado.bg,
                                color: estado.color,
                              }}
                            >
                              {estado.label}
                            </span>
                          </td>
                        </tr>
                      );
                    })
                  : datosTitulacion.map((item, index) => {
                      const tasa = Number(item.tasa ?? 0);

                      const estado = getStatus(Math.round(tasa));

                      const esActual =
                        item.cohorte.replace(/\s+/g, '').toUpperCase() ===
                        cohort.replace(/\s+/g, '').toUpperCase();

                      return (
                        <tr
                          key={`${item.cohorte}-${index}`}
                          style={{
                            borderBottom: '1px solid rgba(27,58,107,0.05)',
                            background: esActual
                              ? '#EEF5FF'
                              : index % 2 === 0
                                ? '#FFFFFF'
                                : '#FAFBFD',
                          }}
                        >
                          <td className="px-5 py-3">
                            <div className="flex items-center gap-2">
                              <span
                                className="font-bold"
                                style={{
                                  fontFamily: "'DM Mono',monospace",
                                  color: esActual ? '#1B3A6B' : '#0F1E3C',
                                }}
                              >
                                Cohorte {item.cohorte}
                              </span>

                              {esActual && (
                                <span
                                  className="text-xs px-2 py-0.5 rounded-full font-bold"
                                  style={{
                                    background: '#1B3A6B',
                                    color: '#FFFFFF',
                                  }}
                                >
                                  actual
                                </span>
                              )}
                            </div>
                          </td>

                          <td className="px-5 py-3" style={{ color: '#374151' }}>
                            {Number(item.matriculados ?? 0)}
                          </td>

                          <td className="px-5 py-3" style={{ color: '#374151' }}>
                            {Number(item.graduados ?? 0)}
                          </td>

                          <td className="px-5 py-3">
                            <span
                              className="font-bold px-2.5 py-1 rounded-lg"
                              style={{
                                background: estado.bg,
                                color: estado.color,
                                fontFamily: "'DM Mono',monospace",
                              }}
                            >
                              {tasa.toFixed(2)}%
                            </span>
                          </td>

                          <td className="px-5 py-3">
                            <span
                              className="text-xs px-2 py-0.5 rounded-full font-semibold"
                              style={{
                                background: estado.bg,
                                color: estado.color,
                              }}
                            >
                              {estado.label}
                            </span>
                          </td>
                        </tr>
                      );
                    })}
              </tbody>

              {cantidadRegistros > 1 && (
                <tfoot>
                  <tr
                    style={{
                      borderTop: '2px solid rgba(27,58,107,0.12)',
                      background: '#EEF2F7',
                    }}
                  >
                    <td
                      className="px-5 py-3 text-xs font-bold uppercase"
                      style={{ color: '#5A7295' }}
                    >
                      {esDesercion ? 'Totales / promedio' : 'Totales'}
                    </td>

                    {esDesercion ? (
                      <>
                        <td className="px-5 py-3 font-bold" style={{ color: '#0F1E3C' }}>
                          {totalPrimerNivel}
                        </td>

                        <td className="px-5 py-3 font-bold" style={{ color: '#0F1E3C' }}>
                          {totalSegundoAnio}
                        </td>

                        <td className="px-5 py-3 font-bold" style={{ color: '#0F1E3C' }}>
                          {totalDesertados}
                        </td>

                        <td className="px-5 py-3 font-bold" style={{ color: '#0F1E3C' }}>
                          {tasaPromedioDesercion.toFixed(2)}%
                        </td>

                        <td className="px-5 py-3">
                          {getStatus(Math.round(tasaPromedioDesercion)).label}
                        </td>
                      </>
                    ) : (
                      <>
                        <td className="px-5 py-3 font-bold" style={{ color: '#0F1E3C' }}>
                          {totalMatriculados}
                        </td>

                        <td className="px-5 py-3 font-bold" style={{ color: '#0F1E3C' }}>
                          {totalGraduados}
                        </td>

                        <td className="px-5 py-3 font-bold" style={{ color: '#0F1E3C' }}>
                          {tasaGeneralTitulacion.toFixed(2)}%
                        </td>

                        <td className="px-5 py-3">
                          {getStatus(Math.round(tasaGeneralTitulacion)).label}
                        </td>
                      </>
                    )}
                  </tr>
                </tfoot>
              )}
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
