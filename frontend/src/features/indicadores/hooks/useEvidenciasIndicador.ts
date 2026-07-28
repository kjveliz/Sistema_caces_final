import { useEffect, useState } from 'react';
import { toast } from 'sonner';

import {
  obtenerEvaluacion,
  obtenerEvidenciasCompartidas,
  obtenerEvidenciasGuardadas,
  urlVisorEvidenciaLegacy,
} from '../../../shared/services/evidencias';
import { obtenerEvidenciaAsignatura } from '../../../shared/services/seguimientoSyllabus';
import { obtenerEvidenciaTutorias } from '../../../shared/services/tutoriasAcademicas';
import { urlVisorEvidenciaAsignatura } from '../../../shared/services/evidenciaAsignaturaVisor';
import {
  I2_SOURCE_NUM_TO_TIPO,
  I3_SOURCE_NUM_TO_TIPO,
  resolverArchivoPorTipo,
} from '../constants/evidenciasMapping';
import type { Career, IndicatorDef } from '../../../types/index';

export function useEvidenciasIndicador({
  ind,
  career,
  cohort,
  idAsignatura,
  nombreAsignatura,
}: {
  ind: IndicatorDef;
  career: Career | null;
  cohort: string;
  idAsignatura: number | null;
  nombreAsignatura?: string | null;
}) {
  // I2 y I3 trabajan por asignatura: se muestra un chip con el nombre de la
  // materia que se está viendo, para no perder el contexto dentro de la
  // pestaña Evidencias. I1/I4/I5 trabajan a nivel carrera+cohorte, sin
  // asignatura, así que este chip no aplica para ellos.
  const mostrarAsignatura = (ind.id === 'I2' || ind.id === 'I3') && !!nombreAsignatura;
  const [slots, setSlots] = useState<IndicatorDef['slots']>(() =>
    ind.slots.map((slot) => ({ ...slot })),
  );

  const [selected, setSelected] = useState<number | null>(
    ind.slots.find((slot) => slot.file)?.sourceNum ?? null,
  );

  const [cargandoEvidencias, setCargandoEvidencias] = useState(true);

  useEffect(() => {
    setSlots(ind.slots.map((slot) => ({ ...slot })));
    setSelected(ind.slots.find((slot) => slot.file)?.sourceNum ?? null);
  }, [ind]);

  useEffect(() => {
    let cancelado = false;

    async function cargarEvidencias() {
      if (!career) {
        if (!cancelado) {
          setCargandoEvidencias(false);
        }
        return;
      }

      try {
        setCargandoEvidencias(true);

        const cohorteNormalizada = cohort.replace(/\s+/g, '').toUpperCase();

        const evaluacion = await obtenerEvaluacion(career.code, cohorteNormalizada);

        const idIndicador = Number(ind.id.replace(/\D/g, ''));

        // Para I2, ademas de las tablas viejas (evidencias), traemos
        // tambien la evidencia por asignatura (evidencia_asignatura) para
        // los 5 slots (1-5, incl. CSV de encuesta) que se suben a la tabla
        // real -- ver MEMORIA v18. `guardadas`/`compartidas` ya no se usan
        // para el slot 5. Para I3, mismo mecanismo pero via el endpoint
        // real de tutorias academicas (incluye la validacion por puntos).
        const [guardadas, compartidas, evidenciaAsignatura, evidenciaTutorias] = await Promise.all([
          obtenerEvidenciasGuardadas(evaluacion.id_evaluacion, idIndicador),
          obtenerEvidenciasCompartidas(evaluacion.id_evaluacion, idIndicador),
          // Solo para I2 con asignatura resuelta
          ind.id === 'I2' && idAsignatura !== null
            ? obtenerEvidenciaAsignatura(idAsignatura)
            : Promise.resolve(null),
          // Solo para I3 con asignatura resuelta
          ind.id === 'I3' && idAsignatura !== null
            ? obtenerEvidenciaTutorias(idAsignatura)
            : Promise.resolve(null),
        ]);

        if (cancelado) {
          return;
        }

        const nuevasSlots = ind.slots.map((slot) => {
          let propia;
          let compartida;

          // ── I2 slots 1-5 (incl. CSV de encuesta, tipo 'encuesta_csv'): usar
          // evidencia_asignatura (tabla real) ──
          // Nota: la condicion depende del MAPEO (slot de I2), no de si
          // evidenciaAsignatura vino con datos. Si idAsignatura todavia no
          // resolvio (evidenciaAsignatura === null), estos slots deben mostrarse
          // como "sin archivo" -- nunca caer al fallback viejo (tabla `evidencias`,
          // evaluacion-wide), que es exactamente el bug que se esta arreglando.
          if (ind.id === 'I2' && I2_SOURCE_NUM_TO_TIPO[slot.sourceNum] !== undefined) {
            const tipo = I2_SOURCE_NUM_TO_TIPO[slot.sourceNum];

            return {
              ...slot,
              file: resolverArchivoPorTipo(evidenciaAsignatura, tipo),
            };
          }

          // ── I3 slots 1-4: mismo patrón estricto que I2 arriba, pero contra el
          // endpoint real de tutorías académicas (evidencia_asignatura + validación
          // por puntos). Nunca cae al mecanismo viejo evaluation-wide. ──
          if (ind.id === 'I3' && I3_SOURCE_NUM_TO_TIPO[slot.sourceNum] !== undefined) {
            const tipo = I3_SOURCE_NUM_TO_TIPO[slot.sourceNum];

            return {
              ...slot,
              file: resolverArchivoPorTipo(evidenciaTutorias, tipo),
            };
          }

          /*
           * En Tasa de Titulación el orden visual es:
           * 1 = Matriculados  -> DOC.TIT.02
           * 2 = Graduados     -> DOC.TIT.01
           */
          /*
           * Malla Curricular (DOC.SYL.01, sourceNum 7) y Normativa Institucional
           * (DOC.SEG.01, sourceNum 6): ambos evaluation-wide (a nivel carrera+cohorte,
           * NO por asignatura), a diferencia de los slots 1-5 de arriba. Malla es
           * propia del catálogo de I1 y llega a I2 solo vía `compartidas` (regla
           * compartir_catalogo ya existente en la base real); Normativa es propia del
           * catálogo de I2 y llega vía `guardadas`. Se matchea por codigo_evidencia,
           * no por orden, porque ninguno de los dos coincide con su sourceNum visual
           * (DOC.SEG.01 tiene orden=1 en su catálogo, pero ese sourceNum ya lo usa
           * Syllabus).
           *
           * I5 - Tasa de Titulación:
           * 1 = Graduados                    -> DOC.TIT.01
           * 2 = Matriculados de primer nivel -> DOC.TIT.02
           *
           * I4 - Tasa de Deserción:
           * 1 = Matriculados de primer nivel comparte DOC.TIT.02 con I5
           * 2 = Matriculados de segundo nivel conserva su evidencia propia
           * 3 = Estudiantes desertados conserva su evidencia propia
           */
          /*
           * Malla curricular compartida:
           * cualquier indicador que use sharedKey="malla_curricular"
           * mostrará DOC.SYL.01, sin importar desde cuál indicador se cargó.
           *
           * Esto cubre:
           * - I1: Malla curricular
           * - I2: Malla curricular
           * - I5: Malla curricular
           *
           * Las demás evidencias compartidas, como DOC.TIT.02 entre I4 e I5,
           * conservan su lógica actual sin cambios.
           */
          if (slot.sharedKey === 'malla_curricular') {
            propia = guardadas.find((evidencia) => evidencia.codigo_evidencia === 'DOC.SYL.01');

            compartida = compartidas.find(
              (evidencia) => evidencia.codigo_evidencia === 'DOC.SYL.01',
            );
          } else if (ind.id === 'I2' && slot.sourceNum === 6) {
            propia = guardadas.find((evidencia) => evidencia.codigo_evidencia === 'DOC.SEG.01');

            compartida = compartidas.find(
              (evidencia) => evidencia.codigo_evidencia === 'DOC.SEG.01',
            );
          } else if (ind.id === 'I5' && slot.sourceNum === 1) {
            // Posición 1: estudiantes graduados
            propia = guardadas.find((evidencia) => evidencia.codigo_evidencia === 'DOC.TIT.01');

            compartida = compartidas.find(
              (evidencia) => evidencia.codigo_evidencia === 'DOC.TIT.01',
            );
          } else if (ind.id === 'I5' && slot.sourceNum === 2) {
            // Posición 2: estudiantes matriculados
            propia = guardadas.find((evidencia) => evidencia.codigo_evidencia === 'DOC.TIT.02');

            compartida = compartidas.find(
              (evidencia) => evidencia.codigo_evidencia === 'DOC.TIT.02',
            );
          } else if (ind.id === 'I4' && slot.sourceNum === 1) {
            propia = guardadas.find((evidencia) => evidencia.codigo_evidencia === 'DOC.TIT.02');

            compartida = compartidas.find(
              (evidencia) => evidencia.codigo_evidencia === 'DOC.TIT.02',
            );
          } else if (ind.id === 'I4' && (slot.sourceNum === 2 || slot.sourceNum === 3)) {
            /*
             * Segundo nivel y estudiantes desertados: evidencias propias de I4,
             * no se comparten con otros indicadores.
             */
            propia = guardadas.find(
              (evidencia) => Number(evidencia.orden) === Number(slot.sourceNum),
            );

            compartida = undefined;
          } else {
            propia = guardadas.find(
              (evidencia) => Number(evidencia.orden) === Number(slot.sourceNum),
            );

            compartida = compartidas.find(
              (evidencia) => Number(evidencia.orden) === Number(slot.sourceNum),
            );
          }

          const evidencia = propia ?? compartida;

          if (!evidencia) {
            return {
              ...slot,
              file: undefined,
            };
          }

          return {
            ...slot,
            idEvidencia: Number(evidencia.id_evidencia),
            file: {
              originalName: evidencia.nombre_archivo,
              fileName: evidencia.nombre_archivo,
              url: evidencia.url_archivo,
              serverUrl: evidencia.url_archivo,
              type: evidencia.tipo,
              size: 0,
            } as NonNullable<typeof slot.file>,
          };
        });

        setSlots(nuevasSlots);

        const primerArchivo = nuevasSlots.find((slot) => slot.file);

        setSelected((actual) => {
          const seleccionExiste = nuevasSlots.some(
            (slot) => slot.sourceNum === actual && Boolean(slot.file),
          );

          if (seleccionExiste) {
            return actual;
          }

          return primerArchivo?.sourceNum ?? null;
        });
      } catch (error) {
        if (cancelado) {
          return;
        }

        console.error(error);

        setSlots(ind.slots.map((slot) => ({ ...slot })));

        toast.error('No se pudieron cargar las evidencias guardadas.', {
          description: error instanceof Error ? error.message : 'Ocurrio un error inesperado.',
        });
      } finally {
        if (!cancelado) {
          setCargandoEvidencias(false);
        }
      }
    }

    void cargarEvidencias();

    return () => {
      cancelado = true;
    };
  }, [career, cohort, ind, idAsignatura]);

  const selectedSlot = slots.find((slot) => slot.sourceNum === selected);

  // Slots de I2/I3 (evidencia_asignatura) traen idEvidenciaAsig -- para
  // esos, el visor real (GET /evidencia-asignatura/ver) reemplaza abrir
  // `url_archivo` directo, que puede ser una ruta de filesystem local no
  // abrible desde el navegador si la carrera está en modo 'local' (ver
  // plan_interruptor_almacenamiento.txt §4.5/§4.6). El resto de los slots
  // (malla curricular / normativa institucional compartidas, I1/I4/I5)
  // traen idEvidencia (tabla `evidencias`) y usan el visor equivalente
  // (GET /api/google_drive/ver_archivo.php), ya ramificado Drive/local
  // desde el paso 5 pero nunca antes consumido desde acá -- ver MEMORIA,
  // bug reportado al migrar una carrera a local (evidencia de I1/I4/I5
  // se veía "subida" pero no abría ni previsualizaba).
  const idEvidenciaAsig = selectedSlot?.file?.idEvidenciaAsig;
  const idEvidenciaLegacy = selectedSlot?.idEvidencia;

  const urlDocumento = idEvidenciaAsig
    ? urlVisorEvidenciaAsignatura(idEvidenciaAsig)
    : idEvidenciaLegacy
      ? urlVisorEvidenciaLegacy(idEvidenciaLegacy)
      : selectedSlot?.file?.serverUrl || selectedSlot?.file?.url || '';

  function convertirUrlVistaPrevia(url: string): string {
    const coincidencia = url.match(/drive\.google\.com\/file\/d\/([^/]+)/);

    if (!coincidencia) {
      return url;
    }

    return `https://drive.google.com/file/d/${coincidencia[1]}/preview`;
  }

  const urlVistaPrevia = convertirUrlVistaPrevia(urlDocumento);

  const hasFile = Boolean(selectedSlot?.file && urlDocumento);

  // Solo para archivos servidos por nuestro propio visor (evidencia_asignatura
  // vía idEvidenciaAsig, o evidencias vía idEvidencia) con extensión .csv:
  // esos se muestran como tabla (CsvPreviewTable) en vez de en el <iframe>,
  // porque el navegador no tiene visor nativo para 'text/csv'/'text/plain'
  // dentro de un iframe (se descargaba solo -- ver MEMORIA, bug reportado
  // tras paso 6 parte 2b). Los CSV que siguen viviendo en Drive (ninguno de
  // los dos visores propios) no entran acá: ya se ven bien con el visor
  // propio de Google (`convertirUrlVistaPrevia`).
  const nombreArchivoSeleccionado = selectedSlot?.file?.fileName ?? '';
  const esCsvInterno =
    Boolean(idEvidenciaAsig || idEvidenciaLegacy) &&
    nombreArchivoSeleccionado.toLowerCase().endsWith('.csv');

  function abrirDocumento() {
    if (!urlDocumento) {
      toast.error('La evidencia no contiene una URL válida.');
      return;
    }

    window.open(urlDocumento, '_blank', 'noopener,noreferrer');
  }

  return {
    mostrarAsignatura,
    slots,
    selected,
    setSelected,
    cargandoEvidencias,
    selectedSlot,
    urlDocumento,
    urlVistaPrevia,
    hasFile,
    esCsvInterno,
    abrirDocumento,
  };
}
