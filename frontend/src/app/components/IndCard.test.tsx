import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import IndCard from './IndCard';

import type { CohortRow, IndicatorDef } from '../../types';

/**
 * IndCard es la tarjeta de indicador del dashboard general (I1-I5): calcula
 * su propio % a partir de ind.cohorts (calcRate, en src/utils/evaluation.ts)
 * y pinta el estado (Ring + SemLight + badge) en consecuencia. Es un botón
 * clickeable real -- se testea también que dispare onClick.
 */

function crearIndicador(cohorts: CohortRow[], overrides: Partial<IndicatorDef> = {}): IndicatorDef {
  return {
    id: 'I5',
    num: 5,
    code: 'I5',
    name: 'Tasa de Titulación',
    description: '',
    formula: '',
    period: '',
    purpose: '',
    slots: [],
    cohorts,
    ...overrides,
  };
}

describe('IndCard', () => {
  it('muestra el código, el nombre del indicador y el % calculado a partir de los cohortes', () => {
    render(
      <IndCard
        ind={crearIndicador([{ period: '2026-A', enrolled: 100, graduated: 80 }])}
        onClick={vi.fn()}
      />,
    );

    expect(screen.getByText('I5')).toBeInTheDocument();
    expect(screen.getByText('Tasa de Titulación')).toBeInTheDocument();
    expect(screen.getByText('80%')).toBeInTheDocument();
  });

  it('suma varios cohortes antes de calcular el %, no promedia cohorte por cohorte', () => {
    // 2 cohortes: 100 matriculados/50 graduados y 50 matriculados/50 graduados.
    // Promediar los % individuales (50% y 100%) daría 75%; sumar primero y
    // dividir después (lo que hace calcRate) da (50+50)/(100+50) = 67%.
    render(
      <IndCard
        ind={crearIndicador([
          { period: '2025-A', enrolled: 100, graduated: 50 },
          { period: '2025-B', enrolled: 50, graduated: 50 },
        ])}
        onClick={vi.fn()}
      />,
    );

    expect(screen.getByText('67%')).toBeInTheDocument();
  });

  it('con 0 matriculados en todos los cohortes, muestra "Sin datos" en vez de dividir por cero', () => {
    render(<IndCard ind={crearIndicador([])} onClick={vi.fn()} />);

    expect(screen.getByText('Sin datos')).toBeInTheDocument();
    expect(screen.getByText('—')).toBeInTheDocument();
  });

  it('muestra el badge de estado correcto según el % (Poco Satisfactorio, 25-49%)', () => {
    render(
      <IndCard
        ind={crearIndicador([{ period: '2026-A', enrolled: 100, graduated: 30 }])}
        onClick={vi.fn()}
      />,
    );

    expect(screen.getByText('Poco Satisfactorio')).toBeInTheDocument();
  });

  it('dispara onClick al hacer click en la tarjeta', async () => {
    const onClick = vi.fn();
    const user = userEvent.setup();
    render(
      <IndCard
        ind={crearIndicador([{ period: '2026-A', enrolled: 100, graduated: 80 }])}
        onClick={onClick}
      />,
    );

    await user.click(screen.getByRole('button'));
    expect(onClick).toHaveBeenCalledTimes(1);
  });
});
