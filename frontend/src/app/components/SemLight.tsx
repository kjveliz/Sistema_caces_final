interface SemLightProps {
  pct: number;
  dot?: number;
}

function getStatusColor(pct: number): string {
  if (pct >= 75) return "#16A34A";
  if (pct >= 50) return "#CA8A04";
  if (pct >= 25) return "#EA580C";
  if (pct > 0) return "#DC2626";
  return "#9CA3AF";
}

export default function SemLight({
  pct,
  dot = 11,
}: SemLightProps) {
  const active = getStatusColor(pct);

  const colors = [
    "#DC2626",
    "#EA580C",
    "#CA8A04",
    "#16A34A",
  ] as const;

  return (
    <div className="flex items-center gap-1">
      {colors.map((color) => (
        <div
          key={color}
          className="rounded-full transition-all duration-300"
          style={{
            width: dot,
            height: dot,
            backgroundColor:
              color === active ? color : "#E5E7EB",
            boxShadow:
              color === active
                ? `0 0 5px ${color}`
                : "none",
          }}
        />
      ))}
    </div>
  );
}