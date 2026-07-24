<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * 3 carreras de ejemplo (nombres reales de la oferta académica de la
 * universidad, no datos inventados) para tener un entorno de prueba
 * funcional sin depender del dump de nadie.
 */
final class CarrerasSeeder extends AbstractSeed
{
    public function run(): void
    {
        $this->table('carreras')->insert([
            [
                'id_carrera' => 1,
                'codigo' => 'DESSOF',
                'nombre' => 'Desarrollo de Software',
                'area_conocimiento' => 'Ciencias de la Ingeniería',
                'modalidad' => 'En línea',
                'activo' => 1,
            ],
            [
                'id_carrera' => 2,
                'codigo' => 'ADMFIN',
                'nombre' => 'Administración Financiera',
                'area_conocimiento' => 'Ciencias Administrativas y Empresariales',
                'modalidad' => 'En línea',
                'activo' => 1,
            ],
            [
                'id_carrera' => 3,
                'codigo' => 'INTART',
                'nombre' => 'Inteligencia Artificial',
                'area_conocimiento' => 'Ciencias de la Ingeniería',
                'modalidad' => 'En línea',
                'activo' => 1,
            ],
        ])->saveData();
    }
}
