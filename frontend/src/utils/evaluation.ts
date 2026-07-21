import type { CohortRow } from "../types";

export interface StatusResult {
  label: string;
  color: string;
  bg: string;
  border: string;
}

export function getStatus(pct: number): StatusResult {
  if (pct >= 75) {
    return {
      label: "Satisfactorio",
      color: "#16A34A",
      bg: "#DCFCE7",
      border: "#16A34A",
    };
  }

  if (pct >= 50) {
    return {
      label: "Cuasi Satisfactorio",
      color: "#CA8A04",
      bg: "#FEF9C3",
      border: "#CA8A04",
    };
  }

  if (pct >= 25) {
    return {
      label: "Poco Satisfactorio",
      color: "#EA580C",
      bg: "#FFEDD5",
      border: "#EA580C",
    };
  }

  if (pct > 0) {
    return {
      label: "Deficiente",
      color: "#DC2626",
      bg: "#FEE2E2",
      border: "#DC2626",
    };
  }

  return {
    label: "Sin datos",
    color: "#9CA3AF",
    bg: "#F3F4F6",
    border: "#E5E7EB",
  };
}

export function calcRate(cohorts: CohortRow[]): number {
  const totalEnrolled = cohorts.reduce(
    (total, cohort) => total + cohort.enrolled,
    0,
  );

  const totalGraduated = cohorts.reduce(
    (total, cohort) => total + cohort.graduated,
    0,
  );

  if (totalEnrolled === 0) {
    return 0;
  }

  return Math.round(
    (totalGraduated / totalEnrolled) * 100,
  );
}