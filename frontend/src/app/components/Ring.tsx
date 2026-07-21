interface RingProps {
  pct: number;
  r?: number;
  sw?: number;
}

function getStatus(pct: number) {
  if (pct >= 75) {
    return {
      label: "Satisfactorio",
      color: "#16A34A",
      bg: "#DCFCE7",
      border: "#16A34A",
    };
  }

  if (pct >= 50) {
    return {
      label: "Cuasi Satisfactorio",
      color: "#CA8A04",
      bg: "#FEF9C3",
      border: "#CA8A04",
    };
  }

  if (pct >= 25) {
    return {
      label: "Poco Satisfactorio",
      color: "#EA580C",
      bg: "#FFEDD5",
      border: "#EA580C",
    };
  }

  if (pct > 0) {
    return {
      label: "Deficiente",
      color: "#DC2626",
      bg: "#FEE2E2",
      border: "#DC2626",
    };
  }

  return {
    label: "Sin datos",
    color: "#9CA3AF",
    bg: "#F3F4F6",
    border: "#E5E7EB",
  };
}

export default function Ring({
  pct,
  r = 36,
  sw = 7,
}: RingProps) {
  const status = getStatus(pct);
  const size = (r + sw) * 2;
  const circumference = 2 * Math.PI * r;
  const progress = circumference * (pct / 100);

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
          stroke={status.color}
          strokeWidth={sw}
          strokeDasharray={`${progress} ${circumference}`}
          strokeLinecap="round"
          style={{
            transition: "stroke-dasharray 0.6s ease",
          }}
        />
      </svg>

      <div className="absolute inset-0 flex items-center justify-center">
        {pct > 0 ? (
          <span
            className="font-bold leading-none"
            style={{
              color: status.color,
              fontFamily: "'DM Mono',monospace",
              fontSize: r * 0.45,
            }}
          >
            {pct}%
          </span>
        ) : (
          <span
            style={{
              color: "#CBD5E1",
              fontSize: r * 0.3,
            }}
          >
            —
          </span>
        )}
      </div>
    </div>
  );
}