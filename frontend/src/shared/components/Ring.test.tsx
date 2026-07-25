import { describe, expect, it } from 'vitest';
import { render } from '@testing-library/react';

import Ring from './Ring';

/**
 * Ring es el anillo de progreso SVG (usado en IndCard y PaoGroupCard) que
 * pinta el % dentro de un círculo, coloreado según el mismo umbral de
 * estado que SemLight/getStatus (75/50/25). Tiene su propia copia local de
 * `getStatus` (no importa la de src/utils/evaluation.ts) -- no exportada,
 * así que se verifica indirectamente vía el color del trazo (`stroke`) del
 * círculo de progreso y el texto central.
 */

function circuloDeProgreso(container: HTMLElement): SVGCircleElement {
  // El primer <circle> es el riel de fondo (siempre #E5E7EB); el segundo es
  // el de progreso, el que cambia de color según el %.
  const circulos = container.querySelectorAll('circle');
  return circulos[1] as SVGCircleElement;
}

describe('Ring', () => {
  it('con pct >= 75 (Satisfactorio), el trazo es verde y muestra el % en el centro', () => {
    const { container, getByText } = render(<Ring pct={90} />);
    expect(circuloDeProgreso(container)).toHaveAttribute('stroke', '#16A34A');
    expect(getByText('90%')).toBeInTheDocument();
  });

  it('con pct entre 50 y 74 (Cuasi Satisfactorio), el trazo es amarillo', () => {
    const { container, getByText } = render(<Ring pct={60} />);
    expect(circuloDeProgreso(container)).toHaveAttribute('stroke', '#CA8A04');
    expect(getByText('60%')).toBeInTheDocument();
  });

  it('con pct entre 25 y 49 (Poco Satisfactorio), el trazo es naranja', () => {
    const { container, getByText } = render(<Ring pct={30} />);
    expect(circuloDeProgreso(container)).toHaveAttribute('stroke', '#EA580C');
    expect(getByText('30%')).toBeInTheDocument();
  });

  it('con pct entre 1 y 24 (Deficiente), el trazo es rojo', () => {
    const { container, getByText } = render(<Ring pct={10} />);
    expect(circuloDeProgreso(container)).toHaveAttribute('stroke', '#DC2626');
    expect(getByText('10%')).toBeInTheDocument();
  });

  it('con pct = 0 (sin datos), el trazo es gris y muestra "—" en vez del %', () => {
    const { container, getByText, queryByText } = render(<Ring pct={0} />);
    expect(circuloDeProgreso(container)).toHaveAttribute('stroke', '#9CA3AF');
    expect(getByText('—')).toBeInTheDocument();
    expect(queryByText('0%')).not.toBeInTheDocument();
  });

  it('respeta los cortes exactos: 75, 50 y 25 activan el color de su propio tramo, no el de abajo', () => {
    const { container: c75 } = render(<Ring pct={75} />);
    expect(circuloDeProgreso(c75)).toHaveAttribute('stroke', '#16A34A');

    const { container: c50 } = render(<Ring pct={50} />);
    expect(circuloDeProgreso(c50)).toHaveAttribute('stroke', '#CA8A04');

    const { container: c25 } = render(<Ring pct={25} />);
    expect(circuloDeProgreso(c25)).toHaveAttribute('stroke', '#EA580C');
  });

  it('el tamaño usa las props "r" y "sw" (por defecto r=36, sw=7 -> viewBox de 86x86)', () => {
    const { container: porDefecto } = render(<Ring pct={50} />);
    const svgPorDefecto = porDefecto.querySelector('svg');
    expect(svgPorDefecto).toHaveAttribute('width', '86');
    expect(svgPorDefecto).toHaveAttribute('height', '86');

    const { container: personalizado } = render(<Ring pct={50} r={24} sw={5} />);
    const svgPersonalizado = personalizado.querySelector('svg');
    expect(svgPersonalizado).toHaveAttribute('width', '58');
    expect(svgPersonalizado).toHaveAttribute('height', '58');
  });

  it('el largo del trazo de progreso (strokeDasharray) es proporcional al %, sobre la circunferencia de "r"', () => {
    // circunferencia = 2 * PI * r. Con r=36 (default), circunferencia ≈ 226.19.
    // A 50%, el progreso debería ser la mitad de esa circunferencia (≈113.10).
    const { container } = render(<Ring pct={50} />);
    const circulo = circuloDeProgreso(container);
    const dasharray = circulo.getAttribute('stroke-dasharray') ?? '';
    const [progreso, circunferencia] = dasharray.split(' ').map(Number);

    expect(circunferencia).toBeCloseTo(2 * Math.PI * 36, 1);
    expect(progreso).toBeCloseTo(circunferencia / 2, 1);
  });
});
