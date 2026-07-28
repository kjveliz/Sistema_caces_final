import { describe, expect, it } from 'vitest';

import { parseCsv } from './csv';

describe('parseCsv', () => {
  it('parsea filas y columnas simples separadas por coma', () => {
    expect(parseCsv('a,b,c\n1,2,3')).toEqual([
      ['a', 'b', 'c'],
      ['1', '2', '3'],
    ]);
  });

  it('respeta comas dentro de campos entrecomillados', () => {
    expect(parseCsv('nombre,comentario\n"Pérez, Juan","Bueno, muy bueno"')).toEqual([
      ['nombre', 'comentario'],
      ['Pérez, Juan', 'Bueno, muy bueno'],
    ]);
  });

  it('respeta comillas escapadas ("") dentro de un campo entrecomillado', () => {
    expect(parseCsv('texto\n"dijo ""hola"""')).toEqual([['texto'], ['dijo "hola"']]);
  });

  it('respeta saltos de línea dentro de un campo entrecomillado', () => {
    expect(parseCsv('a,b\n"linea1\nlinea2",valor')).toEqual([
      ['a', 'b'],
      ['linea1\nlinea2', 'valor'],
    ]);
  });

  it('ignora una línea en blanco final', () => {
    expect(parseCsv('a,b\n1,2\n')).toEqual([
      ['a', 'b'],
      ['1', '2'],
    ]);
  });

  it('devuelve un arreglo vacío para texto vacío', () => {
    expect(parseCsv('')).toEqual([]);
  });
});
