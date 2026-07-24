<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * "Algunas asignaturas" (pide el plan) del PAO 1 de la cohorte de ejemplo.
 * Nombres reales de la malla de Desarrollo de Software. Depende de
 * PeriodoAcademicoSeeder.
 */
final class AsignaturaSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['PeriodoAcademicoSeeder'];
    }

    public function run(): void
    {
        $this->table('asignatura')->insert([
            ['id_asignatura' => 1, 'id_periodoacademico' => 1, 'nombre' => 'Comunicación efectiva y trabajo en equipo', 'docente' => null, 'fecha_creacion' => '2026-07-16'],
            ['id_asignatura' => 2, 'id_periodoacademico' => 1, 'nombre' => 'Cultura tecnológica y digital', 'docente' => null, 'fecha_creacion' => '2026-07-16'],
            ['id_asignatura' => 3, 'id_periodoacademico' => 1, 'nombre' => 'Humanismo y Persona', 'docente' => null, 'fecha_creacion' => '2026-07-16'],
            ['id_asignatura' => 4, 'id_periodoacademico' => 1, 'nombre' => 'Fundamentos de Programación y Algoritmos', 'docente' => null, 'fecha_creacion' => '2026-07-16'],
            ['id_asignatura' => 5, 'id_periodoacademico' => 1, 'nombre' => 'Desarrollo de Interfaces de Usuario y Experiencia de Usuario (UI/UX)', 'docente' => null, 'fecha_creacion' => '2026-07-16'],
        ])->saveData();
    }
}
