import { describe, expect, it } from 'vitest';

import { hexToRgb, statusKeyDeValor, opcionDominante } from './exportarPdfIndicador2';

describe('hexToRgb', () => {
  it('convierte un color hex de la paleta real (maroon) a su RGB exacto', () => {
    expect(hexToRgb('#A00045')).toEqual([160, 0, 69]);
  });

  it('convierte otro color hex de la paleta real (paper)', () => {
    expect(hexToRgb('#FAF9F6')).toEqual([250, 249, 246]);
  });

  it('funciona igual sin el prefijo "#"', () => {
    expect(hexToRgb('A00045')).toEqual([160, 0, 69]);
  });

  it('es insensible a mayúsculas/minúsculas en los dígitos hex', () => {
    expect(hexToRgb('#a00045')).toEqual([160, 0, 69]);
  });

  it('extremos: negro y blanco', () => {
    expect(hexToRgb('#000000')).toEqual([0, 0, 0]);
    expect(hexToRgb('#FFFFFF')).toEqual([255, 255, 255]);
  });
});

describe('statusKeyDeValor', () => {
  it('devuelve "nodata" cuando el valor es null (sin evidencia subida)', () => {
    expect(statusKeyDeValor(null)).toBe('nodata');
  });

  it('devuelve "ok" en 100 y exactamente en el corte de 75 (Satisfactorio)', () => {
    expect(statusKeyDeValor(100)).toBe('ok');
    expect(statusKeyDeValor(75)).toBe('ok');
  });

  it('devuelve "cuasi" justo debajo de 75 y exactamente en el corte de 50', () => {
    expect(statusKeyDeValor(74)).toBe('cuasi');
    expect(statusKeyDeValor(50)).toBe('cuasi');
  });

  it('devuelve "poco" justo debajo de 50 y exactamente en el corte de 25', () => {
    expect(statusKeyDeValor(49)).toBe('poco');
    expect(statusKeyDeValor(25)).toBe('poco');
  });

  it('devuelve "def" justo debajo de 25 y en 0', () => {
    expect(statusKeyDeValor(24)).toBe('def');
    expect(statusKeyDeValor(0)).toBe('def');
  });
});

describe('opcionDominante', () => {
  it('devuelve null cuando el total de respuestas es 0 o negativo', () => {
    expect(opcionDominante({ Siempre: 5 }, 0)).toBeNull();
    expect(opcionDominante({}, -1)).toBeNull();
  });

  it('encuentra la opción con más respuestas y calcula el % redondeado', () => {
    const conteos = {
      Nunca: 1,
      'Pocas veces': 2,
      'Algunas veces': 5,
      'Casi siempre': 1,
      Siempre: 1,
    };
    expect(opcionDominante(conteos, 10)).toEqual({ label: 'Algunas veces', pct: 50 });
  });

  it('en un empate, gana la opción que aparece primero en el orden de frecuencia (Nunca...Siempre)', () => {
    // "Casi siempre" y "Siempre" empatados en 5 -- "Casi siempre" va antes en
    // FREQ_ORDER, así que debe ganar él, no "Siempre".
    const conteos = { 'Casi siempre': 5, Siempre: 5 };
    expect(opcionDominante(conteos, 10)).toEqual({ label: 'Casi siempre', pct: 50 });
  });

  it('redondea el porcentaje al entero más cercano (1/3 -> 33%)', () => {
    expect(opcionDominante({ Siempre: 1 }, 3)).toEqual({ label: 'Siempre', pct: 33 });
  });

  it('con conteos vacíos pero total > 0, no rompe: devuelve la primera opción del orden con 0%', () => {
    // Caso defensivo (inconsistencia de datos, total no coincide con la suma
    // real de conteos) -- documenta el comportamiento real de la función en
    // vez de asumir que nunca puede pasar.
    expect(opcionDominante({}, 10)).toEqual({ label: 'Nunca', pct: 0 });
  });

  it('ignora claves que no pertenecen al orden de frecuencia conocido', () => {
    const conteos = { 'Opción rara sin sentido': 999, Siempre: 2 };
    expect(opcionDominante(conteos, 2)).toEqual({ label: 'Siempre', pct: 100 });
  });
});
