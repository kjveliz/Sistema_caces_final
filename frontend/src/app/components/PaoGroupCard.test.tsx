import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import PaoGroupCard from './PaoGroupCard';

import type { IndicatorDef } from '../../types';

/**
 * PaoGroupCard es la tarjeta con 3 PAOs (usada en I1/I2/I3) que compone
 * Ring + SemLight + badge de estado por cada PAO. Sus datos vienen, en
 * orden de prioridad: `paosOverride` (I2 desde DashboardView) > caso
 * especial de I1 (ver más abajo) > `PAO_SCORES[ind.id]`.
 *
 * Hallazgo real explorando el componente (confirmado con el usuario antes
 * de escribir estos tests): cuando `ind.id === 'I1'` y no hay
 * `paosOverride`, el componente IGNORA `PAO_SCORES.I1` (que sí tiene datos
 * reales: 92%, 78%, 0%) y fuerza los 3 PAO a "Sin datos" (`pct: -1`
 * hardcodeado). Es intencional en el código tal cual está hoy, no un typo
 * de test -- por eso se documenta explícitamente.
 */

function crearIndicador(overrides: Partial<IndicatorDef> = {}): IndicatorDef {
  return {
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
    ...overrides,
  };
}

describe('PaoGroupCard', () => {
  it('muestra el código y el nombre del indicador, y un botón por cada PAO de paosOverride', () => {
    render(
      <PaoGroupCard
        ind={crearIndicador()}
        onClick={vi.fn()}
        paosOverride={[
          { pao: 'PAO 1', pct: 85 },
          { pao: 'PAO 2', pct: 72 },
        ]}
      />,
    );

    expect(screen.getByText('I2')).toBeInTheDocument();
    expect(screen.getByText('Seguimiento de Syllabus')).toBeInTheDocument();
    expect(screen.getAllByRole('button')).toHaveLength(2);
    expect(screen.getByText('PAO 1')).toBeInTheDocument();
    expect(screen.getByText('PAO 2')).toBeInTheDocument();
    expect(screen.getByText('85%')).toBeInTheDocument();
    expect(screen.getByText('72%')).toBeInTheDocument();
  });

  it('dispara onClick(ind.id, índice del PAO + 1) al hacer click en un PAO', async () => {
    const onClick = vi.fn();
    const user = userEvent.setup();
    render(
      <PaoGroupCard
        ind={crearIndicador({ id: 'I3', code: 'I3' })}
        onClick={onClick}
        paosOverride={[
          { pao: 'PAO 1', pct: 90 },
          { pao: 'PAO 2', pct: 65 },
        ]}
      />,
    );

    await user.click(screen.getByText('PAO 2').closest('button')!);
    expect(onClick).toHaveBeenCalledTimes(1);
    expect(onClick).toHaveBeenCalledWith('I3', 2);
  });

  it('con pct negativo (sin datos), muestra el placeholder "—" en vez de Ring/SemLight, y el badge "Sin datos"', () => {
    render(
      <PaoGroupCard
        ind={crearIndicador()}
        onClick={vi.fn()}
        paosOverride={[{ pao: 'PAO 1', pct: -1 }]}
      />,
    );

    expect(screen.getByText('—')).toBeInTheDocument();
    expect(screen.getByText('Sin datos')).toBeInTheDocument();
    // Sin Ring/SemLight: no debe haber ningún <svg> (el de Ring) dentro del botón.
    expect(document.querySelector('svg')).not.toBeInTheDocument();
  });

  it('muestra el badge de estado correcto según el % de cada PAO (Poco Satisfactorio, 25-49%)', () => {
    render(
      <PaoGroupCard
        ind={crearIndicador()}
        onClick={vi.fn()}
        paosOverride={[{ pao: 'PAO 1', pct: 30 }]}
      />,
    );

    expect(screen.getByText('Poco Satisfactorio')).toBeInTheDocument();
  });

  it('caso especial I1: sin paosOverride, fuerza los 3 PAO a "Sin datos" aunque PAO_SCORES.I1 tenga datos reales', () => {
    render(<PaoGroupCard ind={crearIndicador({ id: 'I1', code: 'I1' })} onClick={vi.fn()} />);

    // 3 botones, los 3 con el placeholder "—" (no hay Ring/SemLight en ninguno).
    expect(screen.getAllByRole('button')).toHaveLength(3);
    expect(screen.getAllByText('—')).toHaveLength(3);
    expect(screen.getAllByText('Sin datos')).toHaveLength(3);

    // Los valores reales de PAO_SCORES.I1 (92%, 78%) NO deben aparecer.
    expect(screen.queryByText('92%')).not.toBeInTheDocument();
    expect(screen.queryByText('78%')).not.toBeInTheDocument();
  });

  it('sin paosOverride y con ind.id distinto de I1 (p. ej. I2), usa los datos reales de PAO_SCORES', () => {
    render(<PaoGroupCard ind={crearIndicador({ id: 'I2', code: 'I2' })} onClick={vi.fn()} />);

    // PAO_SCORES.I2: 85%, 72%, 0%.
    expect(screen.getByText('85%')).toBeInTheDocument();
    expect(screen.getByText('72%')).toBeInTheDocument();
    expect(screen.getAllByRole('button')).toHaveLength(3);
  });
});
