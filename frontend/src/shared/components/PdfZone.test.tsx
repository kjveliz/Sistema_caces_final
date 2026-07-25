import { describe, expect, it, vi, beforeAll } from 'vitest';
import { render, screen } from '@testing-library/react';
import { fireEvent } from '@testing-library/react';

import PdfZone from './PdfZone';
import type { EvidenceSlot } from '../../types/index';

/**
 * Primera pasada de tests de componentes con React Testing Library (Fase 5
 * del Plan de Mejora, ver MEMORIA §33). PdfZone se eligió como punto de
 * partida porque es el dropzone de subida de evidencia reusado en los
 * wizards de I1-I5 -- alta reutilización, lógica real de validación
 * (validatePDF/validateCSV, ver src/utils/pdf.ts), y varios estados
 * (con archivo, con error, deshabilitado/compartido, cargando).
 *
 * jsdom no implementa URL.createObjectURL -- se mockea globalmente porque
 * el componente lo llama en la rama "sin onFileSelected" (ver PdfZone.tsx).
 */
beforeAll(() => {
  globalThis.URL.createObjectURL = vi.fn(() => 'blob:mock-url');
});

function crearSlot(overrides: Partial<EvidenceSlot> = {}): EvidenceSlot {
  return {
    sourceNum: 1,
    label: 'Syllabus de la asignatura',
    ...overrides,
  };
}

/** El input de tipo file está oculto con la clase "hidden" (Tailwind, sin
 * efecto real en jsdom) -- se busca directo en el DOM en vez de por rol,
 * porque interactuar con él simula exactamente lo que dispara pick() en el
 * componente real (el usuario elige un archivo del diálogo del sistema). */
function obtenerInputArchivo(container: HTMLElement): HTMLInputElement {
  const input = container.querySelector('input[type="file"]');
  if (!input) {
    throw new Error('No se encontró el input de tipo file en PdfZone.');
  }
  return input as HTMLInputElement;
}

describe('PdfZone', () => {
  it('muestra el label y el nombre de archivo placeholder cuando no hay archivo', () => {
    render(<PdfZone slot={crearSlot()} fileName="I2_SYL_001.pdf" onChange={vi.fn()} />);

    expect(screen.getByText('Syllabus de la asignatura')).toBeInTheDocument();
    expect(screen.getByText('I2_SYL_001.pdf')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /subir/i })).toBeInTheDocument();
  });

  it('con onFileSelected: al elegir un PDF válido, delega en el padre y no llama a onChange', () => {
    const onChange = vi.fn();
    const onFileSelected = vi.fn();
    const { container } = render(
      <PdfZone
        slot={crearSlot()}
        fileName="I2_SYL_001.pdf"
        onChange={onChange}
        onFileSelected={onFileSelected}
      />,
    );

    const archivo = new File(['contenido'], 'syllabus.pdf', { type: 'application/pdf' });
    const input = obtenerInputArchivo(container);
    fireEvent.change(input, { target: { files: [archivo] } });

    expect(onFileSelected).toHaveBeenCalledTimes(1);
    expect(onFileSelected.mock.calls[0][1]).toBe(archivo);
    expect(onChange).not.toHaveBeenCalled();
    expect(input.value).toBe('');
  });

  it('sin onFileSelected (fallback): al elegir un PDF válido, llama a onChange con el archivo', () => {
    const onChange = vi.fn();
    const { container } = render(
      <PdfZone slot={crearSlot()} fileName="I2_SYL_001.pdf" onChange={onChange} />,
    );

    const archivo = new File(['contenido'], 'syllabus.pdf', { type: 'application/pdf' });
    const input = obtenerInputArchivo(container);
    fireEvent.change(input, { target: { files: [archivo] } });

    expect(onChange).toHaveBeenCalledTimes(1);
    const slotActualizado = onChange.mock.calls[0][0] as EvidenceSlot;
    expect(slotActualizado.error).toBeUndefined();
    expect(slotActualizado.file?.originalName).toBe('syllabus.pdf');
    expect(slotActualizado.file?.fileName).toBe('I2_SYL_001.pdf');
    expect(slotActualizado.file?.rawFile).toBe(archivo);
  });

  it('rechaza un archivo que no es PDF y muestra el mensaje de error, sin llamar a onFileSelected', () => {
    const onChange = vi.fn();
    const onFileSelected = vi.fn();
    const { container } = render(
      <PdfZone
        slot={crearSlot()}
        fileName="I2_SYL_001.pdf"
        onChange={onChange}
        onFileSelected={onFileSelected}
      />,
    );

    const archivo = new File(['contenido'], 'notas.txt', { type: 'text/plain' });
    const input = obtenerInputArchivo(container);
    fireEvent.change(input, { target: { files: [archivo] } });

    expect(onFileSelected).not.toHaveBeenCalled();
    expect(onChange).toHaveBeenCalledTimes(1);
    const slotActualizado = onChange.mock.calls[0][0] as EvidenceSlot;
    expect(slotActualizado.error).toBe('Solo se aceptan archivos en formato PDF (.pdf)');
    expect(slotActualizado.file).toBeUndefined();
  });

  it('en modo CSV (acceptedType: "csv") valida con validateCSV en vez de validatePDF', () => {
    const onChange = vi.fn();
    const { container } = render(
      <PdfZone
        slot={crearSlot({ acceptedType: 'csv', label: 'Resultados de encuesta' })}
        fileName="I2_ENC_001.csv"
        onChange={onChange}
      />,
    );

    // Un PDF no debería pasar la validación de CSV.
    const archivoInvalido = new File(['contenido'], 'reporte.pdf', { type: 'application/pdf' });
    const input = obtenerInputArchivo(container);
    fireEvent.change(input, { target: { files: [archivoInvalido] } });

    expect((onChange.mock.calls[0][0] as EvidenceSlot).error).toBe(
      'Solo se aceptan archivos en formato CSV (.csv)',
    );

    onChange.mockClear();

    // Un CSV real sí debería pasar.
    const archivoValido = new File(['a,b,c'], 'encuesta.csv', { type: 'text/csv' });
    fireEvent.change(input, { target: { files: [archivoValido] } });

    const slotActualizado = onChange.mock.calls[0][0] as EvidenceSlot;
    expect(slotActualizado.error).toBeUndefined();
    expect(slotActualizado.file?.originalName).toBe('encuesta.csv');
  });

  it('cuando está deshabilitado y sin archivo, no muestra el botón de subir', () => {
    render(<PdfZone slot={crearSlot()} fileName="I2_SYL_001.pdf" onChange={vi.fn()} disabled />);

    expect(screen.queryByRole('button')).not.toBeInTheDocument();
  });

  it('cuando está cargando, el botón queda deshabilitado y muestra "Cargando..."', () => {
    render(<PdfZone slot={crearSlot()} fileName="I2_SYL_001.pdf" onChange={vi.fn()} loading />);

    const boton = screen.getByRole('button', { name: /cargando/i });
    expect(boton).toBeDisabled();
  });

  it('mientras está cargando, ignora el archivo elegido y limpia el input sin llamar a los callbacks', () => {
    const onChange = vi.fn();
    const onFileSelected = vi.fn();
    const { container } = render(
      <PdfZone
        slot={crearSlot()}
        fileName="I2_SYL_001.pdf"
        onChange={onChange}
        onFileSelected={onFileSelected}
        loading
      />,
    );

    const archivo = new File(['contenido'], 'syllabus.pdf', { type: 'application/pdf' });
    const input = obtenerInputArchivo(container);
    fireEvent.change(input, { target: { files: [archivo] } });

    expect(onChange).not.toHaveBeenCalled();
    expect(onFileSelected).not.toHaveBeenCalled();
    expect(input.value).toBe('');
  });

  it('muestra "Evidencia compartida" con el nombre del indicador de origen cuando disabled+hasFile+sharedFrom', () => {
    const slotCompartido = crearSlot({
      file: {
        fileName: 'I5_MAT_001.pdf',
        originalName: 'matriculados.pdf',
        url: 'blob:existing',
        size: 1024,
      },
      sharedFrom: 'I5',
    });

    render(<PdfZone slot={slotCompartido} fileName="I4_MAT_001.pdf" onChange={vi.fn()} disabled />);

    expect(screen.getByText('🔗 Evidencia compartida')).toBeInTheDocument();
    expect(screen.getByText(/Tasa de Titulación/)).toBeInTheDocument();
  });
});
