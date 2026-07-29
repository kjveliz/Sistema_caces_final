<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\CarreraDTO;
use App\DTOs\CarreraGuardadaDTO;
use mysqli;
use RuntimeException;
use Throwable;

/**
 * Encapsula todo el SQL de administración genérica de Carreras (listar,
 * crear, actualizar, eliminar) — antes disperso e inline en
 * api/carreras/{listar,crear,actualizar,eliminar}.php (hallazgo 1.2.3 del
 * Plan de Mejora — "sin capa de repositorio"). Mismas consultas exactas
 * que el código original, sin cambios de comportamiento (incluye
 * intencionalmente las mismas asimetrías del original: crear() no exige
 * sesión ni filtra por `activo` al validar el nombre duplicado, a
 * diferencia de actualizar()/eliminar() que sí exigen sesión + rol
 * administrador — no se corrige acá, fuera de alcance de esta migración).
 *
 * Distinto de MallaCurricularRepository (I1): este repositorio cubre la
 * entidad Carrera en sí (alta/baja/modificación), no la malla curricular
 * de una carrera.
 */
final class CarrerasRepository
{
    public function __construct(private readonly mysqli $conexion)
    {
    }

    /** @return CarreraDTO[] */
    public function listarActivas(): array
    {
        $sql = "
            SELECT
                c.id_carrera,
                c.codigo,
                c.nombre,
                c.area_conocimiento,
                c.modalidad,
                c.modo_almacenamiento,
                c.ruta_almacenamiento_local,
                m.nombre_archivo AS nombre_malla,
                m.id_drive,
                m.url_drive AS url_malla
            FROM carreras c
            LEFT JOIN mallas_curriculares m
                ON m.id_carrera = c.id_carrera
               AND m.activo = 1
            WHERE c.activo = 1
            ORDER BY
                c.area_conocimiento,
                c.nombre
        ";

        $resultado = $this->conexion->query($sql);

        if (!$resultado) {
            throw new RuntimeException($this->conexion->error);
        }

        $datos = [];

        while ($fila = $resultado->fetch_assoc()) {
            $datos[] = new CarreraDTO(
                idCarrera: (int) $fila['id_carrera'],
                codigo: $fila['codigo'],
                nombre: $fila['nombre'],
                areaConocimiento: $fila['area_conocimiento'],
                modalidad: $fila['modalidad'],
                nombreMalla: $fila['nombre_malla'],
                idDrive: $fila['id_drive'],
                urlMalla: $fila['url_malla'],
                modoAlmacenamiento: $fila['modo_almacenamiento'],
                rutaAlmacenamientoLocal: $fila['ruta_almacenamiento_local'],
            );
        }

        return $datos;
    }

    /** Espejo de la comprobación de código duplicado que hacía crear.php (sin filtrar por `activo`). */
    public function codigoExiste(string $codigo): bool
    {
        $sql = "
            SELECT id_carrera, activo
            FROM carreras
            WHERE codigo = ?
            LIMIT 1
        ";

        $stmt = $this->conexion->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException($this->conexion->error);
        }

        $stmt->bind_param('s', $codigo);
        $stmt->execute();

        return (bool) $stmt->get_result()->fetch_assoc();
    }

    /** Espejo de la comprobación de nombre duplicado que hacía crear.php (sin filtrar por `activo`). */
    public function nombreExiste(string $nombre): bool
    {
        $sql = "
            SELECT id_carrera
            FROM carreras
            WHERE LOWER(nombre) = LOWER(?)
            LIMIT 1
        ";

        $stmt = $this->conexion->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException($this->conexion->error);
        }

        $stmt->bind_param('s', $nombre);
        $stmt->execute();

        return (bool) $stmt->get_result()->fetch_assoc();
    }

    public function insertar(string $codigo, string $nombre, string $areaConocimiento, string $modalidad): CarreraGuardadaDTO
    {
        $sql = "
            INSERT INTO carreras (
                codigo,
                nombre,
                area_conocimiento,
                modalidad,
                activo
            )
            VALUES (?, ?, ?, ?, 1)
        ";

        $stmt = $this->conexion->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException($this->conexion->error);
        }

        $stmt->bind_param('ssss', $codigo, $nombre, $areaConocimiento, $modalidad);

        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }

        $idCarrera = $stmt->insert_id;

        return new CarreraGuardadaDTO(
            idCarrera: $idCarrera,
            codigo: $codigo,
            nombre: $nombre,
            areaConocimiento: $areaConocimiento,
            modalidad: $modalidad,
        );
    }

    /** Espejo de la comprobación "existe y está activa" que hacía actualizar.php antes de modificar. */
    public function existeActiva(int $idCarrera): bool
    {
        $sql = "
            SELECT id_carrera
            FROM carreras
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

    /** Espejo de la comprobación de código duplicado (excluyendo la propia carrera) que hacía actualizar.php. */
    public function codigoDuplicadoParaOtraCarrera(string $codigo, int $idCarrera): bool
    {
        $sql = "
            SELECT id_carrera
            FROM carreras
            WHERE codigo = ?
              AND id_carrera <> ?
              AND activo = 1
            LIMIT 1
        ";

        $stmt = $this->conexion->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException($this->conexion->error);
        }

        $stmt->bind_param('si', $codigo, $idCarrera);
        $stmt->execute();

        return (bool) $stmt->get_result()->fetch_assoc();
    }

    public function actualizar(int $idCarrera, string $codigo, string $nombre, string $areaConocimiento, string $modalidad): CarreraGuardadaDTO
    {
        $sql = "
            UPDATE carreras
            SET
                codigo = ?,
                nombre = ?,
                area_conocimiento = ?,
                modalidad = ?
            WHERE id_carrera = ?
              AND activo = 1
        ";

        $stmt = $this->conexion->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException($this->conexion->error);
        }

        $stmt->bind_param('ssssi', $codigo, $nombre, $areaConocimiento, $modalidad, $idCarrera);

        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }

        return new CarreraGuardadaDTO(
            idCarrera: $idCarrera,
            codigo: $codigo,
            nombre: $nombre,
            areaConocimiento: $areaConocimiento,
            modalidad: $modalidad,
        );
    }

    /** @return array{id_carrera: int, nombre: string}|null Espejo de la búsqueda que hacía eliminar.php (sin filtrar por `activo`). */
    public function buscarPorId(int $idCarrera): ?array
    {
        $sql = "
            SELECT id_carrera, nombre
            FROM carreras
            WHERE id_carrera = ?
            LIMIT 1
        ";

        $stmt = $this->conexion->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException($this->conexion->error);
        }

        $stmt->bind_param('i', $idCarrera);
        $stmt->execute();

        $fila = $stmt->get_result()->fetch_assoc();

        if (!$fila) {
            return null;
        }

        return [
            'id_carrera' => (int) $fila['id_carrera'],
            'nombre' => $fila['nombre'],
        ];
    }

    public function contarEvaluaciones(int $idCarrera): int
    {
        $sql = "
            SELECT COUNT(*) AS total
            FROM evaluaciones
            WHERE id_carrera = ?
        ";

        $stmt = $this->conexion->prepare($sql);

        if (!$stmt) {
            throw new RuntimeException($this->conexion->error);
        }

        $stmt->bind_param('i', $idCarrera);
        $stmt->execute();

        $fila = $stmt->get_result()->fetch_assoc();

        return (int) ($fila['total'] ?? 0);
    }

    /**
     * Elimina primero la malla curricular relacionada y luego la carrera,
     * dentro de una transacción — mismo comportamiento que eliminar.php
     * original (protección de integridad ya cubierta antes, en el
     * controlador, vía contarEvaluaciones()).
     */
    public function eliminarTransaccional(int $idCarrera): void
    {
        $this->conexion->begin_transaction();

        try {
            $sqlMalla = "
                DELETE FROM mallas_curriculares
                WHERE id_carrera = ?
            ";

            $stmtMalla = $this->conexion->prepare($sqlMalla);

            if (!$stmtMalla) {
                throw new RuntimeException(
                    'No se pudo preparar la eliminación de la malla curricular: ' . $this->conexion->error,
                );
            }

            $stmtMalla->bind_param('i', $idCarrera);

            if (!$stmtMalla->execute()) {
                throw new RuntimeException(
                    'No se pudo eliminar la malla curricular: ' . $stmtMalla->error,
                );
            }

            $sqlCarrera = "
                DELETE FROM carreras
                WHERE id_carrera = ?
            ";

            $stmtCarrera = $this->conexion->prepare($sqlCarrera);

            if (!$stmtCarrera) {
                throw new RuntimeException(
                    'No se pudo preparar la eliminación física de la carrera: ' . $this->conexion->error,
                );
            }

            $stmtCarrera->bind_param('i', $idCarrera);

            if (!$stmtCarrera->execute()) {
                throw new RuntimeException(
                    'No se pudo eliminar la carrera: ' . $stmtCarrera->error,
                );
            }

            if ($stmtCarrera->affected_rows !== 1) {
                throw new RuntimeException('La carrera no fue eliminada de la base de datos.');
            }

            $this->conexion->commit();
        } catch (Throwable $error) {
            $this->conexion->rollback();

            throw $error;
        }
    }

    /**
     * Borrado forzado en cascada, pensado explícitamente para carreras de
     * desarrollo/pruebas: a diferencia de eliminarTransaccional() (que el
     * controller solo invoca tras confirmar contarEvaluaciones() === 0),
     * este método SÍ borra una carrera aunque tenga evaluaciones,
     * cohortes, períodos, asignaturas y evidencia relacionadas -- barre
     * TODA la cadena real de FKs que cuelga de una carrera, en el orden
     * que exigen las constraints reales del dump (ver evaluacion_caces.sql):
     *
     *   indicador_evidencia -> evidencias -> datos_tasa_titulacion
     *   -> seguimiento_syllabus -> syllabus -> tutorias -> tutorias_academicas
     *   -> asignatura (cascada automática hacia evidencia_asignatura y
     *      evidencia_validacion_pdf, que sí tienen ON DELETE CASCADE)
     *   -> periodo_academico -> evaluaciones -> cohortes
     *   -> mallas_curriculares -> carreras
     *
     * (datos_tasa_desercion no necesita borrado manual: fk_dtd_evaluacion_2026
     * ya tiene ON DELETE CASCADE hacia evaluaciones).
     *
     * Solo borra filas de la base de datos -- los archivos ya subidos a
     * Google Drive o a almacenamiento local (mallas, evidencias) NO se
     * tocan y quedan huérfanos ahí, a propósito (decisión explícita para
     * esta herramienta de desarrollo: no se integra con la API de Drive
     * ni con el filesystem local acá).
     *
     * El llamador (controller) es responsable de exigir rol administrador
     * antes de invocar este método -- no hay ninguna otra restricción
     * adicional sobre qué carreras pueden borrarse así.
     *
     * @return array{
     *   cohortes_borradas: int,
     *   evaluaciones_borradas: int,
     *   periodos_borrados: int,
     *   asignaturas_borradas: int,
     *   evidencias_borradas: int,
     * }
     */
    public function eliminarForzadaEnCascada(int $idCarrera): array
    {
        $this->conexion->begin_transaction();

        try {
            $stmtIndicadorEvidencia = $this->conexion->prepare(
                'DELETE ie FROM indicador_evidencia ie
                 JOIN evidencias e ON e.id_evidencia = ie.id_evidencia
                 JOIN evaluaciones ev ON ev.id_evaluacion = e.id_evaluacion
                 WHERE ev.id_carrera = ?',
            );
            $stmtIndicadorEvidencia->bind_param('i', $idCarrera);
            $stmtIndicadorEvidencia->execute();

            $stmtEvidencias = $this->conexion->prepare(
                'DELETE e FROM evidencias e
                 JOIN evaluaciones ev ON ev.id_evaluacion = e.id_evaluacion
                 WHERE ev.id_carrera = ?',
            );
            $stmtEvidencias->bind_param('i', $idCarrera);
            $stmtEvidencias->execute();
            $evidenciasBorradas = $stmtEvidencias->affected_rows;

            $stmtTasaTitulacion = $this->conexion->prepare(
                'DELETE d FROM datos_tasa_titulacion d
                 JOIN evaluaciones ev ON ev.id_evaluacion = d.id_evaluacion
                 WHERE ev.id_carrera = ?',
            );
            $stmtTasaTitulacion->bind_param('i', $idCarrera);
            $stmtTasaTitulacion->execute();

            $stmtSeguimientoSyllabus = $this->conexion->prepare(
                'DELETE s FROM seguimiento_syllabus s
                 JOIN evaluaciones ev ON ev.id_evaluacion = s.id_evaluacion
                 WHERE ev.id_carrera = ?',
            );
            $stmtSeguimientoSyllabus->bind_param('i', $idCarrera);
            $stmtSeguimientoSyllabus->execute();

            $stmtSyllabus = $this->conexion->prepare(
                'DELETE s FROM syllabus s
                 JOIN evaluaciones ev ON ev.id_evaluacion = s.id_evaluacion
                 WHERE ev.id_carrera = ?',
            );
            $stmtSyllabus->bind_param('i', $idCarrera);
            $stmtSyllabus->execute();

            $stmtTutorias = $this->conexion->prepare(
                'DELETE t FROM tutorias t
                 JOIN evaluaciones ev ON ev.id_evaluacion = t.id_evaluacion
                 WHERE ev.id_carrera = ?',
            );
            $stmtTutorias->bind_param('i', $idCarrera);
            $stmtTutorias->execute();

            $stmtTutoriasAcademicas = $this->conexion->prepare(
                'DELETE t FROM tutorias_academicas t
                 JOIN evaluaciones ev ON ev.id_evaluacion = t.id_evaluacion
                 WHERE ev.id_carrera = ?',
            );
            $stmtTutoriasAcademicas->bind_param('i', $idCarrera);
            $stmtTutoriasAcademicas->execute();

            $stmtAsignaturas = $this->conexion->prepare(
                'DELETE a FROM asignatura a
                 JOIN periodo_academico p ON p.id_periodoacademico = a.id_periodoacademico
                 JOIN cohortes c ON c.id_cohorte = p.id_cohorte
                 WHERE c.id_carrera = ?',
            );
            $stmtAsignaturas->bind_param('i', $idCarrera);
            $stmtAsignaturas->execute();
            $asignaturasBorradas = $stmtAsignaturas->affected_rows;

            $stmtPeriodos = $this->conexion->prepare(
                'DELETE p FROM periodo_academico p
                 JOIN cohortes c ON c.id_cohorte = p.id_cohorte
                 WHERE c.id_carrera = ?',
            );
            $stmtPeriodos->bind_param('i', $idCarrera);
            $stmtPeriodos->execute();
            $periodosBorrados = $stmtPeriodos->affected_rows;

            $stmtEvaluaciones = $this->conexion->prepare(
                'DELETE FROM evaluaciones WHERE id_carrera = ?',
            );
            $stmtEvaluaciones->bind_param('i', $idCarrera);
            $stmtEvaluaciones->execute();
            $evaluacionesBorradas = $stmtEvaluaciones->affected_rows;

            $stmtCohortes = $this->conexion->prepare(
                'DELETE FROM cohortes WHERE id_carrera = ?',
            );
            $stmtCohortes->bind_param('i', $idCarrera);
            $stmtCohortes->execute();
            $cohortesBorradas = $stmtCohortes->affected_rows;

            $stmtMalla = $this->conexion->prepare(
                'DELETE FROM mallas_curriculares WHERE id_carrera = ?',
            );
            $stmtMalla->bind_param('i', $idCarrera);
            $stmtMalla->execute();

            $stmtCarrera = $this->conexion->prepare(
                'DELETE FROM carreras WHERE id_carrera = ?',
            );
            $stmtCarrera->bind_param('i', $idCarrera);
            $stmtCarrera->execute();

            if ($stmtCarrera->affected_rows !== 1) {
                throw new RuntimeException('La carrera no fue eliminada de la base de datos.');
            }

            $this->conexion->commit();

            return [
                'cohortes_borradas' => $cohortesBorradas,
                'evaluaciones_borradas' => $evaluacionesBorradas,
                'periodos_borrados' => $periodosBorrados,
                'asignaturas_borradas' => $asignaturasBorradas,
                'evidencias_borradas' => $evidenciasBorradas,
            ];
        } catch (Throwable $error) {
            $this->conexion->rollback();

            throw $error;
        }
    }
}
