import { useEffect, useState } from 'react';

import {
  obtenerCatalogoEvidencias,
  obtenerEvaluacion,
  obtenerEvidenciasGuardadas,
  obtenerEvidenciasCompartidas,
} from '../../../shared/services/evidencias';

import { resolverAsignaturaPorNombre } from '../../../shared/utils/asignaturas';

import { obtenerAsignaturas, obtenerPeriodos } from '../../../shared/services/seguimientoSyllabus';

import { obtenerEvidenciaAsignatura } from '../../../shared/services/seguimientoSyllabus';

import { obtenerEvidenciaTutorias } from '../../../shared/services/tutoriasAcademicas';

import {
  I2_SOURCE_NUM_TO_TIPO,
  I3_SOURCE_NUM_TO_TIPO,
  resolverArchivoPorTipo,
  type ItemEvidenciaPorTipo,
} from '../constants/evidenciasMapping';

import type { Career, EvidStep, EvidenceSlot, IndicatorDef } from '../../../types/index';

const ID_INDICADOR_NUM: Record<string, number> = {
  I1: 1,
  I2: 2,
  I3: 3,
  I4: 4,
  I5: 5,
};

/**
 * I2 (slots 1-5, vía evidencia_asignatura) e I3 (los 4 slots, vía su propio
 * endpoint de tutorías) construyen el mismo objeto de slot a partir del
 * catálogo + un item resuelto por tipo: la metadata (label/idCatalogo/
 * codigoEvidencia/nombreArchivoBase/descripcionCompleta) sale siempre del
 * catálogo, `idEvidencia` queda undefined (ese campo solo lo llena el
 * mecanismo viejo evaluation-wide de la tabla `evidencias`, no el de
 * evidencia_asignatura) y el archivo se resuelve con resolverArchivoPorTipo(),
 * ya compartido entre I2/I3 desde v79 (Fase 4).
 */
function construirSlotPorAsignatura(
  slot: EvidenceSlot,
  evidencia: {
    titulo_corto: string;
    id_catalogo: number;
    codigo_evidencia: string;
    nombre_archivo_base: string;
    descripcion: string;
  },
  items: ItemEvidenciaPorTipo[] | null | undefined,
  tipo: string,
): EvidenceSlot {
  return {
    ...slot,
    label: evidencia.titulo_corto || slot.label,
    idCatalogo: evidencia.id_catalogo,
    codigoEvidencia: evidencia.codigo_evidencia,
    nombreArchivoBase: evidencia.nombre_archivo_base,
    descripcionCompleta: evidencia.descripcion,
    idEvidencia: undefined,
    file: resolverArchivoPorTipo(items, tipo),
  };
}

export function useCatalogoEvidencias({
  career,
  indicators,
  onChange,
  preselectedCohort,
  preselectedIndicatorId,
  setStep,
}: {
  career: Career;
  indicators: IndicatorDef[];
  onChange: (inds: IndicatorDef[]) => void;
  preselectedCohort: string;
  preselectedIndicatorId?: string;
  setStep: (step: EvidStep) => void;
}) {
  const [indicatorId, setIndicatorId] = useState<string>(preselectedIndicatorId ?? '');
  const [pao, setPao] = useState<string>('');
  const [module, setModule] = useState<string>('');
  const [materia, setMateria] = useState<string>('');
  const [cohort, setCohort] = useState<string>(preselectedCohort);
  const [loadingCatalogo, setLoadingCatalogo] = useState(false);
  const [catalogoError, setCatalogoError] = useState('');
  const [mensajeSubida, setMensajeSubida] = useState('Preparando evidencia...');
  const [asignaturaId, setAsignaturaId] = useState<number | null>(null);

  // ── Resolución de id_asignatura real para I2/I3 ──────────────────────
  // 'encuesta_csv' (slot 5) se agrega a partir de la migración
  // sql/migracion_i2_encuesta_csv_por_asignatura.sql: el CSV de encuesta
  // ahora se sube por-asignatura igual que los otros 4 slots, en vez de ser
  // el mecanismo evaluation-wide de la tabla vieja `evidencias` (ver
  // MEMORIA v18 -- reemplaza lo decidido en v17 sección 41).
  //
  // I3 (Tutorías Académicas): mismo mecanismo por-asignatura que I2, pero
  // con validación automática del PDF al subir (ver
  // api/tutorias_academicas/_validacion_pdf.php) y evaluación cualitativa
  // por puntos dentro de cada EF, no cuantitativa. Los 4 slots del wizard
  // (App.tsx) mapean 1:1 a los 4 tipos de evidencia_asignatura de I3.
  //
  // Ambos mapeos viven en constants/evidenciasMapping.ts (compartidos con
  // IndicatorView.tsx desde la Fase 4 paso 3) en vez de duplicarse acá.

  useEffect(() => {
    // I3 usa el mismo mecanismo por-asignatura que I2 (ver I2_SOURCE_NUM_TO_TIPO/
    // I3_SOURCE_NUM_TO_TIPO arriba), así que necesita resolver asignaturaId igual que I2.
    if (!['I2', 'I3'].includes(indicatorId) || !pao || !cohort) {
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
        const cohorteNorm = cohort.replace(/\s+/g, '');
        setMensajeSubida('Procesando la información del documento...');

        const evaluacion = await obtenerEvaluacion(career.code, cohorteNorm);
        const periodos = await obtenerPeriodos(evaluacion.id_cohorte);
        const orden = Number(pao.replace(/\D/g, ''));
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
        // existiera en la BD (ver captura del usuario, 19 jul 2026). Lógica
        // extraída a resolverAsignaturaPorNombre() (Fase 5, testing) para
        // poder cubrirla con Vitest -- ver utils/asignaturas.test.ts.
        const asignaturaIdResuelto = resolverAsignaturaPorNombre(asignaturas, materia);
        if (!cancelado) setAsignaturaId(asignaturaIdResuelto);
      } catch {
        if (!cancelado) setAsignaturaId(null);
      }
    }

    resolverAsignatura();
    return () => {
      cancelado = true;
    };
  }, [indicatorId, pao, module, materia, cohort, career.code]);

  const indicator = indicators.find((i) => i.id === indicatorId);
  const isSyllabus = ['I1', 'I2', 'I3'].includes(indicatorId);

  useEffect(() => {
    if (indicatorId !== 'I5' || !cohort) {
      return;
    }

    let activo = true;

    async function cargarEvidenciasTitulacion() {
      setLoadingCatalogo(true);
      setCatalogoError('');

      try {
        /*
         * I5 conserva sus cuatro posiciones visuales, pero la malla curricular
         * usa como fuente canónica DOC.SYL.01 del catálogo de I1.
         *
         * De esta forma, aunque se cargue desde I5, se registra con el mismo
         * id_catalogo y código que usan I1 e I2. Así existe una sola evidencia
         * y Compartir_Catalogo la distribuye a los demás indicadores.
         */
        const [catalogoI5, catalogoI1, evaluacion] = await Promise.all([
          obtenerCatalogoEvidencias(5),
          obtenerCatalogoEvidencias(1),
          obtenerEvaluacion(career.code, cohort.replace(/\s+/g, '')),
        ]);

        const [guardadas, compartidas] = await Promise.all([
          obtenerEvidenciasGuardadas(evaluacion.id_evaluacion, 5),
          obtenerEvidenciasCompartidas(evaluacion.id_evaluacion, 5),
        ]);

        if (!activo) {
          return;
        }

        const mallaCatalogo = catalogoI1.find((item) => item.codigo_evidencia === 'DOC.SYL.01');

        const mallaCompartida = compartidas.find((item) => item.codigo_evidencia === 'DOC.SYL.01');

        onChange(
          indicators.map((ind) => {
            if (ind.id !== 'I5') {
              return ind;
            }

            const nuevosSlots: EvidenceSlot[] = catalogoI5.map((evidencia) => {
              const slotAnterior =
                ind.slots.find((slot) => slot.codigoEvidencia === evidencia.codigo_evidencia) ??
                ind.slots.find((slot) => slot.sourceNum === evidencia.orden);

              /*
               * Posición 4 de I5: Malla curricular.
               * La fila DOC.TIT.04 solo define su posición visual dentro de
               * I5. Para guardar y consultar el archivo se utiliza la fuente
               * canónica DOC.SYL.01 del indicador I1.
               */
              if (evidencia.codigo_evidencia === 'DOC.TIT.04') {
                const metadataMalla = mallaCatalogo ?? evidencia;

                return {
                  sourceNum: evidencia.orden,
                  label: metadataMalla.titulo_corto || 'Malla curricular',
                  idCatalogo: metadataMalla.id_catalogo,
                  codigoEvidencia: metadataMalla.codigo_evidencia,
                  nombreArchivoBase: metadataMalla.nombre_archivo_base,
                  descripcionCompleta: metadataMalla.descripcion,
                  sharedKey: 'malla_curricular',
                  sharedFrom: mallaCompartida?.indicador_origen,
                  idEvidencia: mallaCompartida?.id_evidencia,
                  file: mallaCompartida
                    ? {
                        fileName: mallaCompartida.nombre_archivo,
                        originalName: mallaCompartida.nombre_archivo,
                        url: mallaCompartida.url_archivo,
                        serverUrl: mallaCompartida.url_archivo,
                        size: 0,
                      }
                    : slotAnterior?.file,
                  error: slotAnterior?.error,
                };
              }

              const guardada = guardadas.find(
                (item) =>
                  item.id_catalogo === evidencia.id_catalogo ||
                  item.codigo_evidencia === evidencia.codigo_evidencia,
              );

              let sharedKey: string | undefined;

              if (evidencia.codigo_evidencia === 'DOC.TIT.02') {
                sharedKey = 'matriculados';
              }

              return {
                sourceNum: evidencia.orden,
                label: evidencia.titulo_corto,
                idCatalogo: evidencia.id_catalogo,
                codigoEvidencia: evidencia.codigo_evidencia,
                nombreArchivoBase: evidencia.nombre_archivo_base,
                descripcionCompleta: evidencia.descripcion,
                sharedKey,

                idEvidencia: guardada?.id_evidencia ?? slotAnterior?.idEvidencia,

                file: guardada
                  ? {
                      fileName: guardada.nombre_archivo,
                      originalName: guardada.nombre_archivo,
                      url: guardada.url_archivo,
                      serverUrl: guardada.url_archivo,
                      size: 0,
                    }
                  : slotAnterior?.file,

                error: slotAnterior?.error,
                sharedFrom: slotAnterior?.sharedFrom,
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
          error instanceof Error ? error.message : 'No se pudieron cargar las evidencias.',
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
  useEffect(() => {
    if (!['I1', 'I2', 'I3'].includes(indicatorId) || !cohort) {
      return;
    }

    const idIndicadorReal = ID_INDICADOR_NUM[indicatorId];

    let activo = true;

    async function cargarDatosIndicador() {
      setLoadingCatalogo(true);
      setCatalogoError('');

      try {
        if (indicatorId === 'I2') {
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
            obtenerEvaluacion(career.code, cohort.replace(/\s+/g, '')),
          ]);

          const [guardadas, compartidas] = await Promise.all([
            obtenerEvidenciasGuardadas(evaluacion.id_evaluacion, 2),
            obtenerEvidenciasCompartidas(evaluacion.id_evaluacion, 2),
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
            (item) => item.codigo_evidencia === 'DOC.SYL.02',
          );

          // Malla Curricular (DOC.SYL.01) es propia del catálogo de I1, no del
          // de I2 -- se busca en catalogoI1, igual que el Syllabus arriba.
          const mallaCatalogo = catalogoI1.find((item) => item.codigo_evidencia === 'DOC.SYL.01');

          // Normativa Institucional (DOC.SEG.01) SÍ es propia del catálogo de
          // I2 (orden=1), pero nunca tuvo slot visible -- ver comentario más
          // abajo, en el slot correspondiente.
          const normativaCatalogo = catalogoI2.find(
            (item) => item.codigo_evidencia === 'DOC.SEG.01',
          );

          onChange(
            indicators.map((ind) => {
              if (ind.id !== 'I2') {
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
                      (e: any) => e.codigo_evidencia === 'DOC.SYL.01',
                    );

                    return {
                      ...conMetadata,
                      sharedKey: 'malla_curricular',
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
                      (e: any) => e.codigo_evidencia === 'DOC.SEG.01',
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
                      (e: any) => e.tipo === 'syllabus',
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
                  // el slot 5 entra al mismo bloque estricto que 2-4
                  // (I2_SOURCE_NUM_TO_TIPO ya lo mapea arriba). El bloque
                  // `slotTipo === undefined` de abajo queda como fallback
                  // genérico para I2, pero ya ningún slot de I2 cae ahí.
                  const slotTipo = I2_SOURCE_NUM_TO_TIPO[slot.sourceNum];
                  const evidencia = catalogoI2.find((item) => item.orden === slot.sourceNum);

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

                      idEvidencia: guardada?.id_evidencia ?? slot.idEvidencia,

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

                  return construirSlotPorAsignatura(slot, evidencia, evidenciaAsignatura, slotTipo);
                }),
              };
            }),
          );
        } else if (indicatorId === 'I3') {
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

          let evidenciaTutorias: Awaited<ReturnType<typeof obtenerEvidenciaTutorias>> | null =
            null;
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
              if (ind.id !== 'I3') {
                return ind;
              }

              return {
                ...ind,
                slots: ind.slots.map((slot) => {
                  const evidencia = catalogoI3.find((item) => item.orden === slot.sourceNum);

                  if (!evidencia) {
                    return slot;
                  }

                  const tipo = I3_SOURCE_NUM_TO_TIPO[slot.sourceNum];

                  return construirSlotPorAsignatura(slot, evidencia, evidenciaTutorias, tipo);
                }),
              };
            }),
          );
        } else {
          // --- I1 (y cualquier otro indicador que no sea I2/I3 en este branch) ---
          const [catalogo, evaluacion] = await Promise.all([
            obtenerCatalogoEvidencias(idIndicadorReal),
            obtenerEvaluacion(career.code, cohort.replace(/\s+/g, '')),
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
                  const evidencia = catalogo.find((item) => item.orden === slot.sourceNum);

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

                    idEvidencia: guardada?.id_evidencia ?? slot.idEvidencia,

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
          error instanceof Error ? error.message : 'No se pudo cargar el catálogo de evidencias.',
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
    if (indicatorId !== 'I4' || !cohort) {
      return;
    }

    let activo = true;

    async function cargarEvidenciasDesercion() {
      setLoadingCatalogo(true);
      setCatalogoError('');

      try {
        const [catalogo, evaluacion] = await Promise.all([
          obtenerCatalogoEvidencias(4),
          obtenerEvaluacion(career.code, cohort.replace(/\s+/g, '')),
        ]);

        const [guardadas, compartidas] = await Promise.all([
          obtenerEvidenciasGuardadas(evaluacion.id_evaluacion, 4),
          obtenerEvidenciasCompartidas(evaluacion.id_evaluacion, 4),
        ]);

        if (!activo) {
          return;
        }

        onChange(
          indicators.map((ind) => {
            if (ind.id !== 'I4') {
              return ind;
            }

            const evidenciaCompartidaPrimerNivel = compartidas.find(
              (evidencia) => evidencia.codigo_evidencia === 'DOC.TIT.02',
            );

            const nuevosSlots: EvidenceSlot[] = ind.slots.map((slot) => {
              /*
               * Slot 1: matriculados de primer nivel.
               * Se comparte desde I5 y no se vuelve a subir.
               */
              if (slot.sourceNum === 1 && evidenciaCompartidaPrimerNivel) {
                return {
                  ...slot,
                  label: 'Estudiantes matriculados en primer nivel',
                  idEvidencia: evidenciaCompartidaPrimerNivel.id_evidencia,
                  codigoEvidencia: evidenciaCompartidaPrimerNivel.codigo_evidencia,
                  nombreArchivoBase: evidenciaCompartidaPrimerNivel.nombre_archivo_base,
                  descripcionCompleta: evidenciaCompartidaPrimerNivel.descripcion,
                  sharedKey: 'matriculados',
                  sharedFrom: evidenciaCompartidaPrimerNivel.indicador_origen,
                  file: {
                    fileName: evidenciaCompartidaPrimerNivel.nombre_archivo,
                    originalName: evidenciaCompartidaPrimerNivel.nombre_archivo,
                    url: evidenciaCompartidaPrimerNivel.url_archivo,
                    serverUrl: evidenciaCompartidaPrimerNivel.url_archivo,
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
              const evidenciaCatalogo = catalogo.find(
                (evidencia) => Number(evidencia.orden) === Number(slot.sourceNum),
              );

              const evidenciaGuardada = evidenciaCatalogo
                ? guardadas.find(
                    (evidencia) =>
                      evidencia.id_catalogo === evidenciaCatalogo.id_catalogo ||
                      evidencia.codigo_evidencia === evidenciaCatalogo.codigo_evidencia,
                  )
                : undefined;

              return {
                ...slot,
                label: evidenciaCatalogo?.titulo_corto ?? slot.label,
                idCatalogo: evidenciaCatalogo?.id_catalogo ?? slot.idCatalogo,
                codigoEvidencia: evidenciaCatalogo?.codigo_evidencia ?? slot.codigoEvidencia,
                nombreArchivoBase: evidenciaCatalogo?.nombre_archivo_base ?? slot.nombreArchivoBase,
                descripcionCompleta: evidenciaCatalogo?.descripcion ?? slot.descripcionCompleta,
                idEvidencia: evidenciaGuardada?.id_evidencia ?? slot.idEvidencia,
                file: evidenciaGuardada
                  ? {
                      fileName: evidenciaGuardada.nombre_archivo,
                      originalName: evidenciaGuardada.nombre_archivo,
                      url: evidenciaGuardada.url_archivo,
                      serverUrl: evidenciaGuardada.url_archivo,
                      size: 0,
                    }
                  : slot.file,
                sharedKey: slot.sourceNum === 1 ? 'matriculados' : undefined,
                sharedFrom: slot.sourceNum === 1 ? slot.sharedFrom : undefined,
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
            : 'No se pudieron cargar las evidencias de deserción.',
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
    setPao('');
    setModule('');
    setMateria('');
    if (['I1', 'I2', 'I3'].includes(id)) {
      setCohort(preselectedCohort);
      setStep('configSyllabus');
    } else {
      setCohort(preselectedCohort);
      setStep('configTitDes');
    }
  }

  function updateSlot(indId: string, updated: EvidenceSlot) {
    onChange(
      indicators.map((ind) => {
        if (ind.id !== indId) {
          if (!updated.sharedKey) return ind;
          const matchSlot = ind.slots.find((s) => s.sharedKey === updated.sharedKey);
          if (!matchSlot) return ind;
          const syncedSlot: EvidenceSlot = {
            ...matchSlot,
            file: updated.file,
            error: updated.error,
            sharedFrom: updated.file ? indId : undefined,
          };
          return {
            ...ind,
            slots: ind.slots.map((s) => (s.sharedKey === updated.sharedKey ? syncedSlot : s)),
          };
        }
        const newSlots = ind.slots.map((s) => (s.sourceNum === updated.sourceNum ? updated : s));
        return { ...ind, slots: newSlots };
      }),
    );
  }

  return {
    indicatorId,
    pao,
    setPao,
    module,
    setModule,
    materia,
    setMateria,
    cohort,
    setCohort,
    asignaturaId,
    loadingCatalogo,
    catalogoError,
    mensajeSubida,
    setMensajeSubida,
    indicator,
    isSyllabus,
    handleSelectIndicator,
    updateSlot,
  };
}
