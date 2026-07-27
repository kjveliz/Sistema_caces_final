<?php

declare(strict_types=1);

namespace App\Repositories;

use mysqli;

/**
 * Encapsula todo el SQL de I2 (Seguimiento Syllabus): antes disperso e
 * inline en 9 archivos de api/seguimiento_syllabus/*.php (mismo hallazgo
 * 1.2.3 del Plan de Mejora ya resuelto para I3 — ver TutoriasRepository).
 * Mismas consultas exactas que el código original, sin cambios de
 * comportamiento salvo el filtro por tipo documentado en
 * evidenciasVigentesPorAsignatura().
 */
final class SeguimientoSyllabusRepository
{
    public function __construct(private readonly mysqli $conexion)
    {
    }

    public function conexion(): mysqli
    {
        return $this->conexion;
    }

    /** @return array{id_asignatura: int, nombre: string}|null */
    public function asignaturaPorId(int $idAsignatura): ?array
    {
        $stmt = $this->conexion->prepare('SELECT id_asignatura, nombre FROM asignatura WHERE id_asignatura = ?');
        $stmt->bind_param('i', $idAsignatura);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();

        if ($fila === null) {
            return null;
        }

        return ['id_asignatura' => (int) $fila['id_asignatura'], 'nombre' => $fila['nombre']];
    }

    /**
     * Asignaturas de un periodo académico (PAO), igual que la consulta
     * original de asignaturas.php (GET).
     *
     * @return array<int, array{id_asignatura: int, nombre: string, docente: string|null}>
     */
    public function asignaturasPorPeriodo(int $idPeriodo): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT id_asignatura, nombre, docente FROM asignatura WHERE id_periodoacademico = ? ORDER BY nombre',
        );
        $stmt->bind_param('i', $idPeriodo);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /** Busca una asignatura existente por periodo+nombre exacto (equivalente a get_or_create de Django). */
    public function buscarAsignaturaPorPeriodoYNombre(int $idPeriodo, string $nombre): ?int
    {
        $stmt = $this->conexion->prepare('SELECT id_asignatura FROM asignatura WHERE id_periodoacademico = ? AND nombre = ?');
        $stmt->bind_param('is', $idPeriodo, $nombre);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();

        return $fila !== null ? (int) $fila['id_asignatura'] : null;
    }

    public function crearAsignatura(int $idPeriodo, string $nombre, ?string $docente): int
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO asignatura (id_periodoacademico, nombre, docente, fecha_creacion) VALUES (?, ?, ?, CURDATE())',
        );
        $stmt->bind_param('iss', $idPeriodo, $nombre, $docente);
        $stmt->execute();

        return (int) $stmt->insert_id;
    }

    /**
     * Periodos académicos (PAO) de un cohorte, igual que la consulta
     * original de periodos.php.
     *
     * @return array<int, array{id_periodoacademico: int, nombre: string, orden: int, fecha_inicio: string|null, fecha_fin: string|null}>
     */
    public function periodosPorCohorte(int $idCohorte): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT id_periodoacademico, nombre, orden, fecha_inicio, fecha_fin
             FROM periodo_academico WHERE id_cohorte = ? ORDER BY orden',
        );
        $stmt->bind_param('i', $idCohorte);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Asignaturas de un cohorte (opcionalmente filtradas por periodo),
     * misma consulta que calcularResultadoGeneral() original.
     *
     * @return array<int, array{id_asignatura: int, nombre: string}>
     */
    public function asignaturasPorCohorte(int $idCohorte, ?int $idPeriodo): array
    {
        $sql = 'SELECT a.id_asignatura, a.nombre
                FROM asignatura a
                JOIN periodo_academico p ON p.id_periodoacademico = a.id_periodoacademico
                WHERE p.id_cohorte = ?';

        if ($idPeriodo !== null) {
            $sql .= ' AND a.id_periodoacademico = ?';
            $stmt = $this->conexion->prepare($sql);
            $stmt->bind_param('ii', $idCohorte, $idPeriodo);
        } else {
            $stmt = $this->conexion->prepare($sql);
            $stmt->bind_param('i', $idCohorte);
        }

        $stmt->execute();
        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return array_map(
            fn (array $f) => ['id_asignatura' => (int) $f['id_asignatura'], 'nombre' => $f['nombre']],
            $filas,
        );
    }

    /** Docente guardado en `asignatura.docente` (NULL si nunca se llenó). */
    public function docenteActual(int $idAsignatura): ?string
    {
        $stmt = $this->conexion->prepare('SELECT docente FROM asignatura WHERE id_asignatura = ?');
        $stmt->bind_param('i', $idAsignatura);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();

        return $fila['docente'] ?? null;
    }

    /**
     * Vigencia de evidencia de NIVEL CARRERA (tabla evidencias +
     * catalogo_evidencias, ligada a id_evaluacion). Devuelve los tipos de
     * evidencia_asignatura equivalentes ya subidos.
     *
     * @return string[]
     */
    public function tiposCarreraVigentes(int $idEvaluacion): array
    {
        $sql = "SELECT c.codigo_evidencia
                FROM evidencias e
                JOIN catalogo_evidencias c ON c.id_catalogo = e.id_catalogo
                WHERE e.id_evaluacion = ?
                  AND c.codigo_evidencia IN ('DOC.SYL.01', 'DOC.SEG.01', 'DOC.SEG.06', 'DOC.SEG.07')";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('i', $idEvaluacion);
        $stmt->execute();
        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $mapa = [
            'DOC.SYL.01' => 'malla_curricular',
            'DOC.SEG.01' => 'reglamento_normativa',
            'DOC.SEG.06' => 'reporte_control_siu',
            'DOC.SEG.07' => 'reporte_avances_siu',
        ];
        $codigos = array_column($filas, 'codigo_evidencia');

        return array_values(array_intersect_key($mapa, array_flip($codigos)));
    }

    /**
     * Vigencia de evidencia de NIVEL ASIGNATURA (tabla evidencia_asignatura,
     * vigente=1) -- SIN filtrar por tipo, misma consulta que el original.
     * Se mantiene separada de evidenciasVigentesPorAsignatura() (que sí
     * filtra) porque acá solo se necesitan los tipos, no los archivos.
     *
     * @return string[]
     */
    public function tiposAsignaturaVigentes(int $idAsignatura): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT DISTINCT tipo FROM evidencia_asignatura WHERE id_asignatura = ? AND vigente = 1',
        );
        $stmt->bind_param('i', $idAsignatura);
        $stmt->execute();
        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        return array_column($filas, 'tipo');
    }

    /**
     * Evidencias vigentes (una por tipo, como máximo) de una asignatura,
     * SOLO de los tipos que pertenecen a I2.
     *
     * Fix respecto al original (evidencia_asignatura_listar.php): igual que
     * el bug ya encontrado y corregido en I3 (ver MEMORIA v63/§40.5),
     * `evidencia_asignatura` es una tabla compartida entre indicadores y el
     * original no filtraba por tipo -- acá se filtra explícitamente por
     * $tiposPermitidos para no arrastrar filas de otros indicadores (I3 usa
     * la misma tabla con sus propios 4 tipos).
     *
     * @param string[] $tiposPermitidos
     * @return array<int, array{id_evidencia_asig: int, tipo: string, nombre_archivo: string, url_archivo: string, subido_por: string|null, fecha_subida: string}>
     */
    public function evidenciasVigentesPorAsignatura(int $idAsignatura, array $tiposPermitidos): array
    {
        $placeholders = implode(',', array_fill(0, count($tiposPermitidos), '?'));

        $stmt = $this->conexion->prepare(
            "SELECT id_evidencia_asig, tipo, nombre_archivo, url_archivo, subido_por, fecha_subida
             FROM evidencia_asignatura
             WHERE id_asignatura = ? AND vigente = 1 AND tipo IN ($placeholders)",
        );
        $tipoParams = array_fill(0, count($tiposPermitidos), 's');
        $stmt->bind_param('i' . implode('', $tipoParams), $idAsignatura, ...$tiposPermitidos);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * URL de Drive del CSV de encuesta vigente de una asignatura, o null si
     * todavía no tiene uno subido. Misma consulta que
     * _buscarUrlCsvEncuestaAsignatura() original.
     */
    public function buscarUrlCsvEncuestaVigente(int $idAsignatura, string $tipoEvidenciaEncuesta): ?string
    {
        $sql = 'SELECT url_archivo
                FROM evidencia_asignatura
                WHERE id_asignatura = ?
                  AND tipo = ?
                  AND vigente = 1
                ORDER BY fecha_subida DESC
                LIMIT 1';

        $stmt = $this->conexion->prepare($sql);
        if (!$stmt) {
            error_log('SeguimientoSyllabusRepository: no se pudo preparar la búsqueda del CSV de encuesta por asignatura: ' . $this->conexion->error);

            return null;
        }

        $stmt->bind_param('is', $idAsignatura, $tipoEvidenciaEncuesta);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();

        return $fila['url_archivo'] ?? null;
    }

    /**
     * Contexto (carrera/cohorte/PAO/asignatura) para la jerarquía de
     * carpetas en Drive. Misma consulta que evidencia_asignatura_subir.php
     * original.
     *
     * @return array{asignatura: string, pao: string, cohorte: string, carrera: string}|null
     */
    public function contextoParaDrive(int $idAsignatura): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT a.nombre AS asignatura, p.nombre AS pao, co.nombre_cohorte AS cohorte, ca.nombre AS carrera, ca.id_carrera AS id_carrera
             FROM asignatura a
             JOIN periodo_academico p ON p.id_periodoacademico = a.id_periodoacademico
             JOIN cohortes co ON co.id_cohorte = p.id_cohorte
             JOIN carreras ca ON ca.id_carrera = co.id_carrera
             WHERE a.id_asignatura = ?',
        );
        $stmt->bind_param('i', $idAsignatura);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();

        return $fila ?: null;
    }

    /** Marca vigente=0 la evidencia anterior del mismo tipo/asignatura (se conserva como historial). */
    public function marcarEvidenciaAnteriorNoVigente(int $idAsignatura, string $tipo): void
    {
        $stmt = $this->conexion->prepare(
            'UPDATE evidencia_asignatura SET vigente = 0 WHERE id_asignatura = ? AND tipo = ? AND vigente = 1',
        );
        $stmt->bind_param('is', $idAsignatura, $tipo);
        $stmt->execute();
    }

    /** Inserta la nueva evidencia vigente=1 y devuelve su id. */
    public function guardarNuevaEvidencia(int $idAsignatura, string $tipo, string $nombreArchivo, string $urlArchivo, string $subidoPor): int
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO evidencia_asignatura (id_asignatura, tipo, nombre_archivo, url_archivo, subido_por, vigente)
             VALUES (?, ?, ?, ?, ?, 1)',
        );
        $stmt->bind_param('issss', $idAsignatura, $tipo, $nombreArchivo, $urlArchivo, $subidoPor);
        $stmt->execute();

        return (int) $stmt->insert_id;
    }

    /** Snapshot de auditoría en `seguimiento_syllabus` (upsert por id_asignatura + id_evaluacion). */
    public function guardarSnapshotSeguimiento(int $idAsignatura, int $idEvaluacion, array $resultado): void
    {
        $ef1 = $resultado['ef1'] ?? 0;
        $ef2 = $resultado['ef2'] ?? 0;
        $ef3 = $resultado['ef3'] ?? 0;
        $ef4 = $resultado['ef4'] ?? 0;
        $ef5 = $resultado['ef5'] ?? 0;
        $valoracionGeneral = $resultado['valoracion_general'] ?? 0;
        $categoria = $resultado['escala'];
        $estadoGeneral = $resultado['estado_general'];

        $sql = 'INSERT INTO seguimiento_syllabus
                    (id_asignatura, ef1, ef2, ef3, ef4, ef5, valoracion_general, categoria, estado_general, fecha_calculo, id_evaluacion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)
                ON DUPLICATE KEY UPDATE
                    ef1 = VALUES(ef1), ef2 = VALUES(ef2), ef3 = VALUES(ef3), ef4 = VALUES(ef4), ef5 = VALUES(ef5),
                    valoracion_general = VALUES(valoracion_general), categoria = VALUES(categoria),
                    estado_general = VALUES(estado_general), fecha_calculo = CURDATE()';

        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param(
            'iddddddssi',
            $idAsignatura,
            $ef1,
            $ef2,
            $ef3,
            $ef4,
            $ef5,
            $valoracionGeneral,
            $categoria,
            $estadoGeneral,
            $idEvaluacion,
        );
        $stmt->execute();
    }
}
