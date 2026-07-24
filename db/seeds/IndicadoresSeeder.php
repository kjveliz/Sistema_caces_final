<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Catálogo de referencia real: los 5 indicadores del sistema (I1-I5). No es
 * "dato de ejemplo" — sin esto la aplicación no arranca en ningún entorno,
 * es igual en producción que en cualquier base de prueba.
 */
final class IndicadoresSeeder extends AbstractSeed
{
    public function run(): void
    {
        $this->table('indicadores')->insert([
            [
                'id_indicador' => 1,
                'nombre' => 'Syllabus',
                'tipo' => 'Cuantitativo',
                'criterio' => 'Docencia',
                'descripcion' => 'Evalúa la elaboración y actualización de los sílabos.',
            ],
            [
                'id_indicador' => 2,
                'nombre' => 'Seguimiento de Syllabus',
                'tipo' => 'Cuantitativo',
                'criterio' => 'Docencia',
                'descripcion' => 'Evalúa el cumplimiento y seguimiento de los sílabos.',
            ],
            [
                'id_indicador' => 3,
                'nombre' => 'Tutorías Académicas',
                'tipo' => 'Cualitativo',
                'criterio' => 'Docencia',
                'descripcion' => 'Evalúa la implementación y seguimiento de las tutorías académicas.',
            ],
            [
                'id_indicador' => 4,
                'nombre' => 'Tasa de Deserción',
                'tipo' => 'Cuantitativo',
                'criterio' => 'Docencia',
                'descripcion' => 'Mide el porcentaje de estudiantes que abandonan la carrera.',
            ],
            [
                'id_indicador' => 5,
                'nombre' => 'Tasa de Titulación',
                'tipo' => 'Cuantitativo',
                'criterio' => 'Docencia',
                'descripcion' => 'Mide el porcentaje de estudiantes graduados respecto de los matriculados en primer nivel.',
            ],
        ])->saveData();
    }
}
