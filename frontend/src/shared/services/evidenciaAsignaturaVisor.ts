// GET /evidencia-asignatura/ver?id_evidencia_asig= — visor real para I2/I3
// (evidencia_asignatura), agregado en el paso 6 parte 1 del plan de
// interruptor de almacenamiento (EvidenciaAsignaturaVisorController).
// A diferencia de seguimientoSyllabus.ts/tutoriasAcademicas.ts, este
// endpoint NO devuelve JSON: devuelve el contenido real del archivo
// (Content-Type derivado de la extensión), así que no se envuelve con
// getJson() — solo se necesita la URL, para usarla directo como `src` de
// un <iframe> o como destino de `window.open()` (ambos mandan la cookie
// de sesión igual que un fetch con `credentials: 'include'`).
//
// Reemplaza abrir `url_archivo` directo desde el frontend para estos
// slots: esa URL puede ser una ruta de filesystem local (no abrible desde
// el navegador) si la carrera está en modo 'local' — ver
// plan_interruptor_almacenamiento.txt §4.5/§4.6 y MEMORIA v91 §68.1.
export function urlVisorEvidenciaAsignatura(idEvidenciaAsig: number): string {
  return `http://localhost/sistemacaces/public/evidencia-asignatura/ver?id_evidencia_asig=${idEvidenciaAsig}`;
}
