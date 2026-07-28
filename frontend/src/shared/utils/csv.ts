/**
 * Parser de CSV mínimo (RFC 4180 básico: comillas dobles, comas y saltos de
 * línea dentro de campos entrecomillados, comillas escapadas como "").
 *
 * Se implementa a mano (sin agregar una dependencia nueva como papaparse)
 * porque el único uso es la vista previa en tabla de CSVs que el propio
 * sistema genera/exporta (encuestas, registros de tutorías) — no hace falta
 * un parser de propósito general para eso, y evita sumar un paquete npm
 * nuevo solo para esto.
 */
export function parseCsv(text: string): string[][] {
  const filas: string[][] = [];
  let fila: string[] = [];
  let campo = '';
  let entreComillas = false;

  // Normaliza CRLF a LF de antemano para no tener que manejar '\r' aparte.
  const contenido = text.replace(/\r\n/g, '\n');

  for (let i = 0; i < contenido.length; i++) {
    const c = contenido[i];

    if (entreComillas) {
      if (c === '"') {
        if (contenido[i + 1] === '"') {
          campo += '"';
          i++;
        } else {
          entreComillas = false;
        }
      } else {
        campo += c;
      }
      continue;
    }

    if (c === '"') {
      entreComillas = true;
    } else if (c === ',') {
      fila.push(campo);
      campo = '';
    } else if (c === '\n') {
      fila.push(campo);
      filas.push(fila);
      fila = [];
      campo = '';
    } else {
      campo += c;
    }
  }

  // Última fila (si el archivo no termina con salto de línea).
  if (campo !== '' || fila.length > 0) {
    fila.push(campo);
    filas.push(fila);
  }

  // Descarta filas completamente vacías al final (línea en blanco final).
  return filas.filter((f) => !(f.length === 1 && f[0] === ''));
}
