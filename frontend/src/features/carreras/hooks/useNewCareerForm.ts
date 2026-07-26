import { useRef, useState } from 'react';
import type { FormEvent } from 'react';

import { toast } from 'sonner';

import { crearCarrera, subirMallaCurricular } from '../../../shared/services/carreras';

function generarCodigoCarrera(nombre: string): string {
  const palabrasIgnoradas = ['DE', 'DEL', 'LA', 'LAS', 'EL', 'LOS', 'EN', 'Y', 'PARA'];

  const palabras = nombre
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toUpperCase()
    .replace(/[^A-ZÑ\s]/g, '')
    .trim()
    .split(/\s+/)
    .filter((palabra) => palabra.length > 0 && !palabrasIgnoradas.includes(palabra));

  return palabras
    .map((palabra) => palabra.slice(0, 3))
    .join('')
    .slice(0, 15);
}

export function useNewCareerForm(onSaved: () => Promise<void>) {
  const [showNewCareer, setShowNewCareer] = useState(false);
  const [newCareerName, setNewCareerName] = useState('');
  const [newCareerArea, setNewCareerArea] = useState('');
  const [newCareerCode, setNewCareerCode] = useState('');
  const [newCareerModalidad, setNewCareerModalidad] = useState('');
  const [newCareerFile, setNewCareerFile] = useState<File | null>(null);
  const [savingCareer, setSavingCareer] = useState(false);

  const fileRef = useRef<HTMLInputElement>(null);

  function handleNewCareerNameChange(nombre: string) {
    setNewCareerName(nombre);
    setNewCareerCode(generarCodigoCarrera(nombre));
  }

  function handleNewCareerCodeChange(valor: string) {
    setNewCareerCode(valor.toUpperCase().replace(/\s+/g, ''));
  }

  function closeNewCareerModal() {
    setShowNewCareer(false);
    setNewCareerName('');
    setNewCareerCode('');
    setNewCareerArea('');
    setNewCareerModalidad('');
    setNewCareerFile(null);

    if (fileRef.current) {
      fileRef.current.value = '';
    }
  }

  async function handleSaveCareer(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (savingCareer) {
      return;
    }

    const code = newCareerCode
      .trim()
      .toUpperCase()
      .replace(/[^A-Z0-9]/g, '');

    if (!newCareerName.trim() || !code || !newCareerArea || !newCareerModalidad) {
      toast.error('Complete todos los campos obligatorios.');
      return;
    }

    try {
      setSavingCareer(true);

      if (!newCareerFile) {
        toast.error('Seleccione la malla curricular en PDF.');
        return;
      }

      const carreraCreada = await crearCarrera({
        codigo: code,
        nombre: newCareerName.trim(),
        area_conocimiento: newCareerArea,
        modalidad: newCareerModalidad,
      });

      await subirMallaCurricular({
        archivo: newCareerFile,
        carrera: carreraCreada,
      });

      await onSaved();

      toast.success('Carrera y malla registradas correctamente', {
        description: newCareerName.trim(),
      });

      closeNewCareerModal();
    } catch (error) {
      toast.error(error instanceof Error ? error.message : 'No se pudo crear la carrera.');
    } finally {
      setSavingCareer(false);
    }
  }

  return {
    showNewCareer,
    setShowNewCareer,
    newCareerName,
    newCareerArea,
    setNewCareerArea,
    newCareerCode,
    newCareerModalidad,
    setNewCareerModalidad,
    newCareerFile,
    setNewCareerFile,
    savingCareer,
    fileRef,
    handleNewCareerNameChange,
    handleNewCareerCodeChange,
    closeNewCareerModal,
    handleSaveCareer,
  };
}
