<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Los 3 PAO de la primera cohorte de ejemplo. Depende de CohortesSeeder.
 */
final class PeriodoAcademicoSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['CohortesSeeder'];
    }

    public function run(): void
    {
        $this->table('periodo_academico')->insert([
            ['id_periodoacademico' => 1, 'id_cohorte' => 1, 'nombre' => 'PAO 1', 'orden' => 1, 'fecha_inicio' => null, 'fecha_fin' => null],
            ['id_periodoacademico' => 2, 'id_cohorte' => 1, 'nombre' => 'PAO 2', 'orden' => 2, 'fecha_inicio' => null, 'fecha_fin' => null],
            ['id_periodoacademico' => 3, 'id_cohorte' => 1, 'nombre' => 'PAO 3', 'orden' => 3, 'fecha_inicio' => null, 'fecha_fin' => null],
        ])->saveData();
    }
}
