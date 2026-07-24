import { useEffect, useState } from "react";

import {
  ArrowLeft,
  CheckCircle2,
  ChevronDown,
  FileText,
  Loader2,
} from "lucide-react";

import { toast } from "sonner";

import PdfZone from "../app/components/PdfZone";
import EvidenceHeader from "../app/components/EvidenceHeader";
import Breadcrumb from "../app/components/Breadcrumb";

import {
  prepararPdf,
  obtenerCatalogoEvidencias,
  obtenerEvaluacion,
  guardarEvidencia,
  obtenerEvidenciasGuardadas,
  obtenerEvidenciasCompartidas,
  subirPdfGoogleDrive,
  leerMatriculadosPdf,
  leerPdfTitulacion,
  guardarDatoTitulacion,
  leerPdfDesercion,
  guardarDatoDesercion,
} from "../services/evidencias";

import {
  COHORT_OPTIONS,
  MATERIAS_BY_PAO_MODULE,
} from "../data/academic";

import {
  subirEvidenciaAsignatura,
  obtenerEvidenciaAsignatura,
  obtenerAsignaturas,
  obtenerPeriodos,
} from "../services/seguimientoSyllabus";

import {
  subirEvidenciaTutorias,
  obtenerEvidenciaTutorias,
} from "../services/tutoriasAcademicas";

import type {
  Career,
  EvidStep,
  EvidenceSlot,
  IndicatorDef,
} from "../types";

export default function EvidenceUploadView({ career, indicators, onChange, onBack, preselectedCohort, preselectedIndicatorId }: {
  career: Career;
  indicators: IndicatorDef[];
  onChange: (inds: IndicatorDef[]) => void;
  onBack: () => void;
  preselectedCohort: string;
  preselectedIndicatorId?: string;
}) {
  const initialStep: EvidStep = preselectedIndicatorId
    ? (["I1", "I2", "I3"].includes(preselectedIndicatorId) ? "configSyllabus" : "configTitDes")
    : "selectIndicator";
  const [step, setStep] = useState<EvidStep>(initialStep);
  const [indicatorId, setIndicatorId] = useState<string>(preselectedIndicatorId ?? "");
  const [pao, setPao] = useState<string>("");
  const [module, setModule] = useState<string>("");
  const [materia, setMateria] = useState<string>("");
  const [cohort, setCohort] = useState<string>(preselectedCohort);
  const [loadingCatalogo, setLoadingCatalogo] = useState(false);
  const [catalogoError, setCatalogoError] = useState("");
  const [guardando, setGuardando] = useState(false);
  const [subiendoEvidencia, setSubiendoEvidencia] = useState(false);
  const [mensajeSubida, setMensajeSubida] = useState("Preparando evidencia...");

  // ── Resolución de id_asignatura real para I2 ─────────────────────────
  // 'encuesta_csv' (slot 5) se agrega a partir de la migración
  // sql/migracion_i2_encuesta_csv_por_asignatura.sql: el CSV de encuesta
  // ahora se sube por-asignatura igual que los otros 4 slots, en vez de ser
  // el mecanismo evaluation-wide de la tabla vieja `evidencias` (ver
  // MEMORIA v18 -- reemplaza lo decidido en v17 sección 41).
  const I2_SLOT_TIPO: Record<number, string> = {
    1: "syllabus",
    3: "acta_ajuste_curricular",
    4: "evidencia_difusion",
    5: "encuesta_csv",
  };

  // ── I3 (Tutorías Académicas): mismo mecanismo por-asignatura que I2,
  // pero con validación automática del PDF al subir (ver
  // api/tutorias_academicas/_validacion_pdf.php) y evaluación cualitativa
  // por puntos dentro de cada EF, no cuantitativa. Los 4 slots del wizard
  // (App.tsx) mapean 1:1 a los 4 tipos de evidencia_asignatura de I3.
  const I3_SLOT_TIPO: Record<number, string> = {
    1: "plan_tutorias",
    2: "registro_tutorias",
    3: "informe_tutorias",
    4: "evidencia_atencion",
  };
  const [asignaturaId, setAsignaturaId] = useState<number | null>(null);


  useEffect(() => {
    // I3 usa el mismo mecanismo por-asignatura que I2 (ver I3_SLOT_TIPO
    // arriba), así que necesita resolver asignaturaId igual que I2.
    if (!["I2", "I3"].includes(indicatorId) || !pao || !cohort) {
      setAsignaturaId(null);
      return;
    }

    // Se limpia de inmediato (sin esperar la resolucion async) para que el guard
    // de procesarPdf (asignaturaId === null) bloquee la subida mientras se resuelve
    // la nueva asignatura. Antes de este fix, el valor de la materia previa quedaba
    // vigente durante toda la ventana de la promesa (ver MEMORIA v13 seccion 32,
    // punto b: escritura confirmada bajo id_asignatura incorrecto sin error visible).
    setAsignaturaId(null);
    let cancelado = false;

    async function resolverAsignatura() {
      try {
        const cohorteNorm = cohort.replace(/\s+/g, "");
        setMensajeSubida("Procesando la información del documento...");

    const evaluacion = await obtenerEvaluacion(career.code, cohorteNorm);
        const periodos = await obtenerPeriodos(evaluacion.id_cohorte);
        const orden = Number(pao.replace(/\D/g, ""));
        const periodo = periodos.find((p) => p.orden === orden);
        if (!periodo) {
          if (!cancelado) setAsignaturaId(null);
          return;
        }
        const asignaturas = await obtenerAsignaturas(periodo.id_periodoacademico);
        // Comparación case-insensitive: el selector de materia (mock UI, Title
        // Case) y la tabla `asignatura` real (sentence case en varias filas) no
        // usan el mismo estilo de mayúsculas/minúsculas para el mismo nombre.
        // Antes de este fix, la comparación exacta (===) fallaba en silencio
        // para esos casos y dejaba asignaturaId en null, bloqueando la subida
        // con "No se pudo determinar la asignatura" aunque la materia sí
        // existiera en la BD (ver captura del usuario, 19 jul 2026).
        const materiaNorm = materia.trim().toLowerCase();
        const match = asignaturas.find(
          (a) => a.nombre.trim().toLowerCase() === materiaNorm,
        );
        if (!cancelado) setAsignaturaId(match?.id_asignatura ?? null);
      } catch {
        if (!cancelado) setAsignaturaId(null);
      }
    }

    resolverAsignatura();
    return () => { cancelado = true; };
  }, [indicatorId, pao, module, materia, cohort, career.code]);

  const indicator = indicators.find((i) => i.id === indicatorId);
  const isSyllabus = ["I1", "I2", "I3"].includes(indicatorId);

  useEffect(() => {
  if (indicatorId !== "I5" || !cohort) {
    return;
  }

  let activo = true;

  async function cargarEvidenciasTitulacion() {
    setLoadingCatalogo(true);
    setCatalogoError("");

    try {
      /*
       * I5 conserva sus cuatro posiciones visuales, pero la malla curricular
       * usa como fuente canónica DOC.SYL.01 del catálogo de I1.
       *
       * De esta forma, aunque se cargue desde I5, se registra con el mismo
       * id_catalogo y código que usan I1 e I2. Así existe una sola evidencia
       * y Compartir_Catalogo la distribuye a los demás indicadores.
       */
      const [catalogoI5, catalogoI1, evaluacion] =
        await Promise.all([
          obtenerCatalogoEvidencias(5),
          obtenerCatalogoEvidencias(1),
          obtenerEvaluacion(
            career.code,
            cohort.replace(/\s+/g, ""),
          ),
        ]);

      const [guardadas, compartidas] =
        await Promise.all([
          obtenerEvidenciasGuardadas(
            evaluacion.id_evaluacion,
            5,
          ),
          obtenerEvidenciasCompartidas(
            evaluacion.id_evaluacion,
            5,
          ),
        ]);

      if (!activo) {
        return;
      }

      const mallaCatalogo = catalogoI1.find(
        (item) =>
          item.codigo_evidencia === "DOC.SYL.01",
      );

      const mallaCompartida = compartidas.find(
        (item) =>
          item.codigo_evidencia === "DOC.SYL.01",
      );

      onChange(
        indicators.map((ind) => {
          if (ind.id !== "I5") {
            return ind;
          }

          const nuevosSlots: EvidenceSlot[] =
            catalogoI5.map((evidencia) => {
              const slotAnterior =
                ind.slots.find(
                  (slot) =>
                    slot.codigoEvidencia ===
                    evidencia.codigo_evidencia,
                ) ??
                ind.slots.find(
                  (slot) =>
                    slot.sourceNum ===
                    evidencia.orden,
                );

              /*
               * Posición 4 de I5: Malla curricular.
               * La fila DOC.TIT.04 solo define su posición visual dentro de
               * I5. Para guardar y consultar el archivo se utiliza la fuente
               * canónica DOC.SYL.01 del indicador I1.
               */
              if (
                evidencia.codigo_evidencia ===
                "DOC.TIT.04"
              ) {
                const metadataMalla =
                  mallaCatalogo ?? evidencia;

                return {
                  sourceNum: evidencia.orden,
                  label:
                    metadataMalla.titulo_corto ||
                    "Malla curricular",
                  idCatalogo:
                    metadataMalla.id_catalogo,
                  codigoEvidencia:
                    metadataMalla.codigo_evidencia,
                  nombreArchivoBase:
                    metadataMalla.nombre_archivo_base,
                  descripcionCompleta:
                    metadataMalla.descripcion,
                  sharedKey: "malla_curricular",
                  sharedFrom:
                    mallaCompartida?.indicador_origen,
                  idEvidencia:
                    mallaCompartida?.id_evidencia,
                  file: mallaCompartida
                    ? {
                        fileName:
                          mallaCompartida.nombre_archivo,
                        originalName:
                          mallaCompartida.nombre_archivo,
                        url:
                          mallaCompartida.url_archivo,
                        serverUrl:
                          mallaCompartida.url_archivo,
                        size: 0,
                      }
                    : slotAnterior?.file,
                  error: slotAnterior?.error,
                };
              }

              const guardada = guardadas.find(
                (item) =>
                  item.id_catalogo ===
                    evidencia.id_catalogo ||
                  item.codigo_evidencia ===
                    evidencia.codigo_evidencia,
              );

              let sharedKey: string | undefined;

              if (
                evidencia.codigo_evidencia ===
                "DOC.TIT.02"
              ) {
                sharedKey = "matriculados";
              }

              return {
                sourceNum: evidencia.orden,
                label: evidencia.titulo_corto,
                idCatalogo: evidencia.id_catalogo,
                codigoEvidencia:
                  evidencia.codigo_evidencia,
                nombreArchivoBase:
                  evidencia.nombre_archivo_base,
                descripcionCompleta:
                  evidencia.descripcion,
                sharedKey,

                idEvidencia:
                  guardada?.id_evidencia ??
                  slotAnterior?.idEvidencia,

                file: guardada
                  ? {
                      fileName:
                        guardada.nombre_archivo,
                      originalName:
                        guardada.nombre_archivo,
                      url: guardada.url_archivo,
                      serverUrl:
                        guardada.url_archivo,
                      size: 0,
                    }
                  : slotAnterior?.file,

                error: slotAnterior?.error,
                sharedFrom:
                  slotAnterior?.sharedFrom,
              };
            });

          return {
            ...ind,
            slots: nuevosSlots,
          };
        }),
      );
    } catch (error) {
      if (!activo) {
        return;
      }

      setCatalogoError(
        error instanceof Error
          ? error.message
          : "No se pudieron cargar las evidencias.",
      );
    } finally {
      if (activo) {
        setLoadingCatalogo(false);
      }
    }
  }

  void cargarEvidenciasTitulacion();

  return () => {
    activo = false;
  };
}, [indicatorId, cohort]);

// Efecto unificado para I1 e I2.
// Para I1: trae el catálogo propio (Catalogo_Evidencias) y lo mezcla en los
// slots por `orden` -> `sourceNum`. Los slots sin fila de catálogo (p. ej.
// "Asignaturas" en I1) se dejan intactos.
// Para I2: trae el catálogo propio de I2 (slots 2,3,4), el catálogo de I1
// para obtener DOC.SYL.02 (slot 1), y las evidencias compartidas. Todo se
// combina en UNA SOLA llamada a onChange, eliminando la race condition entre
// los dos efectos anteriores.
const ID_INDICADOR_NUM: Record<string, number> = {
  I1: 1,
  I2: 2,
  I3: 3,
  I4: 4,
  I5: 5,
};

useEffect(() => {
  if (!["I1", "I2", "I3"].includes(indicatorId) || !cohort) {
    return;
  }

  const idIndicadorReal = ID_INDICADOR_NUM[indicatorId];

  let activo = true;

  async function cargarDatosIndicador() {
    setLoadingCatalogo(true);
    setCatalogoError("");

    try {
            if (indicatorId === "I2") {
        // --- I2: catálogo propio (slots 2-4) + metadata de DOC.SYL.02 de I1
        // solo para el label/idCatalogo del slot 1 (ver sección 38 de la
        // memoria). `compartidas` no se usa para llenar el ARCHIVO de ningún
        // slot de I2 (se deja el fetch por si se necesita a futuro).
        // `guardadas` (tabla vieja evaluation-wide `evidencias`) SÍ se sigue
        // usando, pero solo para el slot 5 (CSV de encuesta), que no tiene
        // tipo en evidencia_asignatura -- ver más abajo. ---
        const [catalogoI2, catalogoI1, evaluacion] = await Promise.all([
          obtenerCatalogoEvidencias(2),
          obtenerCatalogoEvidencias(1),
          obtenerEvaluacion(
            career.code,
            cohort.replace(/\s+/g, ""),
          ),
        ]);

        const [guardadas, compartidas] = await Promise.all([
          obtenerEvidenciasGuardadas(
            evaluacion.id_evaluacion,
            2,
          ),
          obtenerEvidenciasCompartidas(
            evaluacion.id_evaluacion,
            2,
          ),
        ]);

        // Para I2 slots 2-4: cargar evidencia real desde evidencia_asignatura
        let evidenciaAsignatura = null;
        if (asignaturaId !== null) {
          try {
            evidenciaAsignatura = await obtenerEvidenciaAsignatura(asignaturaId);
          } catch {
            // Si falla la consulta, seguir con lo que haya en la tabla vieja
          }
        }

        if (!activo) {
          return;
        }

        // Metadata del Syllabus desde catálogo de I1 (DOC.SYL.02). Se sigue
        // usando solo para label/idCatalogo/etc.: el catálogo propio de I2 no
        // tiene una fila equivalente en orden=1 (esa posición la ocupa
        // DOC.SEG.01, Reglamento/Normativa, sin slot visible todavía en el
        // wizard). El ARCHIVO del slot 1 ya NO viene de aquí -- ver más abajo.
        const syllabusCatalogo = catalogoI1.find(
          (item) => item.codigo_evidencia === "DOC.SYL.02",
        );

        // Malla Curricular (DOC.SYL.01) es propia del catálogo de I1, no del
        // de I2 -- se busca en catalogoI1, igual que el Syllabus arriba.
        const mallaCatalogo = catalogoI1.find(
          (item) => item.codigo_evidencia === "DOC.SYL.01",
        );

        // Normativa Institucional (DOC.SEG.01) SÍ es propia del catálogo de
        // I2 (orden=1), pero nunca tuvo slot visible -- ver comentario más
        // abajo, en el slot correspondiente.
        const normativaCatalogo = catalogoI2.find(
          (item) => item.codigo_evidencia === "DOC.SEG.01",
        );

        onChange(
          indicators.map((ind) => {
            if (ind.id !== "I2") {
              return ind;
            }

            return {
              ...ind,
              slots: ind.slots.map((slot) => {
                // Malla Curricular: evaluation-wide (a nivel carrera+cohorte,
                // NO por asignatura), igual que reglamento_normativa. La
                // metadata sale del catálogo de I1 (DOC.SYL.01, dueño
                // original), el ARCHIVO sale de `compartidas` -- la regla de
                // compartición (compartir_catalogo: catálogo 5 -> indicador 2)
                // ya existe en la base real, así que `compartidas` ya trae
                // este documento aunque nunca se haya mostrado en pantalla.
                // Si se sube desde este slot, se guarda con el mismo
                // idCatalogo=5 que usa I1 -- mismo registro (clave única
                // id_evaluacion+id_catalogo), no uno nuevo.
                if (slot.sourceNum === 7) {
                  const conMetadata = mallaCatalogo
                    ? {
                        ...slot,
                        label: mallaCatalogo.titulo_corto || slot.label,
                        idCatalogo: mallaCatalogo.id_catalogo,
                        codigoEvidencia: mallaCatalogo.codigo_evidencia,
                        nombreArchivoBase: mallaCatalogo.nombre_archivo_base,
                        descripcionCompleta: mallaCatalogo.descripcion,
                      }
                    : slot;

                  const compartidaMalla = compartidas.find(
                    (e: any) => e.codigo_evidencia === "DOC.SYL.01",
                  );

                  return {
                    ...conMetadata,
                    sharedKey: "malla_curricular",
                    sharedFrom: compartidaMalla?.indicador_origen,
                    idEvidencia: compartidaMalla?.id_evidencia,
                    file: compartidaMalla
                      ? {
                          fileName: compartidaMalla.nombre_archivo,
                          originalName: compartidaMalla.nombre_archivo,
                          url: compartidaMalla.url_archivo,
                          serverUrl: compartidaMalla.url_archivo,
                          size: 0,
                        }
                      : undefined,
                  };
                }

                // Normativa Institucional: evaluation-wide, propia del
                // catálogo de I2 (DOC.SEG.01, orden=1) -- existía desde el
                // inicio pero sin slot visible porque esa posición (orden=1)
                // quedó ocupada por el Syllabus reubicado (ver más abajo).
                // Se lee de `guardadas` (evaluation-wide de I2), no de
                // evidencia_asignatura -- no es por-materia.
                if (slot.sourceNum === 6) {
                  const conMetadata = normativaCatalogo
                    ? {
                        ...slot,
                        label: normativaCatalogo.titulo_corto || slot.label,
                        idCatalogo: normativaCatalogo.id_catalogo,
                        codigoEvidencia: normativaCatalogo.codigo_evidencia,
                        nombreArchivoBase: normativaCatalogo.nombre_archivo_base,
                        descripcionCompleta: normativaCatalogo.descripcion,
                      }
                    : slot;

                  const guardadaNormativa = guardadas.find(
                    (e: any) => e.codigo_evidencia === "DOC.SEG.01",
                  );

                  return {
                    ...conMetadata,
                    idEvidencia: guardadaNormativa?.id_evidencia,
                    file: guardadaNormativa
                      ? {
                          fileName: guardadaNormativa.nombre_archivo,
                          originalName: guardadaNormativa.nombre_archivo,
                          url: guardadaNormativa.url_archivo,
                          serverUrl: guardadaNormativa.url_archivo,
                          size: 0,
                        }
                      : undefined,
                  };
                }

                // Slot 1 (Syllabus): mismo patrón por-asignatura que los slots
                // 2-4 (ver MEMORIA sección 38). La metadata (label/idCatalogo/
                // codigoEvidencia/etc.) se sigue tomando del catálogo de I1
                // (DOC.SYL.02) -- eso no cambia y es necesario porque
                // procesarPdf exige idCatalogo/codigoEvidencia antes de
                // permitir la subida. Lo que SÍ cambia es el ARCHIVO: ya no
                // viene del mecanismo compartido evaluación-wide de I1
                // (`compartidas` / DOC.SYL.02), sino de evidencia_asignatura
                // con tipo="syllabus", igual que EF2/EF3. Si idAsignatura
                // todavía no resolvió (evidenciaAsignatura === null), el slot
                // se muestra sin archivo -- nunca cae al mecanismo viejo.
                if (slot.sourceNum === 1) {
                  const conMetadata = syllabusCatalogo
                    ? {
                        ...slot,
                        label: syllabusCatalogo.titulo_corto || slot.label,
                        idCatalogo: syllabusCatalogo.id_catalogo,
                        codigoEvidencia: syllabusCatalogo.codigo_evidencia,
                        nombreArchivoBase: syllabusCatalogo.nombre_archivo_base,
                        descripcionCompleta: syllabusCatalogo.descripcion,
                      }
                    : slot;

                  const itemSyllabus = evidenciaAsignatura?.find(
                    (e: any) => e.tipo === "syllabus",
                  );

                  return {
                    ...conMetadata,
                    sharedKey: undefined,
                    sharedFrom: undefined,
                    idEvidencia: undefined,
                    file:
                      itemSyllabus && itemSyllabus.subida && itemSyllabus.archivo
                        ? {
                            fileName: itemSyllabus.archivo.nombre_archivo,
                            originalName: itemSyllabus.archivo.nombre_archivo,
                            url: itemSyllabus.archivo.url_archivo,
                            serverUrl: itemSyllabus.archivo.url_archivo,
                            size: 0,
                          }
                        : undefined,
                  };
                }

                // Slots 2,3,4: metadata (label/idCatalogo/etc.) desde el
                // catálogo propio de I2 -- eso no cambia, `procesarPdf` lo
                // sigue necesitando para subir. El ARCHIVO, en cambio, sale
                // ESTRICTAMENTE de evidencia_asignatura, sin fallback a la
                // tabla vieja evaluation-wide `evidencias` (`guardadas`).
                // Antes, cuando esta materia no tenía fila en
                // evidencia_asignatura para este tipo (materia vacía o
                // asignaturaId aún sin resolver), el código caía a
                // `guardadas`, que es evaluación-wide y no filtra por
                // asignatura -- eso hacía aparecer archivos de OTRA materia
                // como si ya estuvieran cargados en esta (falso check verde,
                // ver MEMORIA sección 39 punto 1, confirmado en vivo). Mismo
                // patrón que el slot 1 (arriba) y que TabEvidences en
                // IndicatorView.tsx, que nunca tuvo este fallback.
                //
                // Slot 5 (CSV Resultados de Encuesta) YA NO es una
                // excepción (ver MEMORIA v18, reemplaza lo decidido en v17
                // sección 41): a partir de la migración
                // sql/migracion_i2_encuesta_csv_por_asignatura.sql,
                // 'encuesta_csv' es un tipo más en evidencia_asignatura, y
                // el slot 5 entra al mismo bloque estricto que 2-4 (I2_SLOT_TIPO
                // ya lo mapea arriba). El bloque `slotTipo === undefined` de
                // abajo queda como fallback genérico para I2, pero ya
                // ningún slot de I2 cae ahí.
                const slotTipo = I2_SLOT_TIPO[slot.sourceNum];
                const evidencia = catalogoI2.find(
                  (item) => item.orden === slot.sourceNum,
                );

                if (!evidencia) {
                  return slot;
                }

                if (slotTipo === undefined) {
                  const guardada = guardadas.find(
                    (item) =>
                      item.id_catalogo === evidencia.id_catalogo ||
                      item.codigo_evidencia === evidencia.codigo_evidencia,
                  );

                  return {
                    ...slot,
                    label: evidencia.titulo_corto || slot.label,
                    idCatalogo: evidencia.id_catalogo,
                    codigoEvidencia: evidencia.codigo_evidencia,
                    nombreArchivoBase: evidencia.nombre_archivo_base,
                    descripcionCompleta: evidencia.descripcion,

                    idEvidencia:
                      guardada?.id_evidencia ?? slot.idEvidencia,

                    file: guardada
                      ? {
                          fileName: guardada.nombre_archivo,
                          originalName: guardada.nombre_archivo,
                          url: guardada.url_archivo,
                          serverUrl: guardada.url_archivo,
                          size: 0,
                        }
                      : slot.file,
                  };
                }

                const item = evidenciaAsignatura?.find(
                  (e: any) => e.tipo === slotTipo,
                );

                return {
                  ...slot,
                  label: evidencia.titulo_corto || slot.label,
                  idCatalogo: evidencia.id_catalogo,
                  codigoEvidencia: evidencia.codigo_evidencia,
                  nombreArchivoBase: evidencia.nombre_archivo_base,
                  descripcionCompleta: evidencia.descripcion,

                  idEvidencia: undefined,

                  file:
                    item && item.subida && item.archivo
                      ? {
                          fileName: item.archivo.nombre_archivo,
                          originalName: item.archivo.nombre_archivo,
                          url: item.archivo.url_archivo,
                          serverUrl: item.archivo.url_archivo,
                          size: 0,
                        }
                      : undefined,
                };
              }),
            };
          }),
        );
      } else if (indicatorId === "I3") {
        // --- I3 (Tutorías Académicas): igual patrón que I2 slots 1-4, pero
        // sin evaluation-wide (I3 no tiene ningún slot compartido/CSV como
        // I2) y sin fallback a la tabla vieja `evidencias` -- estrictamente
        // por-asignatura desde el día 1, mismo criterio ya validado en I2
        // (ver MEMORIA sección 39 punto 1). La metadata (label/idCatalogo/
        // codigoEvidencia/etc.) sigue viniendo del catálogo propio de I3
        // porque `procesarPdf` la exige antes de permitir la subida; el
        // ARCHIVO y su validación (puntos cumplidos por EF) vienen de
        // evidencia_asignatura vía el endpoint real de tutorías. ---
        const catalogoI3 = await obtenerCatalogoEvidencias(3);

        let evidenciaTutorias: Awaited<ReturnType<typeof obtenerEvidenciaTutorias>> | null = null;
        if (asignaturaId !== null) {
          try {
            evidenciaTutorias = await obtenerEvidenciaTutorias(asignaturaId);
          } catch {
            // Si falla la consulta, el slot se muestra sin archivo (nunca
            // cae al mecanismo viejo evaluation-wide).
          }
        }

        if (!activo) {
          return;
        }

        onChange(
          indicators.map((ind) => {
            if (ind.id !== "I3") {
              return ind;
            }

            return {
              ...ind,
              slots: ind.slots.map((slot) => {
                const evidencia = catalogoI3.find(
                  (item) => item.orden === slot.sourceNum,
                );

                if (!evidencia) {
                  return slot;
                }

                const tipo = I3_SLOT_TIPO[slot.sourceNum];
                const item = evidenciaTutorias?.find((e) => e.tipo === tipo);

                return {
                  ...slot,
                  label: evidencia.titulo_corto || slot.label,
                  idCatalogo: evidencia.id_catalogo,
                  codigoEvidencia: evidencia.codigo_evidencia,
                  nombreArchivoBase: evidencia.nombre_archivo_base,
                  descripcionCompleta: evidencia.descripcion,

                  idEvidencia: undefined,

                  file:
                    item && item.subida && item.archivo
                      ? {
                          fileName: item.archivo.nombre_archivo,
                          originalName: item.archivo.nombre_archivo,
                          url: item.archivo.url_archivo,
                          serverUrl: item.archivo.url_archivo,
                          size: 0,
                        }
                      : undefined,
                };
              }),
            };
          }),
        );
      } else {
        // --- I1 (y cualquier otro indicador que no sea I2/I3 en este branch) ---
        const [catalogo, evaluacion] = await Promise.all([
          obtenerCatalogoEvidencias(idIndicadorReal),
          obtenerEvaluacion(
            career.code,
            cohort.replace(/\s+/g, ""),
          ),
        ]);

        const guardadas = await obtenerEvidenciasGuardadas(
          evaluacion.id_evaluacion,
          idIndicadorReal,
        );

        if (!activo) {
          return;
        }

        onChange(
          indicators.map((ind) => {
            if (ind.id !== indicatorId) {
              return ind;
            }

            return {
              ...ind,
              slots: ind.slots.map((slot) => {
                const evidencia = catalogo.find(
                  (item) => item.orden === slot.sourceNum,
                );

                if (!evidencia) {
                  // No hay fila de catálogo para este slot (p. ej.
                  // "Asignaturas" en I1). Se deja tal como está.
                  return slot;
                }

                const guardada = guardadas.find(
                  (item) =>
                    item.id_catalogo === evidencia.id_catalogo ||
                    item.codigo_evidencia === evidencia.codigo_evidencia,
                );

                return {
                  ...slot,
                  label: evidencia.titulo_corto || slot.label,
                  idCatalogo: evidencia.id_catalogo,
                  codigoEvidencia: evidencia.codigo_evidencia,
                  nombreArchivoBase: evidencia.nombre_archivo_base,
                  descripcionCompleta: evidencia.descripcion,

                  idEvidencia:
                    guardada?.id_evidencia ?? slot.idEvidencia,

                  file: guardada
                    ? {
                        fileName: guardada.nombre_archivo,
                        originalName: guardada.nombre_archivo,
                        url: guardada.url_archivo,
                        serverUrl: guardada.url_archivo,
                        size: 0,
                      }
                    : slot.file,
                };
              }),
            };
          }),
        );
      }
    } catch (error) {
      if (!activo) {
        return;
      }

      setCatalogoError(
        error instanceof Error
          ? error.message
          : "No se pudo cargar el catálogo de evidencias.",
      );
    } finally {
      if (activo) {
        setLoadingCatalogo(false);
      }
    }
  }

  cargarDatosIndicador();

  return () => {
    activo = false;
  };
  // asignaturaId agregado a las deps: sin esto, este efecto corre una sola vez
  // al entrar a configSyllabus (antes de elegir PAO/materia), cuando asignaturaId
  // todavia es null, y nunca se vuelve a ejecutar cuando la asignatura se resuelve
  // -- el fetch a evidencia_asignatura de mas abajo casi nunca llegaba a correr.
}, [indicatorId, cohort, asignaturaId]);

useEffect(() => {
  if (indicatorId !== "I4" || !cohort) {
    return;
  }

  let activo = true;

  async function cargarEvidenciasDesercion() {
    setLoadingCatalogo(true);
    setCatalogoError("");

    try {
      const [catalogo, evaluacion] = await Promise.all([
        obtenerCatalogoEvidencias(4),
        obtenerEvaluacion(
          career.code,
          cohort.replace(/\s+/g, ""),
        ),
      ]);

      const [guardadas, compartidas] =
        await Promise.all([
          obtenerEvidenciasGuardadas(
            evaluacion.id_evaluacion,
            4,
          ),
          obtenerEvidenciasCompartidas(
            evaluacion.id_evaluacion,
            4,
          ),
        ]);

      if (!activo) {
        return;
      }

      onChange(
        indicators.map((ind) => {
          if (ind.id !== "I4") {
            return ind;
          }

          const evidenciaCompartidaPrimerNivel =
            compartidas.find(
              (evidencia) =>
                evidencia.codigo_evidencia ===
                "DOC.TIT.02",
            );

          const nuevosSlots: EvidenceSlot[] =
            ind.slots.map((slot) => {
              /*
               * Slot 1: matriculados de primer nivel.
               * Se comparte desde I5 y no se vuelve a subir.
               */
              if (
                slot.sourceNum === 1 &&
                evidenciaCompartidaPrimerNivel
              ) {
                return {
                  ...slot,
                  label:
                    "Estudiantes matriculados en primer nivel",
                  idEvidencia:
                    evidenciaCompartidaPrimerNivel.id_evidencia,
                  codigoEvidencia:
                    evidenciaCompartidaPrimerNivel.codigo_evidencia,
                  nombreArchivoBase:
                    evidenciaCompartidaPrimerNivel.nombre_archivo_base,
                  descripcionCompleta:
                    evidenciaCompartidaPrimerNivel.descripcion,
                  sharedKey: "matriculados",
                  sharedFrom:
                    evidenciaCompartidaPrimerNivel.indicador_origen,
                  file: {
                    fileName:
                      evidenciaCompartidaPrimerNivel.nombre_archivo,
                    originalName:
                      evidenciaCompartidaPrimerNivel.nombre_archivo,
                    url:
                      evidenciaCompartidaPrimerNivel.url_archivo,
                    serverUrl:
                      evidenciaCompartidaPrimerNivel.url_archivo,
                    size: 0,
                  },
                  error: undefined,
                };
              }

              /*
               * Slots 2 y 3: evidencias propias de I4.
               * Aquí sí es indispensable cargar idCatalogo,
               * codigoEvidencia y nombreArchivoBase.
               */
              const evidenciaCatalogo =
                catalogo.find(
                  (evidencia) =>
                    Number(evidencia.orden) ===
                    Number(slot.sourceNum),
                );

              const evidenciaGuardada =
                evidenciaCatalogo
                  ? guardadas.find(
                      (evidencia) =>
                        evidencia.id_catalogo ===
                          evidenciaCatalogo.id_catalogo ||
                        evidencia.codigo_evidencia ===
                          evidenciaCatalogo.codigo_evidencia,
                    )
                  : undefined;

              return {
                ...slot,
                label:
                  evidenciaCatalogo?.titulo_corto ??
                  slot.label,
                idCatalogo:
                  evidenciaCatalogo?.id_catalogo ??
                  slot.idCatalogo,
                codigoEvidencia:
                  evidenciaCatalogo?.codigo_evidencia ??
                  slot.codigoEvidencia,
                nombreArchivoBase:
                  evidenciaCatalogo?.nombre_archivo_base ??
                  slot.nombreArchivoBase,
                descripcionCompleta:
                  evidenciaCatalogo?.descripcion ??
                  slot.descripcionCompleta,
                idEvidencia:
                  evidenciaGuardada?.id_evidencia ??
                  slot.idEvidencia,
                file: evidenciaGuardada
                  ? {
                      fileName:
                        evidenciaGuardada.nombre_archivo,
                      originalName:
                        evidenciaGuardada.nombre_archivo,
                      url:
                        evidenciaGuardada.url_archivo,
                      serverUrl:
                        evidenciaGuardada.url_archivo,
                      size: 0,
                    }
                  : slot.file,
                sharedKey:
                  slot.sourceNum === 1
                    ? "matriculados"
                    : undefined,
                sharedFrom:
                  slot.sourceNum === 1
                    ? slot.sharedFrom
                    : undefined,
                error: slot.error,
              };
            });

          return {
            ...ind,
            slots: nuevosSlots,
          };
        }),
      );
    } catch (error) {
      if (!activo) {
        return;
      }

      setCatalogoError(
        error instanceof Error
          ? error.message
          : "No se pudieron cargar las evidencias de deserción.",
      );
    } finally {
      if (activo) {
        setLoadingCatalogo(false);
      }
    }
  }

  void cargarEvidenciasDesercion();

  return () => {
    activo = false;
  };
}, [indicatorId, cohort]);

  function handleSelectIndicator(id: string) {
    setIndicatorId(id);
    setPao(""); setModule(""); setMateria("");
    if (["I1", "I2", "I3"].includes(id)) {
      setCohort(preselectedCohort);
      setStep("configSyllabus");
    } else {
      setCohort(preselectedCohort);
      setStep("configTitDes");
    }
  }

  function updateSlot(indId: string, updated: EvidenceSlot) {
    onChange(indicators.map((ind) => {
      if (ind.id !== indId) {
        if (!updated.sharedKey) return ind;
        const matchSlot = ind.slots.find((s) => s.sharedKey === updated.sharedKey);
        if (!matchSlot) return ind;
        const syncedSlot: EvidenceSlot = { ...matchSlot, file: updated.file, error: updated.error, sharedFrom: updated.file ? indId : undefined };
        return { ...ind, slots: ind.slots.map((s) => s.sharedKey === updated.sharedKey ? syncedSlot : s) };
      }
      const newSlots = ind.slots.map((s) => s.sourceNum === updated.sourceNum ? updated : s);
      return { ...ind, slots: newSlots};
    }));
  }
  
  async function procesarPdf(
  slot: EvidenceSlot,
  archivo: File,
) {
  if (!slot.idCatalogo) {
    toast.error(
      "La evidencia no está relacionada con el catálogo.",
    );
    return;
  }

  if (!slot.codigoEvidencia) {
    toast.error(
      "La evidencia no tiene código asignado.",
    );
    return;
  }

  if (!indicator) {
    toast.error(
      "No se encontró el indicador seleccionado.",
    );
    return;
  }



    const esCsv = slot.acceptedType === "csv";

  // ── I2: slots 1-5 (incl. CSV de encuesta) → subir a evidencia_asignatura,
  // no a la tabla vieja `evidencias` (ver MEMORIA v18) ──
  if (
    indicator.id === "I2" &&
    I2_SLOT_TIPO[slot.sourceNum] !== undefined
  ) {
    if (asignaturaId === null) {
      toast.error(
        "No se pudo determinar la asignatura. Verifique la configuración de período y materia.",
      );
      return;
    }

    const tipo = I2_SLOT_TIPO[slot.sourceNum];
    const activoSubida = true;

    setSubiendoEvidencia(true);
    setMensajeSubida(esCsv
      ? "Validando y subiendo el archivo CSV..."
      : "Validando y subiendo el archivo PDF...");

    try {
      const resultado = await subirEvidenciaAsignatura({
        idAsignatura: asignaturaId,
        tipo: tipo as any,
        archivo,
      });

      if (!activoSubida) return;

      updateSlot(indicator.id, {
        ...slot,
        error: undefined,
        file: {
          fileName: archivo.name,
          originalName: archivo.name,
          url: resultado.url_archivo,
          serverUrl: resultado.url_archivo,
          size: archivo.size,
        },
      });

      toast.success(esCsv ? "CSV guardado correctamente" : "PDF guardado correctamente", {
        description: "La evidencia se subió a Google Drive y se registró en la asignatura.",
      });
    } catch (error) {
      if (!activoSubida) return;
      updateSlot(indicator.id, {
        ...slot,
        error: error instanceof Error ? error.message : "No se pudo procesar el archivo.",
      });
      toast.error("No se pudo guardar el archivo", {
        description: error instanceof Error ? error.message : "Ocurrió un error inesperado.",
      });
    } finally {
      setSubiendoEvidencia(false);
      setMensajeSubida("Preparando evidencia...");
    }
    return;
  }

  // ── I3: slots 1-4 → subir a evidencia_asignatura vía el endpoint real de
  // tutorías, que además valida el PDF automáticamente (puntos por EF) al
  // guardarlo. Mismo patrón que I2 arriba, pero con su propio mecanismo de
  // validación cualitativa (ver api/tutorias_academicas/_validacion_pdf.php) ──
  if (
    indicator.id === "I3" &&
    I3_SLOT_TIPO[slot.sourceNum] !== undefined
  ) {
    if (asignaturaId === null) {
      toast.error(
        "No se pudo determinar la asignatura. Verifique la configuración de período y materia.",
      );
      return;
    }

    const tipo = I3_SLOT_TIPO[slot.sourceNum];
    const activoSubida = true;

    setSubiendoEvidencia(true);
    setMensajeSubida("Validando y subiendo el archivo PDF...");

    try {
      const resultado = await subirEvidenciaTutorias({
        idAsignatura: asignaturaId,
        tipo: tipo as any,
        archivo,
      });

      if (!activoSubida) return;

      updateSlot(indicator.id, {
        ...slot,
        error: undefined,
        file: {
          fileName: archivo.name,
          originalName: archivo.name,
          url: resultado.url_archivo,
          serverUrl: resultado.url_archivo,
          size: archivo.size,
        },
      });

      toast.success(
        `PDF guardado y validado (${resultado.cumplidos}/${resultado.total_puntos} puntos cumplidos)`,
        {
          description: "La evidencia se subió a Google Drive y se validó automáticamente para " + resultado.ef + ".",
        },
      );
    } catch (error) {
      if (!activoSubida) return;
      updateSlot(indicator.id, {
        ...slot,
        error: error instanceof Error ? error.message : "No se pudo procesar el archivo.",
      });
      toast.error("No se pudo guardar el archivo", {
        description: error instanceof Error ? error.message : "Ocurrió un error inesperado.",
      });
    } finally {
      setSubiendoEvidencia(false);
      setMensajeSubida("Preparando evidencia...");
    }
    return;
  }

  setSubiendoEvidencia(true);
  setMensajeSubida(esCsv
    ? "Validando y subiendo el archivo CSV..."
    : "Validando y subiendo el archivo PDF...");

  try {
  /*
   * 1. Obtener la evaluación y la cohorte real registrada en MySQL.
   */
    const evaluacion = await obtenerEvaluacion(
      career.code,
      cohort.replace(/\s+/g, ""),
    );

    const cohorteBD =
      evaluacion.nombre_cohorte
        .trim()
        .replace(/\s+/g, "")
        .toUpperCase();

    /*
    * 2. Validar el archivo y generar el nombre técnico
    * utilizando la cohorte obtenida desde MySQL.
    */
    const preparacion = await prepararPdf({
      archivo,
      idCatalogo: slot.idCatalogo,
      codigoCarrera: career.code,
      cohorte: cohorteBD,
      criterio: career.criterionNum,
      indicador: indicator.num,
    });
      

    if (!preparacion.datos) {
      throw new Error(
        "El servidor no devolvió la información del PDF.",
      );
    }

    let matriculadosDetectados: number | null = null;

if (slot.codigoEvidencia === "DOC.TIT.02") {
  const lectura = await leerMatriculadosPdf(
    archivo,
  );

  matriculadosDetectados =
    lectura.datos?.matriculados ?? null;

  if (
  lectura.datos?.cohorte_detectada &&
  lectura.datos.cohorte_detectada
    .trim()
    .replace(/\s+/g, "")
    .toUpperCase() !== cohorteBD
) {
  throw new Error(
    `El PDF corresponde a la cohorte ${lectura.datos.cohorte_detectada}, pero está seleccionada la cohorte ${cohorteBD}.`,
  );
}
}
    /*
     * 2. Subir o reemplazar el archivo en Google Drive.
     */
    setMensajeSubida("Subiendo evidencia a Google Drive...");

    const drive = await subirPdfGoogleDrive({
      archivo,
      codigoCarrera: career.code,
      nombreCarrera: career.name,
      cohorte: cohorteBD,
      indicador: indicator.num,
      nombreArchivo:
        preparacion.datos.nombre_generado,
      tipoEsperado: esCsv ? "csv" : "pdf",
    });

    const urlDrive =
      drive.datos?.url_archivo?.trim() ?? "";

    if (
      !urlDrive.startsWith(
        "https://drive.google.com/",
      )
    ) {
      throw new Error(
        "Google Drive no devolvió una URL válida.",
      );
    }

    const cohorteNormalizada = cohorteBD;

    let descripcionResultado:
      | string
      | null = null;

    /*
     * 4A. Lectura y cálculo de Tasa de Titulación (I5).
     * Según el catálogo: DOC.TIT.01 = graduados, DOC.TIT.02 = matriculados.
     */
    if (
      indicator.id === "I5" &&
      (
        slot.codigoEvidencia === "DOC.TIT.01" ||
        slot.codigoEvidencia === "DOC.TIT.02"
      )
    ) {
      const tipoDato =
        slot.codigoEvidencia === "DOC.TIT.01"
          ? "graduados"
          : "matriculados";

      const lectura = await leerPdfTitulacion(
        archivo,
        tipoDato,
      );

      if (
        lectura.cohorte_detectada &&
        lectura.cohorte_detectada !==
          cohorteNormalizada
      ) {
        throw new Error(
          `El PDF corresponde a la cohorte ${lectura.cohorte_detectada}, pero está seleccionada la cohorte ${cohorteNormalizada}.`,
        );
      }

      const guardadoTitulacion =
        await guardarDatoTitulacion({
          idEvaluacion:
            evaluacion.id_evaluacion,
          cohorte: cohorteNormalizada,
          ...(tipoDato === "matriculados"
            ? {
                matriculados:
                  lectura.total,
              }
            : {
                graduados:
                  lectura.total,
              }),
        });

      /*
       * El PDF de matriculados de primer nivel se comparte con I4.
       * Por eso también se registra como dato inicial para la
       * Tasa de Deserción.
       */
      if (tipoDato === "matriculados") {
        await guardarDatoDesercion({
          idEvaluacion:
            evaluacion.id_evaluacion,
          cohorte: cohorteNormalizada,
          iniciaronPrimerNivel:
            lectura.total,
        });
      }

      descripcionResultado =
        guardadoTitulacion.tasa !== null
          ? `${
              tipoDato === "matriculados"
                ? "Matriculados"
                : "Graduados"
            } detectados: ${
              lectura.total
            }. Tasa de titulación calculada: ${
              guardadoTitulacion.tasa
            }%.`
          : `${
              tipoDato === "matriculados"
                ? "Matriculados"
                : "Graduados"
            } detectados: ${
              lectura.total
            }. Falta el otro PDF para calcular la tasa de titulación.`;
    }

    /*
     * 4B. Lectura y cálculo de Tasa de Deserción (I4).
     *
     * Slot 1 = primer nivel (compartido con I5)
     * Slot 2 = segundo año
     * Slot 3 = estudiantes que no continuaron
     */
    if (
      indicator.id === "I4" &&
      (
        slot.sourceNum === 1 ||
        slot.sourceNum === 2 ||
        slot.sourceNum === 3
      )
    ) {
      const tipoDato =
        slot.sourceNum === 1
          ? "primer_nivel"
          : slot.sourceNum === 2
            ? "segundo_anio"
            : "no_continuaron";

      const lectura = await leerPdfDesercion(
        archivo,
        tipoDato,
      );

      if (
        lectura.cohorte_detectada &&
        lectura.cohorte_detectada !==
          cohorteNormalizada
      ) {
        throw new Error(
          `El PDF corresponde a la cohorte ${lectura.cohorte_detectada}, pero está seleccionada la cohorte ${cohorteNormalizada}.`,
        );
      }

      const guardadoDesercion =
        await guardarDatoDesercion({
          idEvaluacion:
            evaluacion.id_evaluacion,
          cohorte: cohorteNormalizada,
          ...(tipoDato === "primer_nivel"
            ? {
                iniciaronPrimerNivel:
                  lectura.total,
              }
            : tipoDato === "segundo_anio"
              ? {
                  matriculadosSegundoAnio:
                    lectura.total,
                }
              : {
                  noContinuaron:
                    lectura.total,
                }),
        });

      const nombreDato =
        tipoDato === "primer_nivel"
          ? "Estudiantes de primer nivel"
          : tipoDato === "segundo_anio"
            ? "Matriculados en segundo año"
            : "Estudiantes que no continuaron";

      descripcionResultado =
        guardadoDesercion.tasa !== null
          ? `${nombreDato} detectados: ${lectura.total}. Tasa de deserción calculada: ${guardadoDesercion.tasa}%.`
          : `${nombreDato} detectados: ${lectura.total}. Faltan datos para calcular la tasa de deserción.`;
    }

    /*
     * 5. Registrar o actualizar inmediatamente
     * la URL de Google Drive en MySQL.
     */
    setMensajeSubida("Guardando la evidencia en la base de datos...");

    const guardado = await guardarEvidencia({
      idCatalogo: slot.idCatalogo,
      idEvaluacion:
        evaluacion.id_evaluacion,
      codigoEvidencia:
        slot.codigoEvidencia,
      descripcion:
        slot.descripcionCompleta ??
        slot.label,
      nombreArchivo:
        preparacion.datos.nombre_generado,
      tipo: esCsv ? "text/csv" : "application/pdf",
      urlArchivo: urlDrive,
    });

    if (!guardado.id_evidencia) {
      throw new Error(
        "MySQL no devolvió el identificador de la evidencia.",
      );
    }

    /*
     * 6. Actualizar la interfaz.
     */
    updateSlot(indicator.id, {
      ...slot,
      idEvidencia:
        guardado.id_evidencia,
      error: undefined,
      file: {
        fileName:
          preparacion.datos.nombre_generado,
        originalName: archivo.name,
        url: urlDrive,
        serverUrl: urlDrive,
        size: archivo.size,
      },
    });

    toast.success(
      esCsv ? "CSV guardado correctamente" : "PDF guardado correctamente",
      {
        description:
          descripcionResultado ??
          "El documento se subió a Google Drive y su URL se actualizó en MySQL.",
      },
    );
  } catch (error) {
    updateSlot(indicator.id, {
      ...slot,
      error:
        error instanceof Error
          ? error.message
          : "No se pudo procesar el PDF.",
    });

    toast.error(
      "No se pudo guardar el archivo",
      {
        description:
          error instanceof Error
            ? error.message
            : "Ocurrió un error inesperado.",
      },
    );
  } finally {
    setSubiendoEvidencia(false);
    setMensajeSubida("Preparando evidencia...");
  }
}

  async function guardarEvidenciasSeleccionadas() {
  if (!indicator) {
    toast.error(
      "No se encontró el indicador seleccionado.",
    );
    return;
  }

  // Cuenta por `file`, no por `idEvidencia`: los slots 1-4 de I2 se
  // persisten de inmediato en evidencia_asignatura vía subirEvidenciaAsignatura
  // (ver procesarPdf), que nunca setea idEvidencia -- ese campo solo lo llena
  // el mecanismo viejo (guardarEvidencia / tabla evidencias). Contar por
  // idEvidencia hacía que "Guardar y volver" fallara con "Debe cargar al
  // menos una evidencia" incluso con un Syllabus (u otro slot 1-4) recién
  // subido y visible en pantalla.
  const cargadas =
    indicator.slots.filter(
      (slot) => slot.file,
    ).length;

  if (cargadas === 0) {
    toast.error(
      "Debe cargar al menos una evidencia.",
    );
    return;
  }

  toast.success(
    "Cambios guardados correctamente",
  );

  onBack();
}

  // Step 1: Select indicator
  if (step === "selectIndicator") {
    const INDICATOR_ICONS = ["I1","I2","I3","I4","I5"];
    const COLORS = { I1: "#2563EB", I2: "#7C3AED", I3: "#0891B2", I4: "#DC2626", I5: "#16A34A" };
    return (
      <div className="h-screen flex flex-col overflow-hidden" style={{ background: "#EEF2F7", fontFamily: "'Plus Jakarta Sans',sans-serif" }}>
        <EvidenceHeader title="Carga de Evidencias" subtitle={career.name} backLabel="Panel principal" onBackClick={onBack} />
        <div className="flex-1 overflow-auto">
          <div className="max-w-3xl mx-auto px-6 py-8">
            <Breadcrumb items={["Seleccionar indicador"]} />
            <h2 className="text-lg font-bold mb-1" style={{ fontFamily: "'Libre Baskerville',serif", color: "#0F1E3C" }}>¿Para qué indicador desea cargar evidencias?</h2>
            <p className="text-sm mb-6" style={{ color: "#5A7295" }}>Seleccione el indicador al que pertenecen los documentos que va a subir.</p>
            <div className="grid grid-cols-1 gap-3">
              {indicators.map((ind) => {
                const done = ind.slots.filter((s) => s.file).length;
                const total = ind.slots.length;
                const complete = done === total;
                const color = COLORS[ind.id as keyof typeof COLORS] || "#1B3A6B";
                return (
                  <button key={ind.id} onClick={() => handleSelectIndicator(ind.id)}
                    className="bg-white rounded-xl px-4 py-2.5 text-left flex items-center gap-3 transition-all hover:-translate-y-0.5 hover:shadow-lg group"
                    style={{ border: `1.5px solid ${complete ? "#16A34A" : "rgba(27,58,107,0.12)"}`, boxShadow: "0 1px 6px rgba(0,0,0,0.04)" }}>
                    <div className="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0 font-bold text-xs"
                      style={{ background: `${color}18`, color }}>
                      {ind.code}
                    </div>
                    <div className="flex-1 min-w-0">
                      <p className="font-semibold text-sm" style={{ fontFamily: "'Libre Baskerville',serif", color: "#0F1E3C" }}>{ind.name}</p>
                      <p className="text-xs truncate" style={{ color: "#5A7295" }}>
                        {["I1","I2","I3"].includes(ind.id) ? "Requiere selección de período · módulo · materia" : "Requiere selección de cohorte"}
                      </p>
                    </div>
                    <div className="flex-shrink-0 flex items-center gap-2">
                      <span className="text-xs font-mono px-2 py-0.5 rounded-full" style={{ background: complete ? "#DCFCE7" : "#F3F4F6", color: complete ? "#16A34A" : "#6B7280" }}>
                        {done}/{total}
                      </span>
                      {complete && <CheckCircle2 size={14} style={{ color: "#16A34A" }} />}
                      <span className="text-blue-600 text-xs font-semibold group-hover:text-blue-700">→</span>
                    </div>
                  </button>
                );
              })}
            </div>
          </div>
        </div>
      </div>
    );
  }

  // Step 2a: Config for Syllabus indicators (I1/I2/I3)
  if (step === "configSyllabus") {
    const canContinue = cohort && pao && module && materia;
    const materias = (pao && module) ? (MATERIAS_BY_PAO_MODULE[pao]?.[module] ?? []) : [];
    const selectCls = "w-full px-3 py-2 rounded-xl text-sm border outline-none appearance-none cursor-pointer";
    const selectStyle = { background: "#F4F7FB", borderColor: "rgba(27,58,107,0.2)", color: "#0F1E3C" };

    function BtnGroup({ label, options, value, onChange }: { label: string; options: string[]; value: string; onChange: (v: string) => void }) {
      return (
        <div>
          <label className="block text-xs font-bold uppercase tracking-widest mb-1.5" style={{ color: "#5A7295" }}>{label}</label>
          <div className="flex gap-2">
            {options.map((opt) => (
              <button key={opt} type="button" onClick={() => onChange(opt)}
                className="flex-1 py-2 rounded-xl text-sm font-semibold border transition-all"
                style={{
                  background: value === opt ? "#1B3A6B" : "#F4F7FB",
                  color: value === opt ? "#fff" : "#374151",
                  borderColor: value === opt ? "#1B3A6B" : "rgba(27,58,107,0.2)",
                }}>
                {opt}
              </button>
            ))}
          </div>
        </div>
      );
    }

    return (
      <div className="h-screen flex flex-col overflow-hidden" style={{ background: "#EEF2F7", fontFamily: "'Plus Jakarta Sans',sans-serif" }}>
        <EvidenceHeader title={indicator?.name || ""} subtitle="Configurar período" backLabel="Indicadores" onBackClick={() => preselectedIndicatorId ? onBack() : setStep("selectIndicator")} />
        <div className="flex-1 flex items-center justify-center px-6">
          <div className="w-full max-w-lg">
            <Breadcrumb items={["Seleccionar indicador", indicator?.name || "", "Configurar período"]} />
            <h2 className="text-lg font-bold mb-1" style={{ fontFamily: "'Libre Baskerville',serif", color: "#0F1E3C" }}>Seleccionar período</h2>
            <p className="text-sm mb-5" style={{ color: "#5A7295" }}>Indique el período, módulo y materia correspondientes a los sílabos que va a cargar.</p>

            <div className="bg-white rounded-2xl p-5 space-y-4" style={{ border: "1px solid rgba(27,58,107,0.09)" }}>
              {/* Cohorte dropdown */}
              <div>
                <label className="block text-xs font-bold uppercase tracking-widest mb-1.5" style={{ color: "#5A7295" }}>Cohorte</label>
                <div className="relative">
                  <select value={cohort} onChange={(e) => setCohort(e.target.value)} className={selectCls} style={selectStyle}>
                    <option value="">— Seleccionar cohorte —</option>
                    {COHORT_OPTIONS.map((c) => <option key={c} value={c}>Cohorte {c}</option>)}
                  </select>
                  <ChevronDown size={14} className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" style={{ color: "#5A7295" }} />
                </div>
              </div>

              {/* PAO buttons */}
              <BtnGroup label="Período (PAO)" options={["PAO 1", "PAO 2", "PAO 3"]} value={pao} onChange={setPao} />

              {/* Module buttons */}
              <BtnGroup label="Módulo" options={["A", "B", "C"]} value={module} onChange={(v) => { setModule(v); setMateria(""); }} />

              {/* Materia */}
              <div style={{ opacity: module ? 1 : 0.45, pointerEvents: module ? "auto" : "none" }}>
                <label className="block text-xs font-bold uppercase tracking-widest mb-1.5" style={{ color: "#5A7295" }}>Materia</label>
                <div className="relative">
                  <select value={materia} onChange={(e) => setMateria(e.target.value)} className={selectCls} style={selectStyle}>
                    <option value="">— Seleccionar materia —</option>
                    {materias.map((mat) => <option key={mat} value={mat}>{mat}</option>)}
                  </select>
                  <ChevronDown size={14} className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" style={{ color: "#5A7295" }} />
                </div>
              </div>
            </div>

            <button onClick={() => canContinue && setStep("upload")} disabled={!canContinue}
              className="w-full mt-4 py-3 rounded-xl font-bold text-sm transition-all"
              style={{ background: canContinue ? "#1B3A6B" : "#E5E7EB", color: canContinue ? "#fff" : "#9CA3AF", cursor: canContinue ? "pointer" : "not-allowed" }}>
              Continuar a carga de archivos →
            </button>
          </div>
        </div>
      </div>
    );
  }

  // Step 2b: Config for Titulación/Deserción (I4/I5)
  if (step === "configTitDes") {
    const canContinue = !!cohort;
    return (
      <div className="h-screen flex flex-col overflow-hidden" style={{ background: "#EEF2F7", fontFamily: "'Plus Jakarta Sans',sans-serif" }}>
        <EvidenceHeader title={indicator?.name || ""} subtitle="Seleccionar cohorte" backLabel="Indicadores" onBackClick={() => preselectedIndicatorId ? onBack() : setStep("selectIndicator")} />
        <div className="flex-1 flex items-center justify-center px-6">
          <div className="w-full max-w-sm">
            <Breadcrumb items={["Seleccionar indicador", indicator?.name || "", "Seleccionar cohorte"]} />
            <h2 className="text-lg font-bold mb-1" style={{ fontFamily: "'Libre Baskerville',serif", color: "#0F1E3C" }}>Seleccionar cohorte</h2>
            <p className="text-sm mb-5" style={{ color: "#5A7295" }}>Seleccione la cohorte a la que pertenecen los documentos que va a cargar.</p>

            <div className="bg-white rounded-2xl p-5 mb-4" style={{ border: "1px solid rgba(27,58,107,0.09)" }}>
              <label className="block text-xs font-bold uppercase tracking-widest mb-1.5" style={{ color: "#5A7295" }}>Cohorte</label>
              <div className="relative">
                <select value={cohort} onChange={(e) => setCohort(e.target.value)}
                  className="w-full px-3 py-2 rounded-xl text-sm border outline-none appearance-none cursor-pointer"
                  style={{ background: "#F4F7FB", borderColor: "rgba(27,58,107,0.2)", color: "#0F1E3C" }}>
                  <option value="">— Seleccionar cohorte —</option>
                  {COHORT_OPTIONS.map((c) => <option key={c} value={c}>Cohorte {c}</option>)}
                </select>
                <ChevronDown size={14} className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" style={{ color: "#5A7295" }} />
              </div>
            </div>

            <button onClick={() => canContinue && setStep("upload")} disabled={!canContinue}
              className="w-full py-3 rounded-xl font-bold text-sm transition-all"
              style={{ background: canContinue ? "#1B3A6B" : "#E5E7EB", color: canContinue ? "#fff" : "#9CA3AF", cursor: canContinue ? "pointer" : "not-allowed" }}>
              Continuar a carga de archivos →
            </button>
          </div>
        </div>
      </div>
    );
  }

  // Step 3: Upload files
  if (step === "upload" && indicator) {
    const contextLabel = isSyllabus ? `${pao} · Módulo ${module} · ${materia}` : `Cohorte ${cohort}`;
    const done = indicator.slots.filter((s) => s.file).length;
    const total = indicator.slots.length;

    return (
      <div className="h-screen flex flex-col overflow-hidden" style={{ background: "#EEF2F7", fontFamily: "'Plus Jakarta Sans',sans-serif" }}>
        <div className="flex-shrink-0 border-b" style={{ background: "#fff", borderColor: "rgba(27,58,107,0.1)" }}>
          <div className="max-w-3xl mx-auto px-6 h-14 flex items-center justify-between">
            <div className="flex items-center gap-3">
              <button onClick={() => setStep(isSyllabus ? "configSyllabus" : "configTitDes")}
                className="flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-blue-50 transition-colors" style={{ color: "#1B3A6B" }}>
                <ArrowLeft size={13} /> Configuración
              </button>
              <div className="h-4 w-px" style={{ background: "rgba(27,58,107,0.15)" }} />
              <span className="text-sm font-bold" style={{ fontFamily: "'Libre Baskerville',serif", color: "#0F1E3C" }}>{indicator.name}</span>
            </div>
            <span className="text-xs font-mono" style={{ color: done === total ? "#16A34A" : "#5A7295" }}>{done}/{total}</span>
          </div>
        </div>
        <div className="flex-1 overflow-auto">
          <div className="max-w-3xl mx-auto px-6 py-6">
            <Breadcrumb items={["Seleccionar indicador", indicator.name, "Configuración", "Cargar archivos"]} />

            {/* Context info */}
            <div className="rounded-xl px-4 py-3 mb-5 flex items-center gap-3"
              style={{ background: "#EEF2F7", border: "1px solid rgba(27,58,107,0.12)" }}>
              <div className="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style={{ background: "#1B3A6B" }}>
                <FileText size={14} className="text-white" />
              </div>
              <div>
                <p className="text-xs font-bold" style={{ color: "#0F1E3C" }}>{indicator.code} · {indicator.name}</p>
                <p className="text-xs" style={{ color: "#5A7295" }}>{contextLabel}</p>
              </div>
            </div>

            <h3 className="text-sm font-bold mb-3" style={{ color: "#0F1E3C" }}>Fuentes de información requeridas</h3>
            {loadingCatalogo && (
              <div
                className="rounded-xl px-4 py-3 mb-3 text-xs"
                style={{
                  background: "#EEF5FF",
                  color: "#1B3A6B",
                  border: "1px solid rgba(37,99,235,0.15)",
                }}
              >
                Cargando fuentes de información desde la base de datos...
              </div>
            )}

            {catalogoError && (
              <div
                className="rounded-xl px-4 py-3 mb-3 text-xs"
                style={{
                  background: "#FEE2E2",
                  color: "#DC2626",
                  border: "1px solid rgba(220,38,38,0.15)",
                }}
              >
                {catalogoError}
              </div>
            )}
            <div className={`grid gap-3 ${indicator.slots.length <= 2 ? "grid-cols-2" : indicator.slots.length === 3 ? "grid-cols-3" : "grid-cols-2"}`}>
              {indicator.slots.map((slot) => (
                <PdfZone
                  key={slot.sourceNum}
                  slot={slot}
                  fileName={`${career.code}.${cohort.replace(/\s+/g, "")}.C${career.criterionNum}.${indicator.num}.${slot.sourceNum}.${slot.nombreArchivoBase ?? "Evidencia"}.${slot.acceptedType === "csv" ? "csv" : "pdf"}`}
                  onChange={(updated) =>
                    updateSlot(indicator.id, updated)
                  }
                  onFileSelected={(selectedSlot, archivo) =>
                    procesarPdf(selectedSlot, archivo)
                  }
                  disabled={!!slot.sharedFrom || subiendoEvidencia}
                  loading={subiendoEvidencia}
                />
              ))}
            </div>

            {/* Guardar y volver at bottom */}
            <div className="flex justify-end mt-5">
            <button
              type="button"
              onClick={guardarEvidenciasSeleccionadas}
              disabled={guardando || subiendoEvidencia}
              className="px-6 py-2.5 rounded-xl text-xs font-bold transition-all hover:opacity-90 active:scale-95"
              style={{
              background: guardando || subiendoEvidencia ? "#94A3B8" : "#1B3A6B",
              color: "#fff",
              cursor: guardando || subiendoEvidencia ? "not-allowed" : "pointer",
            }}
            >
              {guardando
                ? "Guardando..."
                : "Guardar y volver →"}
            </button>    
            </div>
          </div>
        </div>

        {subiendoEvidencia && (
          <div
            className="fixed inset-0 z-50 flex items-center justify-center px-4"
            style={{
              background: "rgba(15,30,60,0.48)",
              backdropFilter: "blur(2px)",
            }}
            role="dialog"
            aria-modal="true"
            aria-live="polite"
          >
            <div
              className="w-full max-w-sm rounded-2xl bg-white px-6 py-6 text-center"
              style={{
                border: "1px solid rgba(27,58,107,0.12)",
                boxShadow: "0 20px 60px rgba(15,30,60,0.25)",
              }}
            >
              <div
                className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full"
                style={{ background: "#EEF5FF", color: "#1B3A6B" }}
              >
                <Loader2 size={24} className="animate-spin" />
              </div>

              <h3 className="text-base font-bold" style={{ color: "#0F1E3C" }}>
                Cargando evidencia...
              </h3>

              <p className="mt-2 text-sm" style={{ color: "#5A7295" }}>
                {mensajeSubida}
              </p>

              <p className="mt-3 text-xs" style={{ color: "#94A3B8" }}>
                Por favor, no cierre esta ventana hasta que finalice el proceso.
              </p>
            </div>
          </div>
        )}
      </div>
    );
  }

  return null;
}