import { useEffect, useState } from 'react';
import { Navigate, Outlet, useLocation, useParams } from 'react-router';

import { resolverCarreraPorCodigo } from '../shared/services/carreras';
import { listarCohortesEvaluaciones } from '../shared/services/cohortes';
import { makeIndicators } from '../shared/data/indicatorDefinitions';
import { CareerContext } from '../contexts/CareerContext';

import type { Career, IndicatorDef } from '../types/index';

/**
 * Layout de las rutas anidadas bajo `/carreras/:code/...`. Resuelve el
 * `Career` completo a partir del código en la URL: si viene de
 * CareersView (que ya tiene el objeto completo) lo toma de
 * `location.state.career` para no pegarle a la API de nuevo; si no
 * (entrada directa por URL o refresh de página), lo resuelve contra el
 * backend real vía `resolverCarreraPorCodigo`.
 */
export default function CareerLayout() {
  const { code } = useParams<{ code: string }>();
  const location = useLocation();

  const careerDeNavegacion = (location.state as { career?: Career } | null)?.career;

  const [career, setCareer] = useState<Career | null>(
    careerDeNavegacion && careerDeNavegacion.code === code ? careerDeNavegacion : null,
  );
  const [indicators, setIndicators] = useState<IndicatorDef[]>(
    career ? makeIndicators(career) : [],
  );
  const [cargandoCareer, setCargandoCareer] = useState(!career);
  const [errorCareer, setErrorCareer] = useState('');

  /*
   * Antes arrancaba hardcodeada en 'B 2025' (mock original de Desarrollo de
   * Software), sin relación con la carrera real ni con sus cohortes reales.
   * Eso hacía que cualquier carrera nueva (p. ej. Carrera de Prueba Parte F)
   * mostrara por defecto una cohorte que no le pertenece, y que ese valor
   * "fantasma" volviera a aparecer en cada recarga porque nunca se
   * reemplazaba por uno real. Ahora arranca vacía y el efecto de abajo la
   * completa con la cohorte real de la carrera (la creada primero) en
   * cuanto llega la respuesta del backend.
   */
  const [selectedCohort, setSelectedCohortState] = useState('');
  const [selectedPAO, setSelectedPAO] = useState(1);

  useEffect(() => {
    if (!code) {
      return;
    }

    if (career && career.code === code) {
      return;
    }

    let cancelado = false;
    setCargandoCareer(true);
    setErrorCareer('');

    resolverCarreraPorCodigo(code)
      .then((carreraResuelta) => {
        if (cancelado) {
          return;
        }

        if (!carreraResuelta) {
          setErrorCareer('No se encontró una carrera con ese código.');
          return;
        }

        setCareer(carreraResuelta);
        setIndicators(makeIndicators(carreraResuelta));
      })
      .catch((error: unknown) => {
        if (!cancelado) {
          setErrorCareer(
            error instanceof Error ? error.message : 'No se pudo cargar la carrera.',
          );
        }
      })
      .finally(() => {
        if (!cancelado) {
          setCargandoCareer(false);
        }
      });

    return () => {
      cancelado = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [code]);

  /*
   * Resuelve la cohorte real por defecto para la carrera actual: la primera
   * creada (menor id_cohorte) entre las cohortes activas de esa carrera. Si
   * la carrera todavía no tiene ninguna cohorte, deja selectedCohort vacío
   * — DashboardView interpreta ese vacío como "No hay cohortes creadas" en
   * vez de inventar un nombre. Corre cada vez que cambia la carrera
   * resuelta, para no arrastrar la cohorte de una carrera anterior.
   */
  useEffect(() => {
    if (!career) {
      return;
    }

    let cancelado = false;

    listarCohortesEvaluaciones()
      .then((cohortes) => {
        if (cancelado) {
          return;
        }

        const cohortesDeLaCarrera = cohortes
          .filter((c) => c.codigo_carrera === career.code && c.estado === 'Activa')
          .sort((a, b) => a.id_cohorte - b.id_cohorte);

        setSelectedCohortState(
          cohortesDeLaCarrera.length > 0 ? cohortesDeLaCarrera[0].nombre_cohorte : '',
        );
      })
      .catch((error: unknown) => {
        console.error('No se pudieron cargar las cohortes de la carrera:', error);

        if (!cancelado) {
          setSelectedCohortState('');
        }
      });

    return () => {
      cancelado = true;
    };
  }, [career]);

  function onCohortChange(cohort: string) {
    if (cohort === selectedCohort) {
      return;
    }

    setSelectedCohortState(cohort);
    setSelectedPAO(1);

    /*
     * Limpia de la interfaz los archivos, resultados y relaciones
     * correspondientes a la cohorte anterior (mismo comportamiento que el
     * handleCohortChange original de App.tsx).
     */
    if (career) {
      setIndicators(makeIndicators(career));
    }
  }

  if (errorCareer) {
    return (
      <div className="flex h-screen items-center justify-center text-center p-6">
        <div>
          <p className="text-red-600 font-semibold mb-2">{errorCareer}</p>
          <Navigate to="/carreras" replace />
        </div>
      </div>
    );
  }

  if (cargandoCareer || !career) {
    return <div className="flex h-screen items-center justify-center">Cargando carrera…</div>;
  }

  return (
    <CareerContext.Provider
      value={{
        career,
        indicators,
        setIndicators,
        selectedCohort,
        onCohortChange,
        selectedPAO,
        setSelectedPAO,
      }}
    >
      <Outlet />
    </CareerContext.Provider>
  );
}
