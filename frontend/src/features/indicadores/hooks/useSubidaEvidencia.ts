import { useState } from 'react';

import { toast } from 'sonner';

import {
  prepararPdf,
  guardarEvidencia,
  subirPdfGoogleDrive,
  leerMatriculadosPdf,
  leerPdfTitulacion,
  guardarDatoTitulacion,
  leerPdfDesercion,
  guardarDatoDesercion,
} from '../../../shared/services/evidencias';

import { obtenerEvaluacion } from '../../../shared/services/evidencias';

import { subirEvidenciaAsignatura } from '../../../shared/services/seguimientoSyllabus';

import { subirEvidenciaTutorias } from '../../../shared/services/tutoriasAcademicas';

import { I2_SOURCE_NUM_TO_TIPO, I3_SOURCE_NUM_TO_TIPO } from '../constants/evidenciasMapping';

import type { Career, EvidenceSlot, IndicatorDef } from '../../../types/index';

/**
 * Sube un archivo al endpoint real de evidencia_asignatura (I2 o su equivalente
 * de tutorías académicas para I3) y actualiza el slot con el resultado. Extrae
 * el patrón que compartían los dos bloques `if (indicator.id === 'I2'/'I3' ...)`
 * de `procesarPdf`: solo cambia qué función de subida se usa y cómo se arma el
 * mensaje de éxito (I3 incluye el resultado de la validación cualitativa por
 * puntos, I2 no). Ver MEMORIA §57 para el detalle completo.
 */
async function subirYActualizarSlot<T extends { url_archivo: string }>({
  indicatorId,
  slot,
  archivo,
  idAsignatura,
  tipo,
  subirFn,
  mensajeSubiendo,
  construirMensajeExito,
  updateSlot,
  setSubiendoEvidencia,
  setMensajeSubida,
}: {
  indicatorId: string;
  slot: EvidenceSlot;
  archivo: File;
  idAsignatura: number;
  tipo: string;
  subirFn: (params: { idAsignatura: number; tipo: any; archivo: File }) => Promise<T>;
  mensajeSubiendo: string;
  construirMensajeExito: (resultado: T) => { titulo: string; descripcion: string };
  updateSlot: (indId: string, updated: EvidenceSlot) => void;
  setSubiendoEvidencia: (valor: boolean) => void;
  setMensajeSubida: (mensaje: string) => void;
}) {
  setSubiendoEvidencia(true);
  setMensajeSubida(mensajeSubiendo);

  try {
    const resultado = await subirFn({ idAsignatura, tipo, archivo });

    updateSlot(indicatorId, {
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

    const { titulo, descripcion } = construirMensajeExito(resultado);
    toast.success(titulo, { description: descripcion });
  } catch (error) {
    updateSlot(indicatorId, {
      ...slot,
      error: error instanceof Error ? error.message : 'No se pudo procesar el archivo.',
    });
    toast.error('No se pudo guardar el archivo', {
      description: error instanceof Error ? error.message : 'Ocurrió un error inesperado.',
    });
  } finally {
    setSubiendoEvidencia(false);
    setMensajeSubida('Preparando evidencia...');
  }
}

export function useSubidaEvidencia({
  career,
  cohort,
  indicator,
  asignaturaId,
  updateSlot,
  onBack,
  setMensajeSubida,
}: {
  career: Career;
  cohort: string;
  indicator: IndicatorDef | undefined;
  asignaturaId: number | null;
  updateSlot: (indId: string, updated: EvidenceSlot) => void;
  onBack: () => void;
  setMensajeSubida: (mensaje: string) => void;
}) {
  const [guardando, setGuardando] = useState(false);
  const [subiendoEvidencia, setSubiendoEvidencia] = useState(false);

  async function procesarPdf(slot: EvidenceSlot, archivo: File) {
    if (!slot.idCatalogo) {
      toast.error('La evidencia no está relacionada con el catálogo.');
      return;
    }

    if (!slot.codigoEvidencia) {
      toast.error('La evidencia no tiene código asignado.');
      return;
    }

    if (!indicator) {
      toast.error('No se encontró el indicador seleccionado.');
      return;
    }

    const esCsv = slot.acceptedType === 'csv';

    // ── I2: slots 1-5 (incl. CSV de encuesta) → subir a evidencia_asignatura,
    // no a la tabla vieja `evidencias` (ver MEMORIA v18) ──
    if (indicator.id === 'I2' && I2_SOURCE_NUM_TO_TIPO[slot.sourceNum] !== undefined) {
      if (asignaturaId === null) {
        toast.error(
          'No se pudo determinar la asignatura. Verifique la configuración de período y materia.',
        );
        return;
      }

      await subirYActualizarSlot({
        indicatorId: indicator.id,
        slot,
        archivo,
        idAsignatura: asignaturaId,
        tipo: I2_SOURCE_NUM_TO_TIPO[slot.sourceNum],
        subirFn: subirEvidenciaAsignatura,
        mensajeSubiendo: esCsv
          ? 'Validando y subiendo el archivo CSV...'
          : 'Validando y subiendo el archivo PDF...',
        construirMensajeExito: () => ({
          titulo: esCsv ? 'CSV guardado correctamente' : 'PDF guardado correctamente',
          descripcion: 'La evidencia se subió a Google Drive y se registró en la asignatura.',
        }),
        updateSlot,
        setSubiendoEvidencia,
        setMensajeSubida,
      });
      return;
    }

    // ── I3: slots 1-4 → subir a evidencia_asignatura vía el endpoint real de
    // tutorías, que además valida el PDF automáticamente (puntos por EF) al
    // guardarlo. Mismo patrón que I2 arriba, pero con su propio mecanismo de
    // validación cualitativa (ver api/tutorias_academicas/_validacion_pdf.php) ──
    if (indicator.id === 'I3' && I3_SOURCE_NUM_TO_TIPO[slot.sourceNum] !== undefined) {
      if (asignaturaId === null) {
        toast.error(
          'No se pudo determinar la asignatura. Verifique la configuración de período y materia.',
        );
        return;
      }

      await subirYActualizarSlot({
        indicatorId: indicator.id,
        slot,
        archivo,
        idAsignatura: asignaturaId,
        tipo: I3_SOURCE_NUM_TO_TIPO[slot.sourceNum],
        subirFn: subirEvidenciaTutorias,
        mensajeSubiendo: 'Validando y subiendo el archivo PDF...',
        construirMensajeExito: (resultado) => ({
          titulo: `PDF guardado y validado (${resultado.cumplidos}/${resultado.total_puntos} puntos cumplidos)`,
          descripcion:
            'La evidencia se subió a Google Drive y se validó automáticamente para ' +
            resultado.ef +
            '.',
        }),
        updateSlot,
        setSubiendoEvidencia,
        setMensajeSubida,
      });
      return;
    }

    setSubiendoEvidencia(true);
    setMensajeSubida(
      esCsv ? 'Validando y subiendo el archivo CSV...' : 'Validando y subiendo el archivo PDF...',
    );

    try {
      /*
       * 1. Obtener la evaluación y la cohorte real registrada en MySQL.
       */
      const evaluacion = await obtenerEvaluacion(career.code, cohort.replace(/\s+/g, ''));

      const cohorteBD = evaluacion.nombre_cohorte.trim().replace(/\s+/g, '').toUpperCase();

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
        throw new Error('El servidor no devolvió la información del PDF.');
      }

      let matriculadosDetectados: number | null = null;

      if (slot.codigoEvidencia === 'DOC.TIT.02') {
        const lectura = await leerMatriculadosPdf(archivo);

        matriculadosDetectados = lectura.datos?.matriculados ?? null;

        if (
          lectura.datos?.cohorte_detectada &&
          lectura.datos.cohorte_detectada.trim().replace(/\s+/g, '').toUpperCase() !== cohorteBD
        ) {
          throw new Error(
            `El PDF corresponde a la cohorte ${lectura.datos.cohorte_detectada}, pero está seleccionada la cohorte ${cohorteBD}.`,
          );
        }
      }
      /*
       * 2. Subir o reemplazar el archivo en Google Drive.
       */
      setMensajeSubida('Subiendo evidencia a Google Drive...');

      const drive = await subirPdfGoogleDrive({
        archivo,
        codigoCarrera: career.code,
        nombreCarrera: career.name,
        cohorte: cohorteBD,
        indicador: indicator.num,
        nombreArchivo: preparacion.datos.nombre_generado,
        tipoEsperado: esCsv ? 'csv' : 'pdf',
      });

      const urlDrive = drive.datos?.url_archivo?.trim() ?? '';

      if (!urlDrive.startsWith('https://drive.google.com/')) {
        throw new Error('Google Drive no devolvió una URL válida.');
      }

      const cohorteNormalizada = cohorteBD;

      let descripcionResultado: string | null = null;

      /*
       * 4A. Lectura y cálculo de Tasa de Titulación (I5).
       * Según el catálogo: DOC.TIT.01 = graduados, DOC.TIT.02 = matriculados.
       */
      if (
        indicator.id === 'I5' &&
        (slot.codigoEvidencia === 'DOC.TIT.01' || slot.codigoEvidencia === 'DOC.TIT.02')
      ) {
        const tipoDato = slot.codigoEvidencia === 'DOC.TIT.01' ? 'graduados' : 'matriculados';

        const lectura = await leerPdfTitulacion(archivo, tipoDato);

        if (lectura.cohorte_detectada && lectura.cohorte_detectada !== cohorteNormalizada) {
          throw new Error(
            `El PDF corresponde a la cohorte ${lectura.cohorte_detectada}, pero está seleccionada la cohorte ${cohorteNormalizada}.`,
          );
        }

        const guardadoTitulacion = await guardarDatoTitulacion({
          idEvaluacion: evaluacion.id_evaluacion,
          cohorte: cohorteNormalizada,
          ...(tipoDato === 'matriculados'
            ? {
                matriculados: lectura.total,
              }
            : {
                graduados: lectura.total,
              }),
        });

        /*
         * El PDF de matriculados de primer nivel se comparte con I4.
         * Por eso también se registra como dato inicial para la
         * Tasa de Deserción.
         */
        if (tipoDato === 'matriculados') {
          await guardarDatoDesercion({
            idEvaluacion: evaluacion.id_evaluacion,
            cohorte: cohorteNormalizada,
            iniciaronPrimerNivel: lectura.total,
          });
        }

        descripcionResultado =
          guardadoTitulacion.tasa !== null
            ? `${tipoDato === 'matriculados' ? 'Matriculados' : 'Graduados'} detectados: ${
                lectura.total
              }. Tasa de titulación calculada: ${guardadoTitulacion.tasa}%.`
            : `${tipoDato === 'matriculados' ? 'Matriculados' : 'Graduados'} detectados: ${
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
        indicator.id === 'I4' &&
        (slot.sourceNum === 1 || slot.sourceNum === 2 || slot.sourceNum === 3)
      ) {
        const tipoDato =
          slot.sourceNum === 1
            ? 'primer_nivel'
            : slot.sourceNum === 2
              ? 'segundo_anio'
              : 'no_continuaron';

        const lectura = await leerPdfDesercion(archivo, tipoDato);

        if (lectura.cohorte_detectada && lectura.cohorte_detectada !== cohorteNormalizada) {
          throw new Error(
            `El PDF corresponde a la cohorte ${lectura.cohorte_detectada}, pero está seleccionada la cohorte ${cohorteNormalizada}.`,
          );
        }

        const guardadoDesercion = await guardarDatoDesercion({
          idEvaluacion: evaluacion.id_evaluacion,
          cohorte: cohorteNormalizada,
          ...(tipoDato === 'primer_nivel'
            ? {
                iniciaronPrimerNivel: lectura.total,
              }
            : tipoDato === 'segundo_anio'
              ? {
                  matriculadosSegundoAnio: lectura.total,
                }
              : {
                  noContinuaron: lectura.total,
                }),
        });

        const nombreDato =
          tipoDato === 'primer_nivel'
            ? 'Estudiantes de primer nivel'
            : tipoDato === 'segundo_anio'
              ? 'Matriculados en segundo año'
              : 'Estudiantes que no continuaron';

        descripcionResultado =
          guardadoDesercion.tasa !== null
            ? `${nombreDato} detectados: ${lectura.total}. Tasa de deserción calculada: ${guardadoDesercion.tasa}%.`
            : `${nombreDato} detectados: ${lectura.total}. Faltan datos para calcular la tasa de deserción.`;
      }

      /*
       * 5. Registrar o actualizar inmediatamente
       * la URL de Google Drive en MySQL.
       */
      setMensajeSubida('Guardando la evidencia en la base de datos...');

      const guardado = await guardarEvidencia({
        idCatalogo: slot.idCatalogo,
        idEvaluacion: evaluacion.id_evaluacion,
        codigoEvidencia: slot.codigoEvidencia,
        descripcion: slot.descripcionCompleta ?? slot.label,
        nombreArchivo: preparacion.datos.nombre_generado,
        tipo: esCsv ? 'text/csv' : 'application/pdf',
        urlArchivo: urlDrive,
      });

      if (!guardado.id_evidencia) {
        throw new Error('MySQL no devolvió el identificador de la evidencia.');
      }

      /*
       * 6. Actualizar la interfaz.
       */
      updateSlot(indicator.id, {
        ...slot,
        idEvidencia: guardado.id_evidencia,
        error: undefined,
        file: {
          fileName: preparacion.datos.nombre_generado,
          originalName: archivo.name,
          url: urlDrive,
          serverUrl: urlDrive,
          size: archivo.size,
        },
      });

      toast.success(esCsv ? 'CSV guardado correctamente' : 'PDF guardado correctamente', {
        description:
          descripcionResultado ??
          'El documento se subió a Google Drive y su URL se actualizó en MySQL.',
      });
    } catch (error) {
      updateSlot(indicator.id, {
        ...slot,
        error: error instanceof Error ? error.message : 'No se pudo procesar el PDF.',
      });

      toast.error('No se pudo guardar el archivo', {
        description: error instanceof Error ? error.message : 'Ocurrió un error inesperado.',
      });
    } finally {
      setSubiendoEvidencia(false);
      setMensajeSubida('Preparando evidencia...');
    }
  }

  async function guardarEvidenciasSeleccionadas() {
    if (!indicator) {
      toast.error('No se encontró el indicador seleccionado.');
      return;
    }

    // Cuenta por `file`, no por `idEvidencia`: los slots 1-4 de I2 se
    // persisten de inmediato en evidencia_asignatura vía subirEvidenciaAsignatura
    // (ver procesarPdf), que nunca setea idEvidencia -- ese campo solo lo llena
    // el mecanismo viejo (guardarEvidencia / tabla evidencias). Contar por
    // idEvidencia hacía que "Guardar y volver" fallara con "Debe cargar al
    // menos una evidencia" incluso con un Syllabus (u otro slot 1-4) recién
    // subido y visible en pantalla.
    const cargadas = indicator.slots.filter((slot) => slot.file).length;

    if (cargadas === 0) {
      toast.error('Debe cargar al menos una evidencia.');
      return;
    }

    toast.success('Cambios guardados correctamente');

    onBack();
  }

  return {
    guardando,
    setGuardando,
    subiendoEvidencia,
    procesarPdf,
    guardarEvidenciasSeleccionadas,
  };
}
