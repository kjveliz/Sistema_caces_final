import { useState } from 'react';

import UsersManagementModal from './UsersManagementModal';
import CohortsManagementModal from './CohortsManagementModal';
import NewCareerModal from './NewCareerModal';
import EditCareerModal from './EditCareerModal';
import DeleteCareerModal from './DeleteCareerModal';
import StorageSettingsModal from './StorageSettingsModal';

import TopBar from './components/TopBar';
import AreasGrid from './components/AreasGrid';

import { useCareers } from './hooks/useCareers';
import { useNewCareerForm } from './hooks/useNewCareerForm';
import { useEditCareerForm } from './hooks/useEditCareerForm';
import { useDeleteCareerForm } from './hooks/useDeleteCareerForm';
import { useStorageSettingsForm } from './hooks/useStorageSettingsForm';

import type { Career } from '../../types/index';
import type { UsuarioSesion } from '../../shared/services/auth';

interface CareersViewProps {
  onSelect: (career: Career) => void;
  onLogout: () => void;
  usuario: UsuarioSesion;
}

export default function CareersView({ onSelect, onLogout, usuario }: CareersViewProps) {
  const { areas, carrerasBD, loadingCareers, careersError, cargarCarreras, carrerasAdministrables } =
    useCareers();

  const newCareerForm = useNewCareerForm(cargarCarreras);
  const editCareerForm = useEditCareerForm(carrerasBD, cargarCarreras);
  const deleteCareerForm = useDeleteCareerForm(areas, carrerasBD, cargarCarreras);
  const storageSettingsForm = useStorageSettingsForm(carrerasBD, cargarCarreras);

  const [showManageMenu, setShowManageMenu] = useState(false);
  const [showUsersManagement, setShowUsersManagement] = useState(false);
  const [showCohortsManagement, setShowCohortsManagement] = useState(false);

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

      <EditCareerModal
        open={editCareerForm.showEditCareer}
        onClose={editCareerForm.closeEditCareerModal}
        carrerasBD={carrerasBD}
        areas={areas}
        editCareerId={editCareerForm.editCareerId}
        onSelectCareer={editCareerForm.seleccionarCarreraParaEditar}
        editCareerName={editCareerForm.editCareerName}
        onNameChange={editCareerForm.setEditCareerName}
        editCareerCode={editCareerForm.editCareerCode}
        onCodeChange={editCareerForm.handleEditCareerCodeChange}
        editCareerArea={editCareerForm.editCareerArea}
        onAreaChange={editCareerForm.setEditCareerArea}
        editCareerModalidad={editCareerForm.editCareerModalidad}
        onModalidadChange={editCareerForm.setEditCareerModalidad}
        updatingCareer={editCareerForm.updatingCareer}
        onSubmit={editCareerForm.handleUpdateCareer}
      />

      <StorageSettingsModal
        open={storageSettingsForm.showStorageSettings}
        onClose={storageSettingsForm.closeStorageSettingsModal}
        carrerasBD={carrerasBD}
        storageCareerId={storageSettingsForm.storageCareerId}
        onSelectCareer={storageSettingsForm.seleccionarCarreraParaAlmacenamiento}
        storageModo={storageSettingsForm.storageModo}
        onModoChange={storageSettingsForm.setStorageModo}
        storageRutaLocal={storageSettingsForm.storageRutaLocal}
        onRutaLocalChange={storageSettingsForm.setStorageRutaLocal}
        carreraSeleccionada={storageSettingsForm.carreraSeleccionada}
        migrando={storageSettingsForm.migrando}
        onSubmit={storageSettingsForm.handleUpdateStorage}
      />

      <DeleteCareerModal
        open={deleteCareerForm.showDeleteCareer}
        onClose={deleteCareerForm.closeDeleteCareerModal}
        areas={areas}
        deleteArea={deleteCareerForm.deleteArea}
        onAreaChange={deleteCareerForm.handleDeleteAreaChange}
        deleteCareer={deleteCareerForm.deleteCareer}
        onCareerChange={deleteCareerForm.setDeleteCareer}
        deletableCareers={deleteCareerForm.deletableCareers}
        deletingCareer={deleteCareerForm.deletingCareer}
        onSubmit={deleteCareerForm.handleDeleteCareer}
        bloqueadaPorEvaluaciones={deleteCareerForm.bloqueadaPorEvaluaciones}
        confirmacionForzada={deleteCareerForm.confirmacionForzada}
        onConfirmacionForzadaChange={deleteCareerForm.setConfirmacionForzada}
        eliminandoForzado={deleteCareerForm.eliminandoForzado}
        onForcedDelete={deleteCareerForm.handleForcedDeleteCareer}
      />

      <NewCareerModal
        open={newCareerForm.showNewCareer}
        onClose={newCareerForm.closeNewCareerModal}
        areas={areas}
        newCareerName={newCareerForm.newCareerName}
        onNameChange={newCareerForm.handleNewCareerNameChange}
        newCareerCode={newCareerForm.newCareerCode}
        onCodeChange={newCareerForm.handleNewCareerCodeChange}
        newCareerArea={newCareerForm.newCareerArea}
        onAreaChange={newCareerForm.setNewCareerArea}
        newCareerModalidad={newCareerForm.newCareerModalidad}
        onModalidadChange={newCareerForm.setNewCareerModalidad}
        newCareerFile={newCareerForm.newCareerFile}
        onFileChange={newCareerForm.setNewCareerFile}
        fileRef={newCareerForm.fileRef}
        newCohorteNombre={newCareerForm.newCohorteNombre}
        onCohorteNombreChange={newCareerForm.setNewCohorteNombre}
        newCohorteFechaInicio={newCareerForm.newCohorteFechaInicio}
        onCohorteFechaInicioChange={newCareerForm.setNewCohorteFechaInicio}
        newCohorteFechaFin={newCareerForm.newCohorteFechaFin}
        onCohorteFechaFinChange={newCareerForm.setNewCohorteFechaFin}
        newCohorteEstado={newCareerForm.newCohorteEstado}
        onCohorteEstadoChange={newCareerForm.setNewCohorteEstado}
        savingCareer={newCareerForm.savingCareer}
        onSubmit={newCareerForm.handleSaveCareer}
      />

      <TopBar
        usuario={usuario}
        onLogout={onLogout}
        showManageMenu={showManageMenu}
        onToggleManageMenu={() => setShowManageMenu(!showManageMenu)}
        onCloseManageMenu={() => setShowManageMenu(false)}
        onNewCareer={() => {
          setShowManageMenu(false);
          newCareerForm.setShowNewCareer(true);
        }}
        onEditCareer={() => {
          setShowManageMenu(false);
          editCareerForm.setShowEditCareer(true);
        }}
        onManageCohorts={() => {
          setShowManageMenu(false);
          setShowCohortsManagement(true);
        }}
        onManageUsers={() => {
          setShowManageMenu(false);
          setShowUsersManagement(true);
        }}
        onDeleteCareer={() => {
          setShowManageMenu(false);
          deleteCareerForm.setShowDeleteCareer(true);
        }}
        onManageStorage={() => storageSettingsForm.setShowStorageSettings(true)}
      />

      <div
        className="flex-shrink-0"
        style={{
          height: 12,
        }}
      />

      <AreasGrid
        areas={areas}
        loadingCareers={loadingCareers}
        careersError={careersError}
        onRetry={() => void cargarCarreras()}
        onSelect={onSelect}
      />
    </div>
  );
}
