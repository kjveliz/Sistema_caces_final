<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\CohorteTitulacionDTO;
use App\DTOs\DatoTitulacionGuardadoDTO;
use mysqli;
use RuntimeException;

/**
 * Encapsula todo el SQL de I5 (Tasa de Titulación): antes disperso e inline
 * en api/tasa_titulacion/{guardar,obtener}.php (hallazgo 1.2.3 del Plan de
 * Mejora — "sin capa de repositorio"). Mismas consultas exactas que el
 * código original, sin cambios de comportamiento (incluye la clave
 * compuesta id_evaluacion+cohorte de `datos_tasa_titulacion`, sin columna
 * autoincremental — ver §14.13/§3104 de la memoria del proyecto).
 */
final class TitulacionRepository
{
    public function __construct(private readonly mysqli $conexion)
    {
    }

    /** @return array<int, CohorteTitulacionDTO> */
    public function obtenerPorEvaluacion(int $idEvaluacion): array
    {
        $sql = "
            SELECT
                id_evaluacion,
                cohorte,
                matriculados,
                graduados,
                tasa,
                fecha_actualizacion
            FROM datos_tasa_titulacion
            WHERE id_evaluacion = ?
            ORDER BY fecha_actualizacion DESC
        ";

        $stmt = $this->conexion->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException($this->conexion->error);
        }

        $stmt->bind_param('i', $idEvaluacion);
        $stmt->execute();

        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return array_map(
            fn (array $f) => new CohorteTitulacionDTO(
                idEvaluacion: (int) $f['id_evaluacion'],
                cohorte: $f['cohorte'],
                matriculados: $f['matriculados'] !== null ? (int) $f['matriculados'] : null,
                graduados: $f['graduados'] !== null ? (int) $f['graduados'] : null,
                tasa: $f['tasa'] !== null ? (float) $f['tasa'] : null,
                fechaActualizacion: $f['fecha_actualizacion'],
            ),
            $filas,
        );
    }

    /**
     * Crea la fila si no existe, actualiza solo el dato recibido
     * (matriculados o graduados), recalcula la tasa solo cuando existen
     * ambos valores, y devuelve el resultado final. Mismo flujo de 4
     * queries (insertar/actualizar/recalcular/leer) y misma transacción que
     * guardar.php original.
     *
     * @param int|null $matriculados null si no se está actualizando este campo en esta llamada.
     * @param int|null $graduados null si no se está actualizando este campo en esta llamada.
     */
    public function guardarDato(
        int $idEvaluacion,
        string $cohorte,
        ?int $matriculados,
        ?int $graduados,
    ): DatoTitulacionGuardadoDTO {
        $tieneMatriculados = $matriculados !== null;
        $tieneGraduados = $graduados !== null;

        $this->conexion->begin_transaction();

        try {
            /*
             * Crear la fila si aún no existe.
             */
            $sqlInsertar = "
                INSERT IGNORE INTO datos_tasa_titulacion (
                    id_evaluacion,
                    cohorte,
                    matriculados,
                    graduados,
                    tasa,
                    fecha_actualizacion
                )
                VALUES (?, ?, NULL, NULL, NULL, NOW())
            ";

            $stmtInsertar = $this->conexion->prepare($sqlInsertar);
            if (!$stmtInsertar) {
                throw new RuntimeException($this->conexion->error);
            }

            $stmtInsertar->bind_param('is', $idEvaluacion, $cohorte);
            $stmtInsertar->execute();

            /*
             * Actualizar solamente el dato recibido.
             */
            if ($tieneMatriculados) {
                $sqlActualizar = "
                    UPDATE datos_tasa_titulacion
                    SET matriculados = ?,
                        fecha_actualizacion = NOW()
                    WHERE id_evaluacion = ?
                      AND cohorte = ?
                ";

                $stmtActualizar = $this->conexion->prepare($sqlActualizar);
                $stmtActualizar->bind_param('iis', $matriculados, $idEvaluacion, $cohorte);
            } else {
                $sqlActualizar = "
                    UPDATE datos_tasa_titulacion
                    SET graduados = ?,
                        fecha_actualizacion = NOW()
                    WHERE id_evaluacion = ?
                      AND cohorte = ?
                ";

                $stmtActualizar = $this->conexion->prepare($sqlActualizar);
                $stmtActualizar->bind_param('iis', $graduados, $idEvaluacion, $cohorte);
            }

            if (!$stmtActualizar->execute()) {
                throw new RuntimeException($stmtActualizar->error);
            }

            /*
             * Recalcular solamente cuando existan
             * matriculados y graduados.
             */
            $sqlCalcular = "
                UPDATE datos_tasa_titulacion
                SET tasa =
                    CASE
                        WHEN matriculados IS NOT NULL
                         AND matriculados > 0
                         AND graduados IS NOT NULL
                        THEN ROUND(
                            (graduados / matriculados) * 100,
                            2
                        )
                        ELSE NULL
                    END,
                    fecha_actualizacion = NOW()
                WHERE id_evaluacion = ?
                  AND cohorte = ?
            ";

            $stmtCalcular = $this->conexion->prepare($sqlCalcular);
            $stmtCalcular->bind_param('is', $idEvaluacion, $cohorte);
            $stmtCalcular->execute();

            $sqlResultado = "
                SELECT
                    matriculados,
                    graduados,
                    tasa
                FROM datos_tasa_titulacion
                WHERE id_evaluacion = ?
                  AND cohorte = ?
                LIMIT 1
            ";

            $stmtResultado = $this->conexion->prepare($sqlResultado);
            $stmtResultado->bind_param('is', $idEvaluacion, $cohorte);
            $stmtResultado->execute();

            $resultado = $stmtResultado->get_result()->fetch_assoc();

            $this->conexion->commit();
        } catch (\Throwable $e) {
            $this->conexion->rollback();
            throw $e;
        }

        return new DatoTitulacionGuardadoDTO(
            idEvaluacion: $idEvaluacion,
            cohorte: $cohorte,
            matriculados: $resultado['matriculados'] !== null ? (int) $resultado['matriculados'] : null,
            graduados: $resultado['graduados'] !== null ? (int) $resultado['graduados'] : null,
            tasa: $resultado['tasa'] !== null ? (float) $resultado['tasa'] : null,
        );
    }
}
