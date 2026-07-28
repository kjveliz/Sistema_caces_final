import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { subirMallaCurricular, type CarreraBD } from './carreras';

// Cubre la Parte E del plan de malla curricular xlsx (ver
// MEMORIA_v114.md §90.3 / MEMORIA_v115.md §91.3.3): antes de este cambio
// subirMallaCurricular() validaba PDF a pesar de que el input del modal
// (Parte B) ya aceptaba .xlsx, lo que rompía la subida real de cualquier
// Excel. Este archivo prueba solo la validación de tipo/tamaño (la parte
// que cambió); el resto de la función (llamadas de red reales a Drive y a
// /malla-curricular/guardar) se mockea, no se ejercita de verdad.

const carrera: CarreraBD = {
  id_carrera: 5,
  codigo: 'DES',
  nombre: 'Desarrollo de Software',
  area_conocimiento: 'Tecnología',
  modalidad: 'Presencial',
  modo_almacenamiento: 'local',
};

function crearArchivo(nombre: string, tipo: string, tamanoBytes = 1024): File {
  return new File([new Uint8Array(tamanoBytes)], nombre, { type: tipo });
}

describe('subirMallaCurricular — validación de tipo y tamaño (Parte E)', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn());
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('rechaza un PDF (mime y extensión correctos de PDF, ninguno de xlsx)', async () => {
    const archivo = crearArchivo('Malla_Curricular.pdf', 'application/pdf');

    await expect(subirMallaCurricular({ archivo, carrera })).rejects.toThrow(
      'La malla curricular debe ser un archivo Excel (.xlsx).',
    );

    expect(fetch).not.toHaveBeenCalled();
  });

  it('rechaza un archivo sin extensión .xlsx ni mime de xlsx (ej. .docx)', async () => {
    const archivo = crearArchivo(
      'Malla_Curricular.docx',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    );

    await expect(subirMallaCurricular({ archivo, carrera })).rejects.toThrow(
      'La malla curricular debe ser un archivo Excel (.xlsx).',
    );
  });

  it('acepta un archivo con mime de xlsx aunque el navegador no haya seteado bien la extensión', async () => {
    // Mismo criterio "type O extensión" que ya usaba el validador de PDF:
    // el mime alcanza aunque el nombre no termine en .xlsx.
    const archivo = crearArchivo(
      'malla_sin_extension',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );

    vi.mocked(fetch)
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({ ok: true, datos: { id_archivo: '1', url_archivo: 'https://x' } }),
          { status: 200 },
        ),
      )
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            ok: true,
            datos: { id_carrera: 5, nombre_archivo: 'Malla_Curricular_DES.xlsx', id_drive: '1', url_drive: 'https://x' },
          }),
          { status: 200 },
        ),
      );

    await expect(subirMallaCurricular({ archivo, carrera })).resolves.toEqual({
      id_carrera: 5,
      nombre_archivo: 'Malla_Curricular_DES.xlsx',
      id_drive: '1',
      url_drive: 'https://x',
    });
  });

  it('acepta un archivo con extensión .xlsx aunque el mime venga vacío/genérico', async () => {
    const archivo = crearArchivo('Malla_Curricular.xlsx', '');

    vi.mocked(fetch)
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({ ok: true, datos: { id_archivo: '1', url_archivo: 'https://x' } }),
          { status: 200 },
        ),
      )
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({
            ok: true,
            datos: { id_carrera: 5, nombre_archivo: 'Malla_Curricular_DES.xlsx', id_drive: '1', url_drive: 'https://x' },
          }),
          { status: 200 },
        ),
      );

    await expect(subirMallaCurricular({ archivo, carrera })).resolves.toBeTruthy();
  });

  it('manda tipo_esperado=xlsx y el nombre de archivo con extensión .xlsx en el FormData', async () => {
    const archivo = crearArchivo(
      'cualquiera.xlsx',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );

    vi.mocked(fetch)
      .mockResolvedValueOnce(
        new Response(
          JSON.stringify({ ok: true, datos: { id_archivo: '1', url_archivo: 'https://x' } }),
          { status: 200 },
        ),
      )
      .mockResolvedValueOnce(
        new Response(JSON.stringify({ ok: true, datos: { id_carrera: 5 } }), { status: 200 }),
      );

    await subirMallaCurricular({ archivo, carrera });

    const primeraLlamada = vi.mocked(fetch).mock.calls[0];
    const formulario = primeraLlamada[1]?.body as FormData;

    expect(formulario.get('tipo_esperado')).toBe('xlsx');
    expect(formulario.get('nombre_archivo')).toBe('Malla_Curricular_DES.xlsx');
  });

  it('rechaza un xlsx que supera los 25 MB', async () => {
    const archivo = crearArchivo(
      'Malla_Curricular.xlsx',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      26 * 1024 * 1024,
    );

    await expect(subirMallaCurricular({ archivo, carrera })).rejects.toThrow(
      'El archivo no debe superar los 25 MB.',
    );

    expect(fetch).not.toHaveBeenCalled();
  });
});
