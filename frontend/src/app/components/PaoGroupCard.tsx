import Ring from "./Ring";
import SemLight from "./SemLight";

import { getStatus } from "../../utils/evaluation";
import { PAO_SCORES } from "../../data/evaluation";

import type { IndicatorDef } from "../../types";

interface PaoGroupCardProps {
  ind: IndicatorDef;
  onClick: (id: string, pao?: number) => void;
  fullHeight?: boolean;
  paosOverride?: { pao: string; pct: number }[];
}

export default function PaoGroupCard({
  ind,
  onClick,
  fullHeight = false,
  paosOverride,
}: PaoGroupCardProps) {
  const paos =
  // Si hay paosOverride, se usa eso (I2 desde DashboardView)
  paosOverride
    ? paosOverride
  : ind.id === "I1"
    ? [
        { pao: "PAO 1", pct: -1 },
        { pao: "PAO 2", pct: -1 },
        { pao: "PAO 3", pct: -1 },
      ]
    : PAO_SCORES[ind.id] || [];

  const accent =
    ind.id === "I1"
      ? "#1B3A6B"
      : ind.id === "I2"
        ? "#7C3AED"
        : "#0891B2";

  return (
    <div
      className={`${fullHeight ? "h-full" : ""} bg-white rounded-2xl flex flex-col overflow-hidden`}
      style={{
        flex: 1,
        border: `2px solid ${accent}22`,
        boxShadow: "0 2px 10px rgba(0,0,0,0.06)",
        minWidth: 0,
      }}
    >
      <div
        className="h-1.5 flex-shrink-0 rounded-t-2xl"
        style={{
          background: accent,
        }}
      />

      <div
        className="flex-shrink-0 px-4 pt-2.5 pb-2 flex items-center justify-between"
        style={{
          borderBottom: "1px solid rgba(27,58,107,0.07)",
        }}
      >
        <div className="text-left">
          <span
            className="block text-xs font-bold tracking-widest uppercase"
            style={{
              fontFamily: "'DM Mono',monospace",
              color: accent,
            }}
          >
            {ind.code}
          </span>

          <h3
            className="text-sm font-semibold leading-snug mt-0.5"
            style={{
              fontFamily: "'Libre Baskerville',serif",
              color: "#0F1E3C",
            }}
          >
            {ind.name}
          </h3>
        </div>
      </div>

      <div className="flex flex-1 min-h-0">
        {paos.map((pao, index) => {
          const sinDatos = pao.pct < 0;

          const status = sinDatos
            ? {
                label: "Sin datos",
                color: "#6B7280",
                bg: "#F3F4F6",
              }
            : getStatus(pao.pct);

          return (
            <button
              key={pao.pao}
              type="button"
              onClick={() => onClick(ind.id, index + 1)}
              className="flex-1 flex flex-col items-center justify-center gap-1.5 px-2 py-3 transition-all hover:bg-blue-50 active:scale-[0.97] group"
              style={{
                borderRight:
                  index < paos.length - 1
                    ? "1px solid rgba(27,58,107,0.07)"
                    : "none",
                background:
                  index % 2 === 0
                    ? "rgba(27,58,107,0.015)"
                    : "transparent",
              }}
            >
              <span
                className="text-xs font-bold uppercase tracking-widest"
                style={{
                  color: "#5A7295",
                }}
              >
                {pao.pao}
              </span>

              {!sinDatos ? (
                <>
                  <Ring
                    pct={pao.pct}
                    r={24}
                    sw={5}
                  />

                  <SemLight
                    pct={pao.pct}
                    dot={7}
                  />
                </>
              ) : (
                <div
                  className="w-14 h-14 rounded-full flex items-center justify-center text-[11px] font-semibold"
                  style={{
                    background: "#F3F4F6",
                    color: "#6B7280",
                  }}
                >
                  —
                </div>
              )}

              <span
                className="text-xs font-semibold px-2 py-0.5 rounded-full text-center leading-tight"
                style={{
                  background: status.bg,
                  color: status.color,
                  maxWidth: 90,
                }}
              >
                {status.label}
              </span>
            </button>
          );
        })}
      </div>
    </div>
  );
}