-- Migración: Indicador 11.3 — Tutorías Académicas
-- Corresponde al commit d617239e ("feat(I3): implementar backend y mock de
-- Indicador 11.3 - Tutorías Académicas") — reconstruida a partir del dump de
-- producción (evaluacion_caces, 18-07-2026) porque el archivo original quedó
-- fuera de ese commit pese a estar listado en su mensaje.
--
-- Requiere: esquema base con evidencia_asignatura, catalogo_evidencias,
-- asignatura y evaluaciones ya existentes.
--
-- Aplicar dentro de una transacción. Idempotente solo si se corre una vez;
-- correrlo dos veces fallará en el ALTER de catalogo_evidencias (ids fijos)
-- y en el CREATE TABLE (usar IF NOT EXISTS si se necesita re-ejecutar).

START TRANSACTION;

-- 1) Habilita los 4 tipos de evidencia de tutorías en el ENUM existente.
--    Mantiene los 4 valores previos (syllabus / I2) intactos.
ALTER TABLE `evidencia_asignatura`
  MODIFY `tipo` ENUM(
    'syllabus',
    'acta_retroalimentacion',
    'acta_ajuste_curricular',
    'evidencia_difusion',
    'plan_tutorias',
    'registro_tutorias',
    'informe_tutorias',
    'evidencia_atencion'
  ) NOT NULL;

-- 2) Slots del catálogo de evidencias para el indicador 3 (id_indicador = 3),
--    que no tenía ninguno.
INSERT INTO `catalogo_evidencias`
  (`id_catalogo`, `id_indicador`, `codigo_evidencia`, `titulo_corto`, `descripcion`, `nombre_archivo_base`, `orden`, `activo`)
VALUES
  (13, 3, 'DOC.TUT.01', 'Plan de tutorías', 'Plan de tutorías académicas (EF1 — Planeación de tutorías)', 'Plan_Tutorias', 1, 1),
  (14, 3, 'DOC.TUT.02', 'Registros de tutorías', 'Registros de cumplimiento de tutorías (EF2 — Cumplimiento de tutorías)', 'Registros_Tutorias', 2, 1),
  (15, 3, 'DOC.TUT.03', 'Informe de tutorías', 'Informe de seguimiento académico (EF3 — Seguimiento académico)', 'Informe_Tutorias', 3, 1),
  (16, 3, 'DOC.TUT.04', 'Evidencias de atención', 'Normativa institucional de tutorías (EF4 — Normativas institucionales)', 'Evidencias_Atencion', 4, 1);

-- 3) Detalle de validación por punto (uno por cada punto detectado dentro
--    de un EF: encabezado_institucional, horas, firma_docente, firma_director,
--    reporte_mejora, normativa). Referencia a la evidencia subida.
CREATE TABLE `evidencia_validacion_pdf` (
  `id_validacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_evidencia_asig` int(11) NOT NULL,
  `ef` varchar(10) NOT NULL COMMENT 'EF1, EF2, EF3 o EF4',
  `punto_orden` int(11) NOT NULL COMMENT '1..3 o 1..4 según el EF',
  `punto_nombre` varchar(100) NOT NULL COMMENT 'encabezado_institucional | horas | firma_docente | firma_director | reporte_mejora | normativa',
  `cumplido` tinyint(1) NOT NULL DEFAULT 0,
  `valor_extraido` varchar(255) DEFAULT NULL COMMENT 'ej. "6 horas", "Firma: Juan Perez" — para mostrar en UI y depurar',
  `fecha_validacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_validacion`),
  KEY `fk_validacion_evidencia` (`id_evidencia_asig`),
  CONSTRAINT `fk_validacion_evidencia` FOREIGN KEY (`id_evidencia_asig`) REFERENCES `evidencia_asignatura` (`id_evidencia_asig`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Snapshot de auditoría por asignatura + evaluación (análoga a
--    seguimiento_syllabus para I2). Un registro por (id_asignatura, id_evaluacion).
CREATE TABLE `tutorias_academicas` (
  `id_tutoria_resultado` int(11) NOT NULL AUTO_INCREMENT,
  `id_asignatura` int(11) NOT NULL,
  `id_evaluacion` int(11) NOT NULL,
  `ef1` decimal(5,1) DEFAULT NULL COMMENT '% cumplido de EF1 (0/33/66/100)',
  `ef2` decimal(5,1) DEFAULT NULL COMMENT '% cumplido de EF2 (0/33/66/100)',
  `ef3` decimal(5,1) DEFAULT NULL COMMENT '% cumplido de EF3 (0/33/66/100)',
  `ef4` decimal(5,1) DEFAULT NULL COMMENT '% cumplido de EF4 (0/25/50/75/100)',
  `valoracion_general` decimal(5,1) DEFAULT NULL,
  `categoria` varchar(50) DEFAULT NULL,
  `estado_general` varchar(20) DEFAULT NULL,
  `fecha_calculo` date NOT NULL,
  PRIMARY KEY (`id_tutoria_resultado`),
  UNIQUE KEY `uk_tutoria_asig_eval` (`id_asignatura`,`id_evaluacion`),
  KEY `fk_tutoria_evaluacion` (`id_evaluacion`),
  CONSTRAINT `fk_tutoria_asignatura` FOREIGN KEY (`id_asignatura`) REFERENCES `asignatura` (`id_asignatura`),
  CONSTRAINT `fk_tutoria_evaluacion` FOREIGN KEY (`id_evaluacion`) REFERENCES `evaluaciones` (`id_evaluacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;