import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import EvidenceHeader from './EvidenceHeader';

/**
 * EvidenceHeader es la cabecera fija de las 3 pantallas de subida de
 * evidencia dentro de EvidenceUploadView (una por cada uno de los 3 usos
 * de <EvidenceHeader> en ese archivo). Sin lógica de negocio real
 * (v60/§37.4): título, subtítulo opcional, y un botón de "volver" que
 * dispara el callback recibido por props.
 */

describe('EvidenceHeader', () => {
  it('muestra el título y el texto del botón de volver (backLabel)', () => {
    render(
      <EvidenceHeader
        title="I2 · Seguimiento de Syllabus"
        backLabel="Volver al panel"
        onBackClick={vi.fn()}
      />,
    );

    expect(screen.getByText('I2 · Seguimiento de Syllabus')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Volver al panel/i })).toBeInTheDocument();
  });

  it('sin subtitle, no renderiza ningún span adicional de subtítulo', () => {
    render(<EvidenceHeader title="Título" backLabel="Volver" onBackClick={vi.fn()} />);

    expect(screen.queryByText(/carrera|cohorte/i)).not.toBeInTheDocument();
    expect(screen.getByText('Título')).toBeInTheDocument();
  });

  it('con subtitle, lo renderiza junto al título', () => {
    render(
      <EvidenceHeader
        title="Título"
        subtitle="Ingeniería en Software"
        backLabel="Volver"
        onBackClick={vi.fn()}
      />,
    );

    expect(screen.getByText('Título')).toBeInTheDocument();
    expect(screen.getByText('Ingeniería en Software')).toBeInTheDocument();
  });

  it('al hacer click en el botón de volver, dispara onBackClick exactamente una vez', async () => {
    const user = userEvent.setup();
    const onBackClick = vi.fn();

    render(<EvidenceHeader title="Título" backLabel="Volver" onBackClick={onBackClick} />);

    await user.click(screen.getByRole('button', { name: /Volver/i }));

    expect(onBackClick).toHaveBeenCalledTimes(1);
  });

  it('el botón de volver es de type="button" (no dispara submit de ningún form)', () => {
    render(<EvidenceHeader title="Título" backLabel="Volver" onBackClick={vi.fn()} />);

    expect(screen.getByRole('button', { name: /Volver/i })).toHaveAttribute('type', 'button');
  });
});
