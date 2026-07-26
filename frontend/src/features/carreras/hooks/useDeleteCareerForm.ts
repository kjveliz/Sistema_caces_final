import { useState } from 'react';
import type { FormEvent } from 'react';

import { toast } from 'sonner';

import { eliminarCarrera } from '../../../shared/services/carreras';

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

  const deletableCareers = deleteArea
    ? (areas.find((area) => area.name === deleteArea)?.careers ?? [])
    : [];

  function handleDeleteAreaChange(area: string) {
    setDeleteArea(area);
    setDeleteCareer('');
  }

  function closeDeleteCareerModal() {
    if (deletingCareer) return;
    setShowDeleteCareer(false);
    setDeleteArea('');
    setDeleteCareer('');
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
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'No se pudo eliminar la carrera.');
    } finally {
      setDeletingCareer(false);
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
    setDeleteCareer,
    closeDeleteCareerModal,
    handleDeleteCareer,
  };
}
