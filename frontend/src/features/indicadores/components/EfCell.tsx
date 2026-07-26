import type { EF_META } from '../constants/efMetaI2';

type EfScore = (typeof EF_META)[number] & { pct: number | null };

// EF cells – label as title, id as subtitle, percentage only
export default function EfCell({ ef }: { ef: EfScore }) {
  const pct = ef.pct;
  const bc = pct === null ? '#9CA3AF' : pct >= 75 ? '#16A34A' : pct >= 50 ? '#CA8A04' : '#DC2626';
  return (
    <div
      className="rounded-xl p-2.5"
      style={{ background: '#F8FAFD', border: '1px solid rgba(27,58,107,0.07)' }}
    >
      <div className="flex items-center justify-between mb-1">
        <div>
          <p className="font-semibold leading-tight" style={{ color: '#374151', fontSize: 10 }}>
            {ef.label}
          </p>
          <p style={{ color: ef.color, fontFamily: "'DM Mono',monospace", fontSize: 9 }}>
            {ef.id}
          </p>
        </div>
        <span
          className="font-bold"
          style={{ color: bc, fontFamily: "'DM Mono',monospace", fontSize: 13 }}
        >
          {pct === null ? '—' : `${pct}%`}
        </span>
      </div>
      <div className="h-1 rounded-full overflow-hidden" style={{ background: '#E5E7EB' }}>
        <div
          className="h-full rounded-full transition-all duration-500"
          style={{ width: `${pct ?? 0}%`, background: bc }}
        />
      </div>
    </div>
  );
}
