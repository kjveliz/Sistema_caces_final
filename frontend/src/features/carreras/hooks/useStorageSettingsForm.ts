import { useState } from 'react';
import type { FormEvent } from 'react';

import { toast } from 'sonner';

import { actualizarAlmacenamientoCarrera } from '../../../shared/services/carreras';
import type { ModoAlmacenamiento } from '../../../shared/services/carreras';

import type { CarreraBD } from './useCareers';

/**
 * Interruptor de almacenamiento (Drive/local) por carrera — paso 6 parte 2
 * de plan_interruptor_almacenamiento.txt. A diferencia de
 * `useEditCareerForm`, el submit dispara una migración síncrona real en el
 * backend (puede tardar), así que `migrando` se usa para bloquear toda la
 * UI del modal mientras dura — no hay estado intermedio que mostrar (ver
 * decisión §3.6 del plan: experiencia síncrona, todo o nada).
 */
export function useStorageSettingsForm(carrerasBD: CarreraBD[], onSaved: () => Promise<void>) {
  const [showStorageSettings, setShowStorageSettings] = useState(false);
  const [storageCareerId, setStorageCareerId] = useState('');
  const [storageModo, setStorageModo] = useState<ModoAlmacenamiento>('local');
  const [storageRutaLocal, setStorageRutaLocal] = useState('');
  const [migrando, setMigrando] = useState(false);

  const carreraSeleccionada = carrerasBD.find(
    (item) => Number(item.id_carrera) === Number(storageCareerId),
  );

  function seleccionarCarreraParaAlmacenamiento(id: string) {
    setStorageCareerId(id);

    const carrera = carrerasBD.find((item) => Number(item.id_carrera) === Number(id));

    if (!carrera) {
      setStorageModo('local');
      setStorageRutaLocal('');
      return;
    }

    setStorageModo(carrera.modo_almacenamiento);
    setStorageRutaLocal(carrera.ruta_almacenamiento_local ?? '');
  }

  function closeStorageSettingsModal() {
    if (migrando) return;

    setShowStorageSettings(false);
    setStorageCareerId('');
    setStorageModo('local');
    setStorageRutaLocal('');
  }

  async function handleUpdateStorage(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (migrando) return;

    const id = Number(storageCareerId);

    if (!Number.isFinite(id) || id <= 0) {
      toast.error('Seleccione una carrera.');
      return;
    }

    // Nada que migrar si no cambió ni el modo ni (siendo local) la ruta —
    // mismo criterio de "sin cambios" que ya valida el backend
    // (EvidenciaMigradorService::migrar), evitamos el viaje de red.
    const sinCambios =
      !!carreraSeleccionada &&
      storageModo === carreraSeleccionada.modo_almacenamiento &&
      (storageModo !== 'local' ||
        (storageRutaLocal.trim() || null) ===
          (carreraSeleccionada.ruta_almacenamiento_local?.trim() || null));

    if (sinCambios) {
      toast.info('Esa carrera ya está en ese modo de almacenamiento.');
      return;
    }

    try {
      setMigrando(true);

      const resultado = await actualizarAlmacenamientoCarrera({
        id_carrera: id,
        modo_almacenamiento: storageModo,
        ruta_local: storageModo === 'local' ? storageRutaLocal.trim() || null : null,
      });

      await onSaved();

      toast.success('Almacenamiento actualizado correctamente', {
        description: `${resultado.total_migrados} evidencia(s) migrada(s) de ${resultado.modo_anterior} a ${resultado.modo_nuevo}.`,
      });

      closeStorageSettingsModal();
    } catch (error) {
      // Si falla, el backend ya revirtió todo lo migrado y la carrera
      // queda exactamente como estaba (ver plan §3.7) — el modal se queda
      // abierto con los valores tal cual estaban antes del intento, para
      // que el usuario vea el error y pueda reintentar sin perder contexto.
      toast.error(
        error instanceof Error ? error.message : 'No se pudo cambiar el almacenamiento.',
      );
    } finally {
      setMigrando(false);
    }
  }

  return {
    showStorageSettings,
    setShowStorageSettings,
    storageCareerId,
    storageModo,
    setStorageModo,
    storageRutaLocal,
    setStorageRutaLocal,
    carreraSeleccionada,
    migrando,
    seleccionarCarreraParaAlmacenamiento,
    closeStorageSettingsModal,
    handleUpdateStorage,
  };
}
