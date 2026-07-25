import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import Breadcrumb from './Breadcrumb';

/**
 * Breadcrumb es la migaja de pan usada en EvidenceUploadView (única pantalla
 * que la importa hoy -- hay un segundo `Breadcrumb` en `components/ui/`,
 * generado por shadcn/ui, pero no tiene ningún import en toda la app, así
 * que no se testea aquí). Sin lógica de estado real (v60/§37.4): solo
 * recibe `items: string[]` y decide, por posición, el separador "›" (todos
 * menos el primero) y el estilo "actual" (solo el último ítem).
 */

function obtenerChips(container: HTMLElement): HTMLElement[] {
  // Cada ítem es un <span> de texto, hijo directo de un wrapper con el
  // separador opcional -- se toman todos los <span> excepto los "›".
  return Array.from(container.querySelectorAll('span')).filter((span) => span.textContent !== '›');
}

describe('Breadcrumb', () => {
  it('renderiza un chip de texto por cada ítem, en el orden recibido', () => {
    render(<Breadcrumb items={['I2', 'Ingeniería en Software', 'Resultado']} />);

    expect(screen.getByText('I2')).toBeInTheDocument();
    expect(screen.getByText('Ingeniería en Software')).toBeInTheDocument();
    expect(screen.getByText('Resultado')).toBeInTheDocument();
  });

  it('no muestra separador antes del primer ítem, y uno "›" antes de cada uno de los siguientes', () => {
    const { container } = render(<Breadcrumb items={['A', 'B', 'C']} />);

    const separadores = Array.from(container.querySelectorAll('span')).filter(
      (span) => span.textContent === '›',
    );
    expect(separadores).toHaveLength(2);
  });

  it('con un solo ítem, no hay ningún separador', () => {
    const { container } = render(<Breadcrumb items={['Único']} />);

    const separadores = Array.from(container.querySelectorAll('span')).filter(
      (span) => span.textContent === '›',
    );
    expect(separadores).toHaveLength(0);
    expect(screen.getByText('Único')).toBeInTheDocument();
  });

  it('con lista vacía, no renderiza ningún chip ni separador', () => {
    const { container } = render(<Breadcrumb items={[]} />);

    expect(obtenerChips(container)).toHaveLength(0);
  });

  it('solo el último ítem queda marcado como "actual" (fondo azul #1B3A6B, texto blanco)', () => {
    const { container } = render(<Breadcrumb items={['Primero', 'Segundo', 'Último']} />);
    const chips = obtenerChips(container);
    expect(chips).toHaveLength(3);

    const [primero, segundo, ultimo] = chips;
    expect(primero).toHaveStyle({ background: '#EEF2F7', color: '#5A7295' });
    expect(segundo).toHaveStyle({ background: '#EEF2F7', color: '#5A7295' });
    expect(ultimo).toHaveStyle({ background: '#1B3A6B', color: '#fff' });
  });

  it('con un solo ítem, ese único ítem también queda marcado como "actual"', () => {
    const { container } = render(<Breadcrumb items={['Único']} />);
    const [chip] = obtenerChips(container);

    expect(chip).toHaveStyle({ background: '#1B3A6B', color: '#fff' });
  });
});
