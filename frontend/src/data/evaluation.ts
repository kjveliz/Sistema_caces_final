// ── Tipos ────────────────────────────────────────────────────────────────

export interface I2Materia {
  name: string;
  ef: number[];
}

export interface PaoScore {
  pao: string;
  pct: number;
}

// ── I2: Seguimiento de Syllabus ─────────────────────────────────────────

export const EF_DEFS = [
  {
    id: "EF1",
    label: "Seguimiento contenidos",
    weight: 0.33,
    color: "#2563EB",
  },
  {
    id: "EF2",
    label: "Mejora micro currículo",
    weight: 0.27,
    color: "#16A34A",
  },
  {
    id: "EF3",
    label: "Proceso difundido",
    weight: 0.2,
    color: "#0891B2",
  },
  {
    id: "EF4",
    label: "Difusión syllabus EVA",
    weight: 0.13,
    color: "#CA8A04",
  },
  {
    id: "EF5",
    label: "Normativa institucional",
    weight: 0.07,
    color: "#7C3AED",
  },
];

export const ASIGNATURAS_BY_PAO: Record<string, I2Materia[]> = {
  "PAO 1": [
    {
      name: "Comunicación efectiva y trabajo en equipo",
      ef: [0.85, 0.9, 0.8, 0.75, 1],
    },
    {
      name: "Cultura tecnológica y digital",
      ef: [0.78, 0.82, 0.88, 0.65, 0.95],
    },
    {
      name: "Humanismo y Persona",
      ef: [0.9, 1, 0.75, 0.8, 1],
    },
    {
      name: "Fundamentos de Programación y Algoritmos",
      ef: [0.8, 1, 0.9, 0.6, 1],
    },
    {
      name:
        "Desarrollo de Interfaces de Usuario y Experiencia de Usuario (UI/UX)",
      ef: [0.75, 0.85, 0.88, 0.7, 0.95],
    },
    {
      name:
        "Bases para el desarrollo de Aplicaciones Móviles para Android",
      ef: [0.82, 0.78, 0.92, 0.55, 0.88],
    },
    {
      name: "Bases para el desarrollo de Aplicaciones Móviles para iOS",
      ef: [0.88, 0.95, 0.7, 0.85, 1],
    },
    {
      name: "Bases para el desarrollo Cross-Platform",
      ef: [0.72, 0.88, 0.85, 0.6, 0.9],
    },
  ],

  "PAO 2": [
    {
      name: "Seguridad y Optimización en Aplicaciones Móviles",
      ef: [0.9, 0.78, 0.92, 0.55, 0.88],
    },
    {
      name: "Introducción a Lenguajes de Programación",
      ef: [0.85, 1, 0.75, 0.8, 1],
    },
    {
      name:
        "Implementación de Estructuras de Datos y Algoritmos Avanzados",
      ef: [0.7, 0.8, 0.85, 0.5, 0.9],
    },
    {
      name: "Fundamentos de Bases de Datos",
      ef: [0.75, 0.85, 0.88, 0.7, 0.95],
    },
    {
      name: "Práctica Laboral",
      ef: [0.6, 0.7, 0.8, 0.45, 0.85],
    },
    {
      name: "Humanismo y Sociedad",
      ef: [0.88, 0.92, 0.78, 0.82, 1],
    },
    {
      name: "Emprendimiento e Innovación",
      ef: [0.65, 0.75, 0.7, 0.55, 0.8],
    },
    {
      name: "Servicio Comunitario",
      ef: [0.55, 0.65, 0.6, 0.5, 0.75],
    },
  ],

  "PAO 3": [
    {
      name: "Pensamiento Crítico y Lógico",
      ef: [0, 0, 0, 0, 0],
    },
    {
      name: "Aplicación de Conceptos de Ingeniería de Software",
      ef: [0, 0, 0, 0, 0],
    },
    {
      name: "Metodologías de Desarrollo Web",
      ef: [0, 0, 0, 0, 0],
    },
    {
      name: "Principios de Redes y Comunicaciones",
      ef: [0, 0, 0, 0, 0],
    },
    {
      name:
        "Principios de los Sistemas Operativos e Implementación de Software Empresarial",
      ef: [0, 0, 0, 0, 0],
    },
    {
      name: "Pruebas de Software y Aseguramiento de la Calidad",
      ef: [0, 0, 0, 0, 0],
    },
    {
      name: "Integración Curricular en Programación Aplicada",
      ef: [0, 0, 0, 0, 0],
    },
    {
      name: "Investigación Aplicada y Titulación",
      ef: [0, 0, 0, 0, 0],
    },
  ],
};

// ── Resultados generales por PAO ─────────────────────────────────────────

export const PAO_SCORES: Record<string, PaoScore[]> = {
  I1: [
    { pao: "PAO 1", pct: 92 },
    { pao: "PAO 2", pct: 78 },
    { pao: "PAO 3", pct: 0 },
  ],

  I2: [
    { pao: "PAO 1", pct: 85 },
    { pao: "PAO 2", pct: 72 },
    { pao: "PAO 3", pct: 0 },
  ],

  I3: [
    { pao: "PAO 1", pct: 90 },
    { pao: "PAO 2", pct: 65 },
    { pao: "PAO 3", pct: 0 },
  ],
};