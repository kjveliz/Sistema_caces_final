<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fase 2 del Plan de Mejora — migración inicial.
 *
 * Reconstruye el esquema completo tal como está hoy en producción, a partir
 * del dump de referencia más reciente (evaluacion_caces (12).sql, generado
 * 24-07-2026). A partir de esta migración, ningún cambio de esquema futuro
 * debe volver a aplicarse como ALTER TABLE suelto en phpMyAdmin: siempre una
 * migración nueva con su propio archivo.
 *
 * Las tablas se crean en orden de dependencias (una tabla referenciada por
 * FK siempre se crea antes que la que la referencia), para que las
 * restricciones se puedan agregar en la misma pasada. Se usa change() en vez
 * de up()/down() separados: Phinx revierte automáticamente createTable +
 * addForeignKey en el orden inverso correcto al hacer rollback.
 */
final class EsquemaInicial extends AbstractMigration
{
    public function change(): void
    {
        // ------------------------------------------------------------
        // Tablas sin dependencias (catálogos base)
        // ------------------------------------------------------------

        $this->table('carreras', ['id' => 'id_carrera', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('codigo', 'string', ['limit' => 20])
            ->addColumn('nombre', 'string', ['limit' => 150])
            ->addColumn('area_conocimiento', 'string', ['limit' => 150, 'null' => true])
            ->addColumn('modalidad', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('activo', 'boolean', ['default' => true])
            ->addIndex(['codigo'], ['unique' => true, 'name' => 'uk_carreras_codigo'])
            ->create();

        $this->table('indicadores', ['id' => 'id_indicador', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('nombre', 'string', ['limit' => 150])
            ->addColumn('tipo', 'string', ['limit' => 50])
            ->addColumn('criterio', 'string', ['limit' => 100])
            ->addColumn('descripcion', 'text', ['null' => true])
            ->create();

        $this->table('usuarios', ['id' => 'id_usuario', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('nombres', 'string', ['limit' => 100])
            ->addColumn('apellidos', 'string', ['limit' => 100])
            ->addColumn('correo', 'string', ['limit' => 150])
            ->addColumn('contrasena', 'string', ['limit' => 255])
            ->addColumn('rol', 'string', ['limit' => 50])
            ->addColumn('activo', 'boolean', ['default' => true])
            ->addIndex(['correo'], ['unique' => true, 'name' => 'uk_usuarios_correo'])
            ->create();

        // ------------------------------------------------------------
        // Segundo nivel (dependen solo de las anteriores)
        // ------------------------------------------------------------

        $this->table('cohortes', ['id' => 'id_cohorte', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('nombre_cohorte', 'string', ['limit' => 100])
            ->addColumn('fecha_inicio', 'date', ['null' => true])
            ->addColumn('fecha_fin', 'date', ['null' => true])
            ->addColumn('id_carrera', 'integer', ['signed' => true])
            ->addForeignKey('id_carrera', 'carreras', 'id_carrera', ['constraint' => 'fk_cohortes_carrera'])
            ->create();

        $this->table('catalogo_evidencias', ['id' => 'id_catalogo', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('id_indicador', 'integer', ['signed' => true])
            ->addColumn('codigo_evidencia', 'string', ['limit' => 50])
            ->addColumn('titulo_corto', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('descripcion', 'string', ['limit' => 200])
            ->addColumn('nombre_archivo_base', 'string', ['limit' => 120])
            ->addColumn('orden', 'integer')
            ->addColumn('activo', 'boolean', ['default' => true])
            ->addIndex(['codigo_evidencia'], ['unique' => true, 'name' => 'uk_catalogo_codigo'])
            ->addForeignKey('id_indicador', 'indicadores', 'id_indicador', [
                'constraint' => 'fk_catalogo_indicador',
                'update' => 'CASCADE',
            ])
            ->create();

        $this->table('mallas_curriculares', ['id' => 'id_malla', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('id_carrera', 'integer', ['signed' => true])
            ->addColumn('nombre_archivo', 'string', ['limit' => 255])
            ->addColumn('id_drive', 'string', ['limit' => 255])
            ->addColumn('url_drive', 'text')
            ->addColumn('activo', 'boolean', ['default' => true])
            ->addColumn('fecha_subida', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('fecha_actualizacion', 'timestamp', [
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['id_carrera'], ['unique' => true, 'name' => 'uq_malla_carrera'])
            ->addForeignKey('id_carrera', 'carreras', 'id_carrera', [
                'constraint' => 'fk_malla_carrera',
                'update' => 'CASCADE',
            ])
            ->create();

        $this->table('periodo_academico', ['id' => 'id_periodoacademico', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('id_cohorte', 'integer', ['signed' => true])
            ->addColumn('nombre', 'string', ['limit' => 100])
            ->addColumn('orden', 'integer', ['null' => true])
            ->addColumn('fecha_inicio', 'date', ['null' => true])
            ->addColumn('fecha_fin', 'date', ['null' => true])
            ->addForeignKey('id_cohorte', 'cohortes', 'id_cohorte', ['constraint' => 'fk_periodo_cohorte'])
            ->create();

        // ------------------------------------------------------------
        // Tercer nivel
        // ------------------------------------------------------------

        $this->table('asignatura', ['id' => 'id_asignatura', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('id_periodoacademico', 'integer', ['signed' => true])
            ->addColumn('nombre', 'string', ['limit' => 150])
            ->addColumn('docente', 'string', ['limit' => 150, 'null' => true])
            ->addColumn('fecha_creacion', 'date', ['null' => true])
            ->addForeignKey('id_periodoacademico', 'periodo_academico', 'id_periodoacademico', [
                'constraint' => 'fk_asignatura_periodo',
            ])
            ->create();

        $this->table('evaluaciones', ['id' => 'id_evaluacion', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('nombre_evaluacion', 'string', ['limit' => 150])
            ->addColumn('id_cohorte', 'integer', ['signed' => true])
            ->addColumn('fecha_inicio', 'date', ['null' => true])
            ->addColumn('fecha_fin', 'date', ['null' => true])
            ->addColumn('estado', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('id_usuario', 'integer', ['signed' => true])
            ->addColumn('id_carrera', 'integer', ['signed' => true])
            ->addForeignKey('id_cohorte', 'cohortes', 'id_cohorte', ['constraint' => 'fk_evaluaciones_cohorte'])
            ->addForeignKey('id_usuario', 'usuarios', 'id_usuario', ['constraint' => 'fk_evaluaciones_usuario'])
            ->addForeignKey('id_carrera', 'carreras', 'id_carrera', ['constraint' => 'fk_evaluaciones_carrera'])
            ->create();

        $this->table('compartir_catalogo', ['id' => 'id_compartir', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('id_catalogo_origen', 'integer', ['signed' => true])
            ->addColumn('id_indicador_destino', 'integer', ['signed' => true])
            ->addColumn('activo', 'boolean', ['default' => true])
            ->addIndex(['id_catalogo_origen', 'id_indicador_destino'], [
                'unique' => true,
                'name' => 'uk_catalogo_destino',
            ])
            ->addForeignKey('id_catalogo_origen', 'catalogo_evidencias', 'id_catalogo', [
                'constraint' => 'fk_compartir_catalogo',
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('id_indicador_destino', 'indicadores', 'id_indicador', [
                'constraint' => 'fk_compartir_indicador',
                'update' => 'CASCADE',
            ])
            ->create();

        // ------------------------------------------------------------
        // Cuarto nivel
        // ------------------------------------------------------------

        $this->table('evidencias', ['id' => 'id_evidencia', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('id_catalogo', 'integer', ['signed' => true])
            ->addColumn('id_evaluacion', 'integer', ['signed' => true])
            ->addColumn('codigo_evidencia', 'string', ['limit' => 50])
            ->addColumn('descripcion', 'string', ['limit' => 150])
            ->addColumn('nombre_archivo', 'string', ['limit' => 255])
            ->addColumn('tipo', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('url_archivo', 'string', ['limit' => 255])
            ->addColumn('fecha_subida', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('id_usuario', 'integer', ['signed' => true])
            ->addIndex(['id_evaluacion', 'id_catalogo'], [
                'unique' => true,
                'name' => 'uk_evidencia_evaluacion_catalogo',
            ])
            ->addForeignKey('id_catalogo', 'catalogo_evidencias', 'id_catalogo', [
                'constraint' => 'fk_evidencias_catalogo',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('id_evaluacion', 'evaluaciones', 'id_evaluacion', [
                'constraint' => 'fk_evidencias_evaluacion',
                'update' => 'CASCADE',
            ])
            ->addForeignKey('id_usuario', 'usuarios', 'id_usuario', ['constraint' => 'fk_evidencias_usuario'])
            ->create();

        $this->table('evidencia_asignatura', ['id' => 'id_evidencia_asig', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('id_asignatura', 'integer', ['signed' => true])
            ->addColumn('tipo', 'enum', [
                'values' => [
                    'syllabus',
                    'acta_retroalimentacion',
                    'acta_ajuste_curricular',
                    'evidencia_difusion',
                    'encuesta_csv',
                    'plan_tutorias',
                    'registro_tutorias',
                    'informe_tutorias',
                    'evidencia_atencion',
                ],
            ])
            ->addColumn('nombre_archivo', 'string', ['limit' => 255])
            ->addColumn('url_archivo', 'string', ['limit' => 255])
            ->addColumn('subido_por', 'string', ['limit' => 150, 'null' => true])
            ->addColumn('fecha_subida', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('vigente', 'boolean', ['default' => true])
            ->addIndex(['id_asignatura', 'tipo', 'vigente'], ['name' => 'idx_evasig_tipo_vigente'])
            ->addForeignKey('id_asignatura', 'asignatura', 'id_asignatura', [
                'constraint' => 'fk_evasig_asignatura',
                'delete' => 'CASCADE',
            ])
            ->create();

        $this->table('indicador_evidencia', ['id' => 'id', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('id_indicador', 'integer', ['signed' => true])
            ->addColumn('id_evidencia', 'integer', ['signed' => true])
            ->addIndex(['id_indicador', 'id_evidencia'], ['unique' => true, 'name' => 'uk_indicador_evidencia'])
            ->addForeignKey('id_indicador', 'indicadores', 'id_indicador', [
                'constraint' => 'fk_indicador_evidencia_indicador',
            ])
            ->addForeignKey('id_evidencia', 'evidencias', 'id_evidencia', [
                'constraint' => 'fk_indicador_evidencia_evidencia',
            ])
            ->create();

        $this->table('datos_tasa_desercion', ['id' => 'id_dato', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('id_evaluacion', 'integer', ['signed' => true])
            ->addColumn('cohorte', 'string', ['limit' => 20])
            ->addColumn('iniciaron_primer_nivel', 'integer')
            ->addColumn('matriculados_segundo_anio', 'integer')
            ->addColumn('no_continuaron', 'integer')
            ->addColumn('tasa', 'decimal', ['precision' => 6, 'scale' => 2])
            ->addColumn('fecha_actualizacion', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['id_evaluacion', 'cohorte'], ['unique' => true, 'name' => 'uk_desercion_evaluacion_cohorte'])
            ->addForeignKey('id_evaluacion', 'evaluaciones', 'id_evaluacion', [
                'constraint' => 'fk_dtd_evaluacion_2026',
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
            ])
            ->create();

        // datos_tasa_titulacion no tiene id propio: la PK real del dump es
        // compuesta (id_evaluacion, cohorte), sin columna autoincremental.
        $this->table('datos_tasa_titulacion', [
            'id' => false,
            'primary_key' => ['id_evaluacion', 'cohorte'],
            'collation' => 'utf8mb4_unicode_ci',
        ])
            ->addColumn('id_evaluacion', 'integer', ['signed' => true])
            ->addColumn('cohorte', 'string', ['limit' => 50])
            ->addColumn('matriculados', 'integer', ['null' => true])
            ->addColumn('graduados', 'integer', ['null' => true])
            ->addColumn('tasa', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true])
            ->addColumn('fecha_actualizacion', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('id_evaluacion', 'evaluaciones', 'id_evaluacion', [
                'constraint' => 'fk_datos_titulacion_evaluacion',
            ])
            ->create();

        $this->table('seguimiento_syllabus', ['id' => 'id_seguimiento', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('id_asignatura', 'integer', ['signed' => true])
            ->addColumn('ef1', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true, 'default' => 0.00])
            ->addColumn('ef2', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true, 'default' => 0.00])
            ->addColumn('ef3', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true, 'default' => 0.00])
            ->addColumn('ef4', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true, 'default' => 0.00])
            ->addColumn('ef5', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true, 'default' => 0.00])
            ->addColumn('valoracion_general', 'decimal', ['precision' => 5, 'scale' => 2, 'null' => true, 'default' => 0.00])
            ->addColumn('categoria', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('estado_general', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('fecha_calculo', 'date', ['null' => true])
            ->addColumn('id_evaluacion', 'integer', ['signed' => true])
            ->addIndex(['id_asignatura', 'id_evaluacion'], [
                'unique' => true,
                'name' => 'uk_seguimiento_asig_evaluacion',
            ])
            ->addForeignKey('id_asignatura', 'asignatura', 'id_asignatura', [
                'constraint' => 'fk_seguimiento_asignatura',
            ])
            ->addForeignKey('id_evaluacion', 'evaluaciones', 'id_evaluacion', [
                'constraint' => 'fk_seguimiento_evaluacion',
            ])
            ->create();

        $this->table('syllabus', ['id' => 'id_syllabus', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('id_asignatura', 'integer', ['signed' => true])
            ->addColumn('total_asignaturas', 'integer', ['default' => 0])
            ->addColumn('syllabus_cumplen', 'integer', ['default' => 0])
            ->addColumn('porcentaje', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0.00])
            ->addColumn('categoria', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('id_evaluacion', 'integer', ['signed' => true])
            ->addForeignKey('id_asignatura', 'asignatura', 'id_asignatura', ['constraint' => 'fk_syllabus_asignatura'])
            ->addForeignKey('id_evaluacion', 'evaluaciones', 'id_evaluacion', ['constraint' => 'fk_syllabus_evaluacion'])
            ->create();

        $this->table('tutorias', ['id' => 'id_tutoria', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('plan_tutorias', 'boolean', ['default' => false])
            ->addColumn('registro_tutorias', 'boolean', ['default' => false])
            ->addColumn('seguimiento_estudiante', 'boolean', ['default' => false])
            ->addColumn('informe_tutorias', 'boolean', ['default' => false])
            ->addColumn('porcentaje', 'decimal', ['precision' => 5, 'scale' => 2, 'default' => 0.00])
            ->addColumn('id_evaluacion', 'integer', ['signed' => true])
            ->addForeignKey('id_evaluacion', 'evaluaciones', 'id_evaluacion', ['constraint' => 'fk_tutorias_evaluacion'])
            ->create();

        $this->table('tutorias_academicas', ['id' => 'id_tutoria_resultado', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('id_asignatura', 'integer', ['signed' => true])
            ->addColumn('id_evaluacion', 'integer', ['signed' => true])
            ->addColumn('ef1', 'decimal', [
                'precision' => 5,
                'scale' => 1,
                'null' => true,
                'comment' => '% cumplido de EF1 (0/33/66/100)',
            ])
            ->addColumn('ef2', 'decimal', [
                'precision' => 5,
                'scale' => 1,
                'null' => true,
                'comment' => '% cumplido de EF2 (0/33/66/100)',
            ])
            ->addColumn('ef3', 'decimal', [
                'precision' => 5,
                'scale' => 1,
                'null' => true,
                'comment' => '% cumplido de EF3 (0/33/66/100)',
            ])
            ->addColumn('ef4', 'decimal', [
                'precision' => 5,
                'scale' => 1,
                'null' => true,
                'comment' => '% cumplido de EF4 (0/25/50/75/100)',
            ])
            ->addColumn('valoracion_general', 'decimal', ['precision' => 5, 'scale' => 1, 'null' => true])
            ->addColumn('categoria', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('estado_general', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('fecha_calculo', 'date')
            ->addIndex(['id_asignatura', 'id_evaluacion'], ['unique' => true, 'name' => 'uk_tutoria_asig_eval'])
            ->addForeignKey('id_asignatura', 'asignatura', 'id_asignatura', ['constraint' => 'fk_tutoria_asignatura'])
            ->addForeignKey('id_evaluacion', 'evaluaciones', 'id_evaluacion', ['constraint' => 'fk_tutoria_evaluacion'])
            ->create();

        $this->table('evidencia_validacion_pdf', ['id' => 'id_validacion', 'signed' => true, 'collation' => 'utf8mb4_unicode_ci'])
            ->addColumn('id_evidencia_asig', 'integer', ['signed' => true])
            ->addColumn('ef', 'string', ['limit' => 10, 'comment' => 'EF1, EF2, EF3 o EF4'])
            ->addColumn('punto_orden', 'integer', ['comment' => '1..3 o 1..4 segun el EF'])
            ->addColumn('punto_nombre', 'string', [
                'limit' => 100,
                'comment' => 'encabezado_institucional | horas | firma_docente | firma_director | reporte_mejora | normativa',
            ])
            ->addColumn('cumplido', 'boolean', ['default' => false])
            ->addColumn('valor_extraido', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'ej. "6 horas", "Firma: Juan Perez" - para mostrar en UI y depurar',
            ])
            ->addColumn('fecha_validacion', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addForeignKey('id_evidencia_asig', 'evidencia_asignatura', 'id_evidencia_asig', [
                'constraint' => 'fk_validacion_evidencia',
                'delete' => 'CASCADE',
            ])
            ->create();
    }
}
