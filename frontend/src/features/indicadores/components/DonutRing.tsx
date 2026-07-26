// ── SVG donut ring ────────────────────────────────────────────────────────
export default function DonutRing({ pct, color }: { pct: number; color: string }) {
  const r = 34;
  const circ = 2 * Math.PI * r;
  return (
    <svg width={86} height={86} viewBox="0 0 86 86">
      <circle cx={43} cy={43} r={r} fill="none" stroke="#E5E7EB" strokeWidth={7} />
      <circle
        cx={43}
        cy={43}
        r={r}
        fill="none"
        stroke={color}
        strokeWidth={7}
        strokeDasharray={`${(pct / 100) * circ} ${circ}`}
        strokeLinecap="round"
        transform="rotate(-90 43 43)"
      />
    </svg>
  );
}
