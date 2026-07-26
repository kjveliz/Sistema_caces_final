import type { EfTutorias, ResultadoAsignaturaTutorias } from '../../../shared/services/tutoriasAcademicas';
import { I3_PUNTO_LABELS } from '../constants/tutoriasLabels';
import DonutRing from './DonutRing';

// ── EF ring card ──────────────────────────────────────────────────────────
export default function RingCard({
  efKey,
  materia,
}: {
  efKey: EfTutorias;
  materia: ResultadoAsignaturaTutorias | undefined;
}) {
  const ef = materia?.efs?.[efKey];
  const sinDatos = !ef || ef.estado === 'sin_datos' || ef.pct === null;
  const pctEf = sinDatos ? 0 : (ef!.pct as number);
  // Aporte real al resultado general = % del EF × peso del EF.
  const contribPct = ef ? Math.round(ef.peso * pctEf) : 0;
  const detalle = ef?.detalle_puntos ?? [];

  // Color por escala (mismos cortes CACES ≥75/≥50/≥25 que I2).
  const color = sinDatos
    ? '#9CA3AF'
    : pctEf >= 75
      ? '#16A34A'
      : pctEf >= 50
        ? '#CA8A04'
        : pctEf >= 25
          ? '#F97316'
          : '#EF4444';

  return (
    <div
      className="bg-white rounded-2xl flex flex-col items-center justify-center gap-1.5 py-4"
      style={{ border: '1px solid rgba(27,58,107,0.08)' }}
      title={
        detalle.length
          ? detalle
              .map((p) => `${p.cumplido ? '✓' : '✗'} ${I3_PUNTO_LABELS[p.nombre] ?? p.nombre}`)
              .join('\n')
          : undefined
      }
    >
      <p
        className="text-xs font-semibold text-center leading-tight px-3"
        style={{ color: '#5A7295' }}
      >
        {ef?.label ?? efKey}
      </p>
      <div className="relative flex items-center justify-center" style={{ width: 86, height: 86 }}>
        <DonutRing pct={sinDatos ? 0 : pctEf} color={sinDatos ? '#E5E7EB' : color} />
        <span
          className="absolute font-bold text-center leading-none"
          style={{ color, fontFamily: "'DM Mono',monospace", fontSize: sinDatos ? 9 : 14 }}
        >
          {sinDatos ? 'Sin\ndatos' : `${pctEf}%`}
        </span>
      </div>
      <span
        className="text-xs font-semibold px-2 py-0.5 rounded-full"
        style={
          sinDatos
            ? { background: '#F3F4F6', color: '#9CA3AF' }
            : { background: '#EEF2F7', color: '#1B3A6B' }
        }
      >
        {sinDatos || !ef ? 'Sin datos' : `${ef.cumplidos}/${ef.total_puntos} puntos · aporta ${contribPct}%`}
      </span>
    </div>
  );
}
