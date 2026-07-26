import { Toaster } from 'sonner';
import { Loader2 } from 'lucide-react';
import { Navigate, Route, Routes, useNavigate, useParams } from 'react-router';

import { obtenerEvaluacion, obtenerDatosTasa, obtenerDatosDesercion } from '../shared/services/evidencias';
import { useEffect } from 'react';
import type { ReactNode } from 'react';

import EvidenceUploadView from '../features/indicadores/EvidenceUploadView';
import DashboardView from '../features/dashboard/DashboardView';
import IndicatorView from '../features/indicadores/IndicatorView';
import CriteriaView from '../features/carreras/CriteriaView';
import CareersView from '../features/carreras/CareersView';
import LoginView from '../features/auth/LoginView';

import { useAuth } from '../contexts/AuthContext';
import { useCareerContext } from '../contexts/CareerContext';
import CareerLayout from './CareerLayout';

import type { Career } from '../types/index';

// ── Rutas de nivel raíz (auth) ────────────────────────────────

function RequireAuth({ children }: { children: ReactNode }) {
  const { usuario } = useAuth();

  if (!usuario) {
    return <Navigate to="/login" replace />;
  }

  return <>{children}</>;
}

function LoginRoute() {
  const { login } = useAuth();
  const navigate = useNavigate();

  return (
    <LoginView
      onLogin={(usuarioAutenticado) => {
        login(usuarioAutenticado);
        navigate('/carreras', { replace: true });
      }}
    />
  );
}

function CareersRoute() {
  const { usuario, logout } = useAuth();
  const navigate = useNavigate();

  if (!usuario) {
    return <Navigate to="/login" replace />;
  }

  function selectCareer(career: Career) {
    // Pasa el Career ya resuelto por navigate state, para que CareerLayout
    // no tenga que volver a pedirlo a la API (ver CareerLayout.tsx).
    navigate(`/carreras/${career.code}/criterios`, { state: { career } });
  }

  return (
    <CareersView
      onSelect={selectCareer}
      onLogout={() => {
        logout();
        navigate('/login', { replace: true });
      }}
      usuario={usuario}
    />
  );
}

// ── Rutas anidadas bajo /carreras/:code (dependen de CareerLayout) ────

function CriteriaRoute() {
  const { career } = useCareerContext();
  const { logout } = useAuth();
  const navigate = useNavigate();

  return (
    <CriteriaView
      career={career}
      onSelectDocencia={() => navigate(`/carreras/${career.code}/dashboard`)}
      onBack={() => navigate('/carreras', { replace: true })}
      onLogout={() => {
        logout();
        navigate('/login', { replace: true });
      }}
    />
  );
}

function DashboardRoute() {
  const { career, indicators, setIndicators, selectedCohort, onCohortChange, setSelectedPAO } =
    useCareerContext();
  const { usuario, puedeCargar, logout } = useAuth();
  const navigate = useNavigate();

  // Carga los datos reales de I4/I5 para el dashboard (matriculados/
  // graduados/desertores de la cohorte seleccionada). Antes vivía en un
  // useEffect de App.tsx gateado por `view === 'dashboard'`; ahora, al
  // vivir en el propio componente de la ruta, ese gateo ya lo da el router.
  useEffect(() => {
    let cancelado = false;

    async function cargarResultadosDashboard() {
      try {
        const cohorteNormalizada = selectedCohort.replace(/\s+/g, '').toUpperCase();

        const evaluacion = await obtenerEvaluacion(career.code, cohorteNormalizada);

        const [datosTitulacion, datosDesercion] = await Promise.all([
          obtenerDatosTasa(evaluacion.id_evaluacion),
          obtenerDatosDesercion(evaluacion.id_evaluacion),
        ]);

        if (cancelado) {
          return;
        }

        const registroTitulacion = datosTitulacion.find(
          (dato) => dato.cohorte.replace(/\s+/g, '').toUpperCase() === cohorteNormalizada,
        );

        const registroDesercion = datosDesercion.find(
          (dato) => dato.cohorte.replace(/\s+/g, '').toUpperCase() === cohorteNormalizada,
        );

        setIndicators((actuales) =>
          actuales.map((indicator) => {
            if (indicator.id === 'I5') {
              if (!registroTitulacion) {
                return { ...indicator, cohorts: [] };
              }

              return {
                ...indicator,
                cohorts: [
                  {
                    period: selectedCohort,
                    enrolled: Number(registroTitulacion.matriculados) || 0,
                    graduated: Number(registroTitulacion.graduados) || 0,
                  },
                ],
              };
            }

            if (indicator.id === 'I4') {
              if (
                !registroDesercion ||
                registroDesercion.iniciaron_primer_nivel === null ||
                registroDesercion.no_continuaron === null
              ) {
                return { ...indicator, cohorts: [] };
              }

              /*
               * calcRate() calcula:
               * graduated / enrolled × 100
               *
               * Para I4 se reutilizan esos campos así:
               * enrolled   = iniciaron en primer nivel
               * graduated  = no continuaron
               */
              return {
                ...indicator,
                cohorts: [
                  {
                    period: selectedCohort,
                    enrolled: Number(registroDesercion.iniciaron_primer_nivel) || 0,
                    graduated: Number(registroDesercion.no_continuaron) || 0,
                  },
                ],
              };
            }

            return indicator;
          }),
        );
      } catch (error) {
        console.error('No se pudieron cargar los resultados del dashboard:', error);

        if (!cancelado) {
          setIndicators((actuales) =>
            actuales.map((indicator) =>
              indicator.id === 'I4' || indicator.id === 'I5'
                ? { ...indicator, cohorts: [] }
                : indicator,
            ),
          );
        }
      }
    }

    void cargarResultadosDashboard();

    return () => {
      cancelado = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [career, selectedCohort]);

  return (
    <DashboardView
      indicators={indicators}
      career={career}
      cohort={selectedCohort}
      onCohortChange={onCohortChange}
      onSelect={(id, pao) => {
        if (pao !== undefined) {
          setSelectedPAO(pao);
        }

        navigate(`/carreras/${career.code}/indicadores/${id}`);
      }}
      onLogout={() => {
        logout();
        navigate('/login', { replace: true });
      }}
      onUpload={() => {
        if (!puedeCargar) {
          return;
        }

        navigate(`/carreras/${career.code}/evidencias`);
      }}
      onBackToCareers={() => navigate('/carreras', { replace: true })}
      usuario={usuario!}
      puedeCargar={Boolean(puedeCargar)}
    />
  );
}

function IndicatorRoute() {
  const { career, indicators, selectedCohort, selectedPAO } = useCareerContext();
  const { puedeCargar } = useAuth();
  const navigate = useNavigate();
  const { codigo } = useParams<{ codigo: string }>();

  const selected = indicators.find((indicator) => indicator.id === codigo);

  if (!selected) {
    return <Navigate to={`/carreras/${career.code}/dashboard`} replace />;
  }

  return (
    <IndicatorView
      indicator={selected}
      onBack={() => navigate(`/carreras/${career.code}/dashboard`, { replace: true })}
      career={career}
      cohort={selectedCohort}
      pao={selectedPAO}
      onUpload={() => {
        if (!puedeCargar) {
          return;
        }

        navigate(`/carreras/${career.code}/indicadores/${selected.id}/evidencias`);
      }}
      puedeCargar={Boolean(puedeCargar)}
    />
  );
}

function EvidenceUploadRoute() {
  const { career, indicators, setIndicators, selectedCohort } = useCareerContext();
  const { puedeCargar } = useAuth();
  const navigate = useNavigate();
  const { codigo } = useParams<{ codigo?: string }>();

  if (!puedeCargar) {
    return <Navigate to={`/carreras/${career.code}/dashboard`} replace />;
  }

  return (
    <EvidenceUploadView
      career={career}
      indicators={indicators}
      onChange={setIndicators}
      onBack={() =>
        navigate(
          codigo
            ? `/carreras/${career.code}/indicadores/${codigo}`
            : `/carreras/${career.code}/dashboard`,
          { replace: true },
        )
      }
      preselectedCohort={selectedCohort}
      preselectedIndicatorId={codigo}
    />
  );
}

// ── App ──────────────────────────────────────────────────────

export default function App() {
  const { usuario, cargando } = useAuth();

  // Mientras se resuelve si la cookie de sesión existente corresponde a un
  // usuario logueado (ver AuthContext), no se sabe todavía si mandar a
  // /carreras o a /login. Esperar acá, en vez de asumir "no hay usuario",
  // es lo que evita que un refresh de página expulse al login de entrada.
  if (cargando) {
    return (
      <div className="min-h-screen flex items-center justify-center gap-2 text-gray-500">
        <Loader2 size={20} className="animate-spin" />
        Cargando sesión…
      </div>
    );
  }

  return (
    <>
      <Toaster richColors position="bottom-right" />

      <Routes>
        <Route path="/login" element={<LoginRoute />} />

        <Route
          path="/carreras"
          element={
            <RequireAuth>
              <CareersRoute />
            </RequireAuth>
          }
        />

        <Route
          path="/carreras/:code"
          element={
            <RequireAuth>
              <CareerLayout />
            </RequireAuth>
          }
        >
          <Route path="criterios" element={<CriteriaRoute />} />
          <Route path="dashboard" element={<DashboardRoute />} />
          <Route path="evidencias" element={<EvidenceUploadRoute />} />
          <Route path="indicadores/:codigo" element={<IndicatorRoute />} />
          <Route path="indicadores/:codigo/evidencias" element={<EvidenceUploadRoute />} />
        </Route>

        <Route path="/" element={<Navigate to={usuario ? '/carreras' : '/login'} replace />} />
        <Route path="*" element={<Navigate to={usuario ? '/carreras' : '/login'} replace />} />
      </Routes>
    </>
  );
}
