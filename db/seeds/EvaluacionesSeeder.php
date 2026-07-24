<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * 2 evaluaciones de ejemplo, como pide el criterio de "hecho" de la Fase 2.
 * Depende de CarrerasSeeder, CohortesSeeder y UsuariosSeeder.
 */
final class EvaluacionesSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['CarrerasSeeder', 'CohortesSeeder', 'UsuariosSeeder'];
    }

    public function run(): void
    {
        $this->table('evaluaciones')->insert([
            [
                'id_evaluacion' => 1,
                'nombre_evaluacion' => 'Evaluación Desarrollo de Software - B2025',
                'id_cohorte' => 1,
                'fecha_inicio' => null,
                'fecha_fin' => null,
                'estado' => 'Activa',
                'id_usuario' => 1,
                'id_carrera' => 1,
            ],
            [
                'id_evaluacion' => 2,
                'nombre_evaluacion' => 'Evaluación Desarrollo de Software A2026',
                'id_cohorte' => 2,
                'fecha_inicio' => '2026-05-20',
                'fecha_fin' => '2027-11-20',
                'estado' => 'Activa',
                'id_usuario' => 1,
                'id_carrera' => 1,
            ],
        ])->saveData();
    }
}
