-- Migración: Indicador I4 — Tasa de Deserción
-- Traída de la rama `developer` e integrada a `feature/seguimiento-syllabus`.
-- Ejecutada y confirmada contra la BD real (phpMyAdmin, mensaje de éxito,
-- "cero columnas" — respuesta esperada para un DDL sin errores). Ver Memoria
-- del proyecto v28 §14.4 para el detalle de la comparación de esquemas.
--
-- No toca ninguna otra tabla. En particular, `datos_tasa_titulacion` (I5) se
-- dejó intacta a propósito: su esquema en `developer` era una alteración
-- manual sin respaldo en código, descartada — ver §14.4/§14.3.
--
-- Idempotente solo si se corre una vez; correrlo dos veces fallará en el
-- CREATE TABLE (usar IF NOT EXISTS si se necesita re-ejecutar).

START TRANSACTION;

CREATE TABLE `datos_tasa_desercion` (
  `id_dato` int(11) NOT NULL AUTO_INCREMENT,
  `id_evaluacion` int(11) NOT NULL,
  `cohorte` varchar(50) NOT NULL,
  `iniciaron_primer_nivel` int(11) DEFAULT NULL,
  `matriculados_segundo_anio` int(11) DEFAULT NULL,
  `no_continuaron` int(11) DEFAULT NULL,
  `tasa` decimal(6,2) DEFAULT NULL,
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_dato`),
  UNIQUE KEY `uk_desercion_evaluacion_cohorte` (`id_evaluacion`,`cohorte`),
  KEY `fk_datos_desercion_evaluacion` (`id_evaluacion`),
  CONSTRAINT `fk_datos_desercion_evaluacion` FOREIGN KEY (`id_evaluacion`) REFERENCES `evaluaciones` (`id_evaluacion`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;