// ── Generación del PDF de resultados de Indicador 11.2 (Seguimiento de Syllabus) ──
//
// Rediseño visual (jul 2026) a partir del mockup HTML aprobado en sesión aparte
// (mockup_pagina1_v4.html / mockup_pagina2_v2.html — logo, colores y tipografía
// institucional UCSG). Reemplaza el diseño anterior (fondo navy, donut) por:
// masthead con logo + ficha de metadatos, resultado general como cifra + barra
// de progreso, tabla de "Fuentes de evidencia por elemento" con leyenda de
// estados, detalle de la encuesta de heteroevaluación (EF1/EF4) con barras de
// distribución de respuestas, y anexo de las 23 preguntas en página(s) aparte
// con encabezado de continuación (logo pequeño + número de página).
//
// Decisiones de esta sesión (ver memoria del proyecto):
//   - Se elimina la tabla de "Evidencia documental" (EF2/EF3/EF5) que tenía el
//     diseño anterior: no forma parte del nuevo layout aprobado.
//   - Las 3 tipografías del mockup (Source Serif 4, IBM Plex Sans, IBM Plex
//     Mono) NO son estándar de PDF, así que se embeben a mano vía
//     doc.addFileToVFS + doc.addFont. Los TTF (subseteados a caracteres
//     latinos/español) están en base64 en ./pdfIndicador2Fuentes.ts —ver ese
//     archivo para la procedencia exacta de cada uno. Ahí también vive el logo
//     PNG (el mismo bitmap embebido en el mockup aprobado, sin cambios).
//
// Sin html2canvas: todo se dibuja directo con primitivas de jsPDF, así el alto
// de cada bloque se mide a partir del texto real antes de dibujar.
//
// La construcción del documento está separada en `construirDocumentoIndicador2`
// (devuelve el PdfDoc ya armado, sin guardar) para poder probarla/inspeccionarla
// sin depender de un entorno de navegador; `exportarPdfIndicador2` es la función
// pública que usa la app y hace el `doc.save(...)` final.

import type { ResultadoAsignatura, EncuestaDetalle } from "../services/seguimientoSyllabus";
import {
  SourceSerif4_Regular,
  SourceSerif4_Italic,
  SourceSerif4_SemiBold,
  PlexSans_Regular,
  PlexSans_Italic,
  PlexSans_Medium,
  PlexSans_SemiBold,
  PlexMono_Regular,
  PlexMono_Medium,
  LOGO_UCSG_PNG_BASE64,
} from "./pdfIndicador2Fuentes";

type PdfDoc = InstanceType<typeof import("jspdf").default>;
type RGB = [number, number, number];
type StatusKey = "ok" | "cuasi" | "poco" | "def" | "nodata";
type FontRole = "serif" | "serifItalic" | "serifBold" | "sans" | "sansItalic" | "sansMedium" | "sansBold" | "mono" | "monoMedium";

function hexToRgb(hex: string): RGB {
  const clean = hex.replace("#", "");
  const bigint = parseInt(clean, 16);
  return [(bigint >> 16) & 255, (bigint >> 8) & 255, bigint & 255];
}

// ── Paleta (idéntica a las custom properties --maroon/--paper/... del mockup) ──
const COLOR = {
  maroon: hexToRgb("#A00045"),
  maroonDark: hexToRgb("#6E0032"),
  paper: hexToRgb("#FAF9F6"),
  ink: hexToRgb("#1C1B19"),
  ink2: hexToRgb("#5B5850"),
  ink3: hexToRgb("#8C887F"),
  rule: hexToRgb("#E4E0D6"),
  rowAlt: hexToRgb("#F4F2EC"),
  chipBg: hexToRgb("#F7E7EE"),
  white: [255, 255, 255] as RGB,
  ok: hexToRgb("#2E7D4F"),
  cuasi: hexToRgb("#2E7D82"),
  poco: hexToRgb("#B4791E"),
  def: hexToRgb("#B23A2E"),
  nodata: hexToRgb("#B7B2A6"),
};

const STATUS_LABEL: Record<StatusKey, string> = {
  ok: "Satisfactorio",
  cuasi: "Cuasi satisf.",
  poco: "Poco satisf.",
  def: "Deficiente",
  nodata: "Sin datos",
};
const STATUS_ORDER: StatusKey[] = ["ok", "cuasi", "poco", "def", "nodata"];

// Cortes oficiales de CACES (≥75 / ≥50 / ≥25 / <25) — igual que el diseño anterior.
function statusKeyDeValor(valor: number | null): StatusKey {
  if (valor === null) return "nodata";
  if (valor >= 75) return "ok";
  if (valor >= 50) return "cuasi";
  if (valor >= 25) return "poco";
  return "def";
}

const FREQ_ORDER = ["Nunca", "Pocas veces", "Algunas veces", "Casi siempre", "Siempre"];
const FREQ_COLOR: Record<string, RGB> = {
  Nunca: hexToRgb("#B23A2E"),
  "Pocas veces": hexToRgb("#C2703A"),
  "Algunas veces": hexToRgb("#C9A227"),
  "Casi siempre": hexToRgb("#6B9C4A"),
  Siempre: hexToRgb("#2E7D4F"),
};

const EF_INFO: Record<"ef1" | "ef2" | "ef3" | "ef4" | "ef5", { titulo: string; descripcion: string; peso: number; fuente: string }> = {
  ef1: { titulo: "EF1", descripcion: "Seguimiento de contenidos del syllabus", peso: 33, fuente: "Encuesta de heteroevaluación, syllabus y malla curricular" },
  ef2: { titulo: "EF2", descripcion: "Mejora al micro currículo", peso: 27, fuente: "Documentos de planificación y actas de resolución" },
  ef3: { titulo: "EF3", descripcion: "Proceso de seguimiento difundido", peso: 20, fuente: "EVA, informes y registros de difusión" },
  ef4: { titulo: "EF4", descripcion: "Difusión del syllabus en el EVA", peso: 13, fuente: "EVA — carga y difusión del syllabus" },
  ef5: { titulo: "EF5", descripcion: "Normativa institucional", peso: 7, fuente: "Reglamento interno de seguimiento" },
};

function opcionDominante(conteos: Record<string, number>, total: number): { label: string; pct: number } | null {
  if (total <= 0) return null;
  let mejorLabel = FREQ_ORDER[0];
  let mejorConteo = -1;
  FREQ_ORDER.forEach((op) => {
    const c = conteos[op] ?? 0;
    if (c > mejorConteo) { mejorConteo = c; mejorLabel = op; }
  });
  return { label: mejorLabel, pct: Math.round((mejorConteo / total) * 100) };
}

// ── Registro de fuentes embebidas (ver pdfIndicador2Fuentes.ts) ──
function registrarFuentes(doc: PdfDoc) {
  doc.addFileToVFS("SourceSerif4-Regular.ttf", SourceSerif4_Regular);
  doc.addFont("SourceSerif4-Regular.ttf", "SourceSerif4", "normal");
  doc.addFileToVFS("SourceSerif4-Italic.ttf", SourceSerif4_Italic);
  doc.addFont("SourceSerif4-Italic.ttf", "SourceSerif4", "italic");
  doc.addFileToVFS("SourceSerif4-SemiBold.ttf", SourceSerif4_SemiBold);
  doc.addFont("SourceSerif4-SemiBold.ttf", "SourceSerif4SemiBold", "normal");

  doc.addFileToVFS("PlexSans-Regular.ttf", PlexSans_Regular);
  doc.addFont("PlexSans-Regular.ttf", "PlexSans", "normal");
  doc.addFileToVFS("PlexSans-Italic.ttf", PlexSans_Italic);
  doc.addFont("PlexSans-Italic.ttf", "PlexSans", "italic");
  doc.addFileToVFS("PlexSans-Medium.ttf", PlexSans_Medium);
  doc.addFont("PlexSans-Medium.ttf", "PlexSansMedium", "normal");
  doc.addFileToVFS("PlexSans-SemiBold.ttf", PlexSans_SemiBold);
  doc.addFont("PlexSans-SemiBold.ttf", "PlexSansSemiBold", "normal");

  doc.addFileToVFS("PlexMono-Regular.ttf", PlexMono_Regular);
  doc.addFont("PlexMono-Regular.ttf", "PlexMono", "normal");
  doc.addFileToVFS("PlexMono-Medium.ttf", PlexMono_Medium);
  doc.addFont("PlexMono-Medium.ttf", "PlexMonoMedium", "normal");
}

// Wrapper: fija familia + tamaño (pt) + color de texto en un solo llamado.
function fuente(doc: PdfDoc, rol: FontRole, sizePt: number, color: RGB) {
  switch (rol) {
    case "serif": doc.setFont("SourceSerif4", "normal"); break;
    case "serifItalic": doc.setFont("SourceSerif4", "italic"); break;
    case "serifBold": doc.setFont("SourceSerif4SemiBold", "normal"); break;
    case "sans": doc.setFont("PlexSans", "normal"); break;
    case "sansItalic": doc.setFont("PlexSans", "italic"); break;
    case "sansMedium": doc.setFont("PlexSansMedium", "normal"); break;
    case "sansBold": doc.setFont("PlexSansSemiBold", "normal"); break;
    case "mono": doc.setFont("PlexMono", "normal"); break;
    case "monoMedium": doc.setFont("PlexMonoMedium", "normal"); break;
  }
  doc.setFontSize(sizePt);
  doc.setTextColor(color[0], color[1], color[2]);
}
function fill(doc: PdfDoc, c: RGB) { doc.setFillColor(c[0], c[1], c[2]); }
function stroke(doc: PdfDoc, c: RGB) { doc.setDrawColor(c[0], c[1], c[2]); }

// ── Geometría de página (mm — igual que el mockup, ya diseñado en mm para A4) ──
const PAGE_W = 210;
const PAGE_H = 297;
const MARGIN_X = 16;
const MARGIN_TOP = 16;
const MARGIN_BOTTOM = 14;
const CONTENT_W = PAGE_W - MARGIN_X * 2; // 178mm
const CONT_HEADER_BOTTOM_Y = 32; // y donde empieza el contenido en páginas de continuación

function drawDiamond(doc: PdfDoc, cx: number, cy: number, r: number, color: RGB) {
  fill(doc, color);
  doc.triangle(cx, cy - r, cx - r, cy, cx + r, cy, "F");
  doc.triangle(cx, cy + r, cx - r, cy, cx + r, cy, "F");
}
function drawDot(doc: PdfDoc, cx: number, cy: number, r: number, color: RGB) {
  fill(doc, color);
  doc.circle(cx, cy, r, "F");
}

// Encabezado de sección: ◆ Título ───────── (línea a la derecha hasta el borde).
function dividerConTitulo(doc: PdfDoc, x: number, y: number, w: number, titulo: string): number {
  const cy = y + 2.4;
  drawDiamond(doc, x + 1.1, cy, 1.1, COLOR.maroon);
  fuente(doc, "serifBold", 11, COLOR.ink);
  const tx = x + 4.2;
  doc.text(titulo, tx, cy + 1.1);
  const tw = doc.getTextWidth(titulo);
  stroke(doc, COLOR.rule);
  doc.setLineWidth(0.15);
  doc.line(tx + tw + 3, cy, x + w, cy);
  return y + 6.5;
}
function drawSectionSub(doc: PdfDoc, x: number, y: number, w: number, texto: string): number {
  fuente(doc, "sans", 7.2, COLOR.ink2);
  const lineas = doc.splitTextToSize(texto, w);
  doc.text(lineas, x, y);
  return y + lineas.length * 3.2 + 3;
}

// ── Masthead de página 1 (logo + eyebrow/h1/h2 + ficha de metadatos) ──
function drawMasthead(
  doc: PdfDoc,
  asignatura: ResultadoAsignatura,
  cohortLabel: string,
  paoNumero: number,
): number {
  const logoH = 9;
  const logoW = logoH * (460 / 241);
  doc.addImage(LOGO_UCSG_PNG_BASE64, "PNG", MARGIN_X, MARGIN_TOP, logoW, logoH);

  const rightX = MARGIN_X + CONTENT_W;
  const tituloLineas = doc.splitTextToSize(asignatura.nombre_asignatura, CONTENT_W - logoW - 6);
  const bloqueTextoH = 3.2 + 5.3 + tituloLineas.length * 4.1;
  const headerH = Math.max(logoH, bloqueTextoH) + 4; // + padding-bottom

  let ty = MARGIN_TOP;
  fuente(doc, "mono", 7.2, COLOR.maroon);
  doc.text("INDICADOR 11.2 - CACES", rightX, ty, { align: "right" });
  ty += 5.3;
  fuente(doc, "serifBold", 13.5, COLOR.ink);
  doc.text("Seguimiento de syllabus", rightX, ty, { align: "right" });
  ty += 4.6;
  fuente(doc, "serifItalic", 9, COLOR.ink2);
  doc.text(tituloLineas, rightX, ty, { align: "right" });

  const yBorde = MARGIN_TOP + headerH;
  stroke(doc, COLOR.maroon);
  doc.setLineWidth(0.5);
  doc.line(MARGIN_X, yBorde, MARGIN_X + CONTENT_W, yBorde);

  // Ficha: Docente / Cohorte / PAO / Generado
  const yFicha = yBorde + 5;
  const colW = CONTENT_W / 4;
  const meta: [string, string, boolean][] = [
    ["Docente", "Sin asignar", true],
    ["Cohorte", `Cohorte ${cohortLabel}`, false],
    ["PAO", `PAO ${paoNumero}`, false],
    // Formato corto ("18 jul 2026, 08:43") en vez del largo ("18 de julio de 2026
    // a las 8:43 a. m.") — el largo se desbordaba fuera de su columna (1/4 del
    // ancho de contenido) y hasta fuera del margen de página.
    ["Generado", new Date().toLocaleString("es-EC", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", hour12: false }).replace(/\.$/, ""), false],
  ];
  meta.forEach(([label, valor, muted], i) => {
    const cx = MARGIN_X + i * colW;
    if (i > 0) {
      stroke(doc, COLOR.rule);
      doc.setLineWidth(0.1);
      doc.line(cx, yFicha - 3.6, cx, yFicha + 2.5);
    }
    const tx0 = cx + (i > 0 ? 4 : 0);
    fuente(doc, "mono", 6.3, COLOR.ink3);
    doc.text(label.toUpperCase(), tx0, yFicha - 1.5);
    if (muted) {
      fuente(doc, "sansItalic", 9.5, COLOR.ink3);
    } else {
      fuente(doc, "sansMedium", 9.5, COLOR.ink);
    }
    // Shrink-to-fit defensivo: si aun así el valor no entra en su columna
    // (ej. un nombre de cohorte largo a futuro), se reduce el tamaño de
    // fuente en vez de dejarlo desbordar sobre la columna siguiente.
    const maxW = colW - (i > 0 ? 4 : 0) - 2;
    let size = 9.5;
    doc.setFontSize(size);
    while (doc.getTextWidth(valor) > maxW && size > 6.5) {
      size -= 0.5;
      doc.setFontSize(size);
    }
    doc.text(valor, tx0, yFicha + 2.5);
  });

  return yFicha + 6;
}

// ── Encabezado de página de continuación (logo pequeño + número de página) ──
function drawRunHeader(doc: PdfDoc, paginaNum: number) {
  const logoH = 9;
  const logoW = logoH * (460 / 241);
  doc.addImage(LOGO_UCSG_PNG_BASE64, "PNG", MARGIN_X, MARGIN_TOP, logoW, logoH);
  fuente(doc, "mono", 7.5, COLOR.ink3);
  doc.text(String(paginaNum).padStart(2, "0"), MARGIN_X + CONTENT_W, MARGIN_TOP + logoH - 1.5, { align: "right" });
  const yBorde = MARGIN_TOP + logoH + 2.5;
  stroke(doc, COLOR.rule);
  doc.setLineWidth(0.2);
  doc.line(MARGIN_X, yBorde, MARGIN_X + CONTENT_W, yBorde);
}

// ── Pie fijo (idéntico en todas las páginas, no depende del contenido) ──
function drawFondoPapel(doc: PdfDoc) {
  fill(doc, COLOR.paper);
  doc.rect(0, 0, PAGE_W, PAGE_H, "F");
}

function drawFooter(doc: PdfDoc) {
  const y = PAGE_H - MARGIN_BOTTOM + 4;
  stroke(doc, COLOR.rule);
  doc.setLineWidth(0.15);
  doc.line(MARGIN_X, y - 4, MARGIN_X + CONTENT_W, y - 4);
  fuente(doc, "sans", 7.2, COLOR.ink3);
  doc.text("UCSG - Sistema de seguimiento académico", MARGIN_X, y);
  doc.text("Indicador 11.2 — Seguimiento de syllabus", MARGIN_X + CONTENT_W, y, { align: "right" });
}

// ── Resultado general: cifra + estado + barra de progreso ──
function drawResultadoGeneral(doc: PdfDoc, y: number, asignatura: ResultadoAsignatura): number {
  const valor = asignatura.valoracion_general;
  const statusKey = statusKeyDeValor(valor);
  const color = COLOR[statusKey];

  fuente(doc, "serifBold", 30, COLOR.ink);
  doc.text(valor === null ? "—" : `${valor}%`, MARGIN_X, y + 8);
  const numW = doc.getTextWidth(valor === null ? "—" : `${valor}%`);

  const statusX = MARGIN_X + numW + 6;
  drawDot(doc, statusX + 0.9, y + 4.3, 0.9, color);
  fuente(doc, "monoMedium", 8.5, color);
  doc.text((asignatura.escala ?? STATUS_LABEL.nodata).toUpperCase(), statusX + 2.6, y + 5.2);

  fuente(doc, "mono", 7, COLOR.ink3);
  doc.text("Puntaje agregado - Indicador 11.2", MARGIN_X + CONTENT_W, y + 5.2, { align: "right" });

  const meterY = y + 12;
  fill(doc, COLOR.rule);
  doc.roundedRect(MARGIN_X, meterY, CONTENT_W, 1.4, 0.7, 0.7, "F");
  if (valor !== null && valor > 0) {
    fill(doc, color);
    doc.roundedRect(MARGIN_X, meterY, (CONTENT_W * Math.min(100, valor)) / 100, 1.4, 0.7, 0.7, "F");
  }
  fuente(doc, "mono", 5.6, COLOR.ink3);
  [0, 25, 50, 75, 100].forEach((n, i) => {
    const align = i === 0 ? "left" : i === 4 ? "right" : "center";
    const tx = MARGIN_X + (CONTENT_W * n) / 100;
    doc.text(String(n), tx, meterY + 4, { align });
  });

  return meterY + 8;
}

// ── Tabla "Fuentes de evidencia por elemento" (estilo master-table) ──
function drawTablaEFs(
  doc: PdfDoc,
  yInicial: number,
  asignatura: ResultadoAsignatura,
  encuestaDetalle: EncuestaDetalle,
  checkPageBreak: (yActual: number, alturaNecesaria: number) => number,
): number {
  let y = yInicial;
  const cols = [
    { w: 11 }, // EF
    { w: 127 }, // Elemento + fuente
    { w: 16 }, // Peso
    { w: 24 }, // Resultado
  ];
  const headerH = 7;

  const dibujarEncabezado = () => {
    fill(doc, COLOR.maroon);
    doc.rect(MARGIN_X, y, CONTENT_W, headerH, "F");
    fuente(doc, "monoMedium", 6.3, COLOR.white);
    let cx = MARGIN_X;
    ["EF", "ELEMENTO FUNDAMENTAL Y FUENTE DE EVIDENCIA", "PESO", "RESULTADO"].forEach((h, i) => {
      const alineadoDerecha = i === 3;
      const tx = alineadoDerecha ? cx + cols[i].w - 3 : cx + 3;
      doc.text(h, tx, y + 4.6, alineadoDerecha ? { align: "right" } : {});
      cx += cols[i].w;
    });
    y += headerH;
  };
  dibujarEncabezado();

  const efKeys: ("ef1" | "ef2" | "ef3" | "ef4" | "ef5")[] = ["ef1", "ef2", "ef3", "ef4", "ef5"];
  efKeys.forEach((key, i) => {
    const info = EF_INFO[key];
    const valor = asignatura[key];
    const statusKey = statusKeyDeValor(valor);
    const color = COLOR[statusKey];

    // Preguntas de la encuesta que alimentan este EF (ref. "P5 - P8 - P13").
    const numerosPregunta = key === "ef1" || key === "ef4"
      ? encuestaDetalle.preguntas.filter((p) => (key === "ef1" ? p.es_ef1 : p.es_ef4)).map((p) => `P${p.numero}`)
      : [];

    fuente(doc, "sansMedium", 8.2, COLOR.ink);
    const nombreLineas = doc.splitTextToSize(info.descripcion, cols[1].w - 5);
    fuente(doc, "sans", 7.4, COLOR.ink2);
    const fuenteTexto = numerosPregunta.length > 0
      ? `Encuesta de heteroevaluación (${numerosPregunta.join(" - ")}) — ${info.fuente.replace(/^Encuesta de heteroevaluación,\s*/, "")}`
      : info.fuente;
    const fuenteLineas = doc.splitTextToSize(fuenteTexto, cols[1].w - 5);
    const rowH = Math.max(nombreLineas.length * 3.7 + fuenteLineas.length * 3.3 + 5, 10);

    const yNueva = checkPageBreak(y, rowH);
    if (yNueva !== y) { y = yNueva; dibujarEncabezado(); }

    if (i % 2 === 1) {
      fill(doc, COLOR.rowAlt);
      doc.rect(MARGIN_X, y, CONTENT_W, rowH, "F");
    }

    let cx2 = MARGIN_X;
    fuente(doc, "monoMedium", 8, COLOR.maroon);
    doc.text(info.titulo, cx2 + 3, y + 5);
    cx2 += cols[0].w;

    fuente(doc, "sansMedium", 8.2, COLOR.ink);
    doc.text(nombreLineas, cx2 + 3, y + 4.6);
    fuente(doc, "sans", 7.4, COLOR.ink2);
    doc.text(fuenteLineas, cx2 + 3, y + 4.6 + nombreLineas.length * 3.7 + 1.2);
    cx2 += cols[1].w;

    fuente(doc, "mono", 7.6, COLOR.ink3);
    doc.text(`${info.peso}%`, cx2 + cols[2].w - 3, y + 5, { align: "right" });
    cx2 += cols[2].w;

    const pctTexto = valor === null ? "—" : `${valor}%`;
    fuente(doc, "serifBold", 10, color);
    doc.text(pctTexto, cx2 + cols[3].w - 3, y + 4.8, { align: "right" });
    fuente(doc, "mono", 5.6, color);
    doc.text(STATUS_LABEL[statusKey].toUpperCase(), cx2 + cols[3].w - 3, y + 8, { align: "right" });

    stroke(doc, COLOR.rule);
    doc.setLineWidth(0.1);
    doc.line(MARGIN_X, y + rowH, MARGIN_X + CONTENT_W, y + rowH);

    y += rowH;
  });

  return y + 3;
}

function drawLeyendaEstados(doc: PdfDoc, y: number): number {
  const h = 8;
  fill(doc, COLOR.rowAlt);
  doc.rect(MARGIN_X, y, CONTENT_W, h, "F");
  let cx = MARGIN_X + 3;
  STATUS_ORDER.forEach((key) => {
    drawDot(doc, cx + 0.9, y + h / 2, 0.9, COLOR[key]);
    fuente(doc, "sans", 7, COLOR.ink2);
    doc.text(STATUS_LABEL[key], cx + 2.6, y + h / 2 + 1);
    cx += 4 + doc.getTextWidth(STATUS_LABEL[key]) + 8;
  });
  return y + h + 6;
}

// Mini barra apilada de distribución de respuestas (misma paleta FREQ del mockup).
function drawDistBar(doc: PdfDoc, x: number, y: number, w: number, h: number, conteos: Record<string, number>, total: number) {
  fill(doc, hexToRgb("#EFEDE6"));
  doc.roundedRect(x, y, w, h, h / 2, h / 2, "F");
  if (total <= 0) {
    fill(doc, COLOR.nodata);
    doc.roundedRect(x, y, w, h, h / 2, h / 2, "F");
    return;
  }
  let cursor = x;
  FREQ_ORDER.forEach((op) => {
    const n = conteos[op] ?? 0;
    if (n <= 0) return;
    const segW = (w * n) / total;
    fill(doc, FREQ_COLOR[op]);
    doc.rect(cursor, y, segW, h, "F");
    cursor += segW;
  });
}

// ── Detalle de encuesta de heteroevaluación (EF1/EF4) — filas tipo q-table ──
function drawDetalleEncuesta(
  doc: PdfDoc,
  yInicial: number,
  encuestaDetalle: EncuestaDetalle,
  checkPageBreak: (yActual: number, alturaNecesaria: number) => number,
): number {
  let y = yInicial;
  const preguntasEF = encuestaDetalle.preguntas.filter((p) => p.es_ef1 || p.es_ef4);

  const colPreg = 13, colEF = 11, colDist = 34, colResult = 32;
  const colTexto = CONTENT_W - colPreg - colEF - colDist - colResult;

  const headerH = 6.5;
  const dibujarEncabezado = () => {
    fill(doc, COLOR.maroon);
    doc.rect(MARGIN_X, y, CONTENT_W, headerH, "F");
    fuente(doc, "monoMedium", 6, COLOR.white);
    let cx = MARGIN_X;
    [["PREG.", colPreg], ["EF", colEF], ["ENUNCIADO", colTexto], ["DISTRIBUCIÓN", colDist], ["RESULTADO", colResult]].forEach(([h, w], i) => {
      const centrado = i === 4;
      const tx = centrado ? cx + (w as number) / 2 : cx + 3;
      doc.text(h as string, tx, y + 4.4, centrado ? { align: "center" } : {});
      cx += w as number;
    });
    y += headerH;
  };
  dibujarEncabezado();

  preguntasEF.forEach((p, i) => {
    const textoPregunta = p.texto ?? "(pregunta no encontrada en la encuesta actual)";
    const lineasTexto = doc.splitTextToSize(textoPregunta, colTexto - 5);
    const rowH = Math.max(lineasTexto.length * 3.4 + 4, 9);

    const yNueva = checkPageBreak(y, rowH);
    if (yNueva !== y) { y = yNueva; dibujarEncabezado(); }

    if (i % 2 === 1) {
      fill(doc, COLOR.white);
    } else {
      fill(doc, COLOR.rowAlt);
    }
    doc.rect(MARGIN_X, y, CONTENT_W, rowH, "F");

    let cx = MARGIN_X;
    fuente(doc, "monoMedium", 7.5, COLOR.ink);
    doc.text(`P${p.numero}`, cx + 3, y + rowH / 2 + 1.2);
    cx += colPreg;

    fuente(doc, "monoMedium", 7.5, COLOR.maroon);
    doc.text(p.es_ef1 ? "EF1" : "EF4", cx + 3, y + rowH / 2 + 1.2);
    cx += colEF;

    fuente(doc, "sans", 7.8, COLOR.ink);
    doc.text(lineasTexto, cx + 3, y + 4.2);
    cx += colTexto;

    const total = p.total;
    const dom = opcionDominante(p.conteos, total);
    drawDistBar(doc, cx + 3, y + rowH / 2 - 0.8, colDist - 6, 1.6, p.conteos, total);
    cx += colDist;

    // Centrado sobre el eje de la columna Resultado (no right-align contra el
    // margen de página): así el bloque de texto queda centrado como una unidad
    // en vez de con el borde izquierdo irregular según el largo de cada palabra.
    const centroResultado = cx + colResult / 2;
    if (dom) {
      fuente(doc, "sansBold", 7.5, FREQ_COLOR[dom.label]);
      doc.text(dom.label, centroResultado, y + rowH / 2, { align: "center" });
      fuente(doc, "mono", 6, COLOR.ink3);
      doc.text(`${dom.pct}% - n=${total}`, centroResultado, y + rowH / 2 + 3, { align: "center" });
    } else {
      fuente(doc, "sansItalic", 7, COLOR.ink3);
      doc.text("Sin respuestas", centroResultado, y + rowH / 2 + 1, { align: "center" });
    }

    stroke(doc, COLOR.rule);
    doc.setLineWidth(0.1);
    doc.line(MARGIN_X, y + rowH, MARGIN_X + CONTENT_W, y + rowH);
    y += rowH;
  });

  return y + 5;
}

// ── Nota de validez (línea pequeña itálica antes del pie, solo página 1) ──
function drawValidityNote(doc: PdfDoc, y: number): number {
  drawDiamond(doc, MARGIN_X + 0.6, y + 1.1, 0.6, COLOR.maroon);
  fuente(doc, "monoMedium", 7, COLOR.ink3);
  doc.text("Documento generado automáticamente por el sistema de seguimiento académico UCSG - No requiere firma.", MARGIN_X + 2.6, y + 1.6);
  return y + 6;
}

// ── Anexo: las 23 preguntas, formato compacto (página de continuación) ──
function drawLeyendaFrecuencias(doc: PdfDoc, y: number): number {
  let cx = MARGIN_X;
  fuente(doc, "sans", 7, COLOR.ink2);
  FREQ_ORDER.forEach((op) => {
    drawDot(doc, cx + 0.8, y, 0.8, FREQ_COLOR[op]);
    doc.text(op, cx + 2.3, y + 1);
    cx += 2.3 + doc.getTextWidth(op) + 6;
  });
  return y + 5;
}

function drawAnexoPreguntas(
  doc: PdfDoc,
  yInicial: number,
  encuestaDetalle: EncuestaDetalle,
  checkPageBreak: (yActual: number, alturaNecesaria: number) => number,
): number {
  let y = yInicial;
  const colPreg = 10, colEF = 9, colDist = 30, colResult = 24;
  const colTexto = CONTENT_W - colPreg - colEF - colDist - colResult;

  const headerH = 6;
  const dibujarEncabezado = () => {
    fill(doc, COLOR.maroon);
    doc.rect(MARGIN_X, y, CONTENT_W, headerH, "F");
    fuente(doc, "monoMedium", 5.6, COLOR.white);
    let cx = MARGIN_X;
    [["PREG.", colPreg], ["EF", colEF], ["ENUNCIADO", colTexto], ["DISTRIBUCIÓN", colDist], ["RESULTADO", colResult]].forEach(([h, w], i) => {
      const centrado = i === 4;
      const tx = centrado ? cx + (w as number) / 2 : cx + 3;
      doc.text(h as string, tx, y + 4, centrado ? { align: "center" } : {});
      cx += w as number;
    });
    y += headerH;
  };
  dibujarEncabezado();

  encuestaDetalle.preguntas.forEach((p, i) => {
    const textoPregunta = p.texto ?? "(pregunta no encontrada en la encuesta actual)";
    const lineasTexto = doc.splitTextToSize(textoPregunta, colTexto - 5);
    const rowH = Math.max(lineasTexto.length * 3.2 + 3.5, 8);

    const yNueva = checkPageBreak(y, rowH);
    if (yNueva !== y) { y = yNueva; dibujarEncabezado(); }

    fill(doc, i % 2 === 1 ? COLOR.white : COLOR.rowAlt);
    doc.rect(MARGIN_X, y, CONTENT_W, rowH, "F");

    let cx = MARGIN_X;
    fuente(doc, "monoMedium", 7, COLOR.ink);
    doc.text(`P${p.numero}`, cx + 3, y + rowH / 2 + 1);
    cx += colPreg;

    const marcador = p.es_ef1 ? "EF1" : p.es_ef4 ? "EF4" : "N/A";
    fuente(doc, "monoMedium", 7, p.es_ef1 || p.es_ef4 ? COLOR.maroon : COLOR.ink3);
    doc.text(marcador, cx + 3, y + rowH / 2 + 1);
    cx += colEF;

    fuente(doc, "sans", 7.2, COLOR.ink);
    doc.text(lineasTexto, cx + 3, y + 3.8);
    cx += colTexto;

    const total = p.total;
    const dom = opcionDominante(p.conteos, total);
    drawDistBar(doc, cx + 3, y + rowH / 2 - 0.7, colDist - 6, 1.4, p.conteos, total);
    cx += colDist;

    const centroResultado = cx + colResult / 2;
    if (dom) {
      fuente(doc, "sansBold", 6.8, FREQ_COLOR[dom.label]);
      doc.text(dom.label, centroResultado, y + rowH / 2, { align: "center" });
      fuente(doc, "mono", 5.4, COLOR.ink3);
      doc.text(`${dom.pct}% - n=${total}`, centroResultado, y + rowH / 2 + 2.8, { align: "center" });
    } else {
      fuente(doc, "sansItalic", 6.5, COLOR.ink3);
      doc.text("Sin respuestas", centroResultado, y + rowH / 2 + 0.8, { align: "center" });
    }

    stroke(doc, COLOR.rule);
    doc.setLineWidth(0.08);
    doc.line(MARGIN_X, y + rowH, MARGIN_X + CONTENT_W, y + rowH);
    y += rowH;
  });

  return y;
}

// ══════════════════════════════════════════════════════════════════════════
// Construcción del documento completo (testable: no depende del navegador).
// ══════════════════════════════════════════════════════════════════════════
export interface DatosPdfIndicador2 {
  asignatura: ResultadoAsignatura;
  cohortLabel: string;
  paoNumero: number;
  encuestaDetalle: EncuestaDetalle;
}

export async function construirDocumentoIndicador2(datos: DatosPdfIndicador2): Promise<PdfDoc> {
  const { asignatura, cohortLabel, paoNumero, encuestaDetalle } = datos;

  const { default: jsPDF } = await import("jspdf");
  const doc: PdfDoc = new jsPDF({ unit: "mm", format: "a4" });
  registrarFuentes(doc);

  let paginaActual = 1;
  drawFondoPapel(doc);
  drawFooter(doc);

  function checkPageBreak(yActual: number, alturaNecesaria: number): number {
    if (yActual + alturaNecesaria > PAGE_H - MARGIN_BOTTOM - 4) {
      doc.addPage();
      paginaActual += 1;
      drawFondoPapel(doc);
      drawRunHeader(doc, paginaActual);
      drawFooter(doc);
      return CONT_HEADER_BOTTOM_Y;
    }
    return yActual;
  }

  // ── Página 1 ──
  let y = drawMasthead(doc, asignatura, cohortLabel, paoNumero);

  y = dividerConTitulo(doc, MARGIN_X, y, CONTENT_W, "Resultado general");
  y = drawResultadoGeneral(doc, y, asignatura);

  y = dividerConTitulo(doc, MARGIN_X, y, CONTENT_W, "Elementos fundamentales y fuentes de evidencia");
  y = drawTablaEFs(doc, y, asignatura, encuestaDetalle, checkPageBreak);
  y = drawLeyendaEstados(doc, y);

  y = dividerConTitulo(doc, MARGIN_X, y, CONTENT_W, "Detalle de la encuesta de heteroevaluación");
  y = drawSectionSub(doc, MARGIN_X, y, CONTENT_W, `Preguntas que alimentan EF1 y EF4 - respuestas consideradas para esta asignatura: ${encuestaDetalle.respuestas_totales_materia}.`);
  y = drawDetalleEncuesta(doc, y, encuestaDetalle, checkPageBreak);

  y = drawValidityNote(doc, y);

  // ── Página(s) de anexo: las 23 preguntas ──
  doc.addPage();
  paginaActual += 1;
  drawFondoPapel(doc);
  drawRunHeader(doc, paginaActual);
  drawFooter(doc);
  y = CONT_HEADER_BOTTOM_Y;

  y = dividerConTitulo(doc, MARGIN_X, y, CONTENT_W, "Anexo — Las 23 preguntas de la encuesta de heteroevaluación");
  y = drawSectionSub(doc, MARGIN_X, y, CONTENT_W, "Distribución de respuestas por pregunta. Las filas marcadas con EF alimentan directamente el cálculo del indicador.");
  y = drawLeyendaFrecuencias(doc, y);
  drawAnexoPreguntas(doc, y, encuestaDetalle, checkPageBreak);

  return doc;
}

// ══════════════════════════════════════════════════════════════════════════
// API pública usada por la app (IndicatorView.tsx).
// ══════════════════════════════════════════════════════════════════════════
export interface ExportarPdfIndicador2Params extends DatosPdfIndicador2 {}

export async function exportarPdfIndicador2(params: ExportarPdfIndicador2Params): Promise<void> {
  const doc = await construirDocumentoIndicador2(params);
  doc.save(`indicador_11.2_${params.asignatura.nombre_asignatura.replace(/\s+/g, "_")}.pdf`);
}