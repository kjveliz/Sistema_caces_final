import { useState } from 'react';
import type { FormEvent } from 'react';

import { toast } from 'sonner';

import { actualizarCarrera } from '../../../shared/services/carreras';

import type { CarreraBD } from './useCareers';

/**
 * NOTA (30 jul 2026): este hook tenía además un flujo opcional de "subir
 * Excel al editar" que creaba una cohorte nueva y generaba sus PAO/
 * asignaturas desde el archivo. Se sacó de acá por decisión acordada con
 * el usuario: generaba cohortes duplicadas cuando ya existía una creada a
 * mano desde "Gestionar cohortes" (el nombre no está protegido por un
 * índice único real en la tabla `cohortes`, así que ni siquiera fallaba
 * con un error claro). Ese flujo se reemplazó por una columna "Malla" en
 * "Gestión de cohortes" que carga la malla directo sobre la cohorte que
 * ya existe, sin crear una nueva (ver CohortsManagementModal.tsx y
 * shared/utils/generarMallaEnCohorte.ts). "Editar carrera" ahora solo
 * edita los datos de la carrera.
 */
export function useEditCareerForm(carrerasBD: CarreraBD[], onSaved: () => Promise<void>) {
  const [showEditCareer, setShowEditCareer] = useState(false);
  const [editCareerId, setEditCareerId] = useState('');
  const [editCareerCode, setEditCareerCode] = useState('');
  const [editCareerName, setEditCareerName] = useState('');
  const [editCareerArea, setEditCareerArea] = useState('');
  const [editCareerModalidad, setEditCareerModalidad] = useState('');
  const [updatingCareer, setUpdatingCareer] = useState(false);

  function seleccionarCarreraParaEditar(id: string) {
    setEditCareerId(id);

    const carrera = carrerasBD.find((item) => Number(item.id_carrera) === Number(id));

    if (!carrera) {
      setEditCareerCode('');
      setEditCareerName('');
      setEditCareerArea('');
      setEditCareerModalidad('');
      return;
    }

    setEditCareerCode(carrera.codigo);
    setEditCareerName(carrera.nombre);
    setEditCareerArea(carrera.area_conocimiento);
    setEditCareerModalidad(carrera.modalidad);
  }

  function handleEditCareerCodeChange(valor: string) {
    setEditCareerCode(valor.toUpperCase().replace(/\s+/g, ''));
  }

  function closeEditCareerModal() {
    if (updatingCareer) return;

    setShowEditCareer(false);
    setEditCareerId('');
    setEditCareerCode('');
    setEditCareerName('');
    setEditCareerArea('');
    setEditCareerModalidad('');
  }

  async function handleUpdateCareer(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (updatingCareer) return;

    const id = Number(editCareerId);
    const codigo = editCareerCode
      .trim()
      .toUpperCase()
      .replace(/[^A-Z0-9]/g, '');

    if (
      !Number.isFinite(id) ||
      id <= 0 ||
      !codigo ||
      !editCareerName.trim() ||
      !editCareerArea ||
      !editCareerModalidad
    ) {
      toast.error('Complete todos los campos obligatorios.');
      return;
    }

    try {
      setUpdatingCareer(true);

      await actualizarCarrera({
        id_carrera: id,
        codigo,
        nombre: editCareerName.trim(),
        area_conocimiento: editCareerArea,
        modalidad: editCareerModalidad,
      });

      await onSaved();

      toast.success('Carrera actualizada correctamente', {
        description: editCareerName.trim(),
      });

      closeEditCareerModal();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'No se pudo actualizar la carrera.');
    } finally {
      setUpdatingCareer(false);
    }
  }

  return {
    showEditCareer,
    setShowEditCareer,
    editCareerId,
    editCareerCode,
    editCareerName,
    setEditCareerName,
    editCareerArea,
    setEditCareerArea,
    editCareerModalidad,
    setEditCareerModalidad,
    updatingCareer,
    seleccionarCarreraParaEditar,
    handleEditCareerCodeChange,
    closeEditCareerModal,
    handleUpdateCareer,
  };
}
