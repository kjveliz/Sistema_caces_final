import { ArrowLeft } from "lucide-react";

interface EvidenceHeaderProps {
  title: string;
  subtitle?: string;
  backLabel: string;
  onBackClick: () => void;
}

export default function EvidenceHeader({
  title,
  subtitle,
  backLabel,
  onBackClick,
}: EvidenceHeaderProps) {
  return (
    <div
      className="flex-shrink-0 border-b"
      style={{
        background: "#fff",
        borderColor: "rgba(27,58,107,0.1)",
      }}
    >
      <div className="max-w-3xl mx-auto px-6 h-14 flex items-center gap-3">
        <button
          type="button"
          onClick={onBackClick}
          className="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-blue-50 transition-colors"
          style={{ color: "#1B3A6B" }}
        >
          <ArrowLeft size={13} />
          {backLabel}
        </button>

        <div
          className="h-4 w-px"
          style={{ background: "rgba(27,58,107,0.15)" }}
        />

        <div>
          <span
            className="text-sm font-bold"
            style={{
              fontFamily: "'Libre Baskerville',serif",
              color: "#0F1E3C",
            }}
          >
            {title}
          </span>

          {subtitle && (
            <span
              className="text-xs ml-2"
              style={{ color: "#5A7295" }}
            >
              {subtitle}
            </span>
          )}
        </div>
      </div>
    </div>
  );
}