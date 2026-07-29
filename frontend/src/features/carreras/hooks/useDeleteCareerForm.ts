import { useState } from 'react';
import type { FormEvent } from 'react';

import { toast } from 'sonner';

import {
  CarreraConEvaluacionesError,
  eliminarCarrera,
  eliminarCarreraForzada,
} from '../../../shared/services/carreras';

import type { CareerArea } from '../../../types/index';
import type { CarreraBD } from './useCareers';

export function useDeleteCareerForm(
  areas: CareerArea[],
  carrerasBD: CarreraBD[],
  onSaved: () => Promise<void>,
) {
  const [showDeleteCareer, setShowDeleteCareer] = useState(false);
  const [deleteArea, setDeleteArea] = useState('');
  const [deleteCareer, setDeleteCareer] = useState('');
  const [deletingCareer, setDeletingCareer] = useState(false);

  // Eliminación forzada (desarrollo/pruebas): se habilita solo después de
  // que la eliminación normal fue bloqueada por contarEvaluaciones() (409),
  // y exige que el usuario tipee el código de la carrera como confirmación
  // extra antes de poder ejecutarla.
  const [bloqueadaPorEvaluaciones, setBloqueadaPorEvaluaciones] = useState(false);
  const [confirmacionForzada, setConfirmacionForzada] = useState('');
  const [eliminandoForzado, setEliminandoForzado] = useState(false);

  const deletableCareers = deleteArea
    ? (areas.find((area) => area.name === deleteArea)?.careers ?? [])
    : [];

  function handleDeleteAreaChange(area: string) {
    setDeleteArea(area);
    setDeleteCareer('');
    setBloqueadaPorEvaluaciones(false);
    setConfirmacionForzada('');
  }

  function handleDeleteCareerChange(value: string) {
    setDeleteCareer(value);
    setBloqueadaPorEvaluaciones(false);
    setConfirmacionForzada('');
  }

  function closeDeleteCareerModal() {
    if (deletingCareer || eliminandoForzado) return;
    setShowDeleteCareer(false);
    setDeleteArea('');
    setDeleteCareer('');
    setBloqueadaPorEvaluaciones(false);
    setConfirmacionForzada('');
  }

  async function handleDeleteCareer(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (deletingCareer || !deleteCareer) return;

    const carreraSeleccionada = carrerasBD.find(
      (carrera) => carrera.codigo.toUpperCase() === deleteCareer.toUpperCase(),
    );

    if (!carreraSeleccionada) {
      toast.error('No se encontró la carrera seleccionada en la base de datos.');
      return;
    }

    try {
      setDeletingCareer(true);

      await eliminarCarrera(carreraSeleccionada.id_carrera);

      await onSaved();

      toast.success('Carrera eliminada correctamente', {
        description: carreraSeleccionada.nombre,
      });

      setShowDeleteCareer(false);
      setDeleteArea('');
      setDeleteCareer('');
      setBloqueadaPorEvaluaciones(false);
      setConfirmacionForzada('');
    } catch (error) {
      if (error instanceof CarreraConEvaluacionesError) {
        setBloqueadaPorEvaluaciones(true);
        toast.error(error.message);
      } else {
        toast.error(error instanceof Error ? error.message : 'No se pudo eliminar la carrera.');
      }
    } finally {
      setDeletingCareer(false);
    }
  }

  async function handleForcedDeleteCareer() {
    if (eliminandoForzado || !deleteCareer) return;

    const carreraSeleccionada = carrerasBD.find(
      (carrera) => carrera.codigo.toUpperCase() === deleteCareer.toUpperCase(),
    );

    if (!carreraSeleccionada) {
      toast.error('No se encontró la carrera seleccionada en la base de datos.');
      return;
    }

    if (confirmacionForzada.trim().toUpperCase() !== carreraSeleccionada.codigo.toUpperCase()) {
      toast.error('El código escrito no coincide con el de la carrera. Verifique antes de forzar la eliminación.');
      return;
    }

    try {
      setEliminandoForzado(true);

      const resultado = await eliminarCarreraForzada(carreraSeleccionada.id_carrera);

      await onSaved();

      toast.success('Carrera y toda su cadena relacionada eliminadas', {
        description: `${resultado.nombre} · ${resultado.cohortes_borradas} cohorte(s), ${resultado.evaluaciones_borradas} evaluación(es), ${resultado.asignaturas_borradas} asignatura(s) borradas. Los archivos ya subidos a Drive/local quedan huérfanos.`,
      });

      setShowDeleteCareer(false);
      setDeleteArea('');
      setDeleteCareer('');
      setBloqueadaPorEvaluaciones(false);
      setConfirmacionForzada('');
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : 'No se pudo eliminar forzadamente la carrera.',
      );
    } finally {
      setEliminandoForzado(false);
    }
  }

  return {
    showDeleteCareer,
    setShowDeleteCareer,
    deleteArea,
    deleteCareer,
    deletableCareers,
    deletingCareer,
    handleDeleteAreaChange,
    setDeleteCareer: handleDeleteCareerChange,
    closeDeleteCareerModal,
    handleDeleteCareer,
    bloqueadaPorEvaluaciones,
    confirmacionForzada,
    setConfirmacionForzada,
    eliminandoForzado,
    handleForcedDeleteCareer,
  };
}
