import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';

import StepConfigSyllabus from './StepConfigSyllabus';

import type { Career, IndicatorDef } from '../../../types/index';

/**
 * Cubre la Parte "3.5" del plan de malla curricular xlsx (ver
 * plan_malla_curricular_xlsx.txt §3.5 y MEMORIA_v117.md §93): antes de este
 * cambio, el dropdown de cohorte y el selector de materia de este componente
 * usaban los mocks fijos `COHORT_OPTIONS`/`MATERIAS_BY_PAO_MODULE` de
 * `shared/data/academic.ts` -- los mismos para cualquier carrera. Ahora
 * salen de `listarCohortesEvaluaciones()` (filtrado por `career.code`) y de
 * `obtenerPeriodos()`/`obtenerAsignaturas()` (filtrado por `modulo`).
 *
 * Se mockea `fetch` global (mismo patrón que carreras.test.ts), no las
 * funciones del service, para no acoplar el test a la forma interna de cada
 * wrapper -- solo a las URLs reales que ya usan.
 */

const carrera: Career = { name: 'Desarrollo de Software', code: 'DES', criterionNum: 2, clickable: true };

const indicador: IndicatorDef = {
  id: 'I2',
  num: 2,
  code: 'I2',
  name: 'Seguimiento de Syllabus',
  description: '',
  formula: '',
  period: '',
  purpose: '',
  slots: [],
  cohorts: [],
};

const cohortesResponse = {
  ok: true,
  datos: [
    {
      id_cohorte: 1,
      nombre_cohorte: 'B2025',
      fecha_inicio: null,
      fecha_fin: null,
      id_carrera: 5,
      carrera: 'Desarrollo de Software',
      codigo_carrera: 'DES',
      id_evaluacion: 10,
      nombre_evaluacion: null,
      estado: 'Activa',
    },
    // Cohorte de OTRA carrera -- no debe aparecer en el dropdown de esta.
    {
      id_cohorte: 2,
      nombre_cohorte: 'A2026',
      fecha_inicio: null,
      fecha_fin: null,
      id_carrera: 10,
      carrera: 'Ingeniería en Arte',
      codigo_carrera: 'INTART',
      id_evaluacion: 11,
      nombre_evaluacion: null,
      estado: 'Activa',
    },
  ],
};

const periodosResponse = {
  ok: true,
  datos: [
    { id_periodoacademico: 100, nombre: 'PAO 1', orden: 1, fecha_inicio: null, fecha_fin: null },
    { id_periodoacademico: 101, nombre: 'PAO 2', orden: 2, fecha_inicio: null, fecha_fin: null },
  ],
};

const asignaturasResponse = {
  ok: true,
  datos: [
    { id_asignatura: 1, nombre: 'Cultura tecnológica y digital', docente: null, modulo: 'A' },
    { id_asignatura: 2, nombre: 'Humanismo y Persona', docente: null, modulo: 'A' },
    { id_asignatura: 3, nombre: 'Fundamentos de Programación y Algoritmos', docente: null, modulo: 'B' },
  ],
};

function mockFetchSecuencial() {
  return vi.fn((url: string) => {
    const responder = (cuerpo: unknown) =>
      Promise.resolve({
        ok: true,
        json: () => Promise.resolve(cuerpo),
      } as Response);

    if (url.includes('/administracion/cohortes/listar')) return responder(cohortesResponse);
    if (url.includes('/periodos?id_cohorte=')) return responder(periodosResponse);
    if (url.includes('/asignaturas?id_periodo=')) return responder(asignaturasResponse);

    return Promise.reject(new Error(`URL no mockeada en el test: ${url}`));
  });
}

/** Harness controlado: mismo patrón que EvidenceUploadView, con estado real. */
function Harness() {
  const [cohort, setCohort] = useState('');
  const [pao, setPao] = useState('');
  const [module, setModule] = useState('');
  const [materia, setMateria] = useState('');

  return (
    <StepConfigSyllabus
      career={carrera}
      indicator={indicador}
      onBack={() => {}}
      onBackToSelectIndicator={() => {}}
      cohort={cohort}
      setCohort={setCohort}
      pao={pao}
      setPao={setPao}
      module={module}
      setModule={setModule}
      materia={materia}
      setMateria={setMateria}
      onContinue={() => {}}
    />
  );
}

describe('StepConfigSyllabus — cohortes y materias reales por carrera (Parte 3.5)', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', mockFetchSecuencial());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('lista solo las cohortes de la carrera actual (filtra por codigo_carrera)', async () => {
    render(<Harness />);

    await waitFor(() => {
      expect(screen.getByRole('option', { name: 'Cohorte B2025' })).toBeInTheDocument();
    });

    expect(screen.queryByRole('option', { name: /A2026/ })).not.toBeInTheDocument();
  });

  it('al elegir cohorte + PAO + módulo, muestra solo las materias reales de ese módulo', async () => {
    const usuario = userEvent.setup();
    render(<Harness />);

    await waitFor(() => {
      expect(screen.getByRole('option', { name: 'Cohorte B2025' })).toBeInTheDocument();
    });

    await usuario.selectOptions(screen.getByDisplayValue('— Seleccionar cohorte —'), 'B2025');
    await usuario.click(screen.getByRole('button', { name: 'PAO 1' }));
    await usuario.click(screen.getByRole('button', { name: 'A' }));

    await waitFor(() => {
      expect(screen.getByRole('option', { name: 'Cultura tecnológica y digital' })).toBeInTheDocument();
      expect(screen.getByRole('option', { name: 'Humanismo y Persona' })).toBeInTheDocument();
    });

    // Módulo B no debe filtrarse acá -- solo A.
    expect(
      screen.queryByRole('option', { name: 'Fundamentos de Programación y Algoritmos' }),
    ).not.toBeInTheDocument();
  });

  it('vacía las materias si se cambia de módulo (evita mostrar materias del módulo anterior)', async () => {
    const usuario = userEvent.setup();
    render(<Harness />);

    await waitFor(() => {
      expect(screen.getByRole('option', { name: 'Cohorte B2025' })).toBeInTheDocument();
    });

    await usuario.selectOptions(screen.getByDisplayValue('— Seleccionar cohorte —'), 'B2025');
    await usuario.click(screen.getByRole('button', { name: 'PAO 1' }));
    await usuario.click(screen.getByRole('button', { name: 'A' }));

    await waitFor(() => {
      expect(screen.getByRole('option', { name: 'Cultura tecnológica y digital' })).toBeInTheDocument();
    });

    await usuario.click(screen.getByRole('button', { name: 'B' }));

    await waitFor(() => {
      expect(screen.getByRole('option', { name: 'Fundamentos de Programación y Algoritmos' })).toBeInTheDocument();
    });
    expect(screen.queryByRole('option', { name: 'Cultura tecnológica y digital' })).not.toBeInTheDocument();
  });
});
