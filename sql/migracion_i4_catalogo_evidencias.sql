-- Migración: catálogo de evidencias para I4 (Tasa de Deserción)
--
-- Sin esto, los slots 2 y 3 del wizard de I4 (matriculados en 2do año /
-- no continuaron) no tienen id_catalogo -> el frontend bloquea la subida
-- con "La evidencia no está relacionada con el catálogo" y, aunque no lo
-- bloqueara, el backend fallaría igual por el FK de `evidencias.id_catalogo`
-- hacia `catalogo_evidencias`.
--
-- El slot 1 (matriculados en primer nivel) NO necesita fila propia aquí
-- porque se comparte desde I5 vía `compartir_catalogo` (id_catalogo_origen=2
-- -> id_indicador_destino=4), regla que ya existe en la BD real. Se agrega
-- de todas formas para que el wizard tenga un catálogo completo si algún
-- día se sube el slot 1 antes de que exista el dato compartido de I5 (mismo
-- criterio que usa developer, ver dump de esa rama).
--
-- Códigos y textos calcados del dump de `developer` (catalogo_evidencias,
-- id_indicador=4), renumerados a partir del AUTO_INCREMENT real de esta BD
-- (17, confirmado en el dump actual) en vez de los ids 5/6/7 que usaba
-- `developer` (esos ids ya están ocupados aquí por I1/I2/I3).
--
-- No toca `compartir_catalogo`: la regla de compartición I5->I4 ya existe.
--
-- Idempotente solo si se corre una vez; correrlo dos veces duplicará las
-- filas (no hay UNIQUE KEY en codigo_evidencia).

START TRANSACTION;

INSERT INTO `catalogo_evidencias`
  (`id_catalogo`, `id_indicador`, `codigo_evidencia`, `titulo_corto`, `descripcion`, `nombre_archivo_base`, `orden`, `activo`)
VALUES
  (17, 4, 'DOC.DES.01', 'Estudiantes matriculados en primer nivel', 'Listado certificado de estudiantes matriculados en primer nivel académico por cohorte.', 'Estudiantes_Matriculados_Primer_Nivel', 1, 1),
  (18, 4, 'DOC.DES.02', 'Estudiantes matriculados en segundo año', 'Listado certificado de estudiantes matriculados en segundo año por cohorte.', 'Estudiantes_Matriculados_Segundo_Anio', 2, 1),
  (19, 4, 'DOC.DES.03', 'Estudiantes que no continuaron en segundo año', 'Listado certificado de estudiantes que no continuaron matriculados en segundo año por cohorte.', 'Estudiantes_Que_No_Continuaron', 3, 1);

COMMIT;