import { useEffect, useState } from 'react';

import {
  ArrowLeft,
  BookOpen,
  CheckCircle2,
  ChevronDown,
  LogOut,
  ShieldCheck,
  Upload,
  User,
} from 'lucide-react';

import IndCard from '../../shared/components/IndCard';
import PaoGroupCard from '../../shared/components/PaoGroupCard';
import { listarCohortesEvaluaciones, type CohorteEvaluacion } from '../../shared/services/cohortes';

import { obtenerEvaluacion } from '../../shared/services/evidencias';
import { obtenerPeriodos, obtenerResultadoCohorte } from '../../shared/services/seguimientoSyllabus';
import { obtenerResultadoCohorteTutorias } from '../../shared/services/tutoriasAcademicas';

import type { UsuarioSesion } from '../../shared/services/auth';
import type { Career, IndicatorDef } from '../../types/index';

interface DashboardViewProps {
  indicators: IndicatorDef[];
  career: Career;
  onSelect: (id: string, pao?: number) => void;
  onLogout: () => void;
  onUpload: () => void;
  cohort: string;
  onCohortChange: (cohort: string) => void;
  onBackToCareers: () => void;
  usuario: UsuarioSesion;
  puedeCargar: boolean;
}

export default function DashboardView({
  indicators,
  career,
  onSelect,
  onLogout,
  onUpload,
  cohort,
  onCohortChange,
  onBackToCareers,
  usuario,
  puedeCargar,
}: DashboardViewProps) {
  const [cohortOpen, setCohortOpen] = useState(false);
  const [cohortes, setCohortes] = useState<CohorteEvaluacion[]>([]);
  // Mientras CareerLayout resuelve la cohorte real por defecto (o si la
  // carrera todavía no tiene ninguna), `cohort` llega vacío — se muestra
  // este texto en vez de "Cohorte " a secas.
  const etiquetaCohorte = cohort ? `Cohorte ${cohort}` : 'No hay cohortes creadas';
  // ── PAOs reales para I2 (Seguimiento de Syllabus) ──────────────────
  const PAO_INICIAL = [
    { pao: 'PAO 1', pct: -1 },
    { pao: 'PAO 2', pct: -1 },
    { pao: 'PAO 3', pct: -1 },
  ];
  const [i2PaoScores, setI2PaoScores] = useState(PAO_INICIAL);

  useEffect(() => {
    let cancelado = false;

    async function cargarCohortes() {
      try {
        const datos = await listarCohortesEvaluaciones();

        if (cancelado) return;

        setCohortes(datos.filter((c) => c.codigo_carrera === career.code && c.estado === 'Activa'));
      } catch (error) {
        console.error(error);
      }
    }

    void cargarCohortes();

    return () => {
      cancelado = true;
    };
  }, [career.code]);

  useEffect(() => {
    let cancelado = false;

    async function cargarPAOsReales() {
      try {
        const evaluacion = await obtenerEvaluacion(career.code, cohort.replace(/\s+/g, ''));

        const periodos = await obtenerPeriodos(evaluacion.id_cohorte);

        const resultados = await Promise.all(
          [1, 2, 3].map(async (orden) => {
            const periodo = periodos.find((p) => p.orden === orden);

            if (!periodo) {
              return { pao: `PAO ${orden}`, pct: -1 };
            }

            const resultado = await obtenerResultadoCohorte(
              evaluacion.id_cohorte,
              evaluacion.id_evaluacion,
              periodo.id_periodoacademico,
            );

            return {
              pao: `PAO ${orden}`,
              pct: resultado?.valoracion_general ?? -1,
            };
          }),
        );

        if (cancelado) {
          return;
        }

        setI2PaoScores(resultados);
      } catch (error) {
        if (cancelado) {
          return;
        }

        console.error('Error al cargar PAOs reales para I2:', error);
        setI2PaoScores(PAO_INICIAL);
      }
    }

    void cargarPAOsReales();

    return () => {
      cancelado = true;
    };
  }, [career, cohort]);

  // ── PAOs reales para I3 (Tutorías Académicas) ──────────────────────
  const [i3PaoScores, setI3PaoScores] = useState(PAO_INICIAL);

  useEffect(() => {
    let cancelado = false;

    async function cargarPAOsRealesI3() {
      try {
        const evaluacion = await obtenerEvaluacion(career.code, cohort.replace(/\s+/g, ''));

        const periodos = await obtenerPeriodos(evaluacion.id_cohorte);

        const resultados = await Promise.all(
          [1, 2, 3].map(async (orden) => {
            const periodo = periodos.find((p) => p.orden === orden);

            if (!periodo) {
              return { pao: `PAO ${orden}`, pct: -1 };
            }

            const resultado = await obtenerResultadoCohorteTutorias(
              evaluacion.id_cohorte,
              evaluacion.id_evaluacion,
              periodo.id_periodoacademico,
            );

            return {
              pao: `PAO ${orden}`,
              pct: resultado?.valoracion_general ?? -1,
            };
          }),
        );

        if (cancelado) {
          return;
        }

        setI3PaoScores(resultados);
      } catch (error) {
        if (cancelado) {
          return;
        }

        console.error('Error al cargar PAOs reales para I3:', error);
        setI3PaoScores(PAO_INICIAL);
      }
    }

    void cargarPAOsRealesI3();

    return () => {
      cancelado = true;
    };
  }, [career, cohort]);

  return (
    <div
      className="h-screen flex flex-col overflow-hidden"
      style={{
        background: '#EEF2F7',
        fontFamily: "'Plus Jakarta Sans',sans-serif",
      }}
    >
      <div
        className="flex-shrink-0 border-b flex items-center justify-between px-6"
        style={{
          height: 52,
          background: '#fff',
          borderColor: 'rgba(27,58,107,0.1)',
        }}
      >
        <div className="flex items-center gap-3">
          <div
            className="w-7 h-7 rounded-lg flex items-center justify-center"
            style={{ background: '#1B3A6B' }}
          >
            <ShieldCheck size={13} className="text-white" />
          </div>

          <span className="font-bold text-sm" style={{ color: '#0F1E3C' }}>
            CACES · UAFTT
          </span>

          <button
            type="button"
            onClick={onBackToCareers}
            className="flex items-center gap-1 text-xs font-medium px-2.5 py-1.5 rounded-lg hover:bg-blue-50 transition-colors"
            style={{ color: '#1B3A6B' }}
          >
            <ArrowLeft size={12} />
            Carreras
          </button>

          <span className="hidden sm:inline text-xs" style={{ color: '#9CA3AF' }}>
            — {career.name}
          </span>
        </div>

        <div className="flex items-center gap-2">
          <div className="relative">
            <button
              type="button"
              onClick={() => setCohortOpen(!cohortOpen)}
              className="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all hover:bg-blue-50"
              style={{
                background: '#F4F7FB',
                borderColor: 'rgba(27,58,107,0.2)',
                color: '#1B3A6B',
              }}
            >
              <span className="w-1.5 h-1.5 rounded-full" style={{ background: '#1B3A6B' }} />
              {etiquetaCohorte}
              <ChevronDown
                size={12}
                className={`transition-transform ${cohortOpen ? 'rotate-180' : ''}`}
              />
            </button>

            {cohortOpen && (
              <div
                className="absolute right-0 mt-1 w-40 bg-white rounded-xl shadow-lg overflow-hidden z-50"
                style={{
                  border: '1px solid rgba(27,58,107,0.12)',
                }}
              >
                {cohortes.length === 0 ? (
                  <div
                    className="px-4 py-2.5 text-xs font-medium"
                    style={{ color: '#9CA3AF' }}
                  >
                    No hay cohortes creadas
                  </div>
                ) : (
                  cohortes.map((item) => (
                    <button
                      key={item.id_cohorte}
                      type="button"
                      onClick={() => {
                        onCohortChange(item.nombre_cohorte);
                        setCohortOpen(false);
                      }}
                      className="w-full text-left px-4 py-2.5 text-xs font-semibold hover:bg-blue-50 transition-colors flex items-center justify-between"
                      style={{
                        color: item.nombre_cohorte === cohort ? '#1B3A6B' : '#374151',
                        background: item.nombre_cohorte === cohort ? '#EEF2F7' : 'transparent',
                      }}
                    >
                      Cohorte {item.nombre_cohorte}
                      {item.nombre_cohorte === cohort && (
                        <CheckCircle2 size={12} style={{ color: '#1B3A6B' }} />
                      )}
                    </button>
                  ))
                )}
              </div>
            )}
          </div>

          {puedeCargar && (
            <button
              type="button"
              onClick={onUpload}
              className="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold transition-all hover:opacity-90 active:scale-95"
              style={{
                background: '#1B3A6B',
                color: '#fff',
              }}
            >
              <Upload size={12} />
              Cargar evidencias
            </button>
          )}

          <div className="hidden lg:flex items-center gap-2 px-2">
            <User size={12} style={{ color: '#5A7295' }} />
            <div className="leading-tight text-right">
              <p className="text-xs font-semibold" style={{ color: '#0F1E3C' }}>
                {usuario.nombres} {usuario.apellidos}
              </p>
              <p className="text-[10px] capitalize" style={{ color: '#5A7295' }}>
                {usuario.rol}
              </p>
            </div>
          </div>

          <button
            type="button"
            onClick={onLogout}
            className="flex items-center gap-1 text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-red-50 hover:text-red-600 transition-colors"
            style={{ color: '#5A7295' }}
          >
            <LogOut size={12} />
            Salir
          </button>
        </div>
      </div>

      <div className="flex-shrink-0 px-6 pt-4 pb-2">
        <div className="flex items-center gap-2 mb-0.5">
          <BookOpen size={12} style={{ color: '#2563EB' }} />
          <span
            className="text-xs font-bold uppercase tracking-widest"
            style={{ color: '#2563EB' }}
          >
            Criterio de evaluación
          </span>
          <span
            className="ml-2 text-xs px-2 py-0.5 rounded-full font-semibold"
            style={{
              background: '#EEF2F7',
              color: '#1B3A6B',
            }}
          >
            {etiquetaCohorte}
          </span>
        </div>

        <h1
          className="text-2xl font-bold"
          style={{
            fontFamily: "'Libre Baskerville',serif",
            color: '#0F1E3C',
          }}
        >
          Docencia
        </h1>
      </div>

      <div className="flex-1 min-h-0 px-6 pb-5 flex flex-col gap-3 overflow-hidden">
        <div className="flex gap-3 flex-1 min-h-0">
          <PaoGroupCard ind={indicators[0]} onClick={onSelect} />
          <PaoGroupCard ind={indicators[1]} onClick={onSelect} paosOverride={i2PaoScores} />
        </div>

        <div className="flex gap-3 flex-1 min-h-0">
          <div className="h-full" style={{ flex: 2, minWidth: 0 }}>
            <PaoGroupCard
              ind={indicators[2]}
              onClick={onSelect}
              fullHeight
              paosOverride={i3PaoScores}
            />
          </div>

          <IndCard ind={indicators[3]} onClick={() => onSelect(indicators[3].id)} />

          <IndCard ind={indicators[4]} onClick={() => onSelect(indicators[4].id)} />
        </div>
      </div>

      {cohortOpen && <div className="fixed inset-0 z-40" onClick={() => setCohortOpen(false)} />}
    </div>
  );
}
