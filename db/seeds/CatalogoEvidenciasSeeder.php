<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Catálogo de referencia real: los tipos de evidencia que acepta cada
 * indicador (mismos valores en cualquier entorno, no datos de ejemplo).
 * Depende de IndicadoresSeeder.
 */
final class CatalogoEvidenciasSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['IndicadoresSeeder'];
    }

    public function run(): void
    {
        $this->table('catalogo_evidencias')->insert([
            ['id_catalogo' => 1, 'id_indicador' => 5, 'codigo_evidencia' => 'DOC.TIT.01', 'titulo_corto' => 'Estudiantes graduados', 'descripcion' => 'Estudiantes graduados en la cohorte evaluada, con fechas o periodos de graduación', 'nombre_archivo_base' => 'Estudiantes_Graduados', 'orden' => 1, 'activo' => 1],
            ['id_catalogo' => 2, 'id_indicador' => 5, 'codigo_evidencia' => 'DOC.TIT.02', 'titulo_corto' => 'Estudiantes matriculados', 'descripcion' => 'Estudiantes matriculados en primer nivel en la cohorte evaluada', 'nombre_archivo_base' => 'Estudiantes_Matriculados_Primer_Nivel', 'orden' => 2, 'activo' => 1],
            ['id_catalogo' => 3, 'id_indicador' => 5, 'codigo_evidencia' => 'DOC.TIT.03', 'titulo_corto' => 'Informe de titulación', 'descripcion' => 'Informes y otros reportes similares sobre los resultados de la titulación estudiantil', 'nombre_archivo_base' => 'Informe_Resultados_Titulacion', 'orden' => 3, 'activo' => 1],
            ['id_catalogo' => 4, 'id_indicador' => 5, 'codigo_evidencia' => 'DOC.TIT.04', 'titulo_corto' => 'Malla curricular', 'descripcion' => 'Mallas curriculares para evidenciar la duración de la carrera', 'nombre_archivo_base' => 'Malla_Curricular', 'orden' => 4, 'activo' => 1],
            ['id_catalogo' => 5, 'id_indicador' => 1, 'codigo_evidencia' => 'DOC.SYL.01', 'titulo_corto' => 'Malla curricular', 'descripcion' => 'Malla curricular vigente de la carrera', 'nombre_archivo_base' => 'Malla_Curricular', 'orden' => 1, 'activo' => 1],
            ['id_catalogo' => 6, 'id_indicador' => 2, 'codigo_evidencia' => 'DOC.SEG.01', 'titulo_corto' => 'Reglamento / Normativa institucional', 'descripcion' => 'Reglamento o normativa institucional vigente (EF5)', 'nombre_archivo_base' => 'Reglamento_Normativa', 'orden' => 1, 'activo' => 1],
            ['id_catalogo' => 7, 'id_indicador' => 1, 'codigo_evidencia' => 'DOC.SYL.02', 'titulo_corto' => 'Syllabus', 'descripcion' => 'Sílabos actualizados de las asignaturas de la carrera', 'nombre_archivo_base' => 'Syllabus', 'orden' => 2, 'activo' => 1],
            ['id_catalogo' => 8, 'id_indicador' => 2, 'codigo_evidencia' => 'DOC.SEG.02', 'titulo_corto' => 'Actas de revisión', 'descripcion' => 'Actas de retroalimentación / revisión periódica del sílabo', 'nombre_archivo_base' => 'Actas_Revision', 'orden' => 2, 'activo' => 1],
            ['id_catalogo' => 9, 'id_indicador' => 2, 'codigo_evidencia' => 'DOC.SEG.03', 'titulo_corto' => 'Acta de Ajuste Curricular (EF2)', 'descripcion' => 'Acta de ajuste curricular derivada de la revisión del sílabo (EF2)', 'nombre_archivo_base' => 'Acta_Ajuste_Curricular', 'orden' => 3, 'activo' => 1],
            ['id_catalogo' => 10, 'id_indicador' => 2, 'codigo_evidencia' => 'DOC.SEG.04', 'titulo_corto' => 'Evidencia de Difusión (EF3)', 'descripcion' => 'Evidencia de difusión del sílabo a estudiantes y docentes (EF3)', 'nombre_archivo_base' => 'Evidencia_Difusion', 'orden' => 4, 'activo' => 1],
            ['id_catalogo' => 12, 'id_indicador' => 2, 'codigo_evidencia' => 'DOC.SEG.05', 'titulo_corto' => 'Resultados de Encuesta (CSV)', 'descripcion' => 'Archivo CSV con los resultados de la encuesta de heteroevaluación estudiantil, usado para calcular EF1 y EF4', 'nombre_archivo_base' => 'Resultados_Encuesta', 'orden' => 5, 'activo' => 1],
            ['id_catalogo' => 13, 'id_indicador' => 3, 'codigo_evidencia' => 'DOC.TUT.01', 'titulo_corto' => 'Plan de tutorías', 'descripcion' => 'Plan de tutorías académicas (EF1 — Planeación de tutorías)', 'nombre_archivo_base' => 'Plan_Tutorias', 'orden' => 1, 'activo' => 1],
            ['id_catalogo' => 14, 'id_indicador' => 3, 'codigo_evidencia' => 'DOC.TUT.02', 'titulo_corto' => 'Registros de tutorías', 'descripcion' => 'Registros de cumplimiento de tutorías (EF2 — Cumplimiento de tutorías)', 'nombre_archivo_base' => 'Registros_Tutorias', 'orden' => 2, 'activo' => 1],
            ['id_catalogo' => 15, 'id_indicador' => 3, 'codigo_evidencia' => 'DOC.TUT.03', 'titulo_corto' => 'Informe de tutorías', 'descripcion' => 'Informe de seguimiento académico (EF3 — Seguimiento académico)', 'nombre_archivo_base' => 'Informe_Tutorias', 'orden' => 3, 'activo' => 1],
            ['id_catalogo' => 16, 'id_indicador' => 3, 'codigo_evidencia' => 'DOC.TUT.04', 'titulo_corto' => 'Evidencias de atención', 'descripcion' => 'Normativa institucional de tutorías (EF4 — Normativas institucionales)', 'nombre_archivo_base' => 'Evidencias_Atencion', 'orden' => 4, 'activo' => 1],
            ['id_catalogo' => 17, 'id_indicador' => 4, 'codigo_evidencia' => 'DOC.DES.01', 'titulo_corto' => 'Estudiantes matriculados en primer nivel', 'descripcion' => 'Listado certificado de estudiantes matriculados en primer nivel académico por cohorte.', 'nombre_archivo_base' => 'Estudiantes_Matriculados_Primer_Nivel', 'orden' => 1, 'activo' => 1],
            ['id_catalogo' => 18, 'id_indicador' => 4, 'codigo_evidencia' => 'DOC.DES.02', 'titulo_corto' => 'Estudiantes matriculados en segundo año', 'descripcion' => 'Listado certificado de estudiantes matriculados en segundo año por cohorte.', 'nombre_archivo_base' => 'Estudiantes_Matriculados_Segundo_Anio', 'orden' => 2, 'activo' => 1],
            ['id_catalogo' => 19, 'id_indicador' => 4, 'codigo_evidencia' => 'DOC.DES.03', 'titulo_corto' => 'Estudiantes que no continuaron en segundo año', 'descripcion' => 'Listado certificado de estudiantes que no continuaron matriculados en segundo año por cohorte.', 'nombre_archivo_base' => 'Estudiantes_Que_No_Continuaron', 'orden' => 3, 'activo' => 1],
            ['id_catalogo' => 20, 'id_indicador' => 2, 'codigo_evidencia' => 'DOC.SEG.06', 'titulo_corto' => 'Reporte de Control de Seguimiento (SIU)', 'descripcion' => 'Reporte del SIU con % de avance de contenidos dictados por asignatura (EF1)', 'nombre_archivo_base' => 'Reporte_Control_Seguimiento', 'orden' => 8, 'activo' => 1],
            ['id_catalogo' => 21, 'id_indicador' => 2, 'codigo_evidencia' => 'DOC.SEG.07', 'titulo_corto' => 'Reporte de Avances del Syllabus (SIU)', 'descripcion' => 'Reporte del SIU con detalle unidad por unidad de contenidos dictados (EF1)', 'nombre_archivo_base' => 'Reporte_Avances_Syllabus', 'orden' => 9, 'activo' => 1],
        ])->saveData();
    }
}
