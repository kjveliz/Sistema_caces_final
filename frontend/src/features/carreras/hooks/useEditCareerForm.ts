import { useRef, useState } from 'react';
import type { FormEvent } from 'react';

import { toast } from 'sonner';

import { actualizarCarrera } from '../../../shared/services/carreras';
import { crearCohorteEvaluacion } from '../../../shared/services/cohortes';
import type { CrearCohorteParams } from '../../../shared/services/cohortes';
import {
  crearAsignatura,
  crearPeriodo,
  eliminarCohorteSeguimiento,
  obtenerPeriodos,
} from '../../../shared/services/seguimientoSyllabus';
import { parsearMallaCurricular } from '../../../shared/utils/mallaCurricular';

import type { CarreraBD } from './useCareers';

/**
 * Rollback cuando falla la generación de PAO/asignaturas después de que la
 * cohorte nueva ya existe (mismo criterio que `limpiarCohorteYCarrera` de
 * useNewCareerForm, pero acá NO se toca la carrera: a diferencia del alta,
 * en edición la carrera ya existía antes de este submit, así que no hay
 * nada que revertir de ella -- solo la cohorte recién creada).
 */
async function limpiarCohorteMalla(idCohorte: number): Promise<void> {
  try {
    await eliminarCohorteSeguimiento(idCohorte);
  } catch (errorLimpieza) {
    toast.error(`No se pudo revertir la cohorte creada (id ${idCohorte}).`, {
      description:
        (errorLimpieza instanceof Error ? errorLimpieza.message : undefined) ??
        'Bórrela manualmente desde "Gestionar cohortes".',
    });
  }
}

export function useEditCareerForm(carrerasBD: CarreraBD[], onSaved: () => Promise<void>) {
  const [showEditCareer, setShowEditCareer] = useState(false);
  const [editCareerId, setEditCareerId] = useState('');
  const [editCareerCode, setEditCareerCode] = useState('');
  const [editCareerName, setEditCareerName] = useState('');
  const [editCareerArea, setEditCareerArea] = useState('');
  const [editCareerModalidad, setEditCareerModalidad] = useState('');
  const [updatingCareer, setUpdatingCareer] = useState(false);

  // Malla curricular (XLSX) opcional durante la edición: si se selecciona
  // un archivo, se crea una cohorte nueva (con estos mismos campos que ya
  // usa "Nueva carrera") y se generan sus PAO/asignaturas a partir del
  // Excel -- decisión acordada con el usuario, ver conversación del
  // 29 jul 2026. A diferencia del alta, acá NO se llama a
  // subirMallaCurricular(): el archivo de malla ya registrado de la
  // carrera no se toca, el Excel solo se usa para generar PAO/asignaturas.
  const [editCareerFile, setEditCareerFile] = useState<File | null>(null);
  const [editCohorteNombre, setEditCohorteNombre] = useState('');
  const [editCohorteFechaInicio, setEditCohorteFechaInicio] = useState('');
  const [editCohorteFechaFin, setEditCohorteFechaFin] = useState('');
  const [editCohorteEstado, setEditCohorteEstado] = useState<CrearCohorteParams['estado']>('Activa');

  const fileRef = useRef<HTMLInputElement>(null);

  function seleccionarCarreraParaEditar(id: string) {
    setEditCareerId(id);

    // Cambiar de carrera reinicia también la malla/cohorte que se venía
    // armando para la carrera anterior -- no tiene sentido arrastrar un
    // Excel ya seleccionado a otra carrera distinta.
    setEditCareerFile(null);
    setEditCohorteNombre('');
    setEditCohorteFechaInicio('');
    setEditCohorteFechaFin('');
    setEditCohorteEstado('Activa');
    if (fileRef.current) {
      fileRef.current.value = '';
    }

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
    setEditCareerFile(null);
    setEditCohorteNombre('');
    setEditCohorteFechaInicio('');
    setEditCohorteFechaFin('');
    setEditCohorteEstado('Activa');

    if (fileRef.current) {
      fileRef.current.value = '';
    }
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

    // La malla/cohorte es opcional en edición (a diferencia de "Nueva
    // carrera", donde es obligatoria): solo se validan estos campos si el
    // usuario efectivamente seleccionó un Excel.
    if (
      editCareerFile &&
      (!editCohorteNombre.trim() || !editCohorteFechaInicio || !editCohorteFechaFin)
    ) {
      toast.error('Complete los datos de la cohorte para poder generar la malla.');
      return;
    }

    // Paso 0 (mismo criterio que useNewCareerForm): el parseo del Excel
    // corre primero y es 100% local, antes de tocar el servidor. Así, si
    // el archivo no tiene el layout esperado, no se llega a actualizar
    // nada ni a crear una cohorte que después haya que deshacer.
    let mallaParseada: ReturnType<typeof parsearMallaCurricular> | null = null;
    if (editCareerFile) {
      try {
        const buffer = await editCareerFile.arrayBuffer();
        mallaParseada = parsearMallaCurricular(buffer);
      } catch (error) {
        toast.error(
          error instanceof Error ? error.message : 'No se pudo leer la malla curricular.',
        );
        return;
      }
    }

    try {
      setUpdatingCareer(true);

      const carreraActualizada = await actualizarCarrera({
        id_carrera: id,
        codigo,
        nombre: editCareerName.trim(),
        area_conocimiento: editCareerArea,
        modalidad: editCareerModalidad,
      });

      // Si se seleccionó un Excel, se crea una cohorte nueva y se generan
      // sus PAO/asignaturas -- el archivo de malla ya registrado de la
      // carrera (subirMallaCurricular) no se toca acá, por decisión
      // acordada con el usuario.
      if (mallaParseada) {
        // Nada que revertir todavía si esta llamada falla -- ni cohorte ni
        // períodos existen aún, así que el error se deja propagar tal cual.
        const cohorteCreada = await crearCohorteEvaluacion({
          idCarrera: carreraActualizada.id_carrera,
          nombreCohorte: editCohorteNombre.trim(),
          fechaInicio: editCohorteFechaInicio,
          fechaFin: editCohorteFechaFin,
          estado: editCohorteEstado,
        });
        const idCohorte = cohorteCreada.id_cohorte;

        // Chequeo defensivo: crearPeriodo() no es get-or-create (a
        // diferencia de crearAsignatura), así que si por algún motivo la
        // cohorte recién creada ya tuviera PAO cargados, se bloquea en vez
        // de duplicarlos -- decisión acordada con el usuario.
        try {
          const periodosExistentes = await obtenerPeriodos(idCohorte);
          if (periodosExistentes.length > 0) {
            throw new Error('Esa cohorte ya tiene malla curricular cargada.');
          }

          for (const pao of mallaParseada.paos) {
            const idPeriodo = await crearPeriodo({
              idCohorte,
              nombre: pao.nombre,
              orden: pao.orden,
            });

            for (const asignatura of pao.asignaturas) {
              await crearAsignatura({
                idPeriodo,
                nombre: asignatura.nombre,
                modulo: asignatura.modulo,
              });
            }
          }
        } catch (error) {
          await limpiarCohorteMalla(idCohorte);
          throw error;
        }
      }

      await onSaved();

      toast.success(
        mallaParseada
          ? 'Carrera actualizada y malla curricular generada correctamente'
          : 'Carrera actualizada correctamente',
        {
          description: editCareerName.trim(),
        },
      );

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
    editCareerFile,
    setEditCareerFile,
    fileRef,
    editCohorteNombre,
    setEditCohorteNombre,
    editCohorteFechaInicio,
    setEditCohorteFechaInicio,
    editCohorteFechaFin,
    setEditCohorteFechaFin,
    editCohorteEstado,
    setEditCohorteEstado,
    seleccionarCarreraParaEditar,
    handleEditCareerCodeChange,
    closeEditCareerModal,
    handleUpdateCareer,
  };
}
