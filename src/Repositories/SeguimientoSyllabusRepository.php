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
    /**
     * `modulo` se agrega al SELECT (no estaba antes de la Parte "3.5" del
     * plan de malla curricular xlsx, ver plan_malla_curricular_xlsx.txt §3.5):
     * sin esta columna en la respuesta, el frontend no tiene forma de
     * reconstruir el agrupador visual A/B/C por carrera real en el selector
     * de carga de evidencia -- ver StepConfigSyllabus.tsx. Agregar un campo
     * a un SELECT ya existente no rompe a ningún consumidor actual (los que
     * no lo leen, simplemente lo ignoran); confirmado contra
     * AsignaturasListarTest.php, que solo verifica `nombre`.
     */
    public function asignaturasPorPeriodo(int $idPeriodo): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT id_asignatura, nombre, docente, modulo FROM asignatura WHERE id_periodoacademico = ? ORDER BY nombre',
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

    /**
     * `modulo` (Parte 4 del plan de malla curricular xlsx, ver
     * plan_malla_curricular_xlsx.txt §7 Parte 4): agrupador visual A/B/C
     * agregado en la Parte 1 (migración `AgregarModuloAsignatura`) a la
     * columna `asignatura.modulo`. NO entra en la clave de
     * get-or-create (sigue siendo id_periodoacademico+nombre, ver
     * buscarAsignaturaPorPeriodoYNombre) -- solo se guarda/actualiza.
     */
    public function crearAsignatura(int $idPeriodo, string $nombre, ?string $docente, ?string $modulo = null): int
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO asignatura (id_periodoacademico, nombre, docente, modulo, fecha_creacion) VALUES (?, ?, ?, ?, CURDATE())',
        );
        $stmt->bind_param('isss', $idPeriodo, $nombre, $docente, $modulo);
        $stmt->execute();

        return (int) $stmt->insert_id;
    }

    /**
     * Actualiza `modulo` de una asignatura ya existente. Se usa cuando el
     * get-or-create de asignaturaCrear() encuentra una fila existente y el
     * body trae `modulo`: se pisa siempre con el valor recibido (decisión
     * acordada explícitamente -- no se compara contra el valor previo).
     */
    public function actualizarModuloAsignatura(int $idAsignatura, string $modulo): void
    {
        $stmt = $this->conexion->prepare('UPDATE asignatura SET modulo = ? WHERE id_asignatura = ?');
        $stmt->bind_param('si', $modulo, $idAsignatura);
        $stmt->execute();
    }

    /** Existencia simple de una carrera (sin exigir `activo = 1`: alcanza para validar el FK antes de insertar). */
    public function carreraExiste(int $idCarrera): bool
    {
        $stmt = $this->conexion->prepare('SELECT id_carrera FROM carreras WHERE id_carrera = ?');
        $stmt->bind_param('i', $idCarrera);
        $stmt->execute();

        return (bool) $stmt->get_result()->fetch_assoc();
    }

    /**
     * Crea una cohorte para una carrera. Parte del flujo nuevo de carga de
     * malla curricular en .xlsx al crear carrera (ver
     * plan_malla_curricular_xlsx.txt §3.2/§7 Parte 2): hoy no existía
     * ningún endpoint para crear cohortes, solo periodos.php las listaba
     * (GET) asumiendo que ya existían cargadas a mano en la BD.
     */
    public function crearCohorte(string $nombreCohorte, int $idCarrera, ?string $fechaInicio, ?string $fechaFin): int
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO cohortes (nombre_cohorte, id_carrera, fecha_inicio, fecha_fin) VALUES (?, ?, ?, ?)',
        );
        $stmt->bind_param('siss', $nombreCohorte, $idCarrera, $fechaInicio, $fechaFin);
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

    /** Existencia simple de una cohorte (alcanza para validar el FK antes de insertar un período). */
    public function cohorteExiste(int $idCohorte): bool
    {
        $stmt = $this->conexion->prepare('SELECT id_cohorte FROM cohortes WHERE id_cohorte = ?');
        $stmt->bind_param('i', $idCohorte);
        $stmt->execute();

        return (bool) $stmt->get_result()->fetch_assoc();
    }

    /**
     * Chequeo previo al borrado en cascada de una cohorte (ver
     * plan_malla_curricular_xlsx.txt §9.5 Parte A / §9 hallazgo sobre
     * FKs reales del dump). A diferencia de `seguimiento_syllabus`,
     * `syllabus` y `tutorias_academicas` (FK RESTRICT hacia
     * `asignatura`, que ya protegen solas rechazando cualquier DELETE
     * con datos reales), `evidencia_asignatura.id_asignatura` tiene
     * `ON DELETE CASCADE` -- borrar una asignatura con evidencia real
     * subida ahí la eliminaría en silencio. Este método es la
     * protección equivalente, hecha a mano, para ese único caso sin
     * cobertura de FK.
     */
    public function cohorteTieneEvidenciaAsignaturaReal(int $idCohorte): bool
    {
        $stmt = $this->conexion->prepare(
            'SELECT ea.id_evidencia_asig
             FROM evidencia_asignatura ea
             JOIN asignatura a ON a.id_asignatura = ea.id_asignatura
             JOIN periodo_academico p ON p.id_periodoacademico = a.id_periodoacademico
             WHERE p.id_cohorte = ?
             LIMIT 1',
        );
        $stmt->bind_param('i', $idCohorte);
        $stmt->execute();

        return (bool) $stmt->get_result()->fetch_assoc();
    }

    /**
     * Borra en cascada una cohorte y todo lo que genera el flujo de
     * carga de malla curricular en .xlsx (ver
     * plan_malla_curricular_xlsx.txt §9.5 Parte A): la evidencia
     * DOC.SYL.01 auto-registrada por `crear.php` (si la hay) + su fila
     * en `indicador_evidencia`, las asignaturas de todos los períodos,
     * los períodos, la evaluación asociada (si la hay) y la cohorte.
     * Todo en una única transacción.
     *
     * El llamador (controller) debe validar antes con `cohorteExiste()`
     * y `cohorteTieneEvidenciaAsignaturaReal()` -- este método asume
     * que ambos chequeos ya pasaron. Si de todos modos hay datos reales
     * que ninguno de los 2 chequeos cubre (ej. filas en
     * `seguimiento_syllabus`/`syllabus`/`tutorias_academicas`), la FK
     * RESTRICT correspondiente hace fallar el DELETE de `asignatura` a
     * mitad de camino -- la excepción resultante hace rollback de toda
     * la transacción (nada queda a medio borrar) y el controller la
     * traduce a 409 (mismo criterio que crear.php con errno 1062).
     *
     * @return array{periodos_borrados: int, asignaturas_borradas: int, evidencia_borrada: bool}
     */
    public function eliminarCohorteEnCascada(int $idCohorte): array
    {
        $this->conexion->begin_transaction();

        try {
            $stmtEvaluacion = $this->conexion->prepare(
                'SELECT id_evaluacion FROM evaluaciones WHERE id_cohorte = ? LIMIT 1',
            );
            $stmtEvaluacion->bind_param('i', $idCohorte);
            $stmtEvaluacion->execute();
            $filaEvaluacion = $stmtEvaluacion->get_result()->fetch_assoc();
            $idEvaluacion = $filaEvaluacion !== null ? (int) $filaEvaluacion['id_evaluacion'] : null;

            $evidenciaBorrada = false;

            if ($idEvaluacion !== null) {
                $stmtEvidencia = $this->conexion->prepare(
                    "SELECT e.id_evidencia
                     FROM evidencias e
                     JOIN catalogo_evidencias c ON c.id_catalogo = e.id_catalogo
                     WHERE e.id_evaluacion = ?
                       AND c.codigo_evidencia = 'DOC.SYL.01'
                     LIMIT 1",
                );
                $stmtEvidencia->bind_param('i', $idEvaluacion);
                $stmtEvidencia->execute();
                $filaEvidencia = $stmtEvidencia->get_result()->fetch_assoc();

                if ($filaEvidencia !== null) {
                    $idEvidencia = (int) $filaEvidencia['id_evidencia'];

                    $stmtIndicadorEvidencia = $this->conexion->prepare(
                        'DELETE FROM indicador_evidencia WHERE id_evidencia = ?',
                    );
                    $stmtIndicadorEvidencia->bind_param('i', $idEvidencia);
                    $stmtIndicadorEvidencia->execute();

                    $stmtEliminarEvidencia = $this->conexion->prepare(
                        'DELETE FROM evidencias WHERE id_evidencia = ?',
                    );
                    $stmtEliminarEvidencia->bind_param('i', $idEvidencia);
                    $stmtEliminarEvidencia->execute();

                    $evidenciaBorrada = true;
                }
            }

            $stmtAsignaturas = $this->conexion->prepare(
                'DELETE a FROM asignatura a
                 JOIN periodo_academico p ON p.id_periodoacademico = a.id_periodoacademico
                 WHERE p.id_cohorte = ?',
            );
            $stmtAsignaturas->bind_param('i', $idCohorte);
            $stmtAsignaturas->execute();
            $asignaturasBorradas = $stmtAsignaturas->affected_rows;

            $stmtPeriodos = $this->conexion->prepare(
                'DELETE FROM periodo_academico WHERE id_cohorte = ?',
            );
            $stmtPeriodos->bind_param('i', $idCohorte);
            $stmtPeriodos->execute();
            $periodosBorrados = $stmtPeriodos->affected_rows;

            if ($idEvaluacion !== null) {
                $stmtEliminarEvaluacion = $this->conexion->prepare(
                    'DELETE FROM evaluaciones WHERE id_evaluacion = ?',
                );
                $stmtEliminarEvaluacion->bind_param('i', $idEvaluacion);
                $stmtEliminarEvaluacion->execute();
            }

            $stmtCohorte = $this->conexion->prepare('DELETE FROM cohortes WHERE id_cohorte = ?');
            $stmtCohorte->bind_param('i', $idCohorte);
            $stmtCohorte->execute();

            $this->conexion->commit();

            return [
                'periodos_borrados' => $periodosBorrados,
                'asignaturas_borradas' => $asignaturasBorradas,
                'evidencia_borrada' => $evidenciaBorrada,
            ];
        } catch (\Throwable $e) {
            $this->conexion->rollback();

            throw $e;
        }
    }

    /**
     * Borra forzadamente una cohorte y toda su cadena relacionada, sin el
     * chequeo de `cohorteTieneEvidenciaAsignaturaReal()` que bloquea a
     * `eliminarCohorteEnCascada()` con 409. Mismo criterio y mismo alcance
     * de tablas que `CarrerasRepository::eliminarForzadaEnCascada()`
     * (herramienta de desarrollo/pruebas), pero acotado a una sola cohorte
     * (`id_cohorte`) en vez de a toda una carrera (`id_carrera`) -- acá
     * `evaluaciones.id_cohorte` es 0 o 1 fila, a diferencia de la versión
     * de carrera que puede tener varias evaluaciones por carrera.
     *
     * Solo borra filas de la BD; los archivos ya subidos a Drive/local
     * quedan huérfanos a propósito, igual que la versión de carrera.
     * `evidencia_asignatura` y `evidencia_validacion_pdf` no se tocan a
     * mano: tienen `ON DELETE CASCADE` hacia `asignatura`, igual que
     * `datos_tasa_desercion` hacia `evaluaciones` -- se van solos cuando se
     * borra la fila padre. El llamador (controller) debe validar antes con
     * `cohorteExiste()`; este método no repite ese chequeo.
     *
     * @return array{evaluaciones_borradas: int, periodos_borrados: int, asignaturas_borradas: int, evidencias_borradas: int}
     */
    public function eliminarForzadaCohorteEnCascada(int $idCohorte): array
    {
        $this->conexion->begin_transaction();

        try {
            $stmtIndicadorEvidencia = $this->conexion->prepare(
                'DELETE ie FROM indicador_evidencia ie
                 JOIN evidencias e ON e.id_evidencia = ie.id_evidencia
                 JOIN evaluaciones ev ON ev.id_evaluacion = e.id_evaluacion
                 WHERE ev.id_cohorte = ?',
            );
            $stmtIndicadorEvidencia->bind_param('i', $idCohorte);
            $stmtIndicadorEvidencia->execute();

            $stmtEvidencias = $this->conexion->prepare(
                'DELETE e FROM evidencias e
                 JOIN evaluaciones ev ON ev.id_evaluacion = e.id_evaluacion
                 WHERE ev.id_cohorte = ?',
            );
            $stmtEvidencias->bind_param('i', $idCohorte);
            $stmtEvidencias->execute();
            $evidenciasBorradas = $stmtEvidencias->affected_rows;

            $stmtTasaTitulacion = $this->conexion->prepare(
                'DELETE d FROM datos_tasa_titulacion d
                 JOIN evaluaciones ev ON ev.id_evaluacion = d.id_evaluacion
                 WHERE ev.id_cohorte = ?',
            );
            $stmtTasaTitulacion->bind_param('i', $idCohorte);
            $stmtTasaTitulacion->execute();

            $stmtSeguimientoSyllabus = $this->conexion->prepare(
                'DELETE s FROM seguimiento_syllabus s
                 JOIN evaluaciones ev ON ev.id_evaluacion = s.id_evaluacion
                 WHERE ev.id_cohorte = ?',
            );
            $stmtSeguimientoSyllabus->bind_param('i', $idCohorte);
            $stmtSeguimientoSyllabus->execute();

            $stmtSyllabus = $this->conexion->prepare(
                'DELETE s FROM syllabus s
                 JOIN evaluaciones ev ON ev.id_evaluacion = s.id_evaluacion
                 WHERE ev.id_cohorte = ?',
            );
            $stmtSyllabus->bind_param('i', $idCohorte);
            $stmtSyllabus->execute();

            $stmtTutorias = $this->conexion->prepare(
                'DELETE t FROM tutorias t
                 JOIN evaluaciones ev ON ev.id_evaluacion = t.id_evaluacion
                 WHERE ev.id_cohorte = ?',
            );
            $stmtTutorias->bind_param('i', $idCohorte);
            $stmtTutorias->execute();

            $stmtTutoriasAcademicas = $this->conexion->prepare(
                'DELETE t FROM tutorias_academicas t
                 JOIN evaluaciones ev ON ev.id_evaluacion = t.id_evaluacion
                 WHERE ev.id_cohorte = ?',
            );
            $stmtTutoriasAcademicas->bind_param('i', $idCohorte);
            $stmtTutoriasAcademicas->execute();

            $stmtAsignaturas = $this->conexion->prepare(
                'DELETE a FROM asignatura a
                 JOIN periodo_academico p ON p.id_periodoacademico = a.id_periodoacademico
                 WHERE p.id_cohorte = ?',
            );
            $stmtAsignaturas->bind_param('i', $idCohorte);
            $stmtAsignaturas->execute();
            $asignaturasBorradas = $stmtAsignaturas->affected_rows;

            $stmtPeriodos = $this->conexion->prepare(
                'DELETE FROM periodo_academico WHERE id_cohorte = ?',
            );
            $stmtPeriodos->bind_param('i', $idCohorte);
            $stmtPeriodos->execute();
            $periodosBorrados = $stmtPeriodos->affected_rows;

            $stmtEvaluaciones = $this->conexion->prepare(
                'DELETE FROM evaluaciones WHERE id_cohorte = ?',
            );
            $stmtEvaluaciones->bind_param('i', $idCohorte);
            $stmtEvaluaciones->execute();
            $evaluacionesBorradas = $stmtEvaluaciones->affected_rows;

            $stmtCohorte = $this->conexion->prepare('DELETE FROM cohortes WHERE id_cohorte = ?');
            $stmtCohorte->bind_param('i', $idCohorte);
            $stmtCohorte->execute();

            if ($stmtCohorte->affected_rows !== 1) {
                throw new \RuntimeException('La cohorte no fue eliminada de la base de datos.');
            }

            $this->conexion->commit();

            return [
                'evaluaciones_borradas' => $evaluacionesBorradas,
                'periodos_borrados' => $periodosBorrados,
                'asignaturas_borradas' => $asignaturasBorradas,
                'evidencias_borradas' => $evidenciasBorradas,
            ];
        } catch (\Throwable $e) {
            $this->conexion->rollback();

            throw $e;
        }
    }

    /**
     * Crea un período académico (PAO) para una cohorte. Parte del flujo de
     * carga de malla curricular en .xlsx (ver plan_malla_curricular_xlsx.txt
     * §3.2/§7 Parte 3): hoy solo existía el GET (periodos.php), que asumía
     * que los 3 PAO ya estaban cargados a mano en la BD.
     *
     * `orden` es requerido (a diferencia de `crearCohorte`, donde las fechas
     * son opcionales): periodosPorCohorte() ya ordena por esta columna, así
     * que un período sin orden quedaría mal ubicado en el selector de PAO.
     */
    public function crearPeriodo(int $idCohorte, string $nombre, int $orden, ?string $fechaInicio, ?string $fechaFin): int
    {
        $stmt = $this->conexion->prepare(
            'INSERT INTO periodo_academico (id_cohorte, nombre, orden, fecha_inicio, fecha_fin) VALUES (?, ?, ?, ?, ?)',
        );
        $stmt->bind_param('isiss', $idCohorte, $nombre, $orden, $fechaInicio, $fechaFin);
        $stmt->execute();

        return (int) $stmt->insert_id;
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
