import { useEffect, useState } from 'react';

import {
  obtenerDatosTasa,
  obtenerDatosDesercion,
  obtenerEvaluacion,
  type CohorteTitulacion,
  type CohorteDesercion,
} from '../../../shared/services/evidencias';
import type { Career, IndicatorDef } from '../../../types/index';

export function useCohortesIndicador({
  ind,
  career,
  cohort,
}: {
  ind: IndicatorDef;
  career: Career | null;
  cohort: string;
}) {
  const [datosTitulacion, setDatosTitulacion] = useState<CohorteTitulacion[]>([]);

  const [datosDesercion, setDatosDesercion] = useState<CohorteDesercion[]>([]);

  const [cargando, setCargando] = useState(true);
  const [errorCarga, setErrorCarga] = useState('');

  const esDesercion = ind.id === 'I4';

  useEffect(() => {
    void cargar();
  }, [career?.code, cohort, ind.id]);

  async function cargar() {
    setCargando(true);
    setErrorCarga('');

    try {
      if (!career) {
        throw new Error('No se ha seleccionado una carrera.');
      }

      const cohorteNormalizada = cohort.replace(/\s+/g, '').toUpperCase();

      const evaluacion = await obtenerEvaluacion(career.code, cohorteNormalizada);

      if (esDesercion) {
        const respuesta = await obtenerDatosDesercion(evaluacion.id_evaluacion);

        setDatosDesercion(respuesta);
        setDatosTitulacion([]);
      } else {
        const respuesta = await obtenerDatosTasa(evaluacion.id_evaluacion);

        setDatosTitulacion(respuesta);
        setDatosDesercion([]);
      }
    } catch (error) {
      console.error(error);

      const mensaje =
        error instanceof Error ? error.message : 'No se pudieron cargar los datos de cohortes.';

      setErrorCarga(mensaje);
      setDatosTitulacion([]);
      setDatosDesercion([]);
    } finally {
      setCargando(false);
    }
  }

  const cantidadRegistros = esDesercion ? datosDesercion.length : datosTitulacion.length;

  const totalPrimerNivel = datosDesercion.reduce(
    (suma, item) => suma + Number(item.iniciaron_primer_nivel ?? 0),
    0,
  );

  const totalSegundoAnio = datosDesercion.reduce(
    (suma, item) => suma + Number(item.matriculados_segundo_anio ?? 0),
    0,
  );

  const totalDesertados = datosDesercion.reduce(
    (suma, item) => suma + Number(item.no_continuaron ?? 0),
    0,
  );

  const tasaPromedioDesercion =
    datosDesercion.length === 0
      ? 0
      : Number(
          (
            datosDesercion.reduce((suma, item) => suma + Number(item.tasa ?? 0), 0) /
            datosDesercion.length
          ).toFixed(2),
        );

  const totalMatriculados = datosTitulacion.reduce(
    (suma, item) => suma + Number(item.matriculados ?? 0),
    0,
  );

  const totalGraduados = datosTitulacion.reduce(
    (suma, item) => suma + Number(item.graduados ?? 0),
    0,
  );

  const tasaGeneralTitulacion =
    totalMatriculados === 0 ? 0 : Number(((totalGraduados / totalMatriculados) * 100).toFixed(2));

  const encabezados = esDesercion
    ? [
        'Cohorte',
        'Matriculados 1er nivel',
        'Matriculados 2do año',
        'Desertados 2do año',
        'Tasa',
        'Estado',
      ]
    : ['Cohorte', 'Matriculados', 'Graduados', 'Tasa', 'Estado'];

  return {
    datosTitulacion,
    datosDesercion,
    cargando,
    errorCarga,
    esDesercion,
    cantidadRegistros,
    totalPrimerNivel,
    totalSegundoAnio,
    totalDesertados,
    tasaPromedioDesercion,
    totalMatriculados,
    totalGraduados,
    tasaGeneralTitulacion,
    encabezados,
  };
}
