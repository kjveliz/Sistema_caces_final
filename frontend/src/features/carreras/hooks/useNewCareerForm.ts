import { useRef, useState } from 'react';
import type { FormEvent } from 'react';

import { toast } from 'sonner';

import { crearCarrera, eliminarCarrera, subirMallaCurricular } from '../../../shared/services/carreras';
import { crearCohorteEvaluacion } from '../../../shared/services/cohortes';
import type { CrearCohorteParams } from '../../../shared/services/cohortes';
import {
  crearAsignatura,
  crearPeriodo,
  eliminarCohorteSeguimiento,
} from '../../../shared/services/seguimientoSyllabus';
import { parsearMallaCurricular } from '../../../shared/utils/mallaCurricular';

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

/**
 * Rollback cuando ya existe carrera pero todavía no hay cohorte (falla el
 * paso 2 -- subida de malla -- o el paso 3 -- creación de cohorte -- del
 * plan §9.2). Sin cohorte creada, `POST /carreras/eliminar` no está
 * bloqueado por `contarEvaluaciones` (ver plan §9.1), así que alcanza con
 * este único paso.
 */
async function limpiarCarreraSinCohorte(idCarrera: number): Promise<void> {
  try {
    await eliminarCarrera(idCarrera);
  } catch (errorLimpieza) {
    // Único caso sin cobertura automática (plan §9.5 Parte D): avisar
    // exactamente qué quedó a medio crear, con los IDs, para limpieza
    // manual -- nunca fallar en silencio.
    toast.error(`No se pudo revertir la carrera creada (id ${idCarrera}).`, {
      description:
        (errorLimpieza instanceof Error ? errorLimpieza.message : undefined) ??
        'Bórrela manualmente desde la lista de carreras.',
    });
  }
}

/**
 * Rollback cuando ya existe cohorte (falla el paso 4 -- períodos -- o el
 * paso 5 -- asignaturas -- del plan §9.2). Hace falta borrar primero la
 * cohorte en cascada (Parte A, `DELETE /seguimiento-syllabus/cohortes/
 * {id}`): mientras exista la evaluación que `crear.php` crea junto con la
 * cohorte, `POST /carreras/eliminar` queda bloqueado con 409 (ver plan
 * §9.1).
 */
async function limpiarCohorteYCarrera(idCohorte: number, idCarrera: number): Promise<void> {
  try {
    await eliminarCohorteSeguimiento(idCohorte);
  } catch (errorLimpiezaCohorte) {
    toast.error(
      `No se pudo revertir la cohorte creada (id ${idCohorte}). La carrera (id ${idCarrera}) ` +
        'tampoco pudo eliminarse porque queda bloqueada mientras la cohorte exista.',
      {
        description:
          (errorLimpiezaCohorte instanceof Error ? errorLimpiezaCohorte.message : undefined) ??
          'Bórrelas manualmente desde "Gestión de cohortes" y la lista de carreras.',
      },
    );
    return;
  }

  await limpiarCarreraSinCohorte(idCarrera);
}

export function useNewCareerForm(onSaved: () => Promise<void>) {
  const [showNewCareer, setShowNewCareer] = useState(false);
  const [newCareerName, setNewCareerName] = useState('');
  const [newCareerArea, setNewCareerArea] = useState('');
  const [newCareerCode, setNewCareerCode] = useState('');
  const [newCareerModalidad, setNewCareerModalidad] = useState('');
  const [newCareerFile, setNewCareerFile] = useState<File | null>(null);
  const [savingCareer, setSavingCareer] = useState(false);

  // Campos de la cohorte que se crea junto con la carrera (Parte B del plan
  // de malla curricular xlsx, ver plan §9.5). El envío real a
  // crearCohorteEvaluacion() dentro de handleSaveCareer queda para la
  // Parte D (orquestación); acá solo se agrega el estado de estos campos.
  const [newCohorteNombre, setNewCohorteNombre] = useState('');
  const [newCohorteFechaInicio, setNewCohorteFechaInicio] = useState('');
  const [newCohorteFechaFin, setNewCohorteFechaFin] = useState('');
  const [newCohorteEstado, setNewCohorteEstado] = useState<CrearCohorteParams['estado']>('Activa');

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
    setNewCohorteNombre('');
    setNewCohorteFechaInicio('');
    setNewCohorteFechaFin('');
    setNewCohorteEstado('Activa');

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

    if (
      !newCareerName.trim() ||
      !code ||
      !newCareerArea ||
      !newCareerModalidad ||
      !newCohorteNombre.trim() ||
      !newCohorteFechaInicio ||
      !newCohorteFechaFin
    ) {
      toast.error('Complete todos los campos obligatorios.');
      return;
    }

    if (!newCareerFile) {
      toast.error('Seleccione la malla curricular en Excel (.xlsx).');
      return;
    }

    // Paso 0 (plan §9.5 Parte D): el parseo del Excel corre primero y es
    // 100% local (SheetJS en el navegador, sin llamadas al servidor). Si
    // el archivo no tiene el layout esperado, el usuario se entera sin
    // que se haya creado todavía carrera, cohorte ni nada que después
    // haya que deshacer.
    let mallaParseada;
    try {
      const buffer = await newCareerFile.arrayBuffer();
      mallaParseada = parsearMallaCurricular(buffer);
    } catch (error) {
      toast.error(
        error instanceof Error ? error.message : 'No se pudo leer la malla curricular.',
      );
      return;
    }

    try {
      setSavingCareer(true);

      // 1) crearCarrera()
      const carreraCreada = await crearCarrera({
        codigo: code,
        nombre: newCareerName.trim(),
        area_conocimiento: newCareerArea,
        modalidad: newCareerModalidad,
      });

      // 2) subirMallaCurricular() -- deja la malla activa=1 para que el
      // paso 3 (crear.php) la encuentre y la auto-registre como
      // evidencia DOC.SYL.01 (orden confirmado en plan §9.2 hallazgo 1).
      try {
        await subirMallaCurricular({
          archivo: newCareerFile,
          carrera: carreraCreada,
        });
      } catch (error) {
        await limpiarCarreraSinCohorte(carreraCreada.id_carrera);
        throw error;
      }

      // 3) crearCohorteEvaluacion() (legacy, crea cohorte + evaluación y
      // auto-linkea la malla ya activa como evidencia).
      let idCohorte: number;
      try {
        const cohorteCreada = await crearCohorteEvaluacion({
          idCarrera: carreraCreada.id_carrera,
          nombreCohorte: newCohorteNombre.trim(),
          fechaInicio: newCohorteFechaInicio,
          fechaFin: newCohorteFechaFin,
          estado: newCohorteEstado,
        });
        idCohorte = cohorteCreada.id_cohorte;
      } catch (error) {
        // Todavía no hay cohorte -- alcanza con revertir la carrera.
        await limpiarCarreraSinCohorte(carreraCreada.id_carrera);
        throw error;
      }

      // 4) y 5) los 3 períodos (PAO 1/2/3) y, por cada uno, sus
      // asignaturas del Excel (ya excluye Práctica Laboral y Servicio
      // Comunitario -- parsearMallaCurricular). A partir de acá, si algo
      // falla hay que deshacer también la cohorte (Parte A).
      try {
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
        await limpiarCohorteYCarrera(idCohorte, carreraCreada.id_carrera);
        throw error;
      }

      await onSaved();

      toast.success('Carrera, cohorte y malla registradas correctamente', {
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
    newCohorteNombre,
    setNewCohorteNombre,
    newCohorteFechaInicio,
    setNewCohorteFechaInicio,
    newCohorteFechaFin,
    setNewCohorteFechaFin,
    newCohorteEstado,
    setNewCohorteEstado,
    savingCareer,
    fileRef,
    handleNewCareerNameChange,
    handleNewCareerCodeChange,
    closeNewCareerModal,
    handleSaveCareer,
  };
}
