import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import UsersManagementModal from './UsersManagementModal';
import CohortsManagementModal from './CohortsManagementModal';
import {
  actualizarCarrera,
  obtenerCarreras,
  crearCarrera,
  eliminarCarrera,
  subirMallaCurricular,
} from '../services/carreras';
import {
  AlertCircle,
  CalendarDays,
  ChevronDown,
  Users,
  LogOut,
  Plus,
  Pencil,
  ShieldCheck,
  TableProperties,
  Upload,
  User,
  X,
} from 'lucide-react';

import { toast } from 'sonner';

import CompassIcon from '../app/components/CompassIcon';
import { AREAS } from '../data/careers';

import type { Career, CareerArea } from '../types';
import type { UsuarioSesion } from '../services/auth';

interface CareersViewProps {
  onSelect: (career: Career) => void;
  onLogout: () => void;
  usuario: UsuarioSesion;
}

type CarreraBD = Awaited<ReturnType<typeof obtenerCarreras>>[number];

export default function CareersView({ onSelect, onLogout, usuario }: CareersViewProps) {
  const [areas, setAreas] = useState<CareerArea[]>(AREAS);
  const [carrerasBD, setCarrerasBD] = useState<CarreraBD[]>([]);

  const [loadingCareers, setLoadingCareers] = useState(true);
  const [careersError, setCareersError] = useState('');
  const [savingCareer, setSavingCareer] = useState(false);
  const [deletingCareer, setDeletingCareer] = useState(false);
  const [updatingCareer, setUpdatingCareer] = useState(false);
  const [showNewCareer, setShowNewCareer] = useState(false);
  const [showEditCareer, setShowEditCareer] = useState(false);
  const [showDeleteCareer, setShowDeleteCareer] = useState(false);
  const [showManageMenu, setShowManageMenu] = useState(false);
  const [showUsersManagement, setShowUsersManagement] = useState(false);
  const [showCohortsManagement, setShowCohortsManagement] = useState(false);
  const [newCareerName, setNewCareerName] = useState('');
  const [newCareerArea, setNewCareerArea] = useState('');
  const [newCareerCode, setNewCareerCode] = useState('');
  const [newCareerModalidad, setNewCareerModalidad] = useState('');
  const [newCareerFile, setNewCareerFile] = useState<File | null>(null);

  const [deleteArea, setDeleteArea] = useState('');
  const [deleteCareer, setDeleteCareer] = useState('');

  const [editCareerId, setEditCareerId] = useState('');
  const [editCareerCode, setEditCareerCode] = useState('');
  const [editCareerName, setEditCareerName] = useState('');
  const [editCareerArea, setEditCareerArea] = useState('');
  const [editCareerModalidad, setEditCareerModalidad] = useState('');

  const fileRef = useRef<HTMLInputElement>(null);
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
  const carrerasAdministrables = carrerasBD.map((carrera) => ({
    id: Number(carrera.id_carrera),
    nombre: carrera.nombre,
  }));

  const deletableCareers = deleteArea
    ? (areas.find((area) => area.name === deleteArea)?.careers ?? [])
    : [];
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

  function closeEditCareerModal() {
    if (updatingCareer) return;

    setShowEditCareer(false);
    setEditCareerId('');
    setEditCareerCode('');
    setEditCareerName('');
    setEditCareerArea('');
    setEditCareerModalidad('');
  }

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

  function closeDeleteCareerModal() {
    if (deletingCareer) return;
    setShowDeleteCareer(false);
    setDeleteArea('');
    setDeleteCareer('');
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

      await cargarCarreras();

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

      await cargarCarreras();

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

      await cargarCarreras();

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

  return (
    <div
      className="h-screen flex flex-col overflow-hidden"
      style={{
        background: '#F1F5F9',
        fontFamily: "'Plus Jakarta Sans',sans-serif",
      }}
    >
      <UsersManagementModal
        open={showUsersManagement}
        onClose={() => setShowUsersManagement(false)}
      />

      <CohortsManagementModal
        open={showCohortsManagement}
        onClose={() => setShowCohortsManagement(false)}
        carreras={carrerasAdministrables}
      />
      {/* Modal: editar carrera */}
      {showEditCareer && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center"
          style={{ background: 'rgba(0,0,0,0.4)' }}
        >
          <div
            className="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4"
            style={{
              border: '1px solid rgba(27,58,107,0.12)',
            }}
          >
            <div
              className="flex items-center justify-between px-6 py-4 border-b"
              style={{
                borderColor: 'rgba(27,58,107,0.1)',
              }}
            >
              <h2
                className="text-base font-bold"
                style={{
                  fontFamily: "'Libre Baskerville',serif",
                  color: '#0F1E3C',
                }}
              >
                Editar Carrera
              </h2>

              <button
                type="button"
                onClick={closeEditCareerModal}
                disabled={updatingCareer}
                className="p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
              >
                <X size={15} style={{ color: '#5A7295' }} />
              </button>
            </div>

            <form onSubmit={handleUpdateCareer} className="px-6 py-5 space-y-4">
              <div>
                <label
                  className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                  style={{ color: '#5A7295' }}
                >
                  Carrera que desea editar
                </label>

                <div className="relative">
                  <select
                    value={editCareerId}
                    onChange={(event) => seleccionarCarreraParaEditar(event.target.value)}
                    required
                    disabled={updatingCareer}
                    className="w-full px-3 py-2 rounded-xl text-sm border outline-none appearance-none cursor-pointer"
                    style={{
                      background: '#F4F7FB',
                      borderColor: 'rgba(27,58,107,0.2)',
                      color: '#0F1E3C',
                    }}
                  >
                    <option value="">— Seleccionar carrera —</option>

                    {carrerasBD.map((carrera) => (
                      <option key={carrera.id_carrera} value={carrera.id_carrera}>
                        {carrera.nombre}
                      </option>
                    ))}
                  </select>

                  <ChevronDown
                    size={14}
                    className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                    style={{ color: '#5A7295' }}
                  />
                </div>
              </div>

              <div
                className="space-y-4"
                style={{
                  opacity: editCareerId ? 1 : 0.45,
                  pointerEvents: editCareerId ? 'auto' : 'none',
                }}
              >
                <div>
                  <label
                    className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                    style={{ color: '#5A7295' }}
                  >
                    Nombre
                  </label>
                  <input
                    type="text"
                    value={editCareerName}
                    onChange={(event) => setEditCareerName(event.target.value)}
                    required
                    disabled={updatingCareer}
                    className="w-full px-3 py-2 rounded-xl text-sm border outline-none"
                    style={{
                      background: '#F4F7FB',
                      borderColor: 'rgba(27,58,107,0.2)',
                      color: '#0F1E3C',
                    }}
                  />
                </div>

                <div>
                  <label
                    className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                    style={{ color: '#5A7295' }}
                  >
                    Código institucional
                  </label>
                  <input
                    type="text"
                    value={editCareerCode}
                    onChange={(event) =>
                      setEditCareerCode(event.target.value.toUpperCase().replace(/\s+/g, ''))
                    }
                    required
                    maxLength={15}
                    disabled={updatingCareer}
                    className="w-full px-3 py-2 rounded-xl text-sm border outline-none"
                    style={{
                      background: '#F4F7FB',
                      borderColor: 'rgba(27,58,107,0.2)',
                      color: '#0F1E3C',
                    }}
                  />
                </div>

                <div>
                  <label
                    className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                    style={{ color: '#5A7295' }}
                  >
                    Área de conocimiento
                  </label>
                  <div className="relative">
                    <select
                      value={editCareerArea}
                      onChange={(event) => setEditCareerArea(event.target.value)}
                      required
                      disabled={updatingCareer}
                      className="w-full px-3 py-2 rounded-xl text-sm border outline-none appearance-none cursor-pointer"
                      style={{
                        background: '#F4F7FB',
                        borderColor: 'rgba(27,58,107,0.2)',
                        color: '#0F1E3C',
                      }}
                    >
                      <option value="">— Seleccionar área —</option>
                      {areas.map((area) => (
                        <option key={area.name} value={area.name}>
                          {area.name}
                        </option>
                      ))}
                    </select>
                    <ChevronDown
                      size={14}
                      className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                      style={{ color: '#5A7295' }}
                    />
                  </div>
                </div>

                <div>
                  <label
                    className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                    style={{ color: '#5A7295' }}
                  >
                    Modalidad
                  </label>
                  <div className="relative">
                    <select
                      value={editCareerModalidad}
                      onChange={(event) => setEditCareerModalidad(event.target.value)}
                      required
                      disabled={updatingCareer}
                      className="w-full px-3 py-2 rounded-xl text-sm border outline-none appearance-none cursor-pointer"
                      style={{
                        background: '#F4F7FB',
                        borderColor: 'rgba(27,58,107,0.2)',
                        color: '#0F1E3C',
                      }}
                    >
                      <option value="">— Seleccionar modalidad —</option>
                      <option value="Presencial">Presencial</option>
                      <option value="En línea">En línea</option>
                      <option value="Híbrida">Híbrida</option>
                    </select>
                    <ChevronDown
                      size={14}
                      className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                      style={{ color: '#5A7295' }}
                    />
                  </div>
                </div>
              </div>

              <div className="flex gap-3 pt-1">
                <button
                  type="submit"
                  disabled={!editCareerId || updatingCareer}
                  className="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all hover:opacity-90"
                  style={{
                    background: editCareerId && !updatingCareer ? '#1B3A6B' : '#E5E7EB',
                    color: editCareerId && !updatingCareer ? '#fff' : '#9CA3AF',
                    cursor: editCareerId && !updatingCareer ? 'pointer' : 'not-allowed',
                  }}
                >
                  {updatingCareer ? 'Actualizando...' : 'Guardar cambios'}
                </button>

                <button
                  type="button"
                  onClick={closeEditCareerModal}
                  disabled={updatingCareer}
                  className="flex-1 py-2.5 rounded-xl text-sm font-bold border transition-all hover:bg-gray-50"
                  style={{
                    borderColor: 'rgba(27,58,107,0.2)',
                    color: '#5A7295',
                  }}
                >
                  Cancelar
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal: eliminar carrera */}
      {showDeleteCareer && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center"
          style={{
            background: 'rgba(0,0,0,0.4)',
          }}
        >
          <div
            className="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4"
            style={{
              border: '1px solid rgba(27,58,107,0.12)',
            }}
          >
            <div
              className="flex items-center justify-between px-6 py-4 border-b"
              style={{
                borderColor: 'rgba(27,58,107,0.1)',
              }}
            >
              <h2
                className="text-base font-bold"
                style={{
                  fontFamily: "'Libre Baskerville',serif",
                  color: '#0F1E3C',
                }}
              >
                Eliminar Carrera
              </h2>

              <button
                type="button"
                onClick={closeDeleteCareerModal}
                className="p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
              >
                <X
                  size={15}
                  style={{
                    color: '#5A7295',
                  }}
                />
              </button>
            </div>

            <form onSubmit={handleDeleteCareer} className="px-6 py-5 space-y-4">
              <p
                className="text-sm"
                style={{
                  color: '#5A7295',
                }}
              >
                Seleccione el área y la carrera que desea eliminar del sistema.
              </p>

              <div>
                <label
                  className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                  style={{
                    color: '#5A7295',
                  }}
                >
                  Área de conocimiento
                </label>

                <div className="relative">
                  <select
                    value={deleteArea}
                    onChange={(event) => {
                      setDeleteArea(event.target.value);
                      setDeleteCareer('');
                    }}
                    required
                    className="w-full px-3 py-2 rounded-xl text-sm border outline-none appearance-none cursor-pointer"
                    style={{
                      background: '#F4F7FB',
                      borderColor: 'rgba(27,58,107,0.2)',
                      color: '#0F1E3C',
                    }}
                  >
                    <option value="">— Seleccionar área —</option>

                    {areas.map((area) => (
                      <option key={area.name} value={area.name}>
                        {area.name}
                      </option>
                    ))}
                  </select>

                  <ChevronDown
                    size={14}
                    className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                    style={{
                      color: '#5A7295',
                    }}
                  />
                </div>
              </div>

              <div
                style={{
                  opacity: deleteArea ? 1 : 0.45,
                  pointerEvents: deleteArea ? 'auto' : 'none',
                }}
              >
                <label
                  className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                  style={{
                    color: '#5A7295',
                  }}
                >
                  Carrera
                </label>

                <div className="relative">
                  <select
                    value={deleteCareer}
                    onChange={(event) => setDeleteCareer(event.target.value)}
                    required={Boolean(deleteArea)}
                    className="w-full px-3 py-2 rounded-xl text-sm border outline-none appearance-none cursor-pointer"
                    style={{
                      background: '#F4F7FB',
                      borderColor: 'rgba(27,58,107,0.2)',
                      color: '#0F1E3C',
                    }}
                  >
                    <option value="">— Seleccionar carrera —</option>

                    {deletableCareers.map((career) => (
                      <option key={career.code} value={career.code}>
                        {career.name}
                      </option>
                    ))}
                  </select>

                  <ChevronDown
                    size={14}
                    className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                    style={{
                      color: '#5A7295',
                    }}
                  />
                </div>
              </div>

              {deleteCareer && (
                <div
                  className="rounded-xl px-3 py-2.5 flex items-center gap-2"
                  style={{
                    background: '#FEE2E2',
                    border: '1px solid #DC262630',
                  }}
                >
                  <AlertCircle
                    size={13}
                    style={{
                      color: '#DC2626',
                      flexShrink: 0,
                    }}
                  />

                  <p
                    className="text-xs"
                    style={{
                      color: '#991B1B',
                    }}
                  >
                    La carrera se eliminará permanentemente de la base de datos.
                  </p>
                </div>
              )}

              <div className="flex gap-3 pt-1">
                <button
                  type="submit"
                  disabled={!deleteCareer || deletingCareer}
                  className="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all hover:opacity-90"
                  style={{
                    background: deleteCareer && !deletingCareer ? '#DC2626' : '#E5E7EB',
                    color: deleteCareer && !deletingCareer ? '#fff' : '#9CA3AF',
                    cursor: deleteCareer ? 'pointer' : 'not-allowed',
                  }}
                >
                  <span>{deletingCareer ? 'Eliminando...' : 'Eliminar'}</span>
                </button>

                <button
                  type="button"
                  onClick={closeDeleteCareerModal}
                  className="flex-1 py-2.5 rounded-xl text-sm font-bold border transition-all hover:bg-gray-50"
                  style={{
                    borderColor: 'rgba(27,58,107,0.2)',
                    color: '#5A7295',
                  }}
                >
                  Cancelar
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Modal: nueva carrera */}
      {showNewCareer && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center"
          style={{
            background: 'rgba(0,0,0,0.4)',
          }}
        >
          <div
            className="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4"
            style={{
              border: '1px solid rgba(27,58,107,0.12)',
            }}
          >
            <div
              className="flex items-center justify-between px-6 py-4 border-b"
              style={{
                borderColor: 'rgba(27,58,107,0.1)',
              }}
            >
              <h2
                className="text-base font-bold"
                style={{
                  fontFamily: "'Libre Baskerville',serif",
                  color: '#0F1E3C',
                }}
              >
                Nueva Carrera
              </h2>

              <button
                type="button"
                onClick={closeNewCareerModal}
                className="p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
              >
                <X
                  size={15}
                  style={{
                    color: '#5A7295',
                  }}
                />
              </button>
            </div>

            <form onSubmit={handleSaveCareer} className="px-6 py-5 space-y-4">
              <div>
                <label
                  className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                  style={{
                    color: '#5A7295',
                  }}
                >
                  Nombre de la carrera
                </label>

                <input
                  type="text"
                  value={newCareerName}
                  onChange={(event) => {
                    const nombre = event.target.value;

                    setNewCareerName(nombre);
                    setNewCareerCode(generarCodigoCarrera(nombre));
                  }}
                  placeholder="Ej: Administración de Empresas"
                  required
                  className="w-full px-3 py-2 rounded-xl text-sm border outline-none"
                  style={{
                    background: '#F4F7FB',
                    borderColor: 'rgba(27,58,107,0.2)',
                    color: '#0F1E3C',
                  }}
                />
              </div>
              <div>
                <label
                  className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                  style={{
                    color: '#5A7295',
                  }}
                >
                  Código institucional
                </label>

                <input
                  type="text"
                  value={newCareerCode}
                  onChange={(event) =>
                    setNewCareerCode(event.target.value.toUpperCase().replace(/\s+/g, ''))
                  }
                  placeholder="Ej: DESSOF"
                  required
                  maxLength={15}
                  className="w-full px-3 py-2 rounded-xl text-sm border outline-none"
                  style={{
                    background: '#F4F7FB',
                    borderColor: 'rgba(27,58,107,0.2)',
                    color: '#0F1E3C',
                  }}
                />

                <p
                  className="text-xs mt-1"
                  style={{
                    color: '#9CA3AF',
                  }}
                >
                  Se guardará en mayúsculas y sin espacios.
                </p>
              </div>

              <div>
                <label
                  className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                  style={{
                    color: '#5A7295',
                  }}
                >
                  Área de conocimiento
                </label>

                <div className="relative">
                  <select
                    value={newCareerArea}
                    onChange={(event) => setNewCareerArea(event.target.value)}
                    required
                    className="w-full px-3 py-2 rounded-xl text-sm border outline-none appearance-none cursor-pointer"
                    style={{
                      background: '#F4F7FB',
                      borderColor: 'rgba(27,58,107,0.2)',
                      color: '#0F1E3C',
                    }}
                  >
                    <option value="">— Seleccionar área —</option>

                    {areas.map((area) => (
                      <option key={area.name} value={area.name}>
                        {area.name}
                      </option>
                    ))}
                  </select>

                  <ChevronDown
                    size={14}
                    className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                    style={{
                      color: '#5A7295',
                    }}
                  />
                </div>
              </div>
              <div>
                <label
                  className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                  style={{
                    color: '#5A7295',
                  }}
                >
                  Modalidad
                </label>

                <div className="relative">
                  <select
                    value={newCareerModalidad}
                    onChange={(event) => setNewCareerModalidad(event.target.value)}
                    required
                    className="w-full px-3 py-2 rounded-xl text-sm border outline-none appearance-none cursor-pointer"
                    style={{
                      background: '#F4F7FB',
                      borderColor: 'rgba(27,58,107,0.2)',
                      color: '#0F1E3C',
                    }}
                  >
                    <option value="">— Seleccionar modalidad —</option>

                    <option value="Presencial">Presencial</option>

                    <option value="En línea">En línea</option>

                    <option value="Híbrida">Híbrida</option>
                  </select>

                  <ChevronDown
                    size={14}
                    className="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"
                    style={{
                      color: '#5A7295',
                    }}
                  />
                </div>
              </div>
              <div>
                <label
                  className="block text-xs font-bold uppercase tracking-widest mb-1.5"
                  style={{
                    color: '#5A7295',
                  }}
                >
                  Malla Curricular (PDF) *
                </label>

                <div className="flex items-center gap-3">
                  <button
                    type="button"
                    onClick={() => fileRef.current?.click()}
                    className="flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-semibold border transition-all hover:bg-blue-50"
                    style={{
                      borderColor: 'rgba(27,58,107,0.2)',
                      color: '#1B3A6B',
                    }}
                  >
                    <Upload size={12} />

                    {newCareerFile ? 'Cambiar archivo' : 'Subir PDF'}
                  </button>

                  {newCareerFile && (
                    <span
                      className="text-xs truncate max-w-40"
                      style={{
                        color: '#16A34A',
                      }}
                    >
                      {newCareerFile.name}
                    </span>
                  )}
                </div>

                <input
                  ref={fileRef}
                  type="file"
                  accept=".pdf,application/pdf"
                  className="hidden"
                  onChange={(event) => setNewCareerFile(event.target.files?.[0] ?? null)}
                />
              </div>

              <div className="flex gap-3 pt-1">
                <button
                  type="submit"
                  disabled={savingCareer}
                  className="flex-1 py-2.5 rounded-xl text-sm font-bold transition-all hover:opacity-90 disabled:opacity-60"
                  style={{
                    background: '#1B3A6B',
                    color: '#fff',
                    cursor: savingCareer ? 'not-allowed' : 'pointer',
                  }}
                >
                  {savingCareer ? 'Guardando...' : 'Guardar'}
                </button>

                <button
                  type="button"
                  onClick={closeNewCareerModal}
                  className="flex-1 py-2.5 rounded-xl text-sm font-bold border transition-all hover:bg-gray-50"
                  style={{
                    borderColor: 'rgba(27,58,107,0.2)',
                    color: '#5A7295',
                  }}
                >
                  Cancelar
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Barra superior */}
      <div
        className="flex-shrink-0 border-b flex items-center justify-between px-6"
        style={{
          height: 52,
          background: '#fff',
          borderColor: 'rgba(27,58,107,0.1)',
        }}
      >
        <div className="flex items-center gap-3">
          <div
            className="w-7 h-7 rounded-lg flex items-center justify-center"
            style={{
              background: '#1B3A6B',
            }}
          >
            <ShieldCheck size={13} className="text-white" />
          </div>

          <span
            className="font-bold text-sm"
            style={{
              color: '#0F1E3C',
            }}
          >
            CACES · UAFTT
          </span>

          <span
            className="hidden sm:inline text-xs"
            style={{
              color: '#9CA3AF',
            }}
          >
            — Sistema de Evaluación Institucional
          </span>
        </div>

        <div className="flex items-center gap-3">
          {usuario.rol === 'administrador' && (
            <div className="relative">
              <button
                type="button"
                onClick={() => setShowManageMenu(!showManageMenu)}
                className="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl text-xs font-bold transition-all hover:opacity-90"
                style={{
                  background: '#1B3A6B',
                  color: '#fff',
                }}
              >
                <TableProperties size={12} />
                Gestionar
                <ChevronDown
                  size={11}
                  className={`transition-transform ${showManageMenu ? 'rotate-180' : ''}`}
                />
              </button>

              {showManageMenu && (
                <div
                  className="absolute right-0 mt-1 w-48 bg-white rounded-xl shadow-lg overflow-hidden z-50"
                  style={{
                    border: '1px solid rgba(27,58,107,0.12)',
                  }}
                >
                  <button
                    type="button"
                    onClick={() => {
                      setShowManageMenu(false);
                      setShowNewCareer(true);
                    }}
                    className="w-full text-left px-4 py-2.5 text-xs font-semibold hover:bg-blue-50 transition-colors flex items-center gap-2"
                    style={{
                      color: '#1B3A6B',
                    }}
                  >
                    <Plus size={13} />
                    Nueva carrera
                  </button>

                  <div
                    style={{
                      height: 1,
                      background: 'rgba(27,58,107,0.07)',
                    }}
                  />

                  <button
                    type="button"
                    onClick={() => {
                      setShowManageMenu(false);
                      setShowEditCareer(true);
                    }}
                    className="w-full text-left px-4 py-2.5 text-xs font-semibold hover:bg-blue-50 transition-colors flex items-center gap-2"
                    style={{
                      color: '#1B3A6B',
                    }}
                  >
                    <Pencil size={13} />
                    Editar carrera
                  </button>

                  <div
                    style={{
                      height: 1,
                      background: 'rgba(27,58,107,0.07)',
                    }}
                  />

                  <button
                    type="button"
                    onClick={() => {
                      setShowManageMenu(false);
                      setShowCohortsManagement(true);
                    }}
                    className="w-full text-left px-4 py-2.5 text-xs font-semibold hover:bg-blue-50 transition-colors flex items-center gap-2"
                    style={{
                      color: '#1B3A6B',
                    }}
                  >
                    <CalendarDays size={13} />
                    Gestionar cohortes
                  </button>

                  <div
                    style={{
                      height: 1,
                      background: 'rgba(27,58,107,0.07)',
                    }}
                  />

                  <button
                    type="button"
                    onClick={() => {
                      setShowManageMenu(false);
                      setShowUsersManagement(true);
                    }}
                    className="w-full text-left px-4 py-2.5 text-xs font-semibold hover:bg-blue-50 transition-colors flex items-center gap-2"
                    style={{
                      color: '#1B3A6B',
                    }}
                  >
                    <Users size={13} />
                    Gestionar usuarios
                  </button>

                  <div
                    style={{
                      height: 1,
                      background: 'rgba(27,58,107,0.07)',
                    }}
                  />

                  <button
                    type="button"
                    onClick={() => {
                      setShowManageMenu(false);
                      setShowDeleteCareer(true);
                    }}
                    className="w-full text-left px-4 py-2.5 text-xs font-semibold hover:bg-red-50 transition-colors flex items-center gap-2"
                    style={{
                      color: '#DC2626',
                    }}
                  >
                    <X size={13} />
                    Eliminar carrera
                  </button>
                </div>
              )}
            </div>
          )}

          <div
            className="hidden sm:flex items-center gap-2 text-xs"
            style={{
              color: '#5A7295',
            }}
          >
            <User size={13} />
            <div className="leading-tight text-right">
              <p className="font-semibold" style={{ color: '#0F1E3C' }}>
                {usuario.nombres} {usuario.apellidos}
              </p>
              <p className="capitalize" style={{ color: '#5A7295', fontSize: 10 }}>
                {usuario.rol}
              </p>
            </div>
          </div>

          <button
            type="button"
            onClick={onLogout}
            className="flex items-center gap-1 text-xs font-medium px-3 py-1.5 rounded-lg hover:bg-red-50 hover:text-red-600 transition-colors"
            style={{
              color: '#5A7295',
            }}
          >
            <LogOut size={12} />
            Salir
          </button>
        </div>
      </div>

      <div
        className="flex-shrink-0"
        style={{
          height: 12,
        }}
      />

      {/* Áreas y carreras */}
      {loadingCareers ? (
        <div className="flex-1 flex items-center justify-center">
          <p className="text-sm font-semibold" style={{ color: '#5A7295' }}>
            Cargando carreras...
          </p>
        </div>
      ) : careersError ? (
        <div className="flex-1 flex flex-col items-center justify-center gap-3">
          <AlertCircle size={28} style={{ color: '#DC2626' }} />
          <p className="text-sm text-center" style={{ color: '#DC2626' }}>
            {careersError}
          </p>
          <button
            type="button"
            onClick={() => void cargarCarreras()}
            className="px-4 py-2 rounded-xl text-xs font-bold"
            style={{ background: '#1B3A6B', color: '#fff' }}
          >
            Reintentar
          </button>
        </div>
      ) : (
        <div className="flex-1 min-h-0 px-6 pb-5 grid grid-cols-3 gap-4 overflow-hidden">
          {areas.map((area) => (
            <div
              key={area.name}
              className="bg-white rounded-xl overflow-hidden flex flex-col border"
              style={{
                borderColor: 'rgba(27,58,107,0.1)',
                boxShadow: '0 1px 8px rgba(0,0,0,0.05)',
              }}
            >
              <div
                className="relative flex-shrink-0"
                style={{
                  height: 110,
                }}
              >
                <img src={area.image} alt={area.name} className="w-full h-full object-cover" />

                <div
                  className="absolute inset-0"
                  style={{
                    background: 'linear-gradient(to bottom,rgba(15,30,60,0.1),rgba(15,30,60,0.7))',
                  }}
                />

                <div className="absolute bottom-0 left-0 right-0 px-3 pb-2 flex items-center gap-2">
                  <CompassIcon size={28} />

                  <h2
                    className="text-xs font-bold text-white leading-tight"
                    style={{
                      fontFamily: "'Libre Baskerville',serif",
                    }}
                  >
                    {area.name}
                  </h2>
                </div>
              </div>

              <div
                style={{
                  height: 1,
                  background: 'rgba(27,58,107,0.08)',
                }}
              />

              <div className="flex-1 flex flex-col justify-start overflow-hidden">
                {area.careers.map((career, index) => (
                  <div
                    key={career.name}
                    onClick={() => {
                      if (career.clickable) {
                        onSelect(career);
                      }
                    }}
                    className={`flex items-center justify-between px-3 transition-colors ${
                      career.clickable ? 'cursor-pointer hover:bg-blue-50 group' : 'cursor-default'
                    }`}
                    style={{
                      borderBottom:
                        index < area.careers.length - 1 ? '1px solid rgba(27,58,107,0.06)' : 'none',
                      minHeight: 34,
                      paddingTop: 5,
                      paddingBottom: 5,
                    }}
                  >
                    <span
                      className={`text-xs leading-tight ${
                        career.clickable ? 'font-semibold group-hover:text-blue-700' : ''
                      }`}
                      style={{
                        color: career.clickable ? '#1B3A6B' : '#374151',
                      }}
                    >
                      {career.name}
                    </span>

                    {career.clickable && (
                      <span
                        className="text-xs px-1.5 py-0.5 rounded font-semibold flex-shrink-0 ml-2"
                        style={{
                          background: '#DBEAFE',
                          color: '#1D4ED8',
                        }}
                      >
                        →
                      </span>
                    )}
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}

      {showManageMenu && (
        <div className="fixed inset-0 z-40" onClick={() => setShowManageMenu(false)} />
      )}
    </div>
  );
}
