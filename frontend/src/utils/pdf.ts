export function validatePDF(file: File): string | null {
  const isPDF =
    file.type === "application/pdf" ||
    file.name.toLowerCase().endsWith(".pdf");

  if (!isPDF) {
    return "Solo se aceptan archivos en formato PDF (.pdf)";
  }

  if (file.size > 25 * 1024 * 1024) {
    return "El archivo no debe superar 25 MB";
  }

  return null;
}

// El navegador reporta MIME types distintos para CSV según el sistema
// operativo/programa que lo generó (Excel, Google Sheets, texto plano),
// por eso se valida sobre todo por extensión y se acepta cualquiera de los
// MIME types comunes en vez de exigir uno solo.
const CSV_MIME_TYPES = [
  "text/csv",
  "application/csv",
  "application/vnd.ms-excel",
  "text/plain",
  "",
];

export function validateCSV(file: File): string | null {
  const isCSV =
    file.name.toLowerCase().endsWith(".csv") &&
    CSV_MIME_TYPES.includes(file.type);

  if (!isCSV) {
    return "Solo se aceptan archivos en formato CSV (.csv)";
  }

  if (file.size > 25 * 1024 * 1024) {
    return "El archivo no debe superar 25 MB";
  }

  return null;
}