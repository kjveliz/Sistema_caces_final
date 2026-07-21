import {
  ArrowLeft,
  LogOut,
  ShieldCheck,
  User,
} from "lucide-react";

import type { Career } from "../types";

interface CriteriaViewProps {
  career: Career;
  onSelectDocencia: () => void;
  onBack: () => void;
  onLogout: () => void;
}

const CRITERIA_ITEMS = [
  {
    name: "Condiciones Institucionales",
    image:
      "https://images.unsplash.com/photo-1497366216548-37526070297c?w=800&h=200&fit=crop",
    clickable: false,
  },
  {
    name: "Docencia",
    image:
      "https://images.unsplash.com/photo-1509062522246-3755977927d7?w=800&h=200&fit=crop",
    clickable: true,
  },
  {
    name:
      "Condiciones del Personal Académico, Apoyo Académico y Estudiantes",
    image:
      "https://images.unsplash.com/photo-1552664730-d307ca884978?w=800&h=200&fit=crop",
    clickable: false,
  },
  {
    name: "Investigación e Innovación",
    image:
      "https://images.unsplash.com/photo-1507413245164-6160d8298b31?w=800&h=200&fit=crop&auto=format",
    clickable: false,
  },
  {
    name: "Vinculación con la Sociedad",
    image:
      "https://images.unsplash.com/photo-1531206715517-5c0ba140b2b8?w=800&h=200&fit=crop",
    clickable: false,
  },
  {
    name: "Sistema de Gestión de Calidad",
    image:
      "https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=800&h=200&fit=crop",
    clickable: false,
  },
];

export default function CriteriaView({
  career,
  onSelectDocencia,
  onBack,
  onLogout,
}: CriteriaViewProps) {
  return (
    <div
      className="h-screen flex flex-col overflow-hidden"
      style={{
        background: "#F1F5F9",
        fontFamily: "'Plus Jakarta Sans',sans-serif",
      }}
    >
      <div
        className="flex-shrink-0 border-b flex items-center justify-between px-6"
        style={{
          height: 52,
          background: "#fff",
          borderColor: "rgba(27,58,107,0.1)",
        }}
      >
        <div className="flex items-center gap-3">
          <div
            className="w-7 h-7 rounded-lg flex items-center justify-center"
            style={{
              background: "#1B3A6B",
            }}
          >
            <ShieldCheck
              size={13}
              className="text-white"
            />
          </div>

          <span
            className="font-bold text-sm"
            style={{
              color: "#0F1E3C",
            }}
          >
            CACES · UAFTT
          </span>

          <span
            className="hidden sm:inline text-xs"
            style={{
              color: "#9CA3AF",
            }}
          >
            — Sistema de Evaluación Institucional
          </span>
        </div>

        <div className="flex items-center gap-4">
          <div
            className="hidden sm:flex items-center gap-1.5 text-xs"
            style={{
              color: "#5A7295",
            }}
          >
            <User size={12} />
            <span>Docente Evaluador</span>
          </div>

          <button
            type="button"
            onClick={onLogout}
            className="flex items-center gap-1 text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-red-50 hover:text-red-600 transition-colors"
            style={{
              color: "#5A7295",
            }}
          >
            <LogOut size={12} />
            Salir
          </button>
        </div>
      </div>

      <div className="flex-shrink-0 px-6 pt-3 pb-2 flex items-center gap-3">
        <button
          type="button"
          onClick={onBack}
          className="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-blue-50 transition-colors"
          style={{
            color: "#1B3A6B",
          }}
        >
          <ArrowLeft size={13} />
          Volver a carreras
        </button>

        <div
          className="h-4 w-px"
          style={{
            background: "rgba(27,58,107,0.15)",
          }}
        />

        <div>
          <span
            className="text-sm font-bold"
            style={{
              fontFamily: "'Libre Baskerville',serif",
              color: "#0F1E3C",
            }}
          >
            {career.name}
          </span>

          <span
            className="text-xs ml-2"
            style={{
              color: "#9CA3AF",
            }}
          >
            Seleccione un criterio
          </span>
        </div>
      </div>

      <div
        className="flex-1 min-h-0 px-8 pb-6 grid grid-cols-3 gap-6 overflow-hidden"
        style={{
          gridTemplateRows: "1fr 1fr",
        }}
      >
        {CRITERIA_ITEMS.map((item) => (
          <div
            key={item.name}
            onClick={() => {
              if (item.clickable) {
                onSelectDocencia();
              }
            }}
            className={`flex flex-col items-center justify-center gap-3 p-4 rounded-2xl transition-all ${
              item.clickable
                ? "cursor-pointer hover:bg-blue-50 group"
                : "cursor-default"
            }`}
          >
            <div
              className="relative flex-shrink-0"
              style={{
                width: 100,
                height: 100,
                borderRadius: "50%",
                overflow: "hidden",
                border: `3px solid ${
                  item.clickable ? "#1B3A6B" : "#CBD5E1"
                }`,
                boxShadow: item.clickable
                  ? "0 4px 16px rgba(27,58,107,0.25)"
                  : "0 1px 6px rgba(0,0,0,0.08)",
              }}
            >
              <img
                src={item.image}
                alt={item.name}
                className="w-full h-full object-cover"
              />

              {item.clickable && (
                <div
                  className="absolute inset-0"
                  style={{
                    background:
                      "linear-gradient(to bottom,transparent 40%,rgba(15,30,60,0.3))",
                  }}
                />
              )}
            </div>

            <p
              className={`text-xs font-semibold text-center leading-snug px-2 transition-colors ${
                item.clickable
                  ? "group-hover:text-blue-700"
                  : ""
              }`}
              style={{
                color: "#0F1E3C",
                fontFamily: "'Plus Jakarta Sans',sans-serif",
              }}
            >
              {item.name}
            </p>

            {item.clickable && (
              <span
                className="text-xs px-3 py-1 rounded-full font-bold"
                style={{
                  background: "#1B3A6B",
                  color: "#fff",
                }}
              >
                Evaluar →
              </span>
            )}
          </div>
        ))}
      </div>
    </div>
  );
}