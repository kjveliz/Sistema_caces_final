<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * 2 cohortes de ejemplo para Desarrollo de Software, como pide el criterio
 * de "hecho" de la Fase 2. Depende de CarrerasSeeder.
 */
final class CohortesSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['CarrerasSeeder'];
    }

    public function run(): void
    {
        $this->table('cohortes')->insert([
            [
                'id_cohorte' => 1,
                'nombre_cohorte' => 'B2025',
                'fecha_inicio' => '2025-09-15',
                'fecha_fin' => '2027-02-27',
                'id_carrera' => 1,
            ],
            [
                'id_cohorte' => 2,
                'nombre_cohorte' => 'A2026',
                'fecha_inicio' => '2026-05-20',
                'fecha_fin' => '2027-11-20',
                'id_carrera' => 1,
            ],
        ])->saveData();
    }
}
