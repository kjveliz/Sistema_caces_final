<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\MallaCurricularDTO;
use App\DTOs\MallaCurricularGuardadaDTO;
use mysqli;
use RuntimeException;

/**
 * Encapsula todo el SQL de I1 (Malla Curricular): antes disperso e inline
 * en api/carreras/{obtener_malla,guardar_malla}.php (hallazgo 1.2.3 del
 * Plan de Mejora — "sin capa de repositorio"). Mismas 3 consultas exactas
 * que el código original, sin cambios de comportamiento.
 *
 * Los otros 4 endpoints de api/carreras/ (listar/crear/actualizar/eliminar)
 * son administración genérica de la entidad Carrera, no del indicador I1
 * en sí, y quedan fuera del alcance de esta migración (mismo criterio que
 * dejó api/evidencias/* y api/google_drive/* sin tocar en I3/I5).
 */
final class MallaCurricularRepository
{
    public function __construct(private readonly mysqli $conexion)
    {
    }

    public function obtenerPorCodigoCarrera(string $codigoCarrera): ?MallaCurricularDTO
    {
        $sql = "
            SELECT
                m.id_malla,
                m.id_carrera,
                m.nombre_archivo,
                m.id_drive,
                m.url_drive,
                m.fecha_subida,
                m.fecha_actualizacion
            FROM Mallas_Curriculares m
            INNER JOIN Carreras c
                ON c.id_carrera = m.id_carrera
            WHERE c.codigo = ?
              AND c.activo = 1
              AND m.activo = 1
            ORDER BY m.id_malla DESC
            LIMIT 1
        ";

        $stmt = $this->conexion->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException($this->conexion->error);
        }

        $stmt->bind_param('s', $codigoCarrera);
        $stmt->execute();

        $fila = $stmt->get_result()->fetch_assoc();

        if (!$fila) {
            return null;
        }

        return new MallaCurricularDTO(
            idMalla: (int) $fila['id_malla'],
            idCarrera: (int) $fila['id_carrera'],
            nombreArchivo: $fila['nombre_archivo'],
            idDrive: $fila['id_drive'],
            urlDrive: $fila['url_drive'],
            fechaSubida: $fila['fecha_subida'],
            fechaActualizacion: $fila['fecha_actualizacion'],
        );
    }

    /** Espejo de la verificación "carrera existe y está activa" que hacía guardar_malla.php antes de insertar. */
    public function carreraExisteActiva(int $idCarrera): bool
    {
        $sql = "
            SELECT id_carrera
            FROM Carreras
            WHERE id_carrera = ?
              AND activo = 1
            LIMIT 1
        ";

        $stmt = $this->conexion->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException($this->conexion->error);
        }

        $stmt->bind_param('i', $idCarrera);
        $stmt->execute();

        return (bool) $stmt->get_result()->fetch_assoc();
    }

    /**
     * INSERT ... ON DUPLICATE KEY UPDATE, mismo comportamiento que el
     * original: si la carrera ya tenía una malla registrada, la
     * sobrescribe (nombre_archivo/id_drive/url_drive/fecha_actualizacion)
     * y la reactiva; si no, crea la fila.
     */
    public function guardar(
        int $idCarrera,
        string $nombreArchivo,
        string $idDrive,
        string $urlDrive,
    ): MallaCurricularGuardadaDTO {
        $sql = "
            INSERT INTO Mallas_Curriculares (
                id_carrera,
                nombre_archivo,
                id_drive,
                url_drive,
                activo
            )
            VALUES (?, ?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE
                nombre_archivo = VALUES(nombre_archivo),
                id_drive = VALUES(id_drive),
                url_drive = VALUES(url_drive),
                activo = 1,
                fecha_actualizacion = CURRENT_TIMESTAMP
        ";

        $stmt = $this->conexion->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException($this->conexion->error);
        }

        $stmt->bind_param('isss', $idCarrera, $nombreArchivo, $idDrive, $urlDrive);

        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }

        return new MallaCurricularGuardadaDTO(
            idCarrera: $idCarrera,
            nombreArchivo: $nombreArchivo,
            idDrive: $idDrive,
            urlDrive: $urlDrive,
        );
    }
}
