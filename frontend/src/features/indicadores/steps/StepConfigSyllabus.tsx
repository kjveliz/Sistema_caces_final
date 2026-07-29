import { useEffect, useState } from 'react';
import { ChevronDown } from 'lucide-react';

import EvidenceHeader from '../../../shared/components/EvidenceHeader';
import Breadcrumb from '../../../shared/components/Breadcrumb';

import BtnGroup from '../components/BtnGroup';

import { listarCohortesEvaluaciones } from '../../../shared/services/cohortes';
import { obtenerAsignaturas, obtenerPeriodos } from '../../../shared/services/seguimientoSyllabus';

import type { Career, IndicatorDef } from '../../../types/index';

/**
 * Reemplaza los mocks `COHORT_OPTIONS`/`MATERIAS_BY_PAO_MODULE` de
 * `shared/data/academic.ts` (calcados letra por letra de una sola malla,
 * DS_MALLA.xlsx) por datos reales por carrera -- Parte "3.5" del plan de
 * malla curricular xlsx (ver plan_malla_curricular_xlsx.txt §3.5 y §0: el
 * "módulo" no es un concepto de negocio de I1, es puramente un agrupador
 * visual para no listar todas las asignaturas del período de una sola vez).
 *
 * El PAO sigue siendo una lista fija ["PAO 1", "PAO 2", "PAO 3"]: no es un
 * mock de una carrera particular, es la convención de nombre que ya usa
 * parsearMallaCurricular() (Parte C) y la resolución de asignaturaId en
 * useCatalogoEvidencias.ts (que ya asume exactamente 3 PAO por cohorte,
 * buscando el período por `orden`). Lo mismo para el rótulo de módulo A/B/C:
 * son las mismas 3 letras que ya reconoce parsearMallaCurricular(); lo único
 * que cambia es DE DÓNDE sale la lista de materias de cada módulo.
 */
interface CohorteReal {
  idCohorte: number;
  nombreCohorte: string;
}

export default function StepConfigSyllabus({
  career,
  indicator,
  preselectedIndicatorId,
  onBack,
  onBackToSelectIndicator,
  cohort,
  setCohort,
  pao,
  setPao,
  module,
  setModule,
  materia,
  setMateria,
  onContinue,
}: {
  career: Career;
  indicator: IndicatorDef | undefined;
  preselectedIndicatorId?: string;
  onBack: () => void;
  onBackToSelectIndicator: () => void;
  cohort: string;
  setCohort: (v: string) => void;
  pao: string;
  setPao: (v: string) => void;
  module: string;
  setModule: (v: string) => void;
  materia: string;
  setMateria: (v: string) => void;
  onContinue: () => void;
}) {
  const [cohortesReales, setCohortesReales] = useState<CohorteReal[]>([]);
  const [cargandoCohortes, setCargandoCohortes] = useState(true);
  const [materiasReales, setMateriasReales] = useState<string[]>([]);
  const [cargandoMaterias, setCargandoMaterias] = useState(false);

  // Cohortes reales de ESTA carrera (antes: mismas 2 cohortes hardcodeadas
  // para cualquier carrera). `codigo_carrera` es el mismo campo que ya usa
  // CohortsManagementModal.tsx para identificar la carrera de cada cohorte.
  useEffect(() => {
    let activo = true;
    setCargandoCohortes(true);

    listarCohortesEvaluaciones()
      .then((todas) => {
        if (!activo) return;
        setCohortesReales(
          todas
            .filter((c) => c.codigo_carrera === career.code)
            .map((c) => ({ idCohorte: c.id_cohorte, nombreCohorte: c.nombre_cohorte })),
        );
      })
      .catch(() => {
        if (activo) setCohortesReales([]);
      })
      .finally(() => {
        if (activo) setCargandoCohortes(false);
      });

    return () => {
      activo = false;
    };
  }, [career.code]);

  // Materias reales del período+módulo elegidos (antes: diccionario fijo
  // MATERIAS_BY_PAO_MODULE). Sigue el mismo patrón cohorte -> período ->
  // asignaturas que ya usa la resolución de asignaturaId en
  // useCatalogoEvidencias.ts, agregando el filtro por `modulo` que ahí no
  // hace falta (ahí se filtra por nombre exacto de materia, no por módulo).
  useEffect(() => {
    const cohorteElegida = cohortesReales.find((c) => c.nombreCohorte === cohort);

    if (!cohorteElegida || !pao || !module) {
      setMateriasReales([]);
      return;
    }

    let activo = true;
    setCargandoMaterias(true);
    const orden = Number(pao.replace(/\D/g, ''));

    obtenerPeriodos(cohorteElegida.idCohorte)
      .then(async (periodos) => {
        const periodo = periodos.find((p) => p.orden === orden);
        if (!periodo) {
          if (activo) setMateriasReales([]);
          return;
        }

        const asignaturas = await obtenerAsignaturas(periodo.id_periodoacademico);
        if (!activo) return;

        setMateriasReales(
          asignaturas.filter((a) => a.modulo === module).map((a) => a.nombre),
        );
      })
      .catch(() => {
        if (activo) setMateriasReales([]);
      })
      .finally(() => {
        if (activo) setCargandoMaterias(false);
      });

    return () => {
      activo = false;
    };
  }, [cohort, pao, module, cohortesReales]);

  const canContinue = cohort && pao && module && materia;
  const materias = materiasReales;
  const selectCls =
    'w-full px-3 py-2 rounded-xl text-sm border outline-none appearance-none cursor-pointer';
  const selectStyle = {
    background: '#F4F7FB',
    borderColor: 'rgba(27,58,107,0.2)',
    color: '#0F1E3C',
  };


  return (
    <div
      className="h-screen flex flex-col overflow-hidden"
      style={{ background: '#EEF2F7', fontFamily: "'Plus Jakarta Sans',sans-serif" }}
    >
      <EvidenceHeader
        title={indicator?.name || ''}
        subtitle="Configurar período"
        backLabel="Indicadores"
        onBackClick={() => (preselectedIndicatorId ? onBack() : onBackToSelectIndicator())}
      />
      <div className="flex-1 flex items-center justify-center px-6">
        <div className="w-full max-w-lg">
          <Breadcrumb
            items={['Seleccionar indicador', indicator?.name || '', 'Configurar período']}
          />
          <h2
            className="text-lg font-bold mb-1"
            style={{ fontFamily: "'Libre Baskerville',serif", color: '#0F1E3C' }}
          >
            Seleccionar período
          </h2>
          <p className="text-sm mb-5" style={{ color: '#5A7295' }}>
            Indique el período, módulo y materia correspondientes a los sílabos que va a cargar.
          </p>

          <div
            className="bg-white rounded-2xl p-5 space-y-4"
            style={{ border: '1px solid rgba(27,58,107,0.09)' }}
          >
            {/* Cohorte dropdown */}
            <div>
              <label
                className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                style={{ color: '#5A7295' }}
              >
                Cohorte
              </label>
              <div className="relative">
                <select
                  value={cohort}
                  onChange={(e) => setCohort(e.target.value)}
                  className={selectCls}
                  style={selectStyle}
                  disabled={cargandoCohortes}
                >
                  <option value="">
                    {cargandoCohortes ? 'Cargando cohortes…' : '— Seleccionar cohorte —'}
                  </option>
                  {cohortesReales.map((c) => (
                    <option key={c.idCohorte} value={c.nombreCohorte}>
                      Cohorte {c.nombreCohorte}
                    </option>
                  ))}
                </select>
                <ChevronDown
                  size={14}
                  className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                  style={{ color: '#5A7295' }}
                />
              </div>
            </div>

            {/* PAO buttons */}
            <BtnGroup
              label="Período (PAO)"
              options={['PAO 1', 'PAO 2', 'PAO 3']}
              value={pao}
              onChange={setPao}
            />

            {/* Module buttons */}
            <BtnGroup
              label="Módulo"
              options={['A', 'B', 'C']}
              value={module}
              onChange={(v) => {
                setModule(v);
                setMateria('');
              }}
            />

            {/* Materia */}
            <div style={{ opacity: module ? 1 : 0.45, pointerEvents: module ? 'auto' : 'none' }}>
              <label
                className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                style={{ color: '#5A7295' }}
              >
                Materia
              </label>
              <div className="relative">
                <select
                  value={materia}
                  onChange={(e) => setMateria(e.target.value)}
                  className={selectCls}
                  style={selectStyle}
                  disabled={cargandoMaterias}
                >
                  <option value="">
                    {cargandoMaterias ? 'Cargando materias…' : '— Seleccionar materia —'}
                  </option>
                  {materias.map((mat) => (
                    <option key={mat} value={mat}>
                      {mat}
                    </option>
                  ))}
                </select>
                <ChevronDown
                  size={14}
                  className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                  style={{ color: '#5A7295' }}
                />
              </div>
            </div>
          </div>

          <button
            onClick={() => canContinue && onContinue()}
            disabled={!canContinue}
            className="w-full mt-4 py-3 rounded-xl font-bold text-sm transition-all"
            style={{
              background: canContinue ? '#1B3A6B' : '#E5E7EB',
              color: canContinue ? '#fff' : '#9CA3AF',
              cursor: canContinue ? 'pointer' : 'not-allowed',
            }}
          >
            Continuar a carga de archivos →
          </button>
        </div>
      </div>
    </div>
  );
}
