export const COHORT_OPTIONS = ["B 2025", "A 2026"];

export const PAO_OPTIONS = [
  "Cohorte B2025 - PAO 1",
  "Cohorte B2025 - PAO 2",
  "Cohorte B2025 - PAO 3",
];

export const MODULE_OPTIONS = ["A", "B", "C"];

// Lista fija (mock) usada solo para poblar el selector de materia del wizard,
// en Title Case para verse bien en UI. La resolución real de asignaturaId
// (EvidenceUploadView.tsx, resolverAsignatura()) compara este nombre contra
// la tabla `asignatura` real de forma case-insensitive, así que las
// diferencias de mayúsculas/minúsculas entre esta lista y la BD no rompen
// nada. "Práctica Laboral" y "Servicio Comunitario" (antes en PAO 2) se
// quitaron: no existen como fila en `asignatura` (el periodo académico 2
// real solo tiene 6 materias, no 8) — decisión confirmada con el usuario.
export const MATERIAS_BY_PAO_MODULE: Record<
  string,
  Record<string, string[]>
> = {
  "PAO 1": {
    A: [
      "Comunicación efectiva y trabajo en equipo",
      "Cultura tecnológica y digital",
      "Humanismo y Persona",
    ],
    B: [
      "Fundamentos de Programación y Algoritmos",
      "Desarrollo de Interfaces de Usuario y Experiencia de Usuario (UI/UX)",
      "Bases para el desarrollo de Aplicaciones Móviles para Android",
    ],
    C: [
      "Bases para el desarrollo de Aplicaciones Móviles para iOS",
      "Bases para el desarrollo Cross-Platform",
    ],
  },

  "PAO 2": {
    A: [
      "Seguridad y Optimización en Aplicaciones Móviles",
      "Introducción a Lenguajes de Programación",
      "Implementación de Estructuras de Datos y Algoritmos Avanzados",
    ],
    B: [
      "Fundamentos de Bases de Datos",
      "Humanismo y Sociedad",
    ],
    C: [
      "Emprendimiento e Innovación",
    ],
  },

  "PAO 3": {
    A: [
      "Pensamiento Crítico y Lógico",
      "Aplicación de Conceptos de Ingeniería de Software",
      "Metodologías de Desarrollo Web",
    ],
    B: [
      "Principios de Redes y Comunicaciones",
      "Principios de los Sistemas Operativos e Implementación de Software Empresarial",
      "Pruebas de Software y Aseguramiento de la Calidad",
    ],
    C: [
      "Integración Curricular en Programación Aplicada",
      "Investigación Aplicada y Titulación",
    ],
  },
};