import { AlertCircle, Download, Loader2 } from 'lucide-react';
import { PolarAngleAxis, PolarGrid, Radar, RadarChart, ResponsiveContainer } from 'recharts';

import { getStatus } from '../../../shared/utils/evaluation';
import type { Career, IndicatorDef } from '../../../types/index';
import { useResultadosI2 } from '../hooks/useResultadosI2';
import EfCell from '../components/EfCell';

// ── Tab Resultados (I2 – Seguimiento de Syllabus) ──────────────────────────
// Solo lectura: la carga/reemplazo de evidencia se hace en la pestaña
// "Evidencias" (mecanismo genérico de slots), no aquí.
export default function TabResultsI2({
  ind,
  career,
  cohort,
  pao,
  onAsignaturaChange,
}: {
  ind: IndicatorDef;
  career: Career | null;
  cohort: string;
  pao: number;
  onAsignaturaChange?: (idAsignatura: number | null, nombreAsignatura?: string | null) => void;
}) {
  const {
    cargando,
    error,
    detalle,
    asig,
    selectedAsig,
    setSelectedAsig,
    efScores,
    totalGeneral,
    totalAsignatura,
    materiaCompleta,
    radarData,
    exportando,
    handleExportarPDF,
  } = useResultadosI2({ career, cohort, pao, onAsignaturaChange });

  if (cargando) {
    return (
      <div className="h-full flex items-center justify-center gap-2" style={{ color: '#5A7295' }}>
        <Loader2 size={16} className="animate-spin" /> Cargando resultados…
      </div>
    );
  }

  if (error) {
    return (
      <div
        className="h-full flex flex-col items-center justify-center gap-2"
        style={{ color: '#DC2626' }}
      >
        <AlertCircle size={20} />
        <p className="text-sm font-medium">{error}</p>
      </div>
    );
  }

  return (
    <div className="h-full flex px-6 py-4 gap-4 overflow-hidden" style={{ maxWidth: 'none' }}>
      {/* ── LEFT 50%: subject card + list ─────────────────────────── */}
      <div className="flex-1 flex flex-col gap-3 min-h-0 min-w-0">
        {/* Career/Cohort/PAO card with Valoración General (agregado del PAO) */}
        {(() => {
          const st = getStatus(totalGeneral ?? 0);
          return (
            <div
              className="flex-shrink-0 bg-white rounded-2xl px-4 py-3 flex items-center justify-between gap-3"
              style={{ border: '1px solid rgba(27,58,107,0.08)' }}
            >
              <div className="min-w-0">
                <p className="text-xs font-bold truncate" style={{ color: '#0F1E3C' }}>
                  {career?.name || '—'}
                </p>
                <p className="text-xs mt-0.5" style={{ color: '#5A7295' }}>
                  Cohorte {cohort} · PAO {pao}
                </p>
              </div>
              <div className="text-right flex-shrink-0">
                <p
                  className="text-xs font-bold uppercase tracking-widest mb-0.5"
                  style={{ color: '#5A7295' }}
                >
                  Valoración General
                </p>
                <p
                  className="text-2xl font-bold leading-none"
                  style={{
                    color: totalGeneral === null ? '#9CA3AF' : st.color,
                    fontFamily: "'DM Mono',monospace",
                  }}
                >
                  {totalGeneral === null ? '—' : `${totalGeneral}%`}
                </p>
                {totalGeneral !== null && (
                  <p
                    className="text-xs mt-0.5 font-semibold px-2 py-0.5 rounded-full inline-block"
                    style={{ background: st.bg, color: st.color }}
                  >
                    {st.label}
                  </p>
                )}
              </div>
            </div>
          );
        })()}

        {/* Subject list */}
        <div
          className="flex-1 bg-white rounded-2xl overflow-hidden flex flex-col min-h-0"
          style={{ border: '1px solid rgba(27,58,107,0.08)' }}
        >
          <div
            className="px-4 py-2 flex-shrink-0"
            style={{ borderBottom: '1px solid rgba(27,58,107,0.07)', background: '#F8FAFD' }}
          >
            <p className="text-xs font-bold uppercase tracking-widest" style={{ color: '#5A7295' }}>
              Asignaturas
            </p>
          </div>
          <div className="flex flex-col flex-1 overflow-auto">
            {detalle.map((d, ai) => {
              const active = selectedAsig === ai;
              const subTotal = d.valoracion_general;
              const subSt = getStatus(subTotal ?? 0);
              return (
                <button
                  key={d.id_asignatura}
                  onClick={() => setSelectedAsig(ai)}
                  className="flex-shrink-0 w-full text-left px-3 flex items-center justify-between gap-2 transition-colors hover:bg-blue-50"
                  style={{
                    height: 36,
                    borderBottom: '1px solid rgba(27,58,107,0.05)',
                    background: active ? '#EEF5FF' : 'transparent',
                    borderLeft: `3px solid ${active ? '#1B3A6B' : 'transparent'}`,
                  }}
                >
                  <div className="flex items-center gap-1.5 min-w-0">
                    <div
                      className="w-1.5 h-1.5 rounded-full flex-shrink-0"
                      style={{ background: subTotal === null ? '#9CA3AF' : subSt.color }}
                    />
                    <p
                      className="text-xs font-semibold truncate"
                      style={{ color: active ? '#1B3A6B' : '#0F1E3C' }}
                    >
                      {d.nombre_asignatura}
                    </p>
                  </div>
                  <span
                    className="text-xs font-bold flex-shrink-0"
                    style={{
                      color: subTotal === null ? '#9CA3AF' : subSt.color,
                      fontFamily: "'DM Mono',monospace",
                    }}
                  >
                    {subTotal === null ? '—' : `${subTotal}%`}
                  </span>
                </button>
              );
            })}
            {detalle.length === 0 && (
              <p className="text-xs px-3 py-3" style={{ color: '#94A3B8' }}>
                No hay asignaturas cargadas para este PAO.
              </p>
            )}
          </div>
        </div>
      </div>

      {/* ── RIGHT 50%: "Resultados por EF" — no scroll ────────────── */}
      <div
        className="flex-1 bg-white rounded-2xl flex flex-col min-h-0 min-w-0 overflow-hidden"
        style={{ border: '1px solid rgba(27,58,107,0.08)' }}
      >
        {/* Card header */}
        <div
          className="flex items-center justify-between px-5 py-2.5 flex-shrink-0"
          style={{ borderBottom: '1px solid rgba(27,58,107,0.07)', background: '#F8FAFD' }}
        >
          <div>
            <h3
              className="font-bold"
              style={{ fontFamily: "'Libre Baskerville',serif", color: '#0F1E3C', fontSize: 13 }}
            >
              {asig?.nombre_asignatura ?? '—'}
            </h3>
          </div>
          <div className="flex items-center gap-1.5 flex-shrink-0">
            {asig && (
              <span
                className="px-2 py-1 rounded-lg font-bold text-xs"
                style={
                  materiaCompleta
                    ? { background: '#DCFCE7', color: '#16A34A' }
                    : { background: '#FEF9C3', color: '#CA8A04' }
                }
              >
                {materiaCompleta ? 'Completo' : 'Incompleto'}
              </span>
            )}
            {(() => {
              const st = getStatus(totalAsignatura ?? 0);
              return (
                <span
                  className="px-2.5 py-1 rounded-lg font-bold flex-shrink-0"
                  style={{
                    background: totalAsignatura === null ? '#F1F5F9' : st.bg,
                    color: totalAsignatura === null ? '#94A3B8' : st.color,
                    fontFamily: "'DM Mono',monospace",
                    fontSize: 13,
                  }}
                >
                  {totalAsignatura === null ? 'Sin datos' : `${totalAsignatura}%`}
                </span>
              );
            })()}
          </div>
        </div>

        {/* Radar — flex-shrink-0 with fixed height */}
        <div className="flex-shrink-0 px-4 pt-2" style={{ height: 185 }}>
          <ResponsiveContainer width="100%" height="100%">
            <RadarChart data={radarData} cx="50%" cy="50%" outerRadius="60%">
              <PolarGrid key="grid" stroke="#E5E7EB" />
              <PolarAngleAxis
                key="axis"
                dataKey="subject"
                tick={{
                  fill: '#5A7295',
                  fontSize: 11,
                  fontWeight: 700,
                  fontFamily: "'DM Mono',monospace",
                }}
              />
              <Radar
                key="radar"
                name="EF"
                dataKey="score"
                stroke="#16A34A"
                fill="#16A34A"
                fillOpacity={0.15}
                strokeWidth={2}
                dot={{ r: 4, fill: '#16A34A' }}
              />
            </RadarChart>
          </ResponsiveContainer>
        </div>

        {/* EF grid — flex-1, fits remaining height */}
        <div className="flex-1 px-4 pb-2 flex flex-col gap-1.5 min-h-0">
          <div className="grid grid-cols-2 gap-1.5 flex-shrink-0">
            <EfCell ef={efScores[0]} />
            <EfCell ef={efScores[1]} />
          </div>
          <div className="grid grid-cols-2 gap-1.5 flex-shrink-0">
            <EfCell ef={efScores[2]} />
            <EfCell ef={efScores[3]} />
          </div>
          <EfCell ef={efScores[4]} />

          {/* Footer note */}
          <div className="flex items-center gap-1.5 flex-shrink-0 mt-0.5">
            <AlertCircle size={10} style={{ color: '#94A3B8', flexShrink: 0 }} />
            <p style={{ color: '#94A3B8', fontSize: 10 }}>
              Calculado desde los archivos subidos en la pestaña Evidencias: EF1 y EF4 desde el CSV
              de resultados de encuesta ({asig?.respuestas ?? 0} respuestas) · EF2, EF3, EF5 desde
              la evidencia documental.
            </p>
          </div>
        </div>

        {/* Export button */}
        <div
          className="flex justify-end px-4 py-2.5 flex-shrink-0"
          style={{ borderTop: '1px solid rgba(27,58,107,0.07)' }}
        >
          <button
            onClick={handleExportarPDF}
            disabled={exportando || !asig}
            className="flex items-center gap-1.5 px-4 py-2 rounded-xl font-bold transition-all hover:opacity-90 active:scale-95 disabled:opacity-60"
            style={{ background: '#1B3A6B', color: '#fff', fontSize: 12 }}
          >
            {exportando ? (
              <>
                <Loader2 size={12} className="animate-spin" /> Generando…
              </>
            ) : (
              <>
                <Download size={12} /> Exportar PDF
              </>
            )}
          </button>
        </div>
      </div>
    </div>
  );
}
