-- Migración: CSV de encuesta pasa a ser un slot más por-asignatura en I2
-- (igual que Syllabus, Actas de revisión, Acta de Ajuste Curricular y
-- Evidencia de Difusión), en vez de un único archivo evaluation-wide con
-- filtrado de filas por nombre de materia.
--
-- Contexto (ver MEMORIA v18): la encuesta real siempre tiene el nombre de
-- una materia y de un profesor fijos -- cada CSV YA es de una sola materia.
-- El filtrado de texto (`_encuesta.php`, calcularEfDesdeCsv/obtenerDetalleEncuesta)
-- estaba adivinando por texto algo que se sabe directamente por quién sube
-- el archivo y para qué materia. Este cambio también resuelve de raíz el
-- pendiente de "matching de nombre de materia poco tolerante" (v17 sección
-- 41, pendiente #10): ya no hay comparación de texto que hacer.
--
-- 1) Agregar 'encuesta_csv' al enum de tipo en evidencia_asignatura.

ALTER TABLE `evidencia_asignatura`
  MODIFY `tipo` ENUM(
    'syllabus',
    'acta_retroalimentacion',
    'acta_ajuste_curricular',
    'evidencia_difusion',
    'encuesta_csv',
    'plan_tutorias',
    'registro_tutorias',
    'informe_tutorias',
    'evidencia_atencion'
  ) NOT NULL;

-- 2) Opcional -- decisión explícita del usuario: el CSV que había quedado
--    subido como "evaluation-wide" (tabla `evidencias`, codigo_evidencia
--    DOC.SEG.05) era una prueba y no sirve; se descarta y cada materia
--    empieza de cero subiendo su propio CSV. Descomentar y ajustar el
--    id_evaluacion si se quiere borrar ese registro de prueba:
--
-- DELETE FROM `evidencias`
--  WHERE codigo_evidencia = 'DOC.SEG.05'
--    AND id_evaluacion = 1;
