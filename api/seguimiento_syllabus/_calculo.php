<?php
require_once __DIR__ . '/_encuesta.php';

const TIPOS_POR_CARRERA = ['malla_curricular', 'reglamento_normativa'];
// 'encuesta_csv' (slot 5 de I2) se agrega aquí a partir de la migración
// sql/migracion_i2_encuesta_csv_por_asignatura.sql: el CSV de la encuesta de
// heteroevaluación ahora se sube por-asignatura, igual que los otros 4
// documentos de I2, en vez de ser un único archivo evaluation-wide con
// filtrado de filas por nombre de materia (ver MEMORIA v18).
const TIPOS_POR_ASIGNATURA = ['syllabus', 'acta_retroalimentacion', 'acta_ajuste_curricular', 'evidencia_difusion', 'encuesta_csv'];

function etiquetasEvidencia(): array
{
    return [
        'malla_curricular'       => 'Malla Curricular',
        'syllabus'                => 'Syllabus',
        'acta_retroalimentacion'  => 'Acta de Retroalimentación',
        'acta_ajuste_curricular'  => 'Acta de Ajuste Curricular (EF2)',
        'evidencia_difusion'      => 'Evidencia de Difusión (EF3)',
        'reglamento_normativa'    => 'Reglamento / Normativa Institucional (EF5)',
        'encuesta_csv'            => 'Resultados de Encuesta (CSV)',
    ];
}

function calcularEscala(?float $valoracion): array
{
    if ($valoracion === null) {
        return [null, null];
    }
    if ($valoracion >= 75) return ['Satisfactorio', '#15803D'];
    if ($valoracion >= 50) return ['Cuasi Satisfactorio', '#CA8A04'];
    if ($valoracion >= 25) return ['Poco Satisfactorio', '#F97316'];
    return ['Deficiente', '#EF4444'];
}

/**
 * Docente guardado en `asignatura.docente` (NULL si nunca se llenó).
 *
 * NOTA (20 jul 2026, ver MEMORIA): se intentó detectar y sincronizar este
 * valor automáticamente desde el CSV de la encuesta, pero se descartó al
 * verificar el formulario oficial real -- "Materia" y "Profesor" son texto
 * fijo en la descripción del formulario (no preguntas con respuesta), así
 * que nunca viajan como columna en el CSV exportado. Por ahora este campo
 * solo refleja lo que se haya guardado manualmente vía `asignaturas.php`.
 */
function _obtenerDocenteActual(mysqli $conexion, int $idAsignatura): ?string
{
    $stmt = $conexion->prepare('SELECT docente FROM asignatura WHERE id_asignatura = ?');
    $stmt->bind_param('i', $idAsignatura);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    return $fila['docente'] ?? null;
}

/** Vigencia de evidencia de NIVEL CARRERA -- tabla Evidencias + Catalogo_Evidencias, ligada a id_evaluacion. */
function tiposCarreraVigentes(mysqli $conexion, int $idEvaluacion): array
{
    $sql = "SELECT c.codigo_evidencia
            FROM Evidencias e
            JOIN Catalogo_Evidencias c ON c.id_catalogo = e.id_catalogo
            WHERE e.id_evaluacion = ?
              AND c.codigo_evidencia IN ('DOC.SYL.01', 'DOC.SEG.01')";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('i', $idEvaluacion);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $mapa = ['DOC.SYL.01' => 'malla_curricular', 'DOC.SEG.01' => 'reglamento_normativa'];
    $codigos = array_column($filas, 'codigo_evidencia');
    return array_values(array_intersect_key($mapa, array_flip($codigos)));
}

/** Vigencia de evidencia de NIVEL ASIGNATURA (tabla evidencia_asignatura, vigente=1). */
function tiposAsignaturaVigentes(mysqli $conexion, int $idAsignatura): array
{
    $stmt = $conexion->prepare(
        'SELECT DISTINCT tipo FROM evidencia_asignatura WHERE id_asignatura = ? AND vigente = 1'
    );
    $stmt->bind_param('i', $idAsignatura);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return array_column($filas, 'tipo');
}

/**
 * Puerto exacto de _calcular_resultado_generico (views.py). Calcula EF1-EF5
 * para UNA asignatura, combinando su evidencia propia + la de nivel carrera
 * + la encuesta filtrada por nombre de materia.
 */
function calcularResultadoAsignatura(mysqli $conexion, int $idAsignatura, string $nombreAsignatura, int $idEvaluacion): array
{
    $etiquetas = etiquetasEvidencia();
    $evidenciasInfo = [];
    foreach ($etiquetas as $tipo => $label) {
        $evidenciasInfo[$tipo] = ['subida' => false, 'label' => $label];
    }

    foreach (tiposAsignaturaVigentes($conexion, $idAsignatura) as $tipo) {
        $evidenciasInfo[$tipo]['subida'] = true;
    }
    foreach (tiposCarreraVigentes($conexion, $idEvaluacion) as $tipo) {
        $evidenciasInfo[$tipo]['subida'] = true;
    }

    $tieneEf2 = $evidenciasInfo['acta_ajuste_curricular']['subida'];
    $tieneEf3 = $evidenciasInfo['evidencia_difusion']['subida'];
    $tieneEf5 = $evidenciasInfo['reglamento_normativa']['subida'];
    $tieneSyllabus = $evidenciasInfo['syllabus']['subida'];
    $tieneMalla = $evidenciasInfo['malla_curricular']['subida'];

    $totalEvidencias = ($tieneEf2 ? 1 : 0) + ($tieneEf3 ? 1 : 0) + ($tieneEf5 ? 1 : 0);
    $pctEvidencias = $totalEvidencias > 0 ? round($totalEvidencias / 3 * 100, 1) : 0;

    // El CSV de la encuesta ya es propio de esta asignatura (slot
    // 'encuesta_csv' en evidencia_asignatura) -- ya no se descarga un CSV
    // evaluation-wide ni se filtran filas por nombre de materia (ver
    // MEMORIA v18). $idEvaluacion ya no se necesita para esto.
    $datosEf = calcularEfDesdeCsv($conexion, $idAsignatura);
    $efDisponible = $datosEf !== null && $datosEf['respuestas'] > 0;

    $ef1Encuesta = $efDisponible ? $datosEf['ef1'] : 0.0;
    $ef1Syllabus = $tieneSyllabus ? 1.0 : 0.0;
    $ef1Malla = $tieneMalla ? 1.0 : 0.0;
    $ef1 = ($efDisponible || $tieneSyllabus || $tieneMalla)
        ? round(($ef1Encuesta + $ef1Syllabus + $ef1Malla) / 3, 4)
        : null;

    $ef4 = $efDisponible ? $datosEf['ef4'] : null;
    $respuestas = $efDisponible ? $datosEf['respuestas'] : 0;
    $promedioGeneral = $efDisponible ? $datosEf['promedio_general'] : 0;

    // Docente: ya no se detecta desde el CSV (ver nota en _obtenerDocenteActual)
    // -- se refleja tal cual lo que haya guardado en `asignatura.docente`.
    $docenteFinal = _obtenerDocenteActual($conexion, $idAsignatura);

    $ef2 = $tieneEf2 ? 1.0 : null;
    $ef3Doc = $tieneEf3 ? 1.0 : null;
    $ef5 = $tieneEf5 ? 1.0 : null;

    $ef1Val = $ef1 ?? 0.0;
    $ef2Val = $ef2 ?? 0.0;
    $ef3Val = $ef3Doc ?? 0.0;
    $ef4Val = $ef4 ?? 0.0;
    $ef5Val = $ef5 ?? 0.0;

    $efPuntaje = round($ef1Val * 0.33 + $ef2Val * 0.27 + $ef3Val * 0.20 + $ef4Val * 0.13 + $ef5Val * 0.07, 4);
    $valoracionGeneral = round($efPuntaje * 100, 1);

    $todosCompletos = $efDisponible && $tieneEf2 && $tieneEf3 && $tieneEf5;
    $estadoGeneral = $todosCompletos ? 'completo' : 'parcial';

    [$escala, $colorEscala] = calcularEscala($valoracionGeneral);

    return [
        'id_asignatura' => $idAsignatura,
        'nombre_asignatura' => $nombreAsignatura,
        'docente' => $docenteFinal,
        'resultado_final' => $valoracionGeneral,
        'valoracion_general' => $valoracionGeneral,
        'estado_general' => $estadoGeneral,
        'escala' => $escala,
        'color_escala' => $colorEscala,
        'fuente_resultado' => $todosCompletos ? 'combinado' : 'parcial',
        'evidencias_info' => $evidenciasInfo,
        'total_evidencias' => $totalEvidencias,
        'pct_evidencias' => $pctEvidencias,
        'ef_disponible' => $efDisponible,
        'ef1' => $ef1 !== null ? round($ef1 * 100, 1) : null,
        'ef1_estado' => $ef1 !== null ? 'ok' : 'sin_datos',
        'ef2' => $ef2 !== null ? round($ef2 * 100, 1) : null,
        'ef2_estado' => $tieneEf2 ? 'ok' : 'sin_datos',
        'ef3' => $ef3Doc !== null ? round($ef3Doc * 100, 1) : null,
        'ef3_estado' => $tieneEf3 ? 'ok' : 'sin_datos',
        'ef4' => $ef4 !== null ? round($ef4 * 100, 1) : null,
        'ef4_estado' => $efDisponible ? 'ok' : 'sin_datos',
        'ef5' => $ef5 !== null ? round($ef5 * 100, 1) : null,
        'ef5_estado' => $tieneEf5 ? 'ok' : 'sin_datos',
        'ef_puntaje' => $efPuntaje,
        'respuestas' => $respuestas,
        'promedio_general' => $promedioGeneral,
    ];
}

/**
 * Puerto de calcular_resultado_general: agrega el resultado de TODAS las
 * asignaturas de un PAO/cohorte, tratando cada asignatura sin dato como 0.
 */
function calcularResultadoGeneral(mysqli $conexion, int $idCohorte, ?int $idPeriodo, int $idEvaluacion): array
{
    $sql = "SELECT a.id_asignatura, a.nombre
            FROM asignatura a
            JOIN periodo_academico p ON p.id_periodoacademico = a.id_periodoacademico
            WHERE p.id_cohorte = ?";
    if ($idPeriodo !== null) {
        $sql .= " AND a.id_periodoacademico = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param('ii', $idCohorte, $idPeriodo);
    } else {
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param('i', $idCohorte);
    }
    $stmt->execute();
    $asignaturas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $resultados = array_map(
        fn($a) => calcularResultadoAsignatura($conexion, (int) $a['id_asignatura'], $a['nombre'], $idEvaluacion),
        $asignaturas,
    );

    $totalResultados = count($resultados);
    $agregados = [];
    $estados = [];
    foreach (['ef1', 'ef2', 'ef3', 'ef4', 'ef5'] as $ef) {
        $valores = array_map(fn($r) => $r[$ef] ?? 0.0, $resultados);
        $agregados[$ef] = $totalResultados > 0 ? round(array_sum($valores) / $totalResultados, 1) : null;
        $tieneAlgunDato = array_reduce($resultados, fn($acc, $r) => $acc || $r[$ef] !== null, false);
        $estados["{$ef}_estado"] = $tieneAlgunDato ? 'ok' : 'sin_datos';
    }

    $efDisponible = array_reduce($resultados, fn($acc, $r) => $acc || $r['ef_disponible'], false);
    $respuestas = array_sum(array_column($resultados, 'respuestas'));
    $promediosValidos = array_filter($resultados, fn($r) => $r['promedio_general'] !== null);
    $promedioGeneral = !empty($promediosValidos)
        ? round(array_sum(array_column($promediosValidos, 'promedio_general')) / count($promediosValidos), 1)
        : 0;

    if ($totalResultados > 0) {
        $efPuntaje = round(
            ($agregados['ef1'] / 100) * 0.33 +
            ($agregados['ef2'] / 100) * 0.27 +
            ($agregados['ef3'] / 100) * 0.20 +
            ($agregados['ef4'] / 100) * 0.13 +
            ($agregados['ef5'] / 100) * 0.07,
            4,
        );
        $valoracionGeneral = round($efPuntaje * 100, 1);
        $estadoGeneral = array_reduce($resultados, fn($acc, $r) => $acc && $r['estado_general'] === 'completo', true) ? 'completo' : 'parcial';
    } else {
        $efPuntaje = null;
        $valoracionGeneral = null;
        $estadoGeneral = 'sin_datos';
    }

    [$escala, $colorEscala] = calcularEscala($valoracionGeneral);

    return [
        'resultado_final' => $valoracionGeneral,
        'valoracion_general' => $valoracionGeneral,
        'estado_general' => $estadoGeneral,
        'escala' => $escala,
        'color_escala' => $colorEscala,
        'fuente_resultado' => 'agregado_por_asignatura',
        'ef_disponible' => $efDisponible,
        'ef1' => $agregados['ef1'], 'ef1_estado' => $estados['ef1_estado'],
        'ef2' => $agregados['ef2'], 'ef2_estado' => $estados['ef2_estado'],
        'ef3' => $agregados['ef3'], 'ef3_estado' => $estados['ef3_estado'],
        'ef4' => $agregados['ef4'], 'ef4_estado' => $estados['ef4_estado'],
        'ef5' => $agregados['ef5'], 'ef5_estado' => $estados['ef5_estado'],
        'ef_puntaje' => $efPuntaje,
        'respuestas' => $respuestas,
        'promedio_general' => $promedioGeneral,
        'detalle_asignaturas' => $resultados,
    ];
}

/** Snapshot de auditoría en seguimiento_syllabus (upsert por id_asignatura + id_evaluacion). */
function guardarSnapshotSeguimiento(mysqli $conexion, int $idAsignatura, int $idEvaluacion, array $resultado): void
{
    $ef1 = $resultado['ef1'] ?? 0;
    $ef2 = $resultado['ef2'] ?? 0;
    $ef3 = $resultado['ef3'] ?? 0;
    $ef4 = $resultado['ef4'] ?? 0;
    $ef5 = $resultado['ef5'] ?? 0;
    $valoracionGeneral = $resultado['valoracion_general'] ?? 0;
    $categoria = $resultado['escala'];
    $estadoGeneral = $resultado['estado_general'];

    $sql = "INSERT INTO seguimiento_syllabus
                (id_asignatura, ef1, ef2, ef3, ef4, ef5, valoracion_general, categoria, estado_general, fecha_calculo, id_evaluacion)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)
            ON DUPLICATE KEY UPDATE
                ef1 = VALUES(ef1), ef2 = VALUES(ef2), ef3 = VALUES(ef3), ef4 = VALUES(ef4), ef5 = VALUES(ef5),
                valoracion_general = VALUES(valoracion_general), categoria = VALUES(categoria),
                estado_general = VALUES(estado_general), fecha_calculo = CURDATE()";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param(
        'iddddddssi',
        $idAsignatura, $ef1, $ef2, $ef3, $ef4, $ef5, $valoracionGeneral, $categoria, $estadoGeneral, $idEvaluacion
    );
    // Tipos: i(id_asignatura) + d×6 (ef1,ef2,ef3,ef4,ef5,valoracion_general) + s×2 (categoria,estado_general) + i(id_evaluacion) = 10 chars para 10 "?".
    $stmt->execute();
}