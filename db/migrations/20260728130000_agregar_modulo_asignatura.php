<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Agrupador visual "módulo" (A/B/C) por asignatura.
 *
 * Ver plan_malla_curricular_xlsx.txt §3.1 / §7 Parte 1.
 *
 * NO es un concepto de negocio de I1 (I1 como indicador fue descartado
 * por el coordinador — ver plan §0). Sirve únicamente para poder
 * reconstruir, por carrera real, el agrupador que hoy en el frontend
 * viene hardcodeado en el mock MATERIAS_BY_PAO_MODULE
 * (frontend/src/shared/data/academic.ts) y que solo cubre la malla de
 * una carrera (Desarrollo de Software). Al cargar evidencia en I2/I3,
 * el "módulo" evita listar de una sola vez todas las asignaturas del
 * período; no participa en la resolución real de asignaturaId (eso
 * sigue siendo cohorte + período + materia, sin tocar).
 *
 * Reversible con change() (no requiere execute() de datos, a
 * diferencia de 20260728120000_default_local_almacenamiento_carreras.php).
 */
final class AgregarModuloAsignatura extends AbstractMigration
{
    public function change(): void
    {
        $this->table('asignatura')
            ->addColumn('modulo', 'string', [
                'limit' => 1,
                'null' => true,
                'after' => 'docente',
            ])
            ->update();
    }
}
