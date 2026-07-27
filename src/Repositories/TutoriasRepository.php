<?php

declare(strict_types=1);

namespace App\Repositories;

use mysqli;

/**
 * Encapsula todo el SQL de I3 (Tutorías Académicas): antes disperso e
 * inline en 6 archivos de api/tutorias_academicas/*.php (hallazgo 1.2.3
 * del Plan de Mejora — "sin capa de repositorio"). Mismas consultas
 * exactas que el código original, sin cambios de comportamiento.
 */
final class TutoriasRepository
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
     * Asignaturas de un cohorte (opcionalmente filtradas por periodo), igual
     * que la consulta original en calcularResultadoGeneralTutorias().
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

    /**
     * Última validación de cada punto de cada EF de una asignatura (solo de
     * la evidencia vigente=1 de cada tipo). Misma consulta y misma lógica de
     * "nos quedamos con la más reciente por (ef, punto_nombre)" que el
     * obtenerValidacionesPorEf() original.
     *
     * @return array<string, array{puntos: array<int, array{nombre: string, cumplido: bool, valor: string|null}>, total_puntos: int, cumplidos: int}>
     */
    public function obtenerValidacionesPorEf(int $idAsignatura): array
    {
        $sql = 'SELECT v.ef, v.punto_nombre, v.cumplido, v.valor_extraido
                FROM evidencia_validacion_pdf v
                JOIN evidencia_asignatura e ON e.id_evidencia_asig = v.id_evidencia_asig
                WHERE e.id_asignatura = ? AND e.vigente = 1
                ORDER BY v.fecha_validacion DESC';
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('i', $idAsignatura);
        $stmt->execute();
        $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $vistos = [];
        $porEf = [];
        foreach ($filas as $fila) {
            $clave = $fila['ef'] . '|' . $fila['punto_nombre'];
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            $porEf[$fila['ef']]['puntos'][] = [
                'nombre' => $fila['punto_nombre'],
                'cumplido' => (bool) $fila['cumplido'],
                'valor' => $fila['valor_extraido'],
            ];
        }

        foreach ($porEf as $ef => &$datos) {
            $datos['total_puntos'] = count($datos['puntos']);
            $datos['cumplidos'] = count(array_filter($datos['puntos'], fn (array $p) => $p['cumplido']));
        }
        unset($datos);

        return $porEf;
    }

    /**
     * Evidencias vigentes (una por tipo, como máximo) de una asignatura,
     * SOLO de los 4 tipos que pertenecen a I3.
     *
     * Fix respecto al original (evidencia_listar.php): `evidencia_asignatura`
     * es una tabla compartida entre indicadores (ej. I2 guarda ahí filas con
     * tipo='syllabus'). El original no filtraba por tipo y ya tenía un bug
     * latente con eso (el array resultante quedaba mal formado para tipos
     * ajenos a I3, sin tirar error). Acá se filtra explícitamente por los
     * tipos de TutoriasCalculoService::TIPOS_TUTORIAS para no arrastrar
     * filas de otros indicadores — encontrado al probar este endpoint
     * contra datos reales tras la migración a Slim.
     *
     * @return array<int, array{id_evidencia_asig: int, tipo: string, nombre_archivo: string, url_archivo: string, subido_por: string|null, fecha_subida: string}>
     */
    public function evidenciasVigentesPorAsignatura(int $idAsignatura): array
    {
        $tipos = \App\Services\TutoriasCalculoService::TIPOS_TUTORIAS;
        $placeholders = implode(',', array_fill(0, count($tipos), '?'));

        $stmt = $this->conexion->prepare(
            "SELECT id_evidencia_asig, tipo, nombre_archivo, url_archivo, subido_por, fecha_subida
             FROM evidencia_asignatura
             WHERE id_asignatura = ? AND vigente = 1 AND tipo IN ($placeholders)"
        );
        $tipoParams = array_fill(0, count($tipos), 's');
        $stmt->bind_param('i' . implode('', $tipoParams), $idAsignatura, ...$tipos);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Contexto (carrera/cohorte/PAO/asignatura) para la jerarquía de
     * carpetas en Drive. Misma consulta que evidencia_subir.php original.
     *
     * @return array{asignatura: string, pao: string, cohorte: string, carrera: string}|null
     */
    public function contextoParaDrive(int $idAsignatura): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT a.nombre AS asignatura, p.nombre AS pao, co.nombre_cohorte AS cohorte, ca.nombre AS carrera
             FROM asignatura a
             JOIN periodo_academico p ON p.id_periodoacademico = a.id_periodoacademico
             JOIN cohortes co ON co.id_cohorte = p.id_cohorte
             JOIN carreras ca ON ca.id_carrera = co.id_carrera
             WHERE a.id_asignatura = ?'
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
            'UPDATE evidencia_asignatura SET vigente = 0 WHERE id_asignatura = ? AND tipo = ? AND vigente = 1'
        );
        $stmt->bind_param('is', $idAsignatura, $tipo);
        $stmt->execute();
    }

    /** Inserta la nueva evidencia vigente=1 y devuelve su id. */
    public function guardarNuevaEvidencia(int $idAsignatura, string $tipo, string $nombreArchivo, string $urlArchivo, string $subidoPor): int
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO evidencia_asignatura (id_asignatura, tipo, nombre_archivo, url_archivo, subido_por, vigente)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        $stmt->bind_param('issss', $idAsignatura, $tipo, $nombreArchivo, $urlArchivo, $subidoPor);
        $stmt->execute();

        return (int) $stmt->insert_id;
    }

    /**
     * Guarda un punto de validación del PDF recién subido.
     *
     * @param string|null $valorExtraido
     */
    public function guardarPuntoValidacion(int $idEvidenciaAsig, string $ef, int $puntoOrden, string $puntoNombre, bool $cumplido, ?string $valorExtraido): void
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO evidencia_validacion_pdf (id_evidencia_asig, ef, punto_orden, punto_nombre, cumplido, valor_extraido)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $cumplidoInt = $cumplido ? 1 : 0;
        // Tipos: i(id_evidencia_asig) s(ef) i(punto_orden) s(punto_nombre) i(cumplido) s(valor_extraido) = "isisis".
        $stmt->bind_param('isisis', $idEvidenciaAsig, $ef, $puntoOrden, $puntoNombre, $cumplidoInt, $valorExtraido);
        $stmt->execute();
    }

    /** Snapshot de auditoría en `tutorias_academicas` (upsert por id_asignatura + id_evaluacion). */
    public function guardarSnapshot(int $idAsignatura, int $idEvaluacion, array $resultado): void
    {
        $ef1 = $resultado['efs']['EF1']['pct'] ?? null;
        $ef2 = $resultado['efs']['EF2']['pct'] ?? null;
        $ef3 = $resultado['efs']['EF3']['pct'] ?? null;
        $ef4 = $resultado['efs']['EF4']['pct'] ?? null;
        $valoracionGeneral = $resultado['valoracion_general'];
        $categoria = $resultado['escala'];
        $estadoGeneral = $resultado['estado_general'];

        $sql = 'INSERT INTO tutorias_academicas
                    (id_asignatura, ef1, ef2, ef3, ef4, valoracion_general, categoria, estado_general, fecha_calculo, id_evaluacion)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)
                ON DUPLICATE KEY UPDATE
                    ef1 = VALUES(ef1), ef2 = VALUES(ef2), ef3 = VALUES(ef3), ef4 = VALUES(ef4),
                    valoracion_general = VALUES(valoracion_general), categoria = VALUES(categoria),
                    estado_general = VALUES(estado_general), fecha_calculo = CURDATE()';

        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param(
            'iddddsssi',
            $idAsignatura,
            $ef1,
            $ef2,
            $ef3,
            $ef4,
            $valoracionGeneral,
            $categoria,
            $estadoGeneral,
            $idEvaluacion,
        );
        $stmt->execute();
    }
}
