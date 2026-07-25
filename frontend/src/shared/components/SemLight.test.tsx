import { describe, expect, it } from 'vitest';
import { render } from '@testing-library/react';

import SemLight from './SemLight';

/**
 * SemLight es el semáforo de 4 puntos (rojo/naranja/amarillo/verde) que
 * indica el estado de un % en varias tarjetas (IndCard, PaoGroupCard). No
 * tiene roles ni texto -- solo 4 divs coloreados -- así que se verifica
 * directamente cuál de los 4 queda "activo" (su propio color) y cuáles
 * quedan "apagados" (#E5E7EB, gris), igual que se ve a simple vista en la
 * UI real.
 */

const COLOR_ROJO = '#DC2626';
const COLOR_NARANJA = '#EA580C';
const COLOR_AMARILLO = '#CA8A04';
const COLOR_VERDE = '#16A34A';
const COLOR_APAGADO = '#E5E7EB';

function obtenerPuntos(container: HTMLElement): HTMLElement[] {
  return Array.from(container.querySelectorAll(':scope > div > div'));
}

describe('SemLight', () => {
  it('con pct >= 75 (Satisfactorio), el punto verde queda activo y el resto apagados', () => {
    const { container } = render(<SemLight pct={90} />);
    const puntos = obtenerPuntos(container);
    expect(puntos).toHaveLength(4);

    const [rojo, naranja, amarillo, verde] = puntos;
    expect(rojo).toHaveStyle({ backgroundColor: COLOR_APAGADO });
    expect(naranja).toHaveStyle({ backgroundColor: COLOR_APAGADO });
    expect(amarillo).toHaveStyle({ backgroundColor: COLOR_APAGADO });
    expect(verde).toHaveStyle({ backgroundColor: COLOR_VERDE });
  });

  it('con pct entre 50 y 74 (Cuasi satisfactorio), el punto amarillo queda activo', () => {
    const { container } = render(<SemLight pct={60} />);
    const [, , amarillo, verde] = obtenerPuntos(container);
    expect(amarillo).toHaveStyle({ backgroundColor: COLOR_AMARILLO });
    expect(verde).toHaveStyle({ backgroundColor: COLOR_APAGADO });
  });

  it('con pct entre 25 y 49 (Poco satisfactorio), el punto naranja queda activo', () => {
    const { container } = render(<SemLight pct={30} />);
    const [rojo, naranja, amarillo] = obtenerPuntos(container);
    expect(rojo).toHaveStyle({ backgroundColor: COLOR_APAGADO });
    expect(naranja).toHaveStyle({ backgroundColor: COLOR_NARANJA });
    expect(amarillo).toHaveStyle({ backgroundColor: COLOR_APAGADO });
  });

  it('con pct entre 1 y 24 (Deficiente), el punto rojo queda activo', () => {
    const { container } = render(<SemLight pct={10} />);
    const [rojo, naranja] = obtenerPuntos(container);
    expect(rojo).toHaveStyle({ backgroundColor: COLOR_ROJO });
    expect(naranja).toHaveStyle({ backgroundColor: COLOR_APAGADO });
  });

  it('con pct = 0 (sin datos), ningún punto queda activo -- los 4 apagados', () => {
    const { container } = render(<SemLight pct={0} />);
    const puntos = obtenerPuntos(container);
    puntos.forEach((punto) => {
      expect(punto).toHaveStyle({ backgroundColor: COLOR_APAGADO });
    });
  });

  it('respeta los cortes exactos: 75, 50 y 25 activan el color de su propio tramo, no el de abajo', () => {
    const { container: c75 } = render(<SemLight pct={75} />);
    const [, , , verde75] = obtenerPuntos(c75);
    expect(verde75).toHaveStyle({ backgroundColor: COLOR_VERDE });

    const { container: c50 } = render(<SemLight pct={50} />);
    const [, , amarillo50] = obtenerPuntos(c50);
    expect(amarillo50).toHaveStyle({ backgroundColor: COLOR_AMARILLO });

    const { container: c25 } = render(<SemLight pct={25} />);
    const [, naranja25] = obtenerPuntos(c25);
    expect(naranja25).toHaveStyle({ backgroundColor: COLOR_NARANJA });
  });

  it('el tamaño de cada punto usa la prop "dot" (por defecto 11, configurable)', () => {
    const { container: porDefecto } = render(<SemLight pct={90} />);
    const [puntoDefecto] = obtenerPuntos(porDefecto);
    expect(puntoDefecto).toHaveStyle({ width: '11px', height: '11px' });

    const { container: personalizado } = render(<SemLight pct={90} dot={20} />);
    const [puntoPersonalizado] = obtenerPuntos(personalizado);
    expect(puntoPersonalizado).toHaveStyle({ width: '20px', height: '20px' });
  });
});
