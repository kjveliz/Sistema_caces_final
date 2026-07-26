import type { IndicatorDef } from '../../../types/index';
import {
  I2_EF_CRITERIA,
  I2_EF_COLORS,
  I3_EF_CRITERIA,
  EF_COLORS,
} from '../constants/fichaTecnica';

// ── Tab Ficha técnica ──────────────────────────────────────────────────────
export default function TabFicha({ ind }: { ind: IndicatorDef }) {
  const scale = [
    { label: 'Satisfactorio', range: '75 – 100%', color: '#16A34A', bg: '#DCFCE7' },
    { label: 'Cuasi Satisfactorio', range: '50 – 74%', color: '#CA8A04', bg: '#FEF9C3' },
    { label: 'Poco Satisfactorio', range: '25 – 49%', color: '#EA580C', bg: '#FFEDD5' },
    { label: 'Deficiente', range: '0 – 24%', color: '#DC2626', bg: '#FEE2E2' },
  ];

  const tipoMap: Record<string, { label: string; bg: string; color: string }> = {
    I1: { label: 'Cuantitativo', bg: '#DBEAFE', color: '#1D4ED8' },
    I2: { label: 'Cualitativo', bg: '#EDE9FE', color: '#7C3AED' },
    I3: { label: 'Cualitativo', bg: '#EDE9FE', color: '#7C3AED' },
    I4: { label: 'Cuantitativo', bg: '#DBEAFE', color: '#1D4ED8' },
    I5: { label: 'Cuantitativo', bg: '#DBEAFE', color: '#1D4ED8' },
  };
  const tipo = tipoMap[ind.id] || { label: 'Cuantitativo', bg: '#DBEAFE', color: '#1D4ED8' };

  const isI3 = ind.id === 'I3';
  const isI2 = ind.id === 'I2';
  const useDetailedLayout = isI2 || isI3;
  // EF config for the detailed layout
  const efCriteria = isI3 ? I3_EF_CRITERIA : I2_EF_CRITERIA;
  const efColors = isI3 ? EF_COLORS : I2_EF_COLORS;

  return (
    <div className="h-full flex flex-col px-6 py-4 max-w-6xl mx-auto overflow-hidden gap-3">
      {/* ── Banner ───────────────────────────────────────────────────── */}
      <div
        className="flex-shrink-0 rounded-2xl px-6 py-4 flex items-center justify-between gap-6"
        style={{ background: 'linear-gradient(135deg,#0F2556,#1B3A6B)', color: '#fff' }}
      >
        <div>
          <h2 className="text-xl font-bold" style={{ fontFamily: "'Libre Baskerville',serif" }}>
            {ind.name}
          </h2>
          <div
            className="inline-flex items-center gap-2 mt-2 px-3 py-1.5 rounded-xl"
            style={{ background: 'rgba(255,255,255,0.12)' }}
          >
            <span className="text-xs text-blue-300 font-semibold">Fórmula:</span>
            <span className="text-sm font-semibold" style={{ fontFamily: "'DM Mono',monospace" }}>
              {useDetailedLayout
                ? efCriteria
                    .map(
                      (ef, i) =>
                        `${ef.id}(×${ef.weight.toFixed(2)})${i < efCriteria.length - 1 ? ' + ' : ''}`,
                    )
                    .join('') + ` = Valor × 100`
                : ind.formula}
            </span>
          </div>
        </div>
        <span
          className="flex-shrink-0 px-3 py-1.5 rounded-full text-sm font-bold"
          style={{ background: 'rgba(255,255,255,0.15)', color: '#fff' }}
        >
          {tipo.label}
        </span>
      </div>

      {/* ── Content rows ─────────────────────────────────────────────── */}
      {useDetailedLayout ? (
        /* I2/I3: two-box layout */
        <div className="flex gap-3 flex-1 min-h-0 overflow-hidden">
          {/* ── Primer cuadro: Descripción + Para qué sirve ─────────── */}
          <div
            className="flex flex-col bg-white rounded-2xl overflow-hidden min-h-0"
            style={{ width: '38%', flexShrink: 0, border: '1px solid rgba(27,58,107,0.08)' }}
          >
            {/* Descripción */}
            <div className="flex-1 p-5 flex flex-col min-h-0">
              <p
                className="text-xs font-bold uppercase tracking-widest mb-2 flex-shrink-0"
                style={{ color: '#5A7295' }}
              >
                Descripción
              </p>
              <p className="text-sm leading-relaxed overflow-auto" style={{ color: '#1F2937' }}>
                {ind.description}
              </p>
            </div>
            {/* Divider */}
            <div
              className="flex-shrink-0 mx-5"
              style={{ height: 1, background: 'rgba(27,58,107,0.08)' }}
            />
            {/* Para qué sirve */}
            <div className="flex-1 p-5 flex flex-col min-h-0">
              <p
                className="text-xs font-bold uppercase tracking-widest mb-2 flex-shrink-0"
                style={{ color: '#5A7295' }}
              >
                Para qué sirve
              </p>
              <p className="text-sm leading-relaxed overflow-auto" style={{ color: '#1F2937' }}>
                {ind.purpose}
              </p>
            </div>
          </div>

          {/* ── Segundo cuadro: Escala + 4 EF detallados ────────────── */}
          <div
            className="flex-1 flex flex-col gap-0 bg-white rounded-2xl overflow-hidden min-h-0"
            style={{ border: '1px solid rgba(27,58,107,0.08)' }}
          >
            {/* Escala — 4 cuadros del mismo tamaño */}
            <div
              className="flex-shrink-0 px-5 py-3"
              style={{ borderBottom: '1px solid rgba(27,58,107,0.07)', background: '#F8FAFD' }}
            >
              <p
                className="text-xs font-bold uppercase tracking-widest mb-2"
                style={{ color: '#5A7295' }}
              >
                Escala de valoración
              </p>
              <div className="grid grid-cols-4 gap-2">
                {scale.map((sc) => (
                  <div
                    key={sc.label}
                    className="flex flex-col items-center justify-center gap-1 rounded-xl py-3 px-2 text-center"
                    style={{ background: sc.bg, border: `2px solid ${sc.color}40` }}
                  >
                    <div
                      className="w-3 h-3 rounded-full flex-shrink-0"
                      style={{ background: sc.color }}
                    />
                    <span className="text-xs font-bold leading-tight" style={{ color: sc.color }}>
                      {sc.label}
                    </span>
                    <span
                      className="text-xs font-mono font-bold"
                      style={{ color: sc.color, opacity: 0.8 }}
                    >
                      {sc.range}
                    </span>
                  </div>
                ))}
              </div>
            </div>

            {/* EF cards — apilados verticalmente, cada uno con checks y regla */}
            <div className="flex-1 overflow-auto px-4 py-3 flex flex-col gap-2.5">
              {efCriteria.map((ef, i) => (
                <div
                  key={ef.id}
                  className="rounded-xl overflow-hidden flex-shrink-0"
                  style={{ border: `1.5px solid ${efColors[i]}30` }}
                >
                  {/* EF header row */}
                  <div
                    className="flex items-center gap-2.5 px-4 py-2.5"
                    style={{ background: `${efColors[i]}10` }}
                  >
                    <span
                      className="text-xs font-bold px-2 py-0.5 rounded-lg flex-shrink-0"
                      style={{
                        background: efColors[i],
                        color: '#fff',
                        fontFamily: "'DM Mono',monospace",
                      }}
                    >
                      {ef.id}
                    </span>
                    <span className="text-sm font-bold flex-1" style={{ color: efColors[i] }}>
                      {ef.label}
                    </span>
                    <span
                      className="text-xs font-bold px-2 py-0.5 rounded-md flex-shrink-0"
                      style={{
                        background: `${efColors[i]}20`,
                        color: efColors[i],
                        fontFamily: "'DM Mono',monospace",
                      }}
                    >
                      Peso {(ef.weight * 100).toFixed(0)}%
                    </span>
                  </div>

                  {/* Body */}
                  <div className="px-4 py-3 flex gap-4" style={{ background: '#FAFBFD' }}>
                    {/* Left: descripción + regla de calificación */}
                    <div className="flex-1 min-w-0 flex flex-col gap-2">
                      <p className="text-xs leading-snug" style={{ color: '#374151' }}>
                        {ef.desc}
                      </p>
                      <div
                        className="flex items-start gap-1.5 mt-0.5 px-2.5 py-1.5 rounded-lg"
                        style={{ background: '#DCFCE7', border: '1px solid #16A34A30' }}
                      >
                        <span
                          className="text-xs font-bold flex-shrink-0"
                          style={{ color: '#16A34A' }}
                        >
                          ✓ Califica si:
                        </span>
                        <span className="text-xs leading-snug" style={{ color: '#15803D' }}>
                          {ef.rule}
                        </span>
                      </div>
                    </div>
                    {/* Right: checklist */}
                    <div className="flex-shrink-0 flex flex-col gap-1" style={{ minWidth: 200 }}>
                      <p
                        className="text-xs font-bold uppercase tracking-widest mb-0.5"
                        style={{ color: '#9CA3AF' }}
                      >
                        Qué se verifica
                      </p>
                      {ef.checks.map((c, ci) => (
                        <div key={ci} className="flex items-start gap-1.5">
                          <div
                            className="w-1 h-1 rounded-full flex-shrink-0 mt-1.5"
                            style={{ background: efColors[i] }}
                          />
                          <span className="text-xs leading-snug" style={{ color: '#4B5563' }}>
                            {c}
                          </span>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      ) : (
        /* I1/I4/I5: original 3-column layout */
        <div className="flex gap-3 flex-1 min-h-0 overflow-hidden">
          <div
            className="flex-1 bg-white rounded-2xl p-5 overflow-hidden"
            style={{ border: '1px solid rgba(27,58,107,0.08)' }}
          >
            <p
              className="text-xs font-bold uppercase tracking-widest mb-2"
              style={{ color: '#5A7295' }}
            >
              Descripción
            </p>
            <p className="text-sm leading-relaxed" style={{ color: '#1F2937' }}>
              {ind.description}
            </p>
          </div>
          <div
            className="flex-1 bg-white rounded-2xl p-5 overflow-hidden"
            style={{ border: '1px solid rgba(27,58,107,0.08)' }}
          >
            <p
              className="text-xs font-bold uppercase tracking-widest mb-2"
              style={{ color: '#5A7295' }}
            >
              Para qué sirve
            </p>
            <p className="text-sm leading-relaxed" style={{ color: '#1F2937' }}>
              {ind.purpose}
            </p>
          </div>
          <div className="flex-1 flex flex-col gap-3 min-h-0">
            <div
              className="flex-shrink-0 bg-white rounded-2xl px-4 py-3"
              style={{ border: '1px solid rgba(27,58,107,0.08)' }}
            >
              <p
                className="text-xs font-bold uppercase tracking-widest mb-2"
                style={{ color: '#5A7295' }}
              >
                Tipo de indicador
              </p>
              <span
                className="inline-block px-3 py-1 rounded-full text-sm font-bold"
                style={{ background: tipo.bg, color: tipo.color }}
              >
                {tipo.label}
              </span>
            </div>
            <div
              className="flex-1 bg-white rounded-2xl px-4 py-3 flex flex-col min-h-0"
              style={{ border: '1px solid rgba(27,58,107,0.08)' }}
            >
              <p
                className="text-xs font-bold uppercase tracking-widest mb-2 flex-shrink-0"
                style={{ color: '#5A7295' }}
              >
                Escala de calificación
              </p>
              <div className="flex flex-col flex-1 gap-1.5 justify-around">
                {scale.map((sc) => (
                  <div
                    key={sc.label}
                    className="rounded-xl px-3 py-2 flex items-center gap-2.5"
                    style={{ background: sc.bg, border: `1.5px solid ${sc.color}30` }}
                  >
                    <div
                      className="w-2.5 h-2.5 rounded-full flex-shrink-0"
                      style={{ background: sc.color }}
                    />
                    <div className="flex items-center justify-between flex-1">
                      <p className="text-xs font-bold" style={{ color: sc.color }}>
                        {sc.label}
                      </p>
                      <p className="text-xs font-mono" style={{ color: sc.color, opacity: 0.75 }}>
                        {sc.range}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ── Metadata strip ───────────────────────────────────────────── */}
      <div
        className="bg-white rounded-2xl px-5 py-3 flex-shrink-0 flex items-center gap-5 flex-wrap"
        style={{ border: '1px solid rgba(27,58,107,0.08)' }}
      >
        {[
          { label: 'Período', value: ind.period },
          { label: 'Fuentes', value: `${ind.slots.length} documentos PDF` },
          { label: 'Indicador', value: ind.code },
        ].map((m) => (
          <div key={m.label} className="flex items-center gap-2">
            <span className="text-xs font-bold" style={{ color: '#5A7295' }}>
              {m.label}:
            </span>
            <span
              className="text-xs px-2 py-0.5 rounded-md font-semibold"
              style={{ background: '#EEF2F7', color: '#1B3A6B' }}
            >
              {m.value}
            </span>
          </div>
        ))}
      </div>
    </div>
  );
}
