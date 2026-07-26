import { useEffect, useState } from 'react';

import { toast } from 'sonner';

import { obtenerCarreras } from '../../../shared/services/carreras';
import { AREAS } from '../../../shared/data/careers';

import type { Career, CareerArea } from '../../../types/index';

export type CarreraBD = Awaited<ReturnType<typeof obtenerCarreras>>[number];

function organizarCarrerasPorArea(carrerasBD: CarreraBD[]): CareerArea[] {
  return AREAS.map((area) => {
    const carrerasDelArea = carrerasBD
      .filter(
        (carrera) =>
          carrera.area_conocimiento.trim().toLowerCase() === area.name.trim().toLowerCase(),
      )
      .map((carrera) => {
        /*
         * Busca la configuración anterior para
         * conservar propiedades de la carrera.
         */
        const carreraAnterior = AREAS.flatMap((areaAnterior) => areaAnterior.careers).find(
          (career) => career.code === carrera.codigo,
        );

        const career: Career = {
          name: carrera.nombre,
          code: carrera.codigo,
          criterionNum: carreraAnterior?.criterionNum ?? 4,
          /*
           * Todas las carreras activas que vienen desde MySQL pueden
           * abrirse. Antes las carreras nuevas quedaban bloqueadas porque
           * solo las que existían en AREAS tenían clickable=true.
           */
          clickable: true,
        };

        return career;
      });

    return {
      ...area,
      careers: carrerasDelArea,
    };
  });
}

/**
 * Carga las carreras desde la BD y las organiza por área. Expone
 * `cargarCarreras` para poder recargar después de crear/editar/eliminar
 * una carrera, sin duplicar la lógica de fetch en cada formulario.
 */
export function useCareers() {
  const [areas, setAreas] = useState<CareerArea[]>(AREAS);
  const [carrerasBD, setCarrerasBD] = useState<CarreraBD[]>([]);
  const [loadingCareers, setLoadingCareers] = useState(true);
  const [careersError, setCareersError] = useState('');

  async function cargarCarreras() {
    try {
      setLoadingCareers(true);
      setCareersError('');

      const carrerasConsultadas = await obtenerCarreras();
      const areasActualizadas = organizarCarrerasPorArea(carrerasConsultadas);

      setCarrerasBD(carrerasConsultadas);
      setAreas(areasActualizadas);
    } catch (error) {
      const mensaje =
        error instanceof Error ? error.message : 'No se pudieron cargar las carreras.';

      setCareersError(mensaje);
      toast.error(mensaje);
    } finally {
      setLoadingCareers(false);
    }
  }

  useEffect(() => {
    void cargarCarreras();
  }, []);

  const carrerasAdministrables = carrerasBD.map((carrera) => ({
    id: Number(carrera.id_carrera),
    nombre: carrera.nombre,
  }));

  return { areas, carrerasBD, loadingCareers, careersError, cargarCarreras, carrerasAdministrables };
}
