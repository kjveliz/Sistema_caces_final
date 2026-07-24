import { describe, expect, it } from 'vitest';

import { resolverAsignaturaPorNombre } from './asignaturas';

import type { AsignaturaReal } from '../services/seguimientoSyllabus';

const asignaturas: AsignaturaReal[] = [
  { id_asignatura: 1, nombre: 'Comunicación efectiva y trabajo en equipo', docente: null },
  { id_asignatura: 2, nombre: 'Cultura tecnológica y digital', docente: null },
  { id_asignatura: 12, nombre: 'Fundamentos de bases de datos', docente: 'Juan Pérez' },
];

describe('resolverAsignaturaPorNombre', () => {
  it('encuentra la materia con casing distinto (Title Case del selector vs sentence case de la BD)', () => {
    // Este es exactamente el bug real (ver MEMORIA, commit 9438cc31): el
    // selector del wizard muestra "Fundamentos de Bases de Datos" (Title
    // Case) pero la fila real en `asignatura` tiene "Fundamentos de bases
    // de datos" (sentence case). Antes del fix esto devolvía null.
    expect(resolverAsignaturaPorNombre(asignaturas, 'Fundamentos de Bases de Datos')).toBe(12);
  });

  it('encuentra la materia con coincidencia exacta', () => {
    expect(resolverAsignaturaPorNombre(asignaturas, 'Cultura tecnológica y digital')).toBe(2);
  });

  it('ignora espacios sobrantes al inicio/fin del nombre', () => {
    expect(resolverAsignaturaPorNombre(asignaturas, '  Cultura tecnológica y digital  ')).toBe(2);
  });

  it('es insensible a mayúsculas/minúsculas en cualquier dirección', () => {
    expect(resolverAsignaturaPorNombre(asignaturas, 'CULTURA TECNOLÓGICA Y DIGITAL')).toBe(2);
    expect(resolverAsignaturaPorNombre(asignaturas, 'cultura tecnológica y digital')).toBe(2);
  });

  it('devuelve null si la materia no existe en la lista', () => {
    expect(resolverAsignaturaPorNombre(asignaturas, 'Materia que no existe')).toBeNull();
  });

  it('devuelve null con lista de asignaturas vacía', () => {
    expect(resolverAsignaturaPorNombre([], 'Cualquier materia')).toBeNull();
  });

  it('no hace match parcial (substring) -- debe ser el nombre completo', () => {
    expect(resolverAsignaturaPorNombre(asignaturas, 'Cultura tecnológica')).toBeNull();
  });
});
