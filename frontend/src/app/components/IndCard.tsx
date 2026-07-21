import Ring from "./Ring";
import SemLight from "./SemLight";

import { calcRate, getStatus } from "../../utils/evaluation";

import type { IndicatorDef } from "../../types";

interface IndCardProps {
  ind: IndicatorDef;
  onClick: () => void;
}

export default function IndCard({
  ind,
  onClick,
}: IndCardProps) {
  const pct = calcRate(ind.cohorts);
  const status = getStatus(pct);

  return (
    <button
      type="button"
      onClick={onClick}
      className="relative flex-1 h-full bg-white rounded-2xl flex flex-col items-center justify-center gap-2.5 p-4 text-center group transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl overflow-hidden"
      style={{
        border: `2px solid ${status.border}`,
        boxShadow: "0 2px 10px rgba(0,0,0,0.06)",
        minWidth: 0,
      }}
    >
      <div
        className="absolute top-0 left-0 right-0 h-1.5 rounded-t-2xl"
        style={{
          background: status.color,
        }}
      />

      <Ring
        pct={pct}
        r={38}
        sw={8}
      />

      <div className="px-2">
        <span
          className="block text-xs font-bold tracking-widest uppercase mb-0.5"
          style={{
            fontFamily: "'DM Mono',monospace",
            color: status.color,
          }}
        >
          {ind.code}
        </span>

        <h3
          className="text-sm font-semibold leading-snug"
          style={{
            fontFamily: "'Libre Baskerville',serif",
            color: "#0F1E3C",
          }}
        >
          {ind.name}
        </h3>
      </div>

      <div className="flex flex-col items-center gap-1">
        <SemLight
          pct={pct}
          dot={10}
        />

        <span
          className="text-xs px-2 py-0.5 rounded-full font-semibold"
          style={{
            background: status.bg,
            color: status.color,
          }}
        >
          {status.label}
        </span>
      </div>
    </button>
  );
}