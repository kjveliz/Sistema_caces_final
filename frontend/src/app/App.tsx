import { Toaster } from "sonner";
import {
  obtenerEvaluacion,
  obtenerDatosTasa,
  obtenerDatosDesercion,
} from "../services/evidencias";

import { useEffect, useState } from "react";
import EvidenceUploadView from "../pages/EvidenceUploadView";
import DashboardView from "../pages/DashboardView";
import IndicatorView from "../pages/IndicatorView";
import CriteriaView from "../pages/CriteriaView";
import CareersView from "../pages/CareersView";
import LoginView from "../pages/LoginView";
import type { UsuarioSesion } from "../services/auth";

import type {
  View,
  IndicatorDef,
  Career,
} from "../types";

// ── Indicator factory ────────────────────────────────────────
function makeIndicators(
  career: Career,
): IndicatorDef[] {
   const indicators: IndicatorDef[] = [
    {
      id: "I1",
      num: 1,
      code: "I1",
      name: "Syllabus",
      description:
        "Evalúa la elaboración y actualización de los sílabos de todas las asignaturas del programa, verificando su coherencia con la malla curricular y el perfil de egreso aprobados institucionalmente.",
      formula:
        "(Asignaturas con sílabo actualizado / Total de asignaturas) × 100",
      period: "Período académico vigente",
      purpose:
        "Garantizar que todas las asignaturas cuenten con una planificación curricular formal, actualizada y coherente que oriente efectivamente el proceso de enseñanza-aprendizaje.",
      cohorts: [],
      slots: [
        {
          sourceNum: 1,
          label: "Malla curricular",
          sharedKey: "malla_curricular",
        },
        {
          sourceNum: 2,
          label: "Syllabus",
          sharedKey: "silabos",
        },
        {
          sourceNum: 3,
          label: "Asignaturas",
        },
      ],
    },
    {
      id: "I2",
      num: 2,
      code: "I2",
      name: "Seguimiento de Syllabus",
      description:
        "Verifica el cumplimiento y seguimiento efectivo de los sílabos durante el período académico a través de registros documentados y actas de revisión periódica.",
      formula:
        "EF1(×0.33) + EF2(×0.27) + EF3(×0.20) + EF4(×0.13) + EF5(×0.07) = Valor asignatura × 100",
      period: "Período académico vigente",
      purpose:
        "Asegurar que los docentes cumplen con la planificación del sílabo y que existen mecanismos formales de control y revisión del avance curricular en cada asignatura.",
      cohorts: [],
      slots: [
        {
          // Malla Curricular (DOC.SYL.01, catálogo propio de I1) --
          // evaluation-wide, a nivel carrera+cohorte, NO por asignatura.
          // sharedKey "malla_curricular" reusa el mismo patrón ya usado por
          // I1 (slot 1) e I5 (slot 4): si se sube desde cualquiera de los
          // indicadores que comparten esta clave, los demás reflejan el
          // mismo archivo de inmediato en memoria (ver updateSlot en
          // EvidenceUploadView.tsx). La regla real de compartición
          // (compartir_catalogo: id_catalogo_origen=5 -> id_indicador_destino=2)
          // ya existe en la base de datos real -- no requirió migración.
          sourceNum: 7,
          label: "Malla Curricular",
          sharedKey: "malla_curricular",
        },
        {
          // Normativa Institucional (DOC.SEG.01, catálogo propio de I2,
          // orden=1) -- evaluation-wide, a nivel carrera+cohorte. Existía en
          // el catálogo desde el inicio pero nunca tuvo slot visible en el
          // frontend (la posición orden=1 quedó ocupada por Syllabus). Ya
          // se usa en _calculo.php para EF5 (tiposCarreraVigentes) -- no
          // requirió cambios de backend, solo exponerlo en la UI.
          sourceNum: 6,
          label: "Normativa Institucional",
        },
        {
          // sourceNum:1 (Syllabus) ya NO usa sharedKey: cada asignatura sube
          // su propio syllabus real vía evidencia_asignatura (tipo:
          // "syllabus"), no un único archivo compartido desde I1. Ver
          // MEMORIA sección 38.
          sourceNum: 1,
          label: "Syllabus",
        },
        {
          sourceNum: 2,
          label: "Actas de revisión",
        },
        {
          sourceNum: 3,
          label: "Acta de Ajuste Curricular (EF2)",
        },
        {
          sourceNum: 4,
          label: "Evidencia de Difusión (EF3)",
        },
        {
          sourceNum: 5,
          label: "Resultados de Encuesta (CSV)",
          acceptedType: "csv",
        },
      ],
    },
    {
      id: "I3",
      num: 3,
      code: "I3",
      name: "Tutorías Académicas",
      description:
        "Evalúa la implementación del sistema institucional de tutorías académicas para el acompañamiento, apoyo y seguimiento al proceso de aprendizaje de los estudiantes.",
      formula:
        "EF1(×0.40) + EF2(×0.30) + EF3(×0.20) + EF4(×0.10) = Valor materia × 100",
      period: "Período académico vigente",
      purpose:
        "Medir la cobertura y efectividad del sistema de tutorías como mecanismo de apoyo al rendimiento académico y como estrategia para reducir la deserción estudiantil.",
      cohorts: [],
      slots: [
        {
          sourceNum: 1,
          label: "Plan de tutorías",
        },
        {
          sourceNum: 2,
          label: "Registros de tutorías",
        },
        {
          sourceNum: 3,
          label: "Informe de tutorías",
        },
        {
          sourceNum: 4,
          label: "Evidencias de atención",
        },
      ],
    },
    {
      id: "I4",
      num: 4,
      code: "I4",
      name: "Tasa de Deserción",
      description:
        "Mide el porcentaje de estudiantes que abandonan sus estudios antes de completar el programa académico, en relación al total de estudiantes matriculados en el período.",
      formula:
        "(Estudiantes desertores / Estudiantes matriculados) × 100",
      period: "Período académico vigente",
      purpose:
        "Identificar el nivel de abandono estudiantil para implementar estrategias de retención, apoyo y mejora de la permanencia académica en la institución.",
      cohorts: [],
      slots: [
        {
          sourceNum: 1,
          label:
            "Estudiantes matriculados en 1er nivel",
          sharedKey: "matriculados",
        },
        {
          sourceNum: 2,
          label:
            "Estudiantes matriculados en 2do año",
        },
        {
          sourceNum: 3,
          label: "Estudiantes desertados en 2do año",
        },
      ],
    },
    {
      id: "I5",
      num: 5,
      code: "I5",
      name: "Tasa de Titulación",
      description:
        "Mide el porcentaje de estudiantes que culminan su proceso formativo y obtienen su título dentro del período de evaluación establecido por el ente rector.",
      formula:
        "(Número de graduados / Número de matriculados) × 100",
      period:
        "Duración de la carrera + 1 año adicional",
      purpose:
        "Permite al evaluador conocer la eficiencia terminal de cada cohorte y determinar si la institución logra que sus estudiantes concluyan sus estudios satisfactoriamente.",
      cohorts: [],
      slots: [
        {
          sourceNum: 1,
          label: "Estudiantes graduados",
        },
        {
          sourceNum: 2,
          label: "Estudiantes matriculados",
          sharedKey: "matriculados",
        },
        {
          sourceNum: 3,
          label: "Informe de titulación",
        },
        {
          sourceNum: 4,
          label: "Malla curricular",
          sharedKey: "malla_curricular",
        },
      ],
    },
  ];
  return indicators.map((indicator) => ({
    ...indicator,
    slots: indicator.slots.map((slot) => ({
      ...slot,
    })),
  }));
}

// ── App ──────────────────────────────────────────────────────
export default function App() {
  const [usuario, setUsuario] =
    useState<UsuarioSesion | null>(null);

  const [view, setView] =
    useState<View>("login");

  const [career, setCareer] =
    useState<Career | null>(null);

  const [indicators, setIndicators] =
    useState<IndicatorDef[]>([]);

  const [selectedId, setSelectedId] =
    useState<string | null>(null);

  const [
    selectedCohort,
    setSelectedCohort,
  ] = useState<string>("B 2025");

  const [selectedPAO, setSelectedPAO] =
    useState<number>(1);

  const selected = indicators.find(
    (indicator) =>
      indicator.id === selectedId,
  );

  const puedeCargar =
    usuario?.rol === "administrador" ||
    usuario?.rol === "coordinador";

  useEffect(() => {
    if (
      view !== "dashboard" ||
      !career
    ) {
      return;
    }

    const carreraActual = career;
    let cancelado = false;

    async function cargarResultadosDashboard() {
      try {
        const cohorteNormalizada =
          selectedCohort
            .replace(/\s+/g, "")
            .toUpperCase();

        const evaluacion =
          await obtenerEvaluacion(
            carreraActual.code,
            cohorteNormalizada,
          );

        const [
          datosTitulacion,
          datosDesercion,
        ] = await Promise.all([
          obtenerDatosTasa(
            evaluacion.id_evaluacion,
          ),
          obtenerDatosDesercion(
            evaluacion.id_evaluacion,
          ),
        ]);

        if (cancelado) {
          return;
        }

        const registroTitulacion =
          datosTitulacion.find(
            (dato) =>
              dato.cohorte
                .replace(/\s+/g, "")
                .toUpperCase() ===
              cohorteNormalizada,
          );

        const registroDesercion =
          datosDesercion.find(
            (dato) =>
              dato.cohorte
                .replace(/\s+/g, "")
                .toUpperCase() ===
              cohorteNormalizada,
          );

        setIndicators((actuales) =>
          actuales.map((indicator) => {
            if (indicator.id === "I5") {
              if (!registroTitulacion) {
                return {
                  ...indicator,
                  cohorts: [],
                };
              }

              return {
                ...indicator,
                cohorts: [
                  {
                    period: selectedCohort,
                    enrolled:
                      Number(
                        registroTitulacion.matriculados,
                      ) || 0,
                    graduated:
                      Number(
                        registroTitulacion.graduados,
                      ) || 0,
                  },
                ],
              };
            }

            if (indicator.id === "I4") {
              if (
                !registroDesercion ||
                registroDesercion.iniciaron_primer_nivel === null ||
                registroDesercion.no_continuaron === null
              ) {
                return {
                  ...indicator,
                  cohorts: [],
                };
              }

              /*
               * calcRate() calcula:
               * graduated / enrolled × 100
               *
               * Para I4 se reutilizan esos campos así:
               * enrolled   = iniciaron en primer nivel
               * graduated  = no continuaron
               */
              return {
                ...indicator,
                cohorts: [
                  {
                    period: selectedCohort,
                    enrolled:
                      Number(
                        registroDesercion.iniciaron_primer_nivel,
                      ) || 0,
                    graduated:
                      Number(
                        registroDesercion.no_continuaron,
                      ) || 0,
                  },
                ],
              };
            }

            return indicator;
          }),
        );
      } catch (error) {
        console.error(
          "No se pudieron cargar los resultados del dashboard:",
          error,
        );

        if (!cancelado) {
          setIndicators((actuales) =>
            actuales.map((indicator) =>
              indicator.id === "I4" ||
              indicator.id === "I5"
                ? {
                    ...indicator,
                    cohorts: [],
                  }
                : indicator,
            ),
          );
        }
      }
    }

    void cargarResultadosDashboard();

    return () => {
      cancelado = true;
    };
  }, [
    view,
    career,
    selectedCohort,
  ]);

  function selectCareer(
    selectedCareer: Career,
  ) {
    setCareer(selectedCareer);

    setIndicators(
      makeIndicators(selectedCareer),
    );

    setSelectedId(null);
    setSelectedCohort("B 2025");
    setSelectedPAO(1);
    setView("criteria");
  }

  function handleSelectIndicator(
    id: string,
    pao?: number,
  ) {
    setSelectedId(id);

    if (pao !== undefined) {
      setSelectedPAO(pao);
    }

    setView("indicator");
  }

  function handleUpload() {
    if (!puedeCargar) {
      return;
    }

    setSelectedId(null);
    setView("evidUpload");
  }

  function handleIndicatorUpload() {
    if (!puedeCargar) {
      return;
    }

    setView("evidUpload");
  }

  function handleCohortChange(
    newCohort: string,
  ) {
    if (newCohort === selectedCohort) {
      return;
    }

    setSelectedCohort(newCohort);
    setSelectedId(null);
    setSelectedPAO(1);

    /*
     * Limpia de la interfaz los archivos, resultados
     * y relaciones correspondientes a la cohorte anterior.
     *
     * Al entrar nuevamente en la carga de evidencias,
     * el sistema consultará MySQL usando la nueva cohorte.
     */
    if (career) {
      setIndicators(
        makeIndicators(career),
      );
    }

    setView("dashboard");
  }

  function handleBackToCareers() {
    setCareer(null);
    setIndicators([]);
    setSelectedId(null);
    setSelectedCohort("B 2025");
    setSelectedPAO(1);
    setView("careers");
  }

  function handleLogout() {
    setUsuario(null);
    setCareer(null);
    setIndicators([]);
    setSelectedId(null);
    setSelectedCohort("B 2025");
    setSelectedPAO(1);
    setView("login");
  }

  return (
    <>
      <Toaster
        richColors
        position="bottom-right"
      />

      {view === "login" && (
        <LoginView
          onLogin={(usuarioAutenticado) => {
            setUsuario(usuarioAutenticado);
            setView("careers");
          }}
        />
      )}

      {view === "careers" && usuario && (
        <CareersView
          onSelect={selectCareer}
          onLogout={handleLogout}
          usuario={usuario}
        />
      )}

      {view === "criteria" && career && (
        <CriteriaView
          career={career}
          onSelectDocencia={() =>
            setView("dashboard")
          }
          onBack={() =>
            setView("careers")
          }
          onLogout={handleLogout}
        />
      )}

      {view === "dashboard" && career && (
        <DashboardView
          indicators={indicators}
          career={career}
          cohort={selectedCohort}
          onCohortChange={
            handleCohortChange
          }
          onSelect={
            handleSelectIndicator
          }
          onLogout={handleLogout}
          onUpload={handleUpload}
          onBackToCareers={
            handleBackToCareers
          }
          usuario={usuario!}
          puedeCargar={Boolean(puedeCargar)}
        />
      )}

      {view === "evidUpload" && career && puedeCargar && (
        <EvidenceUploadView
          career={career}
          indicators={indicators}
          onChange={setIndicators}
          onBack={() =>
            setView(
              selectedId
                ? "indicator"
                : "dashboard",
            )
          }
          preselectedCohort={
            selectedCohort
          }
          preselectedIndicatorId={
            selectedId ?? undefined
          }
        />
      )}

      {view === "indicator" &&
        selected &&
        career && (
          <IndicatorView
            indicator={selected}
            onBack={() =>
              setView("dashboard")
            }
            career={career}
            cohort={selectedCohort}
            pao={selectedPAO}
            onUpload={
              handleIndicatorUpload
            }
            puedeCargar={Boolean(puedeCargar)}
          />
        )}
    </>
  );
}