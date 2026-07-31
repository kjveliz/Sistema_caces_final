<?php

declare(strict_types=1);

namespace App\Repositories;

use mysqli;
use RuntimeException;

/**
 * Repository nuevo del Grupo C (misceláneos) del plan de migración de PHP
 * suelto a Slim (ver plan_migracion_slim_legacy_v3.txt §1/§3), Parte 10.
 * Cubre api/evaluaciones/obtener_evaluacion.php. No hay repository
 * existente que ya cubra este SELECT: CarrerasRepository y
 * SeguimientoSyllabusRepository solo tocan `evaluaciones` para DELETE en
 * cascada, no para este JOIN de lectura (decisión documentada en la
 * memoria del proyecto, sesión de la Parte 10).
 */
final class EvaluacionesRepository
{
    public function __construct(private readonly mysqli $conexion)
    {
    }

    /**
     * Misma consulta exacta (mismas columnas, mismo doble JOIN, mismo
     * WHERE con REPLACE(nombre_cohorte, ' ', ''), mismo ORDER BY
     * id_evaluacion DESC LIMIT 1) que el SELECT original de
     * api/evaluaciones/obtener_evaluacion.php. La normalización de
     * mayúsculas/espacios de $codigoCarrera y $cohorte queda a cargo del
     * llamador (Controller), igual que en el original.
     *
     * @return array{
     *     id_evaluacion: int, nombre_evaluacion: string, estado: string,
     *     fecha_inicio: ?string, fecha_fin: ?string, id_carrera: int,
     *     codigo_carrera: string, carrera: string, id_cohorte: int,
     *     nombre_cohorte: string
     * }|null
     */
    public function obtenerPorCarreraYCohorte(string $codigoCarrera, string $cohorte): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT
                e.id_evaluacion,
                e.nombre_evaluacion,
                e.estado,
                e.fecha_inicio,
                e.fecha_fin,
                c.id_carrera,
                c.codigo AS codigo_carrera,
                c.nombre AS carrera,
                co.id_cohorte,
                co.nombre_cohorte
            FROM evaluaciones e
            INNER JOIN carreras c
                ON c.id_carrera = e.id_carrera
            INNER JOIN cohortes co
                ON co.id_cohorte = e.id_cohorte
            WHERE c.codigo = ?
              AND REPLACE(co.nombre_cohorte, \' \', \'\') = ?
            ORDER BY e.id_evaluacion DESC
            LIMIT 1'
        );

        if (!$stmt) {
            throw new RuntimeException('No se pudo preparar la consulta.');
        }

        $stmt->bind_param('ss', $codigoCarrera, $cohorte);
        $stmt->execute();

        $resultado = $stmt->get_result();
        $evaluacion = $resultado->fetch_assoc();

        if (!$evaluacion) {
            return null;
        }

        $evaluacion['id_evaluacion'] = (int) $evaluacion['id_evaluacion'];
        $evaluacion['id_carrera'] = (int) $evaluacion['id_carrera'];
        $evaluacion['id_cohorte'] = (int) $evaluacion['id_cohorte'];

        return $evaluacion;
    }
}
