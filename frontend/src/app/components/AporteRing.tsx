export type EFCat =
  | "Satisfactorio"
  | "Cuasi"
  | "Poco"
  | "Deficiente";

interface AporteRingProps {
  aporte: number;
  weight: number;
  cat: EFCat;
}

const I3_PERF: Record<EFCat, number> = {
  Satisfactorio: 1,
  Cuasi: 0.75,
  Poco: 0.5,
  Deficiente: 0.25,
};

export default function AporteRing({
  aporte,
  weight,
  cat,
}: AporteRingProps) {
  const placebo = weight === 0;
  const fillRatio = placebo ? I3_PERF[cat] : aporte / weight;

  const r = 44;
  const sw = 9;
  const size = (r + sw) * 2;
  const circumference = 2 * Math.PI * r;
  const progress = circumference * fillRatio;

  const color =
    cat === "Satisfactorio"
      ? "#16A34A"
      : cat === "Cuasi"
        ? "#CA8A04"
        : cat === "Poco"
          ? "#EA580C"
          : "#DC2626";

  return (
    <div
      className="relative flex-shrink-0"
      style={{ width: size, height: size }}
    >
      <svg
        width={size}
        height={size}
        viewBox={`0 0 ${size} ${size}`}
        className="rotate-[-90deg]"
      >
        <circle
          cx={size / 2}
          cy={size / 2}
          r={r}
          fill="none"
          stroke="#E5E7EB"
          strokeWidth={sw}
        />

        <circle
          cx={size / 2}
          cy={size / 2}
          r={r}
          fill="none"
          stroke={color}
          strokeWidth={sw}
          strokeDasharray={`${progress} ${circumference}`}
          strokeLinecap="round"
          style={{
            transition: "stroke-dasharray 0.6s ease",
          }}
        />
      </svg>

      <div className="absolute inset-0 flex items-center justify-center flex-col gap-0.5">
        <span
          className="font-bold leading-none"
          style={{
            color,
            fontFamily: "'DM Mono',monospace",
            fontSize: placebo ? 11 : 20,
          }}
        >
          {placebo ? cat.toUpperCase() : aporte.toFixed(2)}
        </span>

        {placebo && (
          <span
            style={{
              color: "#9CA3AF",
              fontSize: 9,
              fontFamily: "'DM Mono',monospace",
            }}
          >
            verificación
          </span>
        )}
      </div>
    </div>
  );
}