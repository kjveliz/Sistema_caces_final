import {
  CalendarDays,
  ChevronDown,
  LogOut,
  Pencil,
  Plus,
  ShieldCheck,
  TableProperties,
  User,
  Users,
  X,
} from 'lucide-react';

import type { UsuarioSesion } from '../../../shared/services/auth';

export default function TopBar({
  usuario,
  onLogout,
  showManageMenu,
  onToggleManageMenu,
  onCloseManageMenu,
  onNewCareer,
  onEditCareer,
  onManageCohorts,
  onManageUsers,
  onDeleteCareer,
}: {
  usuario: UsuarioSesion;
  onLogout: () => void;
  showManageMenu: boolean;
  onToggleManageMenu: () => void;
  onCloseManageMenu: () => void;
  onNewCareer: () => void;
  onEditCareer: () => void;
  onManageCohorts: () => void;
  onManageUsers: () => void;
  onDeleteCareer: () => void;
}) {
  return (
    <>
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
                onClick={onToggleManageMenu}
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
                    onClick={onNewCareer}
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
                    onClick={onEditCareer}
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
                    onClick={onManageCohorts}
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
                    onClick={onManageUsers}
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
                    onClick={onDeleteCareer}
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

      {showManageMenu && (
        <div className="fixed inset-0 z-40" onClick={onCloseManageMenu} />
      )}
    </>
  );
}
