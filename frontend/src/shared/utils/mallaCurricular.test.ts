// @vitest-environment node
import { readFileSync } from 'node:fs';
import path from 'node:path';

import { describe, expect, it } from 'vitest';
import { utils as xlsxUtils, write as escribirXlsx } from 'xlsx';

import { parsearMallaCurricular } from './mallaCurricular';

// Fixture real: DS_MALLA.xlsx, la misma malla ya "calcada" a mano en
// shared/data/academic.ts (MATERIAS_BY_PAO_MODULE). Se usa el archivo real
// en vez de una hoja armada a mano para probar el parser contra el layout
// real de celdas combinadas, no contra una simplificación nuestra.
function leerFixture(nombre: string): ArrayBuffer {
  const ruta = path.join(__dirname, '__fixtures__', nombre);
  const buffer = readFileSync(ruta);
  return buffer.buffer.slice(buffer.byteOffset, buffer.byteOffset + buffer.byteLength);
}

describe('parsearMallaCurricular', () => {
  const resultado = parsearMallaCurricular(leerFixture('DS_MALLA.xlsx'));

  it('detecta los 3 PAO en orden', () => {
    expect(resultado.paos).toHaveLength(3);
    expect(resultado.paos.map((p) => p.nombre)).toEqual(['PAO 1', 'PAO 2', 'PAO 3']);
    expect(resultado.paos.map((p) => p.orden)).toEqual([1, 2, 3]);
  });

  it('parsea el total de 22 asignaturas (coincide con la celda "NÚMERO TOTAL DE ASIGNATURAS: 22" del propio Excel)', () => {
    const total = resultado.paos.reduce((acc, p) => acc + p.asignaturas.length, 0);
    expect(total).toBe(22);
  });

  it('excluye "Práctica Laboral" y "Servicio Comunitario" (ambas en PAO 2, módulos B y C)', () => {
    const nombres = resultado.paos.flatMap((p) => p.asignaturas.map((a) => a.nombre.toLowerCase()));
    expect(nombres.some((n) => n.includes('práctica laboral'))).toBe(false);
    expect(nombres.some((n) => n.includes('servicio comunitario'))).toBe(false);
  });

  it('descarta las filas de metadata (créditos/horas) como si fueran asignaturas', () => {
    const nombres = resultado.paos.flatMap((p) => p.asignaturas.map((a) => a.nombre));
    expect(nombres).not.toContain('No CREDITOS:');
    expect(nombres).not.toContain('No HORAS:');
    expect(nombres).not.toContain('TOTAL HORAS');
  });

  it('PAO 1 tiene 3 asignaturas en módulo A, 3 en B y 2 en C (coincide con academic.ts)', () => {
    const pao1 = resultado.paos.find((p) => p.orden === 1)!;
    const porModulo = (letra: string) =>
      pao1.asignaturas.filter((a) => a.modulo === letra).map((a) => a.nombre);

    expect(porModulo('A')).toEqual([
      'Comunicación efectiva y trabajo en equipo',
      'Cultura tecnológica y digital',
      'Humanismo y Persona',
    ]);
    expect(porModulo('B')).toEqual([
      'Fundamentos de Programación y Algoritmos',
      'Desarrollo de Interfaces de Usuario y Experiencia de Usuario (UI/UX)',
      'Bases para el desarrollo de Aplicaciones Móviles para Android',
    ]);
    expect(porModulo('C')).toEqual([
      'Bases para el desarrollo de Aplicaciones Móviles para iOS',
      'Bases para el desarrollo Cross-Platform',
    ]);
  });

  it('PAO 2 tiene 6 asignaturas reales (2 excluidas: Práctica Laboral en módulo B, Servicio Comunitario en módulo C)', () => {
    const pao2 = resultado.paos.find((p) => p.orden === 2)!;
    expect(pao2.asignaturas.map((a) => a.nombre)).toEqual([
      'Seguridad y Optimización en Aplicaciones Móviles',
      'Introducción a Lenguajes de Programación',
      'Implementación de Estructuras de Datos y Algoritmos Avanzados',
      'Fundamentos de bases de datos',
      'Humanismo y Sociedad',
      'Emprendimiento e innovación',
    ]);
  });

  it('PAO 3 tiene 3 asignaturas en módulo A, 3 en B y 2 en C', () => {
    const pao3 = resultado.paos.find((p) => p.orden === 3)!;
    const porModulo = (letra: string) =>
      pao3.asignaturas.filter((a) => a.modulo === letra).map((a) => a.nombre);

    expect(porModulo('A')).toEqual([
      'Pensamiento crítico y lógico',
      'Aplicación de conceptos de Ingeniería de software',
      'Metodologías de Desarrollo Web',
    ]);
    expect(porModulo('B')).toEqual([
      'Principios de Redes y Comunicaciones',
      'Principios de los Sistemas Operativos e implementación de Software Empresarial',
      'Pruebas de Software y Aseguramiento de la Calidad',
    ]);
    expect(porModulo('C')).toEqual([
      'Integración Curricular en Programación aplicada',
      'Investigación aplicada y titulación',
    ]);
  });

  it('lanza un error descriptivo si el archivo no tiene el layout esperado (sin columnas PAO ni módulos)', () => {
    const hojaVacia = xlsxUtils.aoa_to_sheet([['esto no es una malla curricular']]);
    const libroVacio = xlsxUtils.book_new();
    xlsxUtils.book_append_sheet(libroVacio, hojaVacia, 'Hoja1');
    const bufferVacio = escribirXlsx(libroVacio, { type: 'array', bookType: 'xlsx' }) as ArrayBuffer;

    expect(() => parsearMallaCurricular(bufferVacio)).toThrow(
      /No se encontraron las 3 columnas/,
    );
  });
});
