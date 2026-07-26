import { AlertCircle, Loader2 } from 'lucide-react';

import type { Career, IndicatorDef } from '../../../types/index';
import { I3_EF_ORDEN } from '../constants/tutoriasLabels';
import { useResultadosI3 } from '../hooks/useResultadosI3';
import RingCard from '../components/RingCard';

// ── Tab Resultados (I3 – Tutorías Académicas) ──────────────────────────────
// Igual patrón que TabResultsI2: solo lectura, trae de la API real el
// resultado ya calculado por api/tutorias_academicas/_calculo.php (pesos,
// % por EF, escala) -- no se recalcula nada en el frontend.
export default function TabResultsI3({
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
    materia,
    selectedIdx,
    setSelectedIdx,
    paoScore,
    paoSt,
    materiaScore,
    materiaCompleta,
  } = useResultadosI3({ career, cohort, pao, onAsignaturaChange });

  if (cargando) {
    return (
      <div className="h-full flex items-center justify-center gap-2" style={{ color: '#5A7295' }}>
        <Loader2 size={16} className="animate-spin" /> Cargando resultados reales…
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
    <div className="h-full flex px-6 py-4 gap-4 overflow-hidden">
      {/* ── LEFT: materia list ───────────────────────────────────────── */}
      <div className="flex-1 flex flex-col gap-3 min-h-0 min-w-0">
        {/* Career/Cohort/PAO card with Valoración General (agregado del PAO) */}
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
                color: paoScore === null ? '#9CA3AF' : paoSt.color,
                fontFamily: "'DM Mono',monospace",
              }}
            >
              {paoScore === null ? '—' : `${Math.round(paoScore)}%`}
            </p>
            {paoScore !== null && (
              <p
                className="text-xs mt-0.5 font-semibold px-2 py-0.5 rounded-full inline-block"
                style={{ background: paoSt.bg, color: paoSt.color }}
              >
                {paoSt.label}
              </p>
            )}
          </div>
        </div>

        {/* Subject list — datos reales de resultado_cohorte.php */}
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
            {detalle.map((m, mi) => {
              const active = selectedIdx === mi;
              const score = m.valoracion_general;
              const dotColor =
                score === null
                  ? '#CA8A04'
                  : m.estado_general === 'completo'
                    ? '#16A34A'
                    : '#CA8A04';
              return (
                <button
                  key={m.id_asignatura}
                  onClick={() => setSelectedIdx(mi)}
                  className="flex-shrink-0 w-full text-left px-3 flex items-center justify-between gap-2 transition-colors hover:bg-blue-50"
                  style={{
                    height: 38,
                    borderBottom: '1px solid rgba(27,58,107,0.05)',
                    background: active ? '#EEF5FF' : 'transparent',
                    borderLeft: `3px solid ${active ? '#0891B2' : 'transparent'}`,
                  }}
                >
                  <div className="flex items-center gap-1.5 min-w-0">
                    <div
                      className="w-1.5 h-1.5 rounded-full flex-shrink-0"
                      style={{ background: dotColor }}
                    />
                    <p
                      className="text-xs font-semibold truncate"
                      style={{ color: active ? '#0891B2' : '#0F1E3C' }}
                    >
                      {m.nombre_asignatura}
                    </p>
                  </div>
                  {score !== null ? (
                    <span
                      className="text-xs font-bold flex-shrink-0"
                      style={{ color: '#16A34A', fontFamily: "'DM Mono',monospace" }}
                    >
                      {Math.round(score)}%
                    </span>
                  ) : (
                    <span
                      className="text-xs font-semibold px-1.5 py-0.5 rounded-full flex-shrink-0"
                      style={{ background: '#FEF9C3', color: '#CA8A04' }}
                    >
                      Falta de evidencia
                    </span>
                  )}
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

      {/* ── RIGHT: materia name + 2×2 ring grid ──────────────────────── */}
      <div className="flex-1 flex flex-col gap-3 min-h-0 min-w-0">
        {/* Materia name card */}
        <div
          className="flex-shrink-0 bg-white rounded-2xl px-4 py-3 flex items-center justify-between gap-3"
          style={{ border: '1px solid rgba(27,58,107,0.08)' }}
        >
          <p
            className="text-sm font-bold"
            style={{ fontFamily: "'Libre Baskerville',serif", color: '#0F1E3C' }}
          >
            {materia?.nombre_asignatura ?? '—'}
          </p>
          {materiaScore !== null ? (
            <div className="flex items-center gap-2 flex-shrink-0">
              <p
                className="text-xl font-bold leading-none"
                style={{ color: '#16A34A', fontFamily: "'DM Mono',monospace" }}
              >
                {Math.round(materiaScore)}%
              </p>
              <p
                className="text-xs font-semibold px-2 py-0.5 rounded-full"
                style={
                  materiaCompleta
                    ? { background: '#DCFCE7', color: '#16A34A' }
                    : { background: '#FEF9C3', color: '#CA8A04' }
                }
              >
                {materiaCompleta ? 'Completo' : 'Incompleto'}
              </p>
            </div>
          ) : (
            <div className="flex items-center gap-2 flex-shrink-0">
              <p
                className="text-xl font-bold leading-none"
                style={{ color: '#CA8A04', fontFamily: "'DM Mono',monospace" }}
              >
                —
              </p>
              <p
                className="text-xs font-semibold px-2 py-0.5 rounded-full"
                style={{ background: '#FEF9C3', color: '#CA8A04' }}
              >
                Incompleto
              </p>
            </div>
          )}
        </div>
        {/* Ring grid */}
        <div
          className="flex-1 grid grid-cols-2 gap-3 min-h-0"
          style={{ gridTemplateRows: '1fr 1fr' }}
        >
          {I3_EF_ORDEN.map((efKey) => (
            <RingCard key={efKey} efKey={efKey} materia={materia} />
          ))}
        </div>
      </div>
    </div>
  );
}
