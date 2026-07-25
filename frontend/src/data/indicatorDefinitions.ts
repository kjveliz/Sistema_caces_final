import type { Career, IndicatorDef } from '../types';

// ── Indicator factory ────────────────────────────────────────
// Movido desde app/App.tsx (Fase 4 del Plan de Mejora, activación de
// react-router): la definición de los 5 indicadores no depende de la
// navegación, así que vive en su propio módulo en vez de en el componente
// raíz. Sin cambios de lógica respecto al original.
export function makeIndicators(career: Career): IndicatorDef[] {
  const indicators: IndicatorDef[] = [
    {
      id: 'I1',
      num: 1,
      code: 'I1',
      name: 'Syllabus',
      description:
        'Evalúa la elaboración y actualización de los sílabos de todas las asignaturas del programa, verificando su coherencia con la malla curricular y el perfil de egreso aprobados institucionalmente.',
      formula: '(Asignaturas con sílabo actualizado / Total de asignaturas) × 100',
      period: 'Período académico vigente',
      purpose:
        'Garantizar que todas las asignaturas cuenten con una planificación curricular formal, actualizada y coherente que oriente efectivamente el proceso de enseñanza-aprendizaje.',
      cohorts: [],
      slots: [
        {
          sourceNum: 1,
          label: 'Malla curricular',
          sharedKey: 'malla_curricular',
        },
        {
          sourceNum: 2,
          label: 'Syllabus',
          sharedKey: 'silabos',
        },
        {
          sourceNum: 3,
          label: 'Asignaturas',
        },
      ],
    },
    {
      id: 'I2',
      num: 2,
      code: 'I2',
      name: 'Seguimiento de Syllabus',
      description:
        'Verifica el cumplimiento y seguimiento efectivo de los sílabos durante el período académico a través de registros documentados y actas de revisión periódica.',
      formula:
        'EF1(×0.33) + EF2(×0.27) + EF3(×0.20) + EF4(×0.13) + EF5(×0.07) = Valor asignatura × 100',
      period: 'Período académico vigente',
      purpose:
        'Asegurar que los docentes cumplen con la planificación del sílabo y que existen mecanismos formales de control y revisión del avance curricular en cada asignatura.',
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
          label: 'Malla Curricular',
          sharedKey: 'malla_curricular',
        },
        {
          // Normativa Institucional (DOC.SEG.01, catálogo propio de I2,
          // orden=1) -- evaluation-wide, a nivel carrera+cohorte. Existía en
          // el catálogo desde el inicio pero nunca tuvo slot visible en el
          // frontend (la posición orden=1 quedó ocupada por Syllabus). Ya
          // se usa en _calculo.php para EF5 (tiposCarreraVigentes) -- no
          // requirió cambios de backend, solo exponerlo en la UI.
          sourceNum: 6,
          label: 'Normativa Institucional',
        },
        {
          // sourceNum:1 (Syllabus) ya NO usa sharedKey: cada asignatura sube
          // su propio syllabus real vía evidencia_asignatura (tipo:
          // "syllabus"), no un único archivo compartido desde I1. Ver
          // MEMORIA sección 38.
          sourceNum: 1,
          label: 'Syllabus',
        },
        {
          sourceNum: 3,
          label: 'Acta de Ajuste Curricular (EF2)',
        },
        {
          sourceNum: 4,
          label: 'Evidencia de Difusión (EF3)',
        },
        {
          sourceNum: 5,
          label: 'Resultados de Encuesta (CSV)',
          acceptedType: 'csv',
        },
        {
          // Reporte de Control de Seguimiento (DOC.SEG.06, catálogo propio
          // de I2, orden=8) -- evaluation-wide, a nivel carrera+cohorte,
          // igual que Malla/Normativa. Pendiente #5 (MEMORIA v44, §23):
          // evidencia real del SIU para EF1, solo verificación de
          // existencia (sin lector/parser de PDF). orden=8 en el catálogo
          // porque debe coincidir exactamente con este sourceNum (así
          // matchea el bloque genérico de EvidenceUploadView.tsx e
          // IndicatorView.tsx) -- 6 y 7 ya están tomados por
          // Normativa/Malla.
          sourceNum: 8,
          label: 'Reporte de Control de Seguimiento (SIU)',
        },
        {
          // Reporte de Avances del Syllabus (DOC.SEG.07, orden=9). Mismo
          // patrón que el slot anterior.
          sourceNum: 9,
          label: 'Reporte de Avances del Syllabus (SIU)',
        },
      ],
    },
    {
      id: 'I3',
      num: 3,
      code: 'I3',
      name: 'Tutorías Académicas',
      description:
        'Evalúa la implementación del sistema institucional de tutorías académicas para el acompañamiento, apoyo y seguimiento al proceso de aprendizaje de los estudiantes.',
      formula: 'EF1(×0.40) + EF2(×0.30) + EF3(×0.20) + EF4(×0.10) = Valor materia × 100',
      period: 'Período académico vigente',
      purpose:
        'Medir la cobertura y efectividad del sistema de tutorías como mecanismo de apoyo al rendimiento académico y como estrategia para reducir la deserción estudiantil.',
      cohorts: [],
      slots: [
        {
          sourceNum: 1,
          label: 'Plan de tutorías',
        },
        {
          sourceNum: 2,
          label: 'Registros de tutorías',
        },
        {
          sourceNum: 3,
          label: 'Informe de tutorías',
        },
        {
          sourceNum: 4,
          label: 'Evidencias de atención',
        },
      ],
    },
    {
      id: 'I4',
      num: 4,
      code: 'I4',
      name: 'Tasa de Deserción',
      description:
        'Mide el porcentaje de estudiantes que abandonan sus estudios antes de completar el programa académico, en relación al total de estudiantes matriculados en el período.',
      formula: '(Estudiantes desertores / Estudiantes matriculados) × 100',
      period: 'Período académico vigente',
      purpose:
        'Identificar el nivel de abandono estudiantil para implementar estrategias de retención, apoyo y mejora de la permanencia académica en la institución.',
      cohorts: [],
      slots: [
        {
          sourceNum: 1,
          label: 'Estudiantes matriculados en 1er nivel',
          sharedKey: 'matriculados',
        },
        {
          sourceNum: 2,
          label: 'Estudiantes matriculados en 2do año',
        },
        {
          sourceNum: 3,
          label: 'Estudiantes desertados en 2do año',
        },
      ],
    },
    {
      id: 'I5',
      num: 5,
      code: 'I5',
      name: 'Tasa de Titulación',
      description:
        'Mide el porcentaje de estudiantes que culminan su proceso formativo y obtienen su título dentro del período de evaluación establecido por el ente rector.',
      formula: '(Número de graduados / Número de matriculados) × 100',
      period: 'Duración de la carrera + 1 año adicional',
      purpose:
        'Permite al evaluador conocer la eficiencia terminal de cada cohorte y determinar si la institución logra que sus estudiantes concluyan sus estudios satisfactoriamente.',
      cohorts: [],
      slots: [
        {
          sourceNum: 1,
          label: 'Estudiantes graduados',
        },
        {
          sourceNum: 2,
          label: 'Estudiantes matriculados',
          sharedKey: 'matriculados',
        },
        {
          sourceNum: 3,
          label: 'Informe de titulación',
        },
        {
          sourceNum: 4,
          label: 'Malla curricular',
          sharedKey: 'malla_curricular',
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
