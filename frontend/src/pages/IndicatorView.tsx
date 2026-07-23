import { useEffect, useState } from "react";

import {
  obtenerDatosTasa,
  obtenerDatosDesercion,
  obtenerEvaluacion,
  obtenerEvidenciasCompartidas,
  obtenerEvidenciasGuardadas,
  type CohorteTitulacion,
  type CohorteDesercion,
} from "../services/evidencias";
import {
  AlertCircle,
  ArrowLeft,
  BarChart2,
  BookOpen,
  Download,
  ExternalLink,
  FolderOpen,
  TableProperties,
  CheckCircle2,
  Upload,
  Loader2,
} from "lucide-react";

import { toast } from "sonner";

import {
  PolarAngleAxis,
  PolarGrid,
  Radar,
  RadarChart,
  ResponsiveContainer,
} from "recharts";

import AporteRing from "../app/components/AporteRing";

import {
  calcRate,
  getStatus,
} from "../utils/evaluation";

import {
  obtenerPeriodos,
  obtenerResultadoCohorte,
  obtenerEncuestaDetalle,
  obtenerEvidenciaAsignatura,
  type ResultadoAsignatura,
  type ResultadoCohorte,
  type EvidenciaAsignaturaItem,
} from "../services/seguimientoSyllabus";

import {
  obtenerResultadoCohorteTutorias,
  obtenerEvidenciaTutorias,
  type ResultadoCohorteTutorias,
  type ResultadoAsignaturaTutorias,
  type EfTutorias,
  type EvidenciaTutoriasItem,
} from "../services/tutoriasAcademicas";

import { exportarPdfIndicador2 } from "../lib/exportarPdfIndicador2";

import type {
  Career,
  IndicatorDef,
  TabId,
} from "../types";

export default function IndicatorView({ indicator, onBack, career, cohort, pao, onUpload, puedeCargar }: {
  indicator: IndicatorDef;
  onBack: () => void;
  career: Career | null;
  cohort: string;
  pao: number;
  onUpload?: () => void;
  puedeCargar: boolean;
}) {
    const isTitDes = indicator.id === "I4" || indicator.id === "I5";
  const isI2 = indicator.id === "I2";
  const isI3 = indicator.id === "I3";
  const defaultTab: TabId = (isI2 || isI3) ? "results" : isTitDes ? "cohorts" : "evidences";
  const [tab, setTab] = useState<TabId>(defaultTab);
  const [idAsignaturaSeleccionada, setIdAsignaturaSeleccionada] = useState<number | null>(null);
  const [nombreAsignaturaSeleccionada, setNombreAsignaturaSeleccionada] = useState<string | null>(null);
  const pct = calcRate(indicator.cohorts);
  const s = getStatus(pct);

  const TABS: { id: TabId; label: string; icon: React.ElementType }[] = [
    ...(isI2 ? [{ id: "results" as TabId, label: "Resultados", icon: BarChart2 }] : []),
    ...(isI3 ? [{ id: "results" as TabId, label: "Materias", icon: BarChart2 }] : []),
    ...(isTitDes ? [{ id: "cohorts" as TabId, label: "Cohortes", icon: TableProperties }] : []),
    { id: "evidences", label: "Evidencias", icon: FolderOpen },
    { id: "ficha", label: "Ficha técnica", icon: BookOpen },
  ];

  return (
    <div className="h-screen flex flex-col overflow-hidden" style={{ background: "#EEF2F7", fontFamily: "'Plus Jakarta Sans',sans-serif" }}>
      <div className="flex-shrink-0 border-b" style={{ background: "#fff", borderColor: "rgba(27,58,107,0.1)" }}>
        <div className="max-w-6xl mx-auto px-6">
          <div className="h-12 flex items-center gap-3">
            <button onClick={onBack} className="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-blue-50 transition-colors" style={{ color: "#1B3A6B" }}>
              <ArrowLeft size={13} /> Docencia
            </button>
            <div className="h-4 w-px" style={{ background: "rgba(27,58,107,0.15)" }} />
            <span className="text-xs font-bold tracking-widest uppercase" style={{ fontFamily: "'DM Mono',monospace", color: s.color }}>{indicator.code}</span>
            <span className="text-sm font-semibold truncate" style={{ color: "#0F1E3C" }}>{indicator.name}</span>
          </div>
          <div className="flex gap-0.5">
            {TABS.map((t) => {
              const Icon = t.icon;
              const active = tab === t.id;
              return (
                <button key={t.id} onClick={() => setTab(t.id)}
                  className="flex items-center gap-1.5 px-4 py-2.5 text-xs font-semibold rounded-t-lg border-b-2 transition-all"
                  style={{ color: active ? "#1B3A6B" : "#5A7295", borderBottomColor: active ? "#1B3A6B" : "transparent", background: active ? "rgba(27,58,107,0.05)" : "transparent" }}>
                  <Icon size={12} />{t.label}
                </button>
              );
            })}
          </div>
        </div>
      </div>


      <div className="flex-1 overflow-hidden min-h-0">
                {tab === "results"   && isI2  && <TabResults    ind={indicator} career={career} cohort={cohort} pao={pao} onAsignaturaChange={(id, nombre) => { setIdAsignaturaSeleccionada(id); setNombreAsignaturaSeleccionada(nombre ?? null); }}/>}
        {tab === "results"   && isI3  && <TabResultsI3  ind={indicator} career={career} cohort={cohort} pao={pao} onAsignaturaChange={(id, nombre) => { setIdAsignaturaSeleccionada(id); setNombreAsignaturaSeleccionada(nombre ?? null); }}/>}
        {tab === "cohorts" && (
          <TabCohorts
            ind={indicator}
            career={career}
            cohort={cohort}
          />
        )}
                {tab === "evidences" && (
          <TabEvidences
            ind={indicator}
            career={career}
            cohort={cohort}
            idAsignatura={idAsignaturaSeleccionada}
            nombreAsignatura={nombreAsignaturaSeleccionada}
            onUpload={onUpload}
            puedeCargar={puedeCargar}
          />
        )}
        {tab === "ficha"     && <TabFicha     ind={indicator} />}
      </div>
    </div>
  );
}

// Metadatos de presentación de cada EF (label/color) -- los VALORES salen
// de la API real (obtenerResultadoCohorte → detalle_asignaturas).
const EF_META = [
  { id: "EF1", key: "ef1" as const, label: "Seguimiento contenidos", color: "#2563EB" },
  { id: "EF2", key: "ef2" as const, label: "Mejora micro currículo", color: "#16A34A" },
  { id: "EF3", key: "ef3" as const, label: "Proceso difundido", color: "#0891B2" },
  { id: "EF4", key: "ef4" as const, label: "Percepción estudiantil", color: "#D97706" },
  { id: "EF5", key: "ef5" as const, label: "Marco normativo", color: "#7C3AED" },
];

// ── Tab Resultados (I2 – Seguimiento de Syllabus) ──────────────────────────
// Solo lectura: la carga/reemplazo de evidencia se hace en la pestaña
// "Evidencias" (mecanismo genérico de slots), no aquí.
function TabResults({ ind, career, cohort, pao, onAsignaturaChange }: { ind: IndicatorDef;career: Career | null; cohort: string; pao: number; onAsignaturaChange?: (idAsignatura: number | null, nombreAsignatura?: string | null) => void }) {
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [resultadoCohorte, setResultadoCohorte] = useState<ResultadoCohorte | null>(null);
  const [selectedAsig, setSelectedAsig] = useState(0);
  const [exportando, setExportando] = useState(false);

  const detalle = resultadoCohorte?.detalle_asignaturas ?? [];
  const asig: ResultadoAsignatura | undefined = detalle[Math.min(selectedAsig, detalle.length - 1)];

  // Notificar al padre cuando cambie la asignatura seleccionada
  useEffect(() => {
    const id = asig?.id_asignatura ?? null;
    onAsignaturaChange?.(id, asig?.nombre_asignatura ?? null);
  }, [selectedAsig, resultadoCohorte]);

  // Resuelve evaluación (id_evaluacion, id_cohorte), el PAO real, y trae
  // de una sola llamada el resultado EF1-EF5 de todas las asignaturas de ese PAO.
  useEffect(() => {
    if (!career) return;
    let cancelado = false;
    setCargando(true);
    setError(null);

    (async () => {
      try {
        const evaluacion = await obtenerEvaluacion(career.code, cohort);
        const periodos = await obtenerPeriodos(evaluacion.id_cohorte);
        const periodo = periodos.find((p) => p.orden === pao);
        if (!periodo) {
          throw new Error(`No existe el PAO ${pao} para esta cohorte.`);
        }
        const rc = await obtenerResultadoCohorte(evaluacion.id_cohorte, evaluacion.id_evaluacion, periodo.id_periodoacademico);
        if (cancelado) return;
        setResultadoCohorte(rc);
        setSelectedAsig(0);
      } catch (e) {
        if (!cancelado) setError(e instanceof Error ? e.message : "No se pudo cargar la información.");
      } finally {
        if (!cancelado) setCargando(false);
      }
    })();

    return () => { cancelado = true; };
  }, [career, cohort, pao]);

  if (cargando) {
    return (
      <div className="h-full flex items-center justify-center gap-2" style={{ color: "#5A7295" }}>
        <Loader2 size={16} className="animate-spin" /> Cargando resultados reales…
      </div>
    );
  }

  if (error) {
    return (
      <div className="h-full flex flex-col items-center justify-center gap-2" style={{ color: "#DC2626" }}>
        <AlertCircle size={20} />
        <p className="text-sm font-medium">{error}</p>
      </div>
    );
  }

    const efScores = EF_META.map((ef) => ({
    ...ef,
    pct: asig?.[ef.key] ?? null,
  }));
  const totalGeneral = resultadoCohorte?.valoracion_general ?? null;
  const totalAsignatura = asig?.valoracion_general ?? null;
  // Nuevo (Pendiente #4, MEMORIA v41/§22.5, cerrado en v47): estado_general ya
  // se calculaba bien en el backend (una vez corregido el Pendiente #4) pero
  // no se mostraba en ningún lado de esta pantalla -- se agrega la etiqueta
  // "Completo"/"Incompleto" junto al % de la materia, igual que ya existe en
  // TabResultsI3 (Tutorías) más abajo en este mismo archivo.
  const materiaCompleta = asig?.estado_general === "completo";
  const radarData = efScores.map((ef) => ({ subject: ef.id, score: ef.pct ?? 0, fullMark: 100 }));

  async function handleExportarPDF() {
    if (!asig || !career) return;
    setExportando(true);
    try {
      const cohorteNormalizada = cohort.replace(/\s+/g, "").toUpperCase();

      const evaluacion = await obtenerEvaluacion(career.code, cohorteNormalizada);
      const encuestaDetalle = await obtenerEncuestaDetalle(asig.id_asignatura, evaluacion.id_evaluacion);

      await exportarPdfIndicador2({
        asignatura: asig,
        cohortLabel: cohort,
        paoNumero: pao,
        encuestaDetalle,
      });
    } catch (e) {
      toast.error(e instanceof Error ? e.message : "No se pudo generar el PDF.");
    } finally {
      setExportando(false);
    }
  }

  // EF cells – label as title, id as subtitle, percentage only
  function EfCell({ ef }: { ef: typeof efScores[0] }) {
    const pct = ef.pct;
    const bc = pct === null ? "#9CA3AF" : pct >= 75 ? "#16A34A" : pct >= 50 ? "#CA8A04" : "#DC2626";
    return (
      <div className="rounded-xl p-2.5" style={{ background: "#F8FAFD", border: "1px solid rgba(27,58,107,0.07)" }}>
        <div className="flex items-center justify-between mb-1">
          <div>
            <p className="font-semibold leading-tight" style={{ color: "#374151", fontSize: 10 }}>{ef.label}</p>
            <p style={{ color: ef.color, fontFamily: "'DM Mono',monospace", fontSize: 9 }}>{ef.id}</p>
          </div>
          <span className="font-bold" style={{ color: bc, fontFamily: "'DM Mono',monospace", fontSize: 13 }}>
            {pct === null ? "—" : `${pct}%`}
          </span>
        </div>
        <div className="h-1 rounded-full overflow-hidden" style={{ background: "#E5E7EB" }}>
          <div className="h-full rounded-full transition-all duration-500" style={{ width: `${pct ?? 0}%`, background: bc }} />
        </div>
      </div>
    );
  }

  return (
    <div className="h-full flex px-6 py-4 gap-4 overflow-hidden" style={{ maxWidth: "none" }}>

      {/* ── LEFT 50%: subject card + list ─────────────────────────── */}
      <div className="flex-1 flex flex-col gap-3 min-h-0 min-w-0">

                {/* Career/Cohort/PAO card with Valoración General (agregado del PAO) */}
        {(() => {
          const st = getStatus(totalGeneral ?? 0);
          return (
            <div className="flex-shrink-0 bg-white rounded-2xl px-4 py-3 flex items-center justify-between gap-3"
              style={{ border: "1px solid rgba(27,58,107,0.08)" }}>
              <div className="min-w-0">
                <p className="text-xs font-bold truncate" style={{ color: "#0F1E3C" }}>{career?.name || "—"}</p>
                <p className="text-xs mt-0.5" style={{ color: "#5A7295" }}>Cohorte {cohort} · PAO {pao}</p>
              </div>
              <div className="text-right flex-shrink-0">
                <p className="text-xs font-bold uppercase tracking-widest mb-0.5" style={{ color: "#5A7295" }}>Valoración General</p>
                <p className="text-2xl font-bold leading-none" style={{ color: totalGeneral === null ? "#9CA3AF" : st.color, fontFamily: "'DM Mono',monospace" }}>
                  {totalGeneral === null ? "—" : `${totalGeneral}%`}
                </p>
                {totalGeneral !== null && (
                  <p className="text-xs mt-0.5 font-semibold px-2 py-0.5 rounded-full inline-block"
                    style={{ background: st.bg, color: st.color }}>{st.label}</p>
                )}
              </div>
            </div>
          );
        })()}

        {/* Subject list */}
        <div className="flex-1 bg-white rounded-2xl overflow-hidden flex flex-col min-h-0"
          style={{ border: "1px solid rgba(27,58,107,0.08)" }}>
          <div className="px-4 py-2 flex-shrink-0" style={{ borderBottom: "1px solid rgba(27,58,107,0.07)", background: "#F8FAFD" }}>
            <p className="text-xs font-bold uppercase tracking-widest" style={{ color: "#5A7295" }}>Asignaturas</p>
          </div>
          <div className="flex flex-col flex-1 overflow-auto">
            {detalle.map((d, ai) => {
              const active = selectedAsig === ai;
              const subTotal = d.valoracion_general;
              const subSt = getStatus(subTotal ?? 0);
              return (
                <button key={d.id_asignatura} onClick={() => setSelectedAsig(ai)}
                  className="flex-shrink-0 w-full text-left px-3 flex items-center justify-between gap-2 transition-colors hover:bg-blue-50"
                  style={{ height: 36, borderBottom: "1px solid rgba(27,58,107,0.05)", background: active ? "#EEF5FF" : "transparent", borderLeft: `3px solid ${active ? "#1B3A6B" : "transparent"}` }}>
                  <div className="flex items-center gap-1.5 min-w-0">
                    <div className="w-1.5 h-1.5 rounded-full flex-shrink-0" style={{ background: subTotal === null ? "#9CA3AF" : subSt.color }} />
                    <p className="text-xs font-semibold truncate" style={{ color: active ? "#1B3A6B" : "#0F1E3C" }}>{d.nombre_asignatura}</p>
                  </div>
                  <span className="text-xs font-bold flex-shrink-0" style={{ color: subTotal === null ? "#9CA3AF" : subSt.color, fontFamily: "'DM Mono',monospace" }}>
                    {subTotal === null ? "—" : `${subTotal}%`}
                  </span>
                </button>
              );
            })}
            {detalle.length === 0 && (
              <p className="text-xs px-3 py-3" style={{ color: "#94A3B8" }}>No hay asignaturas cargadas para este PAO.</p>
            )}
          </div>
        </div>
      </div>

      {/* ── RIGHT 50%: "Resultados por EF" — no scroll ────────────── */}
      <div className="flex-1 bg-white rounded-2xl flex flex-col min-h-0 min-w-0 overflow-hidden"
        style={{ border: "1px solid rgba(27,58,107,0.08)" }}>

        {/* Card header */}
        <div className="flex items-center justify-between px-5 py-2.5 flex-shrink-0"
          style={{ borderBottom: "1px solid rgba(27,58,107,0.07)", background: "#F8FAFD" }}>
          <div>
            <h3 className="font-bold" style={{ fontFamily: "'Libre Baskerville',serif", color: "#0F1E3C", fontSize: 13 }}>{asig?.nombre_asignatura ?? "—"}</h3>
          </div>
          <div className="flex items-center gap-1.5 flex-shrink-0">
            {asig && (
              <span className="px-2 py-1 rounded-lg font-bold text-xs"
                style={materiaCompleta ? { background: "#DCFCE7", color: "#16A34A" } : { background: "#FEF9C3", color: "#CA8A04" }}>
                {materiaCompleta ? "Completo" : "Incompleto"}
              </span>
            )}
                    {(() => { const st = getStatus(totalAsignatura ?? 0); return (
            <span className="px-2.5 py-1 rounded-lg font-bold flex-shrink-0"
              style={{ background: totalAsignatura === null ? "#F1F5F9" : st.bg, color: totalAsignatura === null ? "#94A3B8" : st.color, fontFamily: "'DM Mono',monospace", fontSize: 13 }}>
              {totalAsignatura === null ? "Sin datos" : `${totalAsignatura}%`}
            </span>
          ); })()}
          </div>
        </div>

        {/* Radar — flex-shrink-0 with fixed height */}
        <div className="flex-shrink-0 px-4 pt-2" style={{ height: 185 }}>
          <ResponsiveContainer width="100%" height="100%">
            <RadarChart data={radarData} cx="50%" cy="50%" outerRadius="60%">
              <PolarGrid key="grid" stroke="#E5E7EB" />
              <PolarAngleAxis key="axis" dataKey="subject"
                tick={{ fill: "#5A7295", fontSize: 11, fontWeight: 700, fontFamily: "'DM Mono',monospace" }} />
              <Radar key="radar" name="EF" dataKey="score" stroke="#16A34A" fill="#16A34A" fillOpacity={0.15} strokeWidth={2} dot={{ r: 4, fill: "#16A34A" }} />
            </RadarChart>
          </ResponsiveContainer>
        </div>

        {/* EF grid — flex-1, fits remaining height */}
        <div className="flex-1 px-4 pb-2 flex flex-col gap-1.5 min-h-0">
          <div className="grid grid-cols-2 gap-1.5 flex-shrink-0">
            <EfCell ef={efScores[0]} />
            <EfCell ef={efScores[1]} />
          </div>
          <div className="grid grid-cols-2 gap-1.5 flex-shrink-0">
            <EfCell ef={efScores[2]} />
            <EfCell ef={efScores[3]} />
          </div>
          <EfCell ef={efScores[4]} />

          {/* Footer note */}
          <div className="flex items-center gap-1.5 flex-shrink-0 mt-0.5">
            <AlertCircle size={10} style={{ color: "#94A3B8", flexShrink: 0 }} />
            <p style={{ color: "#94A3B8", fontSize: 10 }}>
              Calculado desde los archivos subidos en la pestaña Evidencias: EF1 y EF4 desde el CSV de resultados de encuesta ({asig?.respuestas ?? 0} respuestas) · EF2, EF3, EF5 desde la evidencia documental.
            </p>
          </div>
        </div>

        {/* Export button */}
        <div className="flex justify-end px-4 py-2.5 flex-shrink-0"
          style={{ borderTop: "1px solid rgba(27,58,107,0.07)" }}>
          <button
            onClick={handleExportarPDF}
            disabled={exportando || !asig}
            className="flex items-center gap-1.5 px-4 py-2 rounded-xl font-bold transition-all hover:opacity-90 active:scale-95 disabled:opacity-60"
            style={{ background: "#1B3A6B", color: "#fff", fontSize: 12 }}>
            {exportando
              ? <><Loader2 size={12} className="animate-spin" /> Generando…</>
              : <><Download size={12} /> Exportar PDF</>}
          </button>
        </div>
      </div>
    </div>
  );
}

// ── Tab Resultados (I3 – Tutorías Académicas) ──────────────────────────────
// Etiquetas legibles para los nombres de puntos de validación que devuelve
// el backend (api/tutorias_academicas/_validacion_pdf.php, puntosPorEf()).
// Solo presentación -- el backend es quien decide cumplido/no cumplido.
const I3_PUNTO_LABELS: Record<string, string> = {
  encabezado_institucional: "Encabezado institucional",
  horas: "Horas",
  firma_docente: "Firma de docente",
  firma_director: "Firma de director de carrera",
  reporte_mejora: "Reporte de mejora académica",
  normativa: "Normativa institucional vigente",
};

const I3_EF_ORDEN: EfTutorias[] = ["EF1", "EF2", "EF3", "EF4"];

// ── Tab Resultados (I3 – Tutorías Académicas) ──────────────────────────────
// Igual patrón que TabResults (I2): solo lectura, trae de la API real el
// resultado ya calculado por api/tutorias_academicas/_calculo.php (pesos,
// % por EF, escala) -- no se recalcula nada en el frontend.
function TabResultsI3({ ind, career, cohort, pao, onAsignaturaChange }: { ind: IndicatorDef; career: Career | null; cohort: string; pao: number; onAsignaturaChange?: (idAsignatura: number | null, nombreAsignatura?: string | null) => void }) {
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [resultadoCohorte, setResultadoCohorte] = useState<ResultadoCohorteTutorias | null>(null);
  const [selectedIdx, setSelectedIdx] = useState(0);

  const detalle = resultadoCohorte?.detalle_asignaturas ?? [];
  const materia: ResultadoAsignaturaTutorias | undefined = detalle[Math.min(selectedIdx, detalle.length - 1)];

  // Notificar al padre cuando cambie la asignatura seleccionada -- misma
  // señal que usa TabEvidences para I2, necesaria para que I3 también
  // pueda mostrar/subir evidencia de la materia correcta en esa pestaña.
  useEffect(() => {
    const id = materia?.id_asignatura ?? null;
    onAsignaturaChange?.(id, materia?.nombre_asignatura ?? null);
  }, [selectedIdx, resultadoCohorte]);

  useEffect(() => {
    if (!career) return;
    let cancelado = false;
    setCargando(true);
    setError(null);

    (async () => {
      try {
        const evaluacion = await obtenerEvaluacion(career.code, cohort);
        const periodos = await obtenerPeriodos(evaluacion.id_cohorte);
        const periodo = periodos.find((p) => p.orden === pao);
        if (!periodo) {
          throw new Error(`No existe el PAO ${pao} para esta cohorte.`);
        }
        const rc = await obtenerResultadoCohorteTutorias(evaluacion.id_cohorte, evaluacion.id_evaluacion, periodo.id_periodoacademico);
        if (cancelado) return;
        setResultadoCohorte(rc);
        setSelectedIdx(0);
      } catch (e) {
        if (!cancelado) setError(e instanceof Error ? e.message : "No se pudo cargar la información.");
      } finally {
        if (!cancelado) setCargando(false);
      }
    })();

    return () => { cancelado = true; };
  }, [career, cohort, pao]);

  if (cargando) {
    return (
      <div className="h-full flex items-center justify-center gap-2" style={{ color: "#5A7295" }}>
        <Loader2 size={16} className="animate-spin" /> Cargando resultados reales…
      </div>
    );
  }

  if (error) {
    return (
      <div className="h-full flex flex-col items-center justify-center gap-2" style={{ color: "#DC2626" }}>
        <AlertCircle size={20} />
        <p className="text-sm font-medium">{error}</p>
      </div>
    );
  }

  const paoScore = resultadoCohorte?.valoracion_general ?? null;
  const paoSt    = getStatus(Math.round(paoScore ?? 0));
  const materiaScore = materia?.valoracion_general ?? null;
  const materiaCompleta = materia?.estado_general === "completo";

  // ── SVG donut ring ────────────────────────────────────────────────────────
  function DonutRing({ pct, color }: { pct: number; color: string }) {
    const r = 34; const circ = 2 * Math.PI * r;
    return (
      <svg width={86} height={86} viewBox="0 0 86 86">
        <circle cx={43} cy={43} r={r} fill="none" stroke="#E5E7EB" strokeWidth={7} />
        <circle cx={43} cy={43} r={r} fill="none" stroke={color} strokeWidth={7}
          strokeDasharray={`${(pct / 100) * circ} ${circ}`} strokeLinecap="round"
          transform="rotate(-90 43 43)" />
      </svg>
    );
  }

  // ── EF ring card ──────────────────────────────────────────────────────────
  function RingCard({ efKey }: { efKey: EfTutorias }) {
    const ef = materia?.efs?.[efKey];
    const sinDatos = !ef || ef.estado === "sin_datos" || ef.pct === null;
    const pctEf = sinDatos ? 0 : (ef!.pct as number);
    // Aporte real al resultado general = % del EF × peso del EF.
    const contribPct = ef ? Math.round(ef.peso * pctEf) : 0;
    const detalle = ef?.detalle_puntos ?? [];

    // Color por escala (mismos cortes CACES ≥75/≥50/≥25 que I2).
    const color = sinDatos
      ? "#9CA3AF"
      : pctEf >= 75 ? "#16A34A"
      : pctEf >= 50 ? "#CA8A04"
      : pctEf >= 25 ? "#F97316"
      : "#EF4444";

    return (
      <div className="bg-white rounded-2xl flex flex-col items-center justify-center gap-1.5 py-4"
        style={{ border: "1px solid rgba(27,58,107,0.08)" }}
        title={detalle.length ? detalle.map(p => `${p.cumplido ? "✓" : "✗"} ${I3_PUNTO_LABELS[p.nombre] ?? p.nombre}`).join("\n") : undefined}>
        <p className="text-xs font-semibold text-center leading-tight px-3"
          style={{ color: "#5A7295" }}>{ef?.label ?? efKey}</p>
        <div className="relative flex items-center justify-center" style={{ width: 86, height: 86 }}>
          <DonutRing pct={sinDatos ? 0 : pctEf} color={sinDatos ? "#E5E7EB" : color} />
          <span className="absolute font-bold text-center leading-none"
            style={{ color, fontFamily: "'DM Mono',monospace", fontSize: sinDatos ? 9 : 14 }}>
            {sinDatos ? "Sin\ndatos" : `${pctEf}%`}
          </span>
        </div>
        <span className="text-xs font-semibold px-2 py-0.5 rounded-full"
          style={sinDatos
            ? { background: "#F3F4F6", color: "#9CA3AF" }
            : { background: "#EEF2F7", color: "#1B3A6B" }}>
          {sinDatos || !ef ? "Sin datos" : `${ef.cumplidos}/${ef.total_puntos} puntos · aporta ${contribPct}%`}
        </span>
      </div>
    );
  }

  return (
    <div className="h-full flex px-6 py-4 gap-4 overflow-hidden">

      {/* ── LEFT: materia list ───────────────────────────────────────── */}
      <div className="flex-1 flex flex-col gap-3 min-h-0 min-w-0">

        {/* Career/Cohort/PAO card with Valoración General (agregado del PAO) */}
        <div className="flex-shrink-0 bg-white rounded-2xl px-4 py-3 flex items-center justify-between gap-3"
          style={{ border: "1px solid rgba(27,58,107,0.08)" }}>
          <div className="min-w-0">
            <p className="text-xs font-bold truncate" style={{ color: "#0F1E3C" }}>{career?.name || "—"}</p>
            <p className="text-xs mt-0.5" style={{ color: "#5A7295" }}>Cohorte {cohort} · PAO {pao}</p>
          </div>
          <div className="text-right flex-shrink-0">
            <p className="text-xs font-bold uppercase tracking-widest mb-0.5" style={{ color: "#5A7295" }}>Valoración General</p>
            <p className="text-2xl font-bold leading-none" style={{ color: paoScore === null ? "#9CA3AF" : paoSt.color, fontFamily: "'DM Mono',monospace" }}>
              {paoScore === null ? "—" : `${Math.round(paoScore)}%`}
            </p>
            {paoScore !== null && (
              <p className="text-xs mt-0.5 font-semibold px-2 py-0.5 rounded-full inline-block"
                style={{ background: paoSt.bg, color: paoSt.color }}>{paoSt.label}</p>
            )}
          </div>
        </div>

        {/* Subject list — datos reales de resultado_cohorte.php */}
        <div className="flex-1 bg-white rounded-2xl overflow-hidden flex flex-col min-h-0"
          style={{ border: "1px solid rgba(27,58,107,0.08)" }}>
          <div className="px-4 py-2 flex-shrink-0" style={{ borderBottom: "1px solid rgba(27,58,107,0.07)", background: "#F8FAFD" }}>
            <p className="text-xs font-bold uppercase tracking-widest" style={{ color: "#5A7295" }}>Asignaturas</p>
          </div>
          <div className="flex flex-col flex-1 overflow-auto">
            {detalle.map((m, mi) => {
                    const active  = selectedIdx === mi;
                    const score   = m.valoracion_general;
                    const dotColor = score === null ? "#CA8A04" : m.estado_general === "completo" ? "#16A34A" : "#CA8A04";
                    return (
                      <button key={m.id_asignatura} onClick={() => setSelectedIdx(mi)}
                        className="flex-shrink-0 w-full text-left px-3 flex items-center justify-between gap-2 transition-colors hover:bg-blue-50"
                        style={{ height: 38, borderBottom: "1px solid rgba(27,58,107,0.05)", background: active ? "#EEF5FF" : "transparent", borderLeft: `3px solid ${active ? "#0891B2" : "transparent"}` }}>
                        <div className="flex items-center gap-1.5 min-w-0">
                          <div className="w-1.5 h-1.5 rounded-full flex-shrink-0" style={{ background: dotColor }} />
                          <p className="text-xs font-semibold truncate" style={{ color: active ? "#0891B2" : "#0F1E3C" }}>{m.nombre_asignatura}</p>
                        </div>
                        {score !== null ? (
                          <span className="text-xs font-bold flex-shrink-0"
                            style={{ color: "#16A34A", fontFamily: "'DM Mono',monospace" }}>
                            {Math.round(score)}%
                          </span>
                        ) : (
                          <span className="text-xs font-semibold px-1.5 py-0.5 rounded-full flex-shrink-0"
                            style={{ background: "#FEF9C3", color: "#CA8A04" }}>
                            Falta de evidencia
                          </span>
                        )}
                      </button>
                    );
                  })}
            {detalle.length === 0 && (
              <p className="text-xs px-3 py-3" style={{ color: "#94A3B8" }}>No hay asignaturas cargadas para este PAO.</p>
            )}
          </div>
        </div>
      </div>

      {/* ── RIGHT: materia name + 2×2 ring grid ──────────────────────── */}
      <div className="flex-1 flex flex-col gap-3 min-h-0 min-w-0">
        {/* Materia name card */}
        <div className="flex-shrink-0 bg-white rounded-2xl px-4 py-3 flex items-center justify-between gap-3"
          style={{ border: "1px solid rgba(27,58,107,0.08)" }}>
          <p className="text-sm font-bold" style={{ fontFamily: "'Libre Baskerville',serif", color: "#0F1E3C" }}>{materia?.nombre_asignatura ?? "—"}</p>
          {materiaScore !== null ? (
            <div className="flex items-center gap-2 flex-shrink-0">
              <p className="text-xl font-bold leading-none" style={{ color: "#16A34A", fontFamily: "'DM Mono',monospace" }}>
                {Math.round(materiaScore)}%
              </p>
              <p className="text-xs font-semibold px-2 py-0.5 rounded-full"
                style={materiaCompleta ? { background: "#DCFCE7", color: "#16A34A" } : { background: "#FEF9C3", color: "#CA8A04" }}>
                {materiaCompleta ? "Completo" : "Incompleto"}
              </p>
            </div>
          ) : (
            <div className="flex items-center gap-2 flex-shrink-0">
              <p className="text-xl font-bold leading-none" style={{ color: "#CA8A04", fontFamily: "'DM Mono',monospace" }}>—</p>
              <p className="text-xs font-semibold px-2 py-0.5 rounded-full"
                style={{ background: "#FEF9C3", color: "#CA8A04" }}>Incompleto</p>
            </div>
          )}
        </div>
        {/* Ring grid */}
        <div className="flex-1 grid grid-cols-2 gap-3 min-h-0" style={{ gridTemplateRows: "1fr 1fr" }}>
          {I3_EF_ORDEN.map((efKey) => <RingCard key={efKey} efKey={efKey} />)}
        </div>
      </div>
    </div>
  );
}

// ── Tab Cohortes ───────────────────────────────────────────────────────────
function TabCohorts({
  ind,
  career,
  cohort,
}: {
  ind: IndicatorDef;
  career: Career | null;
  cohort: string;
}) {
  const [datosTitulacion, setDatosTitulacion] =
    useState<CohorteTitulacion[]>([]);

  const [datosDesercion, setDatosDesercion] =
    useState<CohorteDesercion[]>([]);

  const [cargando, setCargando] = useState(true);
  const [errorCarga, setErrorCarga] = useState("");

  const esDesercion = ind.id === "I4";

  useEffect(() => {
    void cargar();
  }, [career?.code, cohort, ind.id]);

  async function cargar() {
    setCargando(true);
    setErrorCarga("");

    try {
      if (!career) {
        throw new Error(
          "No se ha seleccionado una carrera.",
        );
      }

      const cohorteNormalizada = cohort
        .replace(/\s+/g, "")
        .toUpperCase();

      const evaluacion = await obtenerEvaluacion(
        career.code,
        cohorteNormalizada,
      );

      if (esDesercion) {
        const respuesta =
          await obtenerDatosDesercion(
            evaluacion.id_evaluacion,
          );

        setDatosDesercion(respuesta);
        setDatosTitulacion([]);
      } else {
        const respuesta = await obtenerDatosTasa(
          evaluacion.id_evaluacion,
        );

        setDatosTitulacion(respuesta);
        setDatosDesercion([]);
      }
    } catch (error) {
      console.error(error);

      const mensaje =
        error instanceof Error
          ? error.message
          : "No se pudieron cargar los datos de cohortes.";

      setErrorCarga(mensaje);
      setDatosTitulacion([]);
      setDatosDesercion([]);
    } finally {
      setCargando(false);
    }
  }

  const cantidadRegistros = esDesercion
    ? datosDesercion.length
    : datosTitulacion.length;

  const totalPrimerNivel = datosDesercion.reduce(
    (suma, item) =>
      suma +
      Number(item.iniciaron_primer_nivel ?? 0),
    0,
  );

  const totalSegundoAnio = datosDesercion.reduce(
    (suma, item) =>
      suma +
      Number(item.matriculados_segundo_anio ?? 0),
    0,
  );

  const totalDesertados = datosDesercion.reduce(
    (suma, item) =>
      suma + Number(item.no_continuaron ?? 0),
    0,
  );

  const tasaPromedioDesercion =
    datosDesercion.length === 0
      ? 0
      : Number(
          (
            datosDesercion.reduce(
              (suma, item) =>
                suma + Number(item.tasa ?? 0),
              0,
            ) / datosDesercion.length
          ).toFixed(2),
        );

  const totalMatriculados = datosTitulacion.reduce(
    (suma, item) =>
      suma + Number(item.matriculados ?? 0),
    0,
  );

  const totalGraduados = datosTitulacion.reduce(
    (suma, item) =>
      suma + Number(item.graduados ?? 0),
    0,
  );

  const tasaGeneralTitulacion =
    totalMatriculados === 0
      ? 0
      : Number(
          (
            (totalGraduados / totalMatriculados) *
            100
          ).toFixed(2),
        );

  const encabezados = esDesercion
    ? [
        "Cohorte",
        "Matriculados 1er nivel",
        "Matriculados 2do año",
        "Desertados 2do año",
        "Tasa",
        "Estado",
      ]
    : [
        "Cohorte",
        "Matriculados",
        "Graduados",
        "Tasa",
        "Estado",
      ];

  return (
    <div
      className={`h-full flex flex-col px-6 py-4 mx-auto overflow-hidden ${
        esDesercion ? "max-w-6xl" : "max-w-4xl"
      }`}
    >
      <div
        className="bg-white rounded-2xl overflow-hidden flex-1 min-h-0 flex flex-col"
        style={{
          border: "1px solid rgba(27,58,107,0.08)",
        }}
      >
        <div
          className="px-6 py-4 flex-shrink-0"
          style={{
            borderBottom:
              "1px solid rgba(27,58,107,0.07)",
            background: "#F8FAFD",
          }}
        >
          <h3
            className="text-sm font-semibold"
            style={{
              fontFamily:
                "'Libre Baskerville',serif",
              color: "#0F1E3C",
            }}
          >
            Historial de datos por cohorte
          </h3>

          <p
            className="text-xs mt-1"
            style={{ color: "#5A7295" }}
          >
            Carrera: {career?.name ?? "—"} · Cohorte
            seleccionada: {cohort}
          </p>
        </div>

        {cargando ? (
          <div className="flex-1 flex items-center justify-center">
            <p
              className="text-sm"
              style={{ color: "#5A7295" }}
            >
              Cargando datos...
            </p>
          </div>
        ) : errorCarga ? (
          <div className="flex-1 flex flex-col items-center justify-center text-center px-6">
            <AlertCircle
              size={36}
              className="mb-3"
              style={{ color: "#DC2626" }}
            />

            <p
              className="text-sm font-semibold"
              style={{ color: "#DC2626" }}
            >
              No se pudieron cargar los datos
            </p>

            <p
              className="text-xs mt-1 max-w-md"
              style={{ color: "#6B7280" }}
            >
              {errorCarga}
            </p>
          </div>
        ) : cantidadRegistros === 0 ? (
          <div className="flex-1 flex flex-col items-center justify-center text-center px-6">
            <TableProperties
              size={36}
              className="mb-3"
              style={{ color: "#D1D5DB" }}
            />

            <p
              className="text-sm font-medium"
              style={{ color: "#6B7280" }}
            >
              Sin datos de cohortes
            </p>

            <p
              className="text-xs mt-1 max-w-sm"
              style={{ color: "#9CA3AF" }}
            >
              {esDesercion
                ? "Los resultados aparecerán cuando se lean y guarden los PDF de primer nivel, segundo año y estudiantes desertados."
                : "Los resultados aparecerán cuando se lean y guarden los PDF de matriculados y graduados."}
            </p>
          </div>
        ) : (
          <div className="flex-1 overflow-auto">
            <table className="w-full text-sm">
              <thead>
                <tr
                  style={{
                    borderBottom:
                      "1px solid rgba(27,58,107,0.07)",
                  }}
                >
                  {encabezados.map((encabezado) => (
                    <th
                      key={encabezado}
                      className="px-5 py-3 text-left text-xs font-bold uppercase tracking-wider"
                      style={{
                        color: "#5A7295",
                        background: "#F8FAFD",
                      }}
                    >
                      {encabezado}
                    </th>
                  ))}
                </tr>
              </thead>

              <tbody>
                {esDesercion
                  ? datosDesercion.map(
                      (item, index) => {
                        const tasa = Number(
                          item.tasa ?? 0,
                        );

                        const estado = getStatus(
                          Math.round(tasa),
                        );

                        const esActual =
                          item.cohorte
                            .replace(/\s+/g, "")
                            .toUpperCase() ===
                          cohort
                            .replace(/\s+/g, "")
                            .toUpperCase();

                        return (
                          <tr
                            key={`${item.cohorte}-${index}`}
                            style={{
                              borderBottom:
                                "1px solid rgba(27,58,107,0.05)",
                              background: esActual
                                ? "#EEF5FF"
                                : index % 2 === 0
                                  ? "#FFFFFF"
                                  : "#FAFBFD",
                            }}
                          >
                            <td className="px-5 py-3">
                              <div className="flex items-center gap-2">
                                <span
                                  className="font-bold"
                                  style={{
                                    fontFamily:
                                      "'DM Mono',monospace",
                                    color: esActual
                                      ? "#1B3A6B"
                                      : "#0F1E3C",
                                  }}
                                >
                                  Cohorte {item.cohorte}
                                </span>

                                {esActual && (
                                  <span
                                    className="text-xs px-2 py-0.5 rounded-full font-bold"
                                    style={{
                                      background:
                                        "#1B3A6B",
                                      color: "#FFFFFF",
                                    }}
                                  >
                                    actual
                                  </span>
                                )}
                              </div>
                            </td>

                            <td
                              className="px-5 py-3"
                              style={{ color: "#374151" }}
                            >
                              {Number(
                                item.iniciaron_primer_nivel ??
                                  0,
                              )}
                            </td>

                            <td
                              className="px-5 py-3"
                              style={{ color: "#374151" }}
                            >
                              {Number(
                                item.matriculados_segundo_anio ??
                                  0,
                              )}
                            </td>

                            <td
                              className="px-5 py-3"
                              style={{ color: "#374151" }}
                            >
                              {Number(
                                item.no_continuaron ?? 0,
                              )}
                            </td>

                            <td className="px-5 py-3">
                              <span
                                className="font-bold px-2.5 py-1 rounded-lg"
                                style={{
                                  background: estado.bg,
                                  color: estado.color,
                                  fontFamily:
                                    "'DM Mono',monospace",
                                }}
                              >
                                {tasa.toFixed(2)}%
                              </span>
                            </td>

                            <td className="px-5 py-3">
                              <span
                                className="text-xs px-2 py-0.5 rounded-full font-semibold"
                                style={{
                                  background: estado.bg,
                                  color: estado.color,
                                }}
                              >
                                {estado.label}
                              </span>
                            </td>
                          </tr>
                        );
                      },
                    )
                  : datosTitulacion.map(
                      (item, index) => {
                        const tasa = Number(
                          item.tasa ?? 0,
                        );

                        const estado = getStatus(
                          Math.round(tasa),
                        );

                        const esActual =
                          item.cohorte
                            .replace(/\s+/g, "")
                            .toUpperCase() ===
                          cohort
                            .replace(/\s+/g, "")
                            .toUpperCase();

                        return (
                          <tr
                            key={`${item.cohorte}-${index}`}
                            style={{
                              borderBottom:
                                "1px solid rgba(27,58,107,0.05)",
                              background: esActual
                                ? "#EEF5FF"
                                : index % 2 === 0
                                  ? "#FFFFFF"
                                  : "#FAFBFD",
                            }}
                          >
                            <td className="px-5 py-3">
                              <div className="flex items-center gap-2">
                                <span
                                  className="font-bold"
                                  style={{
                                    fontFamily:
                                      "'DM Mono',monospace",
                                    color: esActual
                                      ? "#1B3A6B"
                                      : "#0F1E3C",
                                  }}
                                >
                                  Cohorte {item.cohorte}
                                </span>

                                {esActual && (
                                  <span
                                    className="text-xs px-2 py-0.5 rounded-full font-bold"
                                    style={{
                                      background:
                                        "#1B3A6B",
                                      color: "#FFFFFF",
                                    }}
                                  >
                                    actual
                                  </span>
                                )}
                              </div>
                            </td>

                            <td
                              className="px-5 py-3"
                              style={{ color: "#374151" }}
                            >
                              {Number(
                                item.matriculados ?? 0,
                              )}
                            </td>

                            <td
                              className="px-5 py-3"
                              style={{ color: "#374151" }}
                            >
                              {Number(
                                item.graduados ?? 0,
                              )}
                            </td>

                            <td className="px-5 py-3">
                              <span
                                className="font-bold px-2.5 py-1 rounded-lg"
                                style={{
                                  background: estado.bg,
                                  color: estado.color,
                                  fontFamily:
                                    "'DM Mono',monospace",
                                }}
                              >
                                {tasa.toFixed(2)}%
                              </span>
                            </td>

                            <td className="px-5 py-3">
                              <span
                                className="text-xs px-2 py-0.5 rounded-full font-semibold"
                                style={{
                                  background: estado.bg,
                                  color: estado.color,
                                }}
                              >
                                {estado.label}
                              </span>
                            </td>
                          </tr>
                        );
                      },
                    )}
              </tbody>

              {cantidadRegistros > 1 && (
                <tfoot>
                  <tr
                    style={{
                      borderTop:
                        "2px solid rgba(27,58,107,0.12)",
                      background: "#EEF2F7",
                    }}
                  >
                    <td
                      className="px-5 py-3 text-xs font-bold uppercase"
                      style={{ color: "#5A7295" }}
                    >
                      {esDesercion
                        ? "Totales / promedio"
                        : "Totales"}
                    </td>

                    {esDesercion ? (
                      <>
                        <td
                          className="px-5 py-3 font-bold"
                          style={{ color: "#0F1E3C" }}
                        >
                          {totalPrimerNivel}
                        </td>

                        <td
                          className="px-5 py-3 font-bold"
                          style={{ color: "#0F1E3C" }}
                        >
                          {totalSegundoAnio}
                        </td>

                        <td
                          className="px-5 py-3 font-bold"
                          style={{ color: "#0F1E3C" }}
                        >
                          {totalDesertados}
                        </td>

                        <td
                          className="px-5 py-3 font-bold"
                          style={{ color: "#0F1E3C" }}
                        >
                          {tasaPromedioDesercion.toFixed(
                            2,
                          )}
                          %
                        </td>

                        <td className="px-5 py-3">
                          {
                            getStatus(
                              Math.round(
                                tasaPromedioDesercion,
                              ),
                            ).label
                          }
                        </td>
                      </>
                    ) : (
                      <>
                        <td
                          className="px-5 py-3 font-bold"
                          style={{ color: "#0F1E3C" }}
                        >
                          {totalMatriculados}
                        </td>

                        <td
                          className="px-5 py-3 font-bold"
                          style={{ color: "#0F1E3C" }}
                        >
                          {totalGraduados}
                        </td>

                        <td
                          className="px-5 py-3 font-bold"
                          style={{ color: "#0F1E3C" }}
                        >
                          {tasaGeneralTitulacion.toFixed(
                            2,
                          )}
                          %
                        </td>

                        <td className="px-5 py-3">
                          {
                            getStatus(
                              Math.round(
                                tasaGeneralTitulacion,
                              ),
                            ).label
                          }
                        </td>
                      </>
                    )}
                  </tr>
                </tfoot>
              )}
            </table>
          </div>
        )}
      </div>
    </div>
  );
}

// ── Tab Evidencias (split view) ────────────────────────────────────────────
// Mapeo de sourceNum de I2 a tipo en evidencia_asignatura. 'encuesta_csv'
// (slot 5) se agrega a partir de la migración
// sql/migracion_i2_encuesta_csv_por_asignatura.sql (ver MEMORIA v18,
// reemplaza lo decidido en v17 sección 41: el CSV ya no es evaluation-wide).
const I2_SOURCE_NUM_TO_TIPO: Record<number, string> = {
  1: "syllabus",
  3: "acta_ajuste_curricular",
  4: "evidencia_difusion",
  5: "encuesta_csv",
};

// I3 (Tutorías Académicas): mismo mecanismo por-asignatura que I2, sin
// slots evaluation-wide -- los 4 slots del wizard mapean 1:1 a los 4 tipos
// reales de evidencia_asignatura para este indicador.
const I3_SOURCE_NUM_TO_TIPO: Record<number, string> = {
  1: "plan_tutorias",
  2: "registro_tutorias",
  3: "informe_tutorias",
  4: "evidencia_atencion",
};

function TabEvidences({
  ind,
  career,
  cohort,
  idAsignatura,
  nombreAsignatura,
  onUpload,
  puedeCargar,
}: {
  ind: IndicatorDef;
  career: Career | null;
  cohort: string;
  idAsignatura: number | null;
  nombreAsignatura?: string | null;
  onUpload?: () => void;
  puedeCargar: boolean;
}) {
  // I2 y I3 trabajan por asignatura: se muestra un chip con el nombre de la
  // materia que se está viendo, para no perder el contexto dentro de la
  // pestaña Evidencias. I1/I4/I5 trabajan a nivel carrera+cohorte, sin
  // asignatura, así que este chip no aplica para ellos.
  const mostrarAsignatura = (ind.id === "I2" || ind.id === "I3") && !!nombreAsignatura;
  const [slots, setSlots] = useState<IndicatorDef["slots"]>(
    () => ind.slots.map((slot) => ({ ...slot })),
  );

  const [selected, setSelected] = useState<number | null>(
    ind.slots.find((slot) => slot.file)?.sourceNum ?? null,
  );

  const [cargandoEvidencias, setCargandoEvidencias] =
    useState(true);

  useEffect(() => {
    setSlots(
      ind.slots.map((slot) => ({ ...slot })),
    );
    setSelected(
      ind.slots.find((slot) => slot.file)?.sourceNum ??
        null,
    );
  }, [ind]);

    useEffect(() => {
    let cancelado = false;

    async function cargarEvidencias() {
      if (!career) {
        if (!cancelado) {
          setCargandoEvidencias(false);
        }
        return;
      }

      try {
        setCargandoEvidencias(true);

        const cohorteNormalizada = cohort
          .replace(/\s+/g, "")
          .toUpperCase();

        const evaluacion = await obtenerEvaluacion(
          career.code,
          cohorteNormalizada,
        );

        const idIndicador = Number(
          ind.id.replace(/\D/g, ""),
        );

        // Para I2, ademas de las tablas viejas (evidencias), traemos
        // tambien la evidencia por asignatura (evidencia_asignatura) para
        // los 5 slots (1-5, incl. CSV de encuesta) que se suben a la tabla
        // real -- ver MEMORIA v18. `guardadas`/`compartidas` ya no se usan
        // para el slot 5. Para I3, mismo mecanismo pero via el endpoint
        // real de tutorias academicas (incluye la validacion por puntos).
        const [guardadas, compartidas, evidenciaAsignatura, evidenciaTutorias] =
          await Promise.all([
            obtenerEvidenciasGuardadas(
              evaluacion.id_evaluacion,
              idIndicador,
            ),
            obtenerEvidenciasCompartidas(
              evaluacion.id_evaluacion,
              idIndicador,
            ),
            // Solo para I2 con asignatura resuelta
            (ind.id === "I2" && idAsignatura !== null)
              ? obtenerEvidenciaAsignatura(idAsignatura)
              : Promise.resolve(null),
            // Solo para I3 con asignatura resuelta
            (ind.id === "I3" && idAsignatura !== null)
              ? obtenerEvidenciaTutorias(idAsignatura)
              : Promise.resolve(null),
          ]);

        if (cancelado) {
          return;
        }

        const nuevasSlots = ind.slots.map((slot) => {
        
          let propia;
let compartida;

// ── I2 slots 1-5 (incl. CSV de encuesta, tipo 'encuesta_csv'): usar
// evidencia_asignatura (tabla real) ──
// Nota: la condicion depende del MAPEO (slot de I2), no de si
// evidenciaAsignatura vino con datos. Si idAsignatura todavia no
// resolvio (evidenciaAsignatura === null), estos slots deben mostrarse
// como "sin archivo" -- nunca caer al fallback viejo (tabla `evidencias`,
// evaluacion-wide), que es exactamente el bug que se esta arreglando.
if (
  ind.id === "I2" &&
  I2_SOURCE_NUM_TO_TIPO[slot.sourceNum] !== undefined
) {
  const tipo = I2_SOURCE_NUM_TO_TIPO[slot.sourceNum];
  const item = evidenciaAsignatura?.find(
    (e: EvidenciaAsignaturaItem) => e.tipo === tipo,
  );

  if (item && item.subida && item.archivo) {
    return {
      ...slot,
      file: {
        originalName: item.archivo.nombre_archivo,
        fileName: item.archivo.nombre_archivo,
        url: item.archivo.url_archivo,
        serverUrl: item.archivo.url_archivo,
        size: 0,
      } as NonNullable<typeof slot.file>,
    };
  }

  // Si no hay evidencia en la tabla real, mostrar como sin archivo
  return {
    ...slot,
    file: undefined,
  };
}

// ── I3 slots 1-4: mismo patrón estricto que I2 arriba, pero contra el
// endpoint real de tutorías académicas (evidencia_asignatura + validación
// por puntos). Nunca cae al mecanismo viejo evaluation-wide. ──
if (
  ind.id === "I3" &&
  I3_SOURCE_NUM_TO_TIPO[slot.sourceNum] !== undefined
) {
  const tipo = I3_SOURCE_NUM_TO_TIPO[slot.sourceNum];
  const item = evidenciaTutorias?.find(
    (e: EvidenciaTutoriasItem) => e.tipo === tipo,
  );

  if (item && item.subida && item.archivo) {
    return {
      ...slot,
      file: {
        originalName: item.archivo.nombre_archivo,
        fileName: item.archivo.nombre_archivo,
        url: item.archivo.url_archivo,
        serverUrl: item.archivo.url_archivo,
        size: 0,
      } as NonNullable<typeof slot.file>,
    };
  }

  // Si no hay evidencia en la tabla real, mostrar como sin archivo
  return {
    ...slot,
    file: undefined,
  };
}

/*
 * En Tasa de Titulación el orden visual es:
 * 1 = Matriculados  -> DOC.TIT.02
 * 2 = Graduados     -> DOC.TIT.01
 */
/*
 * Malla Curricular (DOC.SYL.01, sourceNum 7) y Normativa Institucional
 * (DOC.SEG.01, sourceNum 6): ambos evaluation-wide (a nivel carrera+cohorte,
 * NO por asignatura), a diferencia de los slots 1-5 de arriba. Malla es
 * propia del catálogo de I1 y llega a I2 solo vía `compartidas` (regla
 * compartir_catalogo ya existente en la base real); Normativa es propia del
 * catálogo de I2 y llega vía `guardadas`. Se matchea por codigo_evidencia,
 * no por orden, porque ninguno de los dos coincide con su sourceNum visual
 * (DOC.SEG.01 tiene orden=1 en su catálogo, pero ese sourceNum ya lo usa
 * Syllabus).
 *
  * I5 - Tasa de Titulación:
 * 1 = Graduados                    -> DOC.TIT.01
 * 2 = Matriculados de primer nivel -> DOC.TIT.02
 *
 * I4 - Tasa de Deserción:
 * 1 = Matriculados de primer nivel comparte DOC.TIT.02 con I5
 * 2 = Matriculados de segundo nivel conserva su evidencia propia
 * 3 = Estudiantes desertados conserva su evidencia propia
 */
/*
 * Malla curricular compartida:
 * cualquier indicador que use sharedKey="malla_curricular"
 * mostrará DOC.SYL.01, sin importar desde cuál indicador se cargó.
 *
 * Esto cubre:
 * - I1: Malla curricular
 * - I2: Malla curricular
 * - I5: Malla curricular
 *
 * Las demás evidencias compartidas, como DOC.TIT.02 entre I4 e I5,
 * conservan su lógica actual sin cambios.
 */
if (slot.sharedKey === "malla_curricular") {
  propia = guardadas.find(
    (evidencia) =>
      evidencia.codigo_evidencia === "DOC.SYL.01",
  );

  compartida = compartidas.find(
    (evidencia) =>
      evidencia.codigo_evidencia === "DOC.SYL.01",
  );
} else if (ind.id === "I2" && slot.sourceNum === 6) {
  propia = guardadas.find(
    (evidencia) =>
      evidencia.codigo_evidencia === "DOC.SEG.01",
  );

  compartida = compartidas.find(
    (evidencia) =>
      evidencia.codigo_evidencia === "DOC.SEG.01",
  );
} else if (
  ind.id === "I5" &&
  slot.sourceNum === 1
) {
  // Posición 1: estudiantes graduados
  propia = guardadas.find(
    (evidencia) =>
      evidencia.codigo_evidencia ===
      "DOC.TIT.01",
  );

  compartida = compartidas.find(
    (evidencia) =>
      evidencia.codigo_evidencia ===
      "DOC.TIT.01",
  );
} else if (
  ind.id === "I5" &&
  slot.sourceNum === 2
) {
  // Posición 2: estudiantes matriculados
  propia = guardadas.find(
    (evidencia) =>
      evidencia.codigo_evidencia ===
      "DOC.TIT.02",
  );

  compartida = compartidas.find(
    (evidencia) =>
      evidencia.codigo_evidencia ===
      "DOC.TIT.02",
  );
}
  else if (
  ind.id === "I4" &&
  slot.sourceNum === 1
) {
  propia = guardadas.find(
    (evidencia) =>
      evidencia.codigo_evidencia ===
      "DOC.TIT.02",
  );

  compartida = compartidas.find(
    (evidencia) =>
      evidencia.codigo_evidencia ===
      "DOC.TIT.02",
  );
} else if (
  ind.id === "I4" &&
  (slot.sourceNum === 2 ||
    slot.sourceNum === 3)
) {
  /*
   * Segundo nivel y estudiantes desertados: evidencias propias de I4,
   * no se comparten con otros indicadores.
   */
  propia = guardadas.find(
    (evidencia) =>
      Number(evidencia.orden) ===
      Number(slot.sourceNum),
  );

  compartida = undefined;
} else {
  propia = guardadas.find(
    (evidencia) =>
      Number(evidencia.orden) ===
      Number(slot.sourceNum),
  );

  compartida = compartidas.find(
    (evidencia) =>
      Number(evidencia.orden) ===
      Number(slot.sourceNum),
  );
}

const evidencia = propia ?? compartida;

          if (!evidencia) {
            return {
              ...slot,
              file: undefined,
            };
          }

          return {
            ...slot,
            file: {
              originalName: evidencia.nombre_archivo,
              fileName: evidencia.nombre_archivo,
              url: evidencia.url_archivo,
              serverUrl: evidencia.url_archivo,
              type: evidencia.tipo,
              size: 0,
            } as NonNullable<typeof slot.file>,
          };
        });

        setSlots(nuevasSlots);

        const primerArchivo = nuevasSlots.find(
          (slot) => slot.file,
        );

        setSelected((actual) => {
          const seleccionExiste = nuevasSlots.some(
            (slot) =>
              slot.sourceNum === actual &&
              Boolean(slot.file),
          );

          if (seleccionExiste) {
            return actual;
          }

          return primerArchivo?.sourceNum ?? null;
        });
      } catch (error) {
        if (cancelado) {
          return;
        }

        console.error(error);

        setSlots(
          ind.slots.map((slot) => ({ ...slot })),
        );

        toast.error(
          "No se pudieron cargar las evidencias guardadas.",
          {
            description:
              error instanceof Error
                ? error.message
                : "Ocurrio un error inesperado.",
          },
        );
      } finally {
        if (!cancelado) {
          setCargandoEvidencias(false);
        }
      }
    }

    void cargarEvidencias();

    return () => {
      cancelado = true;
    };
  }, [career, cohort, ind, idAsignatura]);

  const selectedSlot = slots.find(
    (slot) => slot.sourceNum === selected,
  );

  const urlDocumento =
    selectedSlot?.file?.serverUrl ||
    selectedSlot?.file?.url ||
    "";

  function convertirUrlVistaPrevia(
    url: string,
  ): string {
    const coincidencia = url.match(
      /drive\.google\.com\/file\/d\/([^/]+)/,
    );

    if (!coincidencia) {
      return url;
    }

    return `https://drive.google.com/file/d/${coincidencia[1]}/preview`;
  }

  const urlVistaPrevia =
    convertirUrlVistaPrevia(urlDocumento);

  const hasFile = Boolean(
    selectedSlot?.file && urlDocumento,
  );

  function abrirDocumento() {
    if (!urlDocumento) {
      toast.error(
        "La evidencia no contiene una URL válida.",
      );
      return;
    }

    window.open(
      urlDocumento,
      "_blank",
      "noopener,noreferrer",
    );
  }

  return (
    <div className="h-full flex px-6 py-4 max-w-5xl mx-auto gap-4 overflow-hidden">
      <div className="w-80 flex-shrink-0 flex flex-col overflow-hidden gap-2">
        {mostrarAsignatura && (
          <div
            className="bg-white rounded-xl px-3 py-2 flex items-center gap-2 flex-shrink-0"
            style={{
              border: "1px solid rgba(27,58,107,0.08)",
            }}
          >
            <BookOpen size={13} style={{ color: "#1B3A6B", flexShrink: 0 }} />
            <div className="min-w-0">
              <p
                className="text-[10px] font-bold uppercase tracking-wide"
                style={{ color: "#5A7295" }}
              >
                Asignatura
              </p>
              <p
                className="text-xs font-semibold truncate"
                style={{ color: "#0F1E3C" }}
                title={nombreAsignatura ?? undefined}
              >
                {nombreAsignatura}
              </p>
            </div>
          </div>
        )}

        <div
          className="bg-white rounded-2xl overflow-hidden flex-1 flex flex-col"
          style={{
            border:
              "1px solid rgba(27,58,107,0.08)",
          }}
        >
          <div
            className="px-4 py-3 flex-shrink-0 flex items-center justify-between"
            style={{
              borderBottom:
                "1px solid rgba(27,58,107,0.07)",
              background: "#F8FAFD",
            }}
          >
            <div>
              <h3
                className="text-xs font-bold"
                style={{
                  fontFamily:
                    "'Libre Baskerville',serif",
                  color: "#0F1E3C",
                }}
              >
                Fuentes de información
              </h3>

              <p
                className="text-xs mt-0.5"
                style={{ color: "#5A7295" }}
              >
                {
                  slots.filter(
                    (slot) => slot.file,
                  ).length
                }
                /{slots.length} cargadas
              </p>
            </div>

            <span
              className="text-xs font-bold uppercase tracking-widest"
              style={{ color: "#5A7295" }}
            >
              Estado
            </span>
          </div>

          {cargandoEvidencias ? (
            <div className="flex-1 flex items-center justify-center px-5 text-center">
              <div>
                <div
                  className="w-7 h-7 mx-auto mb-3 rounded-full border-2 border-t-transparent animate-spin"
                  style={{
                    borderColor:
                      "#1B3A6B transparent #1B3A6B #1B3A6B",
                  }}
                />

                <p
                  className="text-xs font-semibold"
                  style={{ color: "#5A7295" }}
                >
                  Consultando evidencias...
                </p>
              </div>
            </div>
          ) : (
            <div
              className="flex-1 overflow-auto divide-y"
              style={{
                borderColor:
                  "rgba(27,58,107,0.06)",
              }}
            >
              {slots.map((slot) => {
                const active =
                  selected === slot.sourceNum;

                return (
                  <button
                    key={slot.sourceNum}
                    type="button"
                    onClick={() =>
                      setSelected(slot.sourceNum)
                    }
                    className="w-full text-left px-4 py-3 flex items-center justify-between gap-3 transition-colors hover:bg-blue-50"
                    style={{
                      background: active
                        ? "#EEF2F7"
                        : "transparent",
                      borderLeft: active
                        ? "3px solid #1B3A6B"
                        : "3px solid transparent",
                    }}
                  >
                    <p
                      className="text-xs font-semibold leading-snug flex-1"
                      style={{
                        color: active
                          ? "#1B3A6B"
                          : "#0F1E3C",
                      }}
                    >
                      {slot.label}
                    </p>

                    {slot.file ? (
                      <span
                        className="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0"
                        style={{
                          background: "#DCFCE7",
                          color: "#15803D",
                        }}
                      >
                        Cargado
                      </span>
                    ) : (
                      <span
                        className="text-xs font-semibold px-2 py-0.5 rounded-full flex-shrink-0"
                        style={{
                          background: "#FEF9C3",
                          color: "#92400E",
                        }}
                      >
                        Pendiente
                      </span>
                    )}
                  </button>
                );
              })}
            </div>
          )}
        </div>

        {puedeCargar && (
        <button
          type="button"
          onClick={() =>
            onUpload
              ? onUpload()
              : toast.info(
                  "Use el botón 'Cargar evidencias' desde el panel principal",
                )
          }
          className="w-full flex items-center justify-center gap-1.5 py-2.5 rounded-xl text-xs font-bold transition-all hover:opacity-90"
          style={{
            background: "#1B3A6B",
            color: "#fff",
          }}
        >
          <Upload size={12} />
          Cargar evidencias
        </button>
        )}
      </div>

      <div
        className="flex-1 flex flex-col overflow-hidden bg-white rounded-2xl"
        style={{
          border:
            "1px solid rgba(27,58,107,0.08)",
        }}
      >
        <div
          className="px-5 py-3 flex items-center justify-between flex-shrink-0"
          style={{
            borderBottom:
              "1px solid rgba(27,58,107,0.07)",
            background: "#F8FAFD",
          }}
        >
          <div className="min-w-0">
            <h3
              className="text-xs font-bold"
              style={{ color: "#0F1E3C" }}
            >
              {selectedSlot
                ? selectedSlot.label
                : "Vista previa"}
            </h3>

            {selectedSlot?.file && (
              <p
                className="text-xs mt-0.5 font-mono truncate"
                style={{ color: "#5A7295" }}
              >
                {selectedSlot.file.fileName}
              </p>
            )}
          </div>

          {hasFile && (
            <button
              type="button"
              onClick={abrirDocumento}
              className="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold transition-all hover:opacity-90"
              style={{
                background: "#1B3A6B",
                color: "#fff",
              }}
            >
              <ExternalLink size={11} />
              Abrir documento
            </button>
          )}
        </div>

        <div className="flex-1 overflow-hidden flex items-center justify-center min-h-0">
          {cargandoEvidencias ? (
            <div className="text-center px-8">
              <p
                className="text-sm font-medium"
                style={{ color: "#6B7280" }}
              >
                Cargando documentos...
              </p>
            </div>
          ) : hasFile && selectedSlot?.file ? (
            <iframe
              key={urlVistaPrevia}
              src={urlVistaPrevia}
              className="w-full h-full"
              title={selectedSlot.file.fileName}
              style={{ border: "none" }}
              allow="autoplay"
            />
          ) : selectedSlot ? (
            <div className="text-center px-8">
              <AlertCircle
                size={40}
                className="mx-auto mb-3"
                style={{ color: "#D1D5DB" }}
              />

              <p
                className="text-sm font-medium"
                style={{ color: "#6B7280" }}
              >
                Sin documento cargado
              </p>

              <p
                className="text-xs mt-1"
                style={{ color: "#9CA3AF" }}
              >
                Esta fuente aún no tiene archivo.
                Use “Cargar evidencias”.
              </p>
            </div>
          ) : (
            <div className="text-center px-8">
              <FolderOpen
                size={40}
                className="mx-auto mb-3"
                style={{ color: "#D1D5DB" }}
              />

              <p
                className="text-sm font-medium"
                style={{ color: "#6B7280" }}
              >
                Seleccione una fuente
              </p>

              <p
                className="text-xs mt-1"
                style={{ color: "#9CA3AF" }}
              >
                Haga clic en una fuente para
                visualizar el documento.
              </p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

// ── Tab Ficha técnica ──────────────────────────────────────────────────────
// ── EF criteria for I2 (Seguimiento de Syllabus) — 5 EFs, pesos ordinales ──
const I2_EF_CRITERIA = [
  {
    id: "EF1", weight: 0.33, label: "Seguimiento de contenidos",
    desc: "Se verifica que el docente haya cubierto los contenidos planificados en el sílabo durante el período académico, con respaldo documental de las sesiones ejecutadas.",
    checks: [
      "Registro de clases ejecutadas vs. planificadas en el sílabo",
      "Porcentaje de contenidos cubiertos por unidad temática",
      "Actas o informes de avance curricular firmados",
      "Evidencia de recuperación pedagógica si hubo desfase",
    ],
    rule: "El docente acredita cobertura de los contenidos del sílabo con documentos de respaldo",
  },
  {
    id: "EF2", weight: 0.27, label: "Mejora del micro currículo",
    desc: "Se evalúa si el docente realizó ajustes fundamentados al micro currículo (sílabo) con base en los resultados de aprendizaje obtenidos y las necesidades detectadas.",
    checks: [
      "Propuesta de mejora o actualización del sílabo presentada",
      "Justificación académica de los cambios realizados",
      "Aprobación por la coordinación académica competente",
      "Relación entre ajustes y resultados de aprendizaje previos",
    ],
    rule: "Existe propuesta de mejora documentada y aprobada para el sílabo",
  },
  {
    id: "EF3", weight: 0.20, label: "Proceso difundido",
    desc: "Se comprueba que el proceso de seguimiento del sílabo haya sido comunicado formalmente a los estudiantes y a las instancias académicas responsables.",
    checks: [
      "Acta o registro de socialización del sílabo con estudiantes",
      "Comunicación formal a la coordinación académica",
      "Evidencia de que los estudiantes conocen los criterios de evaluación",
    ],
    rule: "El proceso de seguimiento fue comunicado y documentado formalmente",
  },
  {
    id: "EF4", weight: 0.13, label: "Difusión del sílabo en EVA",
    desc: "Se constata que el sílabo de la asignatura se encuentre publicado y vigente en el Entorno Virtual de Aprendizaje (EVA) institucional durante el período evaluado.",
    checks: [
      "Sílabo publicado en la plataforma EVA institucional",
      "Fecha de publicación dentro del período académico",
      "El sílabo es la versión vigente y aprobada",
    ],
    rule: "El sílabo está publicado en EVA durante el período evaluado",
  },
  {
    id: "EF5", weight: 0.07, label: "Normativa institucional",
    desc: "Se verifica que el sílabo y su proceso de seguimiento cumplan con los lineamientos, formatos y disposiciones establecidas por la normativa interna del instituto.",
    checks: [
      "Sílabo elaborado en el formato oficial del instituto",
      "Cumplimiento de la estructura mínima exigida",
      "Aprobación del sílabo por la autoridad académica competente",
    ],
    rule: "El sílabo y su seguimiento cumplen la normativa interna vigente",
  },
];

const I2_EF_COLORS = ["#2563EB", "#16A34A", "#0891B2", "#CA8A04", "#7C3AED"];

// EF evaluation criteria shown in I3 Ficha Técnica
const I3_EF_CRITERIA = [
  {
    id: "EF1", weight: 0.40, label: "Revisión documental",
    desc: "El evaluador verifica que las evidencias presentadas estén completas, elaboradas en el formato oficial institucional y con fecha vigente dentro del período evaluado.",
    checks: [
      "Documentos completos y sin páginas faltantes",
      "Formato oficial del instituto (membrete, código)",
      "Fecha dentro del período académico evaluado",
      "Firma del docente y del responsable académico",
    ],
    rule: "Presencia de al menos 1 documento válido y vigente",
  },
  {
    id: "EF2", weight: 0.30, label: "Coherencia interna",
    desc: "Se comprueba que los datos consignados en las evidencias sean consistentes entre sí y con los registros del sistema académico institucional.",
    checks: [
      "Horas de tutoría declaradas coinciden con el sistema",
      "Número de estudiantes atendidos es consistente",
      "Fechas de las sesiones no se superponen ni contradicen",
      "Firmas y registros de asistencia concuerdan",
    ],
    rule: "Los datos del documento coinciden con registros del sistema",
  },
  {
    id: "EF3", weight: 0.20, label: "Pertinencia",
    desc: "Se valida que las evidencias presentadas correspondan efectivamente al período de evaluación definido por el modelo CACES y a la asignatura declarada.",
    checks: [
      "Período académico corresponde al evaluado (PAO correcto)",
      "La asignatura declarada coincide con la malla vigente",
      "El tipo de tutoría es pertinente al nivel de instrucción",
    ],
    rule: "Evidencias pertenecen al período y asignatura correctos",
  },
  {
    id: "EF4", weight: 0.10, label: "Normativa institucional",
    desc: "Se verifica que el proceso de tutorías se haya llevado a cabo conforme al reglamento interno y las disposiciones institucionales vigentes.",
    checks: [
      "Proceso cumple el reglamento interno de tutorías",
      "Número mínimo de horas reglamentarias cumplido",
      "Evidencia de socialización del plan de tutorías",
    ],
    rule: "El proceso sigue la normativa institucional vigente",
  },
];

const EF_COLORS = ["#0891B2", "#7C3AED", "#16A34A", "#EA580C"];

function TabFicha({ ind }: { ind: IndicatorDef }) {
  const scale = [
    { label: "Satisfactorio",       range: "75 – 100%", color: "#16A34A", bg: "#DCFCE7" },
    { label: "Cuasi Satisfactorio", range: "50 – 74%",  color: "#CA8A04", bg: "#FEF9C3" },
    { label: "Poco Satisfactorio",  range: "25 – 49%",  color: "#EA580C", bg: "#FFEDD5" },
    { label: "Deficiente",          range: "0 – 24%",   color: "#DC2626", bg: "#FEE2E2" },
  ];

  const tipoMap: Record<string, { label: string; bg: string; color: string }> = {
    I1: { label: "Cuantitativo", bg: "#DBEAFE", color: "#1D4ED8" },
    I2: { label: "Cualitativo",  bg: "#EDE9FE", color: "#7C3AED" },
    I3: { label: "Cualitativo",  bg: "#EDE9FE", color: "#7C3AED" },
    I4: { label: "Cuantitativo", bg: "#DBEAFE", color: "#1D4ED8" },
    I5: { label: "Cuantitativo", bg: "#DBEAFE", color: "#1D4ED8" },
  };
  const tipo = tipoMap[ind.id] || { label: "Cuantitativo", bg: "#DBEAFE", color: "#1D4ED8" };

  const isI3 = ind.id === "I3";
  const isI2 = ind.id === "I2";
  const useDetailedLayout = isI2 || isI3;
  // EF config for the detailed layout
  const efCriteria = isI3 ? I3_EF_CRITERIA : I2_EF_CRITERIA;
  const efColors   = isI3 ? EF_COLORS      : I2_EF_COLORS;

  return (
    <div className="h-full flex flex-col px-6 py-4 max-w-6xl mx-auto overflow-hidden gap-3">

      {/* ── Banner ───────────────────────────────────────────────────── */}
      <div className="flex-shrink-0 rounded-2xl px-6 py-4 flex items-center justify-between gap-6"
        style={{ background: "linear-gradient(135deg,#0F2556,#1B3A6B)", color: "#fff" }}>
        <div>
          <h2 className="text-xl font-bold" style={{ fontFamily: "'Libre Baskerville',serif" }}>{ind.name}</h2>
          <div className="inline-flex items-center gap-2 mt-2 px-3 py-1.5 rounded-xl" style={{ background: "rgba(255,255,255,0.12)" }}>
            <span className="text-xs text-blue-300 font-semibold">Fórmula:</span>
            <span className="text-sm font-semibold" style={{ fontFamily: "'DM Mono',monospace" }}>
              {useDetailedLayout
                ? efCriteria.map((ef, i) =>
                    `${ef.id}(×${ef.weight.toFixed(2)})${i < efCriteria.length - 1 ? " + " : ""}`
                  ).join("") + ` = Valor × 100`
                : ind.formula}
            </span>
          </div>
        </div>
        <span className="flex-shrink-0 px-3 py-1.5 rounded-full text-sm font-bold"
          style={{ background: "rgba(255,255,255,0.15)", color: "#fff" }}>{tipo.label}</span>
      </div>

      {/* ── Content rows ─────────────────────────────────────────────── */}
      {useDetailedLayout ? (
        /* I2/I3: two-box layout */
        <div className="flex gap-3 flex-1 min-h-0 overflow-hidden">

          {/* ── Primer cuadro: Descripción + Para qué sirve ─────────── */}
          <div className="flex flex-col bg-white rounded-2xl overflow-hidden min-h-0"
            style={{ width: "38%", flexShrink: 0, border: "1px solid rgba(27,58,107,0.08)" }}>
            {/* Descripción */}
            <div className="flex-1 p-5 flex flex-col min-h-0">
              <p className="text-xs font-bold uppercase tracking-widest mb-2 flex-shrink-0" style={{ color: "#5A7295" }}>Descripción</p>
              <p className="text-sm leading-relaxed overflow-auto" style={{ color: "#1F2937" }}>{ind.description}</p>
            </div>
            {/* Divider */}
            <div className="flex-shrink-0 mx-5" style={{ height: 1, background: "rgba(27,58,107,0.08)" }} />
            {/* Para qué sirve */}
            <div className="flex-1 p-5 flex flex-col min-h-0">
              <p className="text-xs font-bold uppercase tracking-widest mb-2 flex-shrink-0" style={{ color: "#5A7295" }}>Para qué sirve</p>
              <p className="text-sm leading-relaxed overflow-auto" style={{ color: "#1F2937" }}>{ind.purpose}</p>
            </div>
          </div>

          {/* ── Segundo cuadro: Escala + 4 EF detallados ────────────── */}
          <div className="flex-1 flex flex-col gap-0 bg-white rounded-2xl overflow-hidden min-h-0"
            style={{ border: "1px solid rgba(27,58,107,0.08)" }}>

            {/* Escala — 4 cuadros del mismo tamaño */}
            <div className="flex-shrink-0 px-5 py-3"
              style={{ borderBottom: "1px solid rgba(27,58,107,0.07)", background: "#F8FAFD" }}>
              <p className="text-xs font-bold uppercase tracking-widest mb-2" style={{ color: "#5A7295" }}>
                Escala de valoración
              </p>
              <div className="grid grid-cols-4 gap-2">
                {scale.map((sc) => (
                  <div key={sc.label} className="flex flex-col items-center justify-center gap-1 rounded-xl py-3 px-2 text-center"
                    style={{ background: sc.bg, border: `2px solid ${sc.color}40` }}>
                    <div className="w-3 h-3 rounded-full flex-shrink-0" style={{ background: sc.color }} />
                    <span className="text-xs font-bold leading-tight" style={{ color: sc.color }}>{sc.label}</span>
                    <span className="text-xs font-mono font-bold" style={{ color: sc.color, opacity: 0.8 }}>{sc.range}</span>
                  </div>
                ))}
              </div>
            </div>

            {/* EF cards — apilados verticalmente, cada uno con checks y regla */}
            <div className="flex-1 overflow-auto px-4 py-3 flex flex-col gap-2.5">
              {efCriteria.map((ef, i) => (
                <div key={ef.id} className="rounded-xl overflow-hidden flex-shrink-0"
                  style={{ border: `1.5px solid ${efColors[i]}30` }}>

                  {/* EF header row */}
                  <div className="flex items-center gap-2.5 px-4 py-2.5"
                    style={{ background: `${efColors[i]}10` }}>
                    <span className="text-xs font-bold px-2 py-0.5 rounded-lg flex-shrink-0"
                      style={{ background: efColors[i], color: "#fff", fontFamily: "'DM Mono',monospace" }}>
                      {ef.id}
                    </span>
                    <span className="text-sm font-bold flex-1" style={{ color: efColors[i] }}>{ef.label}</span>
                    <span className="text-xs font-bold px-2 py-0.5 rounded-md flex-shrink-0"
                      style={{ background: `${efColors[i]}20`, color: efColors[i], fontFamily: "'DM Mono',monospace" }}>
                      Peso {(ef.weight * 100).toFixed(0)}%
                    </span>
                  </div>

                  {/* Body */}
                  <div className="px-4 py-3 flex gap-4" style={{ background: "#FAFBFD" }}>
                    {/* Left: descripción + regla de calificación */}
                    <div className="flex-1 min-w-0 flex flex-col gap-2">
                      <p className="text-xs leading-snug" style={{ color: "#374151" }}>{ef.desc}</p>
                      <div className="flex items-start gap-1.5 mt-0.5 px-2.5 py-1.5 rounded-lg"
                        style={{ background: "#DCFCE7", border: "1px solid #16A34A30" }}>
                        <span className="text-xs font-bold flex-shrink-0" style={{ color: "#16A34A" }}>✓ Califica si:</span>
                        <span className="text-xs leading-snug" style={{ color: "#15803D" }}>{ef.rule}</span>
                      </div>
                    </div>
                    {/* Right: checklist */}
                    <div className="flex-shrink-0 flex flex-col gap-1" style={{ minWidth: 200 }}>
                      <p className="text-xs font-bold uppercase tracking-widest mb-0.5" style={{ color: "#9CA3AF" }}>Qué se verifica</p>
                      {ef.checks.map((c, ci) => (
                        <div key={ci} className="flex items-start gap-1.5">
                          <div className="w-1 h-1 rounded-full flex-shrink-0 mt-1.5" style={{ background: efColors[i] }} />
                          <span className="text-xs leading-snug" style={{ color: "#4B5563" }}>{c}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      ) : (
        /* I1/I4/I5: original 3-column layout */
        <div className="flex gap-3 flex-1 min-h-0 overflow-hidden">
          <div className="flex-1 bg-white rounded-2xl p-5 overflow-hidden" style={{ border: "1px solid rgba(27,58,107,0.08)" }}>
            <p className="text-xs font-bold uppercase tracking-widest mb-2" style={{ color: "#5A7295" }}>Descripción</p>
            <p className="text-sm leading-relaxed" style={{ color: "#1F2937" }}>{ind.description}</p>
          </div>
          <div className="flex-1 bg-white rounded-2xl p-5 overflow-hidden" style={{ border: "1px solid rgba(27,58,107,0.08)" }}>
            <p className="text-xs font-bold uppercase tracking-widest mb-2" style={{ color: "#5A7295" }}>Para qué sirve</p>
            <p className="text-sm leading-relaxed" style={{ color: "#1F2937" }}>{ind.purpose}</p>
          </div>
          <div className="flex-1 flex flex-col gap-3 min-h-0">
            <div className="flex-shrink-0 bg-white rounded-2xl px-4 py-3" style={{ border: "1px solid rgba(27,58,107,0.08)" }}>
              <p className="text-xs font-bold uppercase tracking-widest mb-2" style={{ color: "#5A7295" }}>Tipo de indicador</p>
              <span className="inline-block px-3 py-1 rounded-full text-sm font-bold" style={{ background: tipo.bg, color: tipo.color }}>{tipo.label}</span>
            </div>
            <div className="flex-1 bg-white rounded-2xl px-4 py-3 flex flex-col min-h-0" style={{ border: "1px solid rgba(27,58,107,0.08)" }}>
              <p className="text-xs font-bold uppercase tracking-widest mb-2 flex-shrink-0" style={{ color: "#5A7295" }}>Escala de calificación</p>
              <div className="flex flex-col flex-1 gap-1.5 justify-around">
                {scale.map((sc) => (
                  <div key={sc.label} className="rounded-xl px-3 py-2 flex items-center gap-2.5"
                    style={{ background: sc.bg, border: `1.5px solid ${sc.color}30` }}>
                    <div className="w-2.5 h-2.5 rounded-full flex-shrink-0" style={{ background: sc.color }} />
                    <div className="flex items-center justify-between flex-1">
                      <p className="text-xs font-bold" style={{ color: sc.color }}>{sc.label}</p>
                      <p className="text-xs font-mono" style={{ color: sc.color, opacity: 0.75 }}>{sc.range}</p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ── Metadata strip ───────────────────────────────────────────── */}
      <div className="bg-white rounded-2xl px-5 py-3 flex-shrink-0 flex items-center gap-5 flex-wrap" style={{ border: "1px solid rgba(27,58,107,0.08)" }}>
        {[
          { label: "Período",    value: ind.period },
          { label: "Fuentes",    value: `${ind.slots.length} documentos PDF` },
          { label: "Indicador",  value: ind.code },
        ].map((m) => (
          <div key={m.label} className="flex items-center gap-2">
            <span className="text-xs font-bold" style={{ color: "#5A7295" }}>{m.label}:</span>
            <span className="text-xs px-2 py-0.5 rounded-md font-semibold" style={{ background: "#EEF2F7", color: "#1B3A6B" }}>{m.value}</span>
          </div>
        ))}
      </div>
    </div>
  );
}