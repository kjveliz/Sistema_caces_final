<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\CohorteDesercionDTO;
use App\DTOs\DatoDesercionGuardadoDTO;
use mysqli;
use RuntimeException;

/**
 * Encapsula todo el SQL de I4 (Tasa de Deserción): antes disperso e inline
 * en api/tasa_desercion/{guardar,obtener}.php (hallazgo 1.2.3 del Plan de
 * Mejora — "sin capa de repositorio"). Mismas consultas exactas que el
 * código original, sin cambios de comportamiento -- incluida la advertencia
 * de inconsistencia entre iniciaron_primer_nivel/matriculados_segundo_anio/
 * no_continuaron que calculaba guardar.php al final de la transacción.
 */
final class DesercionRepository
{
    public function __construct(private readonly mysqli $conexion)
    {
    }

    /** @return array<int, CohorteDesercionDTO> */
    public function obtenerPorEvaluacion(int $idEvaluacion): array
    {
        $sql = "
            SELECT
                id_dato,
                id_evaluacion,
                cohorte,
                iniciaron_primer_nivel,
                matriculados_segundo_anio,
                no_continuaron,
                tasa,
                fecha_actualizacion
            FROM datos_tasa_desercion
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
            fn (array $f) => new CohorteDesercionDTO(
                idDato: (int) $f['id_dato'],
                idEvaluacion: (int) $f['id_evaluacion'],
                cohorte: $f['cohorte'],
                iniciaronPrimerNivel: $f['iniciaron_primer_nivel'] !== null ? (int) $f['iniciaron_primer_nivel'] : null,
                matriculadosSegundoAnio: $f['matriculados_segundo_anio'] !== null ? (int) $f['matriculados_segundo_anio'] : null,
                noContinuaron: $f['no_continuaron'] !== null ? (int) $f['no_continuaron'] : null,
                tasa: $f['tasa'] !== null ? (float) $f['tasa'] : null,
                fechaActualizacion: $f['fecha_actualizacion'],
            ),
            $filas,
        );
    }

    /**
     * Crea la fila si no existe, actualiza solo el dato recibido
     * (iniciaron_primer_nivel, matriculados_segundo_anio o
     * no_continuaron -- en ese orden de prioridad, igual que el
     * if/elseif/else de guardar.php original), recalcula la tasa
     * (no_continuaron / iniciaron_primer_nivel * 100, redondeada a 2
     * decimales) solo cuando existen iniciaron_primer_nivel Y
     * no_continuaron, y devuelve el resultado final más una advertencia si
     * los 3 valores no son consistentes entre sí (iniciaron_primer_nivel -
     * no_continuaron debería ser igual a matriculados_segundo_anio). Mismo
     * flujo de 4 queries (insertar/actualizar/recalcular/leer) y misma
     * transacción que guardar.php original.
     *
     * @param int|null $primerNivel null si no se está actualizando este campo en esta llamada.
     * @param int|null $segundoAnio null si no se está actualizando este campo en esta llamada.
     * @param int|null $noContinuaron null si no se está actualizando este campo en esta llamada.
     */
    public function guardarDato(
        int $idEvaluacion,
        string $cohorte,
        ?int $primerNivel,
        ?int $segundoAnio,
        ?int $noContinuaron,
    ): DatoDesercionGuardadoDTO {
        $tienePrimerNivel = $primerNivel !== null;
        $tieneSegundoAnio = $segundoAnio !== null;
        $tieneNoContinuaron = $noContinuaron !== null;

        $this->conexion->begin_transaction();

        try {
            /*
             * Crear la fila si aún no existe.
             */
            $sqlInsertar = "
                INSERT IGNORE INTO datos_tasa_desercion (
                    id_evaluacion,
                    cohorte,
                    iniciaron_primer_nivel,
                    matriculados_segundo_anio,
                    no_continuaron,
                    tasa,
                    fecha_actualizacion
                )
                VALUES (?, ?, NULL, NULL, NULL, NULL, NOW())
            ";

            $stmtInsertar = $this->conexion->prepare($sqlInsertar);
            if (!$stmtInsertar) {
                throw new RuntimeException($this->conexion->error);
            }

            $stmtInsertar->bind_param('is', $idEvaluacion, $cohorte);
            $stmtInsertar->execute();

            /*
             * Actualizar solamente el dato recibido, con el mismo orden de
             * prioridad que el original: primer nivel, luego segundo año,
             * luego no continuaron.
             */
            if ($tienePrimerNivel) {
                $sqlActualizar = "
                    UPDATE datos_tasa_desercion
                    SET iniciaron_primer_nivel = ?,
                        fecha_actualizacion = NOW()
                    WHERE id_evaluacion = ?
                      AND cohorte = ?
                ";

                $stmtActualizar = $this->conexion->prepare($sqlActualizar);
                $stmtActualizar->bind_param('iis', $primerNivel, $idEvaluacion, $cohorte);
            } elseif ($tieneSegundoAnio) {
                $sqlActualizar = "
                    UPDATE datos_tasa_desercion
                    SET matriculados_segundo_anio = ?,
                        fecha_actualizacion = NOW()
                    WHERE id_evaluacion = ?
                      AND cohorte = ?
                ";

                $stmtActualizar = $this->conexion->prepare($sqlActualizar);
                $stmtActualizar->bind_param('iis', $segundoAnio, $idEvaluacion, $cohorte);
            } else {
                $sqlActualizar = "
                    UPDATE datos_tasa_desercion
                    SET no_continuaron = ?,
                        fecha_actualizacion = NOW()
                    WHERE id_evaluacion = ?
                      AND cohorte = ?
                ";

                $stmtActualizar = $this->conexion->prepare($sqlActualizar);
                $stmtActualizar->bind_param('iis', $noContinuaron, $idEvaluacion, $cohorte);
            }

            if (!$stmtActualizar->execute()) {
                throw new RuntimeException($stmtActualizar->error);
            }

            /*
             * Recalcular solamente cuando existan iniciaron_primer_nivel
             * y no_continuaron.
             */
            $sqlCalcular = "
                UPDATE datos_tasa_desercion
                SET tasa =
                    CASE
                        WHEN iniciaron_primer_nivel IS NOT NULL
                         AND iniciaron_primer_nivel > 0
                         AND no_continuaron IS NOT NULL
                        THEN ROUND(
                            (no_continuaron / iniciaron_primer_nivel) * 100,
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
                    iniciaron_primer_nivel,
                    matriculados_segundo_anio,
                    no_continuaron,
                    tasa
                FROM datos_tasa_desercion
                WHERE id_evaluacion = ?
                  AND cohorte = ?
                LIMIT 1
            ";

            $stmtResultado = $this->conexion->prepare($sqlResultado);
            $stmtResultado->bind_param('is', $idEvaluacion, $cohorte);
            $stmtResultado->execute();

            $resultado = $stmtResultado->get_result()->fetch_assoc();

            $advertencia = null;

            if (
                $resultado['iniciaron_primer_nivel'] !== null
                && $resultado['matriculados_segundo_anio'] !== null
                && $resultado['no_continuaron'] !== null
            ) {
                $continuaronCalculados =
                    (int) $resultado['iniciaron_primer_nivel']
                    - (int) $resultado['no_continuaron'];

                if ($continuaronCalculados !== (int) $resultado['matriculados_segundo_anio']) {
                    $advertencia = 'Los datos no coinciden: primer nivel menos no continuaron '
                        . 'debería ser igual a matriculados de segundo año.';
                }
            }

            $this->conexion->commit();
        } catch (\Throwable $e) {
            $this->conexion->rollback();
            throw $e;
        }

        return new DatoDesercionGuardadoDTO(
            idEvaluacion: $idEvaluacion,
            cohorte: $cohorte,
            iniciaronPrimerNivel: $resultado['iniciaron_primer_nivel'] !== null ? (int) $resultado['iniciaron_primer_nivel'] : null,
            matriculadosSegundoAnio: $resultado['matriculados_segundo_anio'] !== null ? (int) $resultado['matriculados_segundo_anio'] : null,
            noContinuaron: $resultado['no_continuaron'] !== null ? (int) $resultado['no_continuaron'] : null,
            tasa: $resultado['tasa'] !== null ? (float) $resultado['tasa'] : null,
            advertencia: $advertencia,
        );
    }
}
