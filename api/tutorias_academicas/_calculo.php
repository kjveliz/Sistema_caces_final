<?php
/**
 * Cálculo del Indicador 11.3 — Tutorías Académicas.
 * Evaluación CUALITATIVA por puntos de validación dentro de cada EF
 * (a diferencia de I2, que es cuantitativo vía encuesta + evidencia binaria).
 */

const TIPOS_TUTORIAS = ['plan_tutorias', 'registro_tutorias', 'informe_tutorias', 'evidencia_atencion'];

/** tipo de evidencia_asignatura -> EF que valida. */
function tipoAEf(): array
{
    return [
        'plan_tutorias' => 'EF1',
        'registro_tutorias' => 'EF2',
        'informe_tutorias' => 'EF3',
        'evidencia_atencion' => 'EF4',
    ];
}

function pesosEf(): array
{
    return ['EF1' => 0.40, 'EF2' => 0.30, 'EF3' => 0.20, 'EF4' => 0.10];
}

function etiquetasEfTutorias(): array
{
    return [
        'EF1' => 'Planeación de tutorías',
        'EF2' => 'Cumplimiento de tutorías',
        'EF3' => 'Seguimiento académico',
        'EF4' => 'Normativas institucionales',
    ];
}

/**
 * Tabla de % oficial confirmada con el usuario:
 * EF1/EF2/EF3 tienen base 3 puntos; EF4 tiene base 4 puntos.
 * 0 puntos cumplidos siempre es 0%, sin importar la base.
 */
function porcentajePorPuntos(int $cumplidos, int $totalPuntos): float
{
    if ($cumplidos <= 0) {
        return 0.0;
    }
    if ($totalPuntos === 3) {
        return match ($cumplidos) {
            3 => 100.0,
            2 => 66.0,
            1 => 33.0,
            default => 0.0,
        };
    }
    if ($totalPuntos === 4) {
        return match ($cumplidos) {
            4 => 100.0,
            3 => 75.0,
            2 => 50.0,
            1 => 25.0,
            default => 0.0,
        };
    }
    // Fallback genérico (no debería usarse con los EF actuales).
    return round($cumplidos / max($totalPuntos, 1) * 100, 1);
}

function calcularEscalaTutorias(?float $valoracion): array
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
 * Trae, para una asignatura, la última validación de cada punto de cada EF
 * (solo de la evidencia vigente=1 de cada tipo). Devuelve:
 *   ['EF1' => ['puntos' => [...], 'total_puntos' => 3, 'cumplidos' => 2], ...]
 */
function obtenerValidacionesPorEf(mysqli $conexion, int $idAsignatura): array
{
    $sql = "SELECT v.ef, v.punto_nombre, v.cumplido, v.valor_extraido
            FROM evidencia_validacion_pdf v
            JOIN evidencia_asignatura e ON e.id_evidencia_asig = v.id_evidencia_asig
            WHERE e.id_asignatura = ? AND e.vigente = 1
            ORDER BY v.fecha_validacion DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('i', $idAsignatura);
    $stmt->execute();
    $filas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Nos quedamos con la validación MÁS RECIENTE por (ef, punto_nombre),
    // ya que puede haber historial de varias subidas del mismo tipo.
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
        $datos['cumplidos'] = count(array_filter($datos['puntos'], fn($p) => $p['cumplido']));
    }
    unset($datos);

    return $porEf;
}

/** Calcula el resultado de I3 para UNA asignatura. */
function calcularResultadoAsignaturaTutorias(mysqli $conexion, int $idAsignatura, string $nombreAsignatura): array
{
    $pesos = pesosEf();
    $etiquetas = etiquetasEfTutorias();
    $validacionesPorEf = obtenerValidacionesPorEf($conexion, $idAsignatura);

    $efs = [];
    $efPuntaje = 0.0;
    $algunEfConDatos = false;
    $todosLosEf = true;

    foreach (['EF1', 'EF2', 'EF3', 'EF4'] as $ef) {
        $datos = $validacionesPorEf[$ef] ?? null;
        if ($datos === null) {
            $efs[$ef] = [
                'label' => $etiquetas[$ef],
                'peso' => $pesos[$ef],
                'pct' => null,
                'estado' => 'sin_datos',
                'cumplidos' => 0,
                'total_puntos' => $ef === 'EF4' ? 4 : 3,
                'detalle_puntos' => [],
            ];
            $todosLosEf = false;
            continue;
        }
        $pct = porcentajePorPuntos($datos['cumplidos'], $datos['total_puntos']);
        $efs[$ef] = [
            'label' => $etiquetas[$ef],
            'peso' => $pesos[$ef],
            'pct' => $pct,
            'estado' => 'ok',
            'cumplidos' => $datos['cumplidos'],
            'total_puntos' => $datos['total_puntos'],
            'detalle_puntos' => $datos['puntos'],
        ];
        $efPuntaje += ($pct / 100) * $pesos[$ef];
        $algunEfConDatos = true;
    }

    $valoracionGeneral = $algunEfConDatos ? round($efPuntaje * 100, 1) : null;
    $estadoGeneral = $todosLosEf ? 'completo' : 'parcial';
    [$escala, $colorEscala] = calcularEscalaTutorias($valoracionGeneral);

    return [
        'id_asignatura' => $idAsignatura,
        'nombre_asignatura' => $nombreAsignatura,
        'valoracion_general' => $valoracionGeneral,
        'estado_general' => $estadoGeneral,
        'escala' => $escala,
        'color_escala' => $colorEscala,
        'efs' => $efs,
    ];
}

/** Agrega el resultado de I3 de todas las asignaturas de un PAO/cohorte. */
function calcularResultadoGeneralTutorias(mysqli $conexion, int $idCohorte, ?int $idPeriodo): array
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
        fn($a) => calcularResultadoAsignaturaTutorias($conexion, (int) $a['id_asignatura'], $a['nombre']),
        $asignaturas,
    );

    $total = count($resultados);
    // Asignatura sin datos cuenta como 0, igual que I2.
    $valoresGenerales = array_map(fn($r) => $r['valoracion_general'] ?? 0.0, $resultados);
    $valoracionGeneral = $total > 0 ? round(array_sum($valoresGenerales) / $total, 1) : null;
    $estadoGeneral = ($total > 0 && array_reduce($resultados, fn($acc, $r) => $acc && $r['estado_general'] === 'completo', true))
        ? 'completo' : 'parcial';

    [$escala, $colorEscala] = calcularEscalaTutorias($valoracionGeneral);

    return [
        'valoracion_general' => $valoracionGeneral,
        'estado_general' => $total > 0 ? $estadoGeneral : 'sin_datos',
        'escala' => $escala,
        'color_escala' => $colorEscala,
        'detalle_asignaturas' => $resultados,
    ];
}

/** Snapshot de auditoría en `tutorias_academicas` (upsert por id_asignatura + id_evaluacion). */
function guardarSnapshotTutorias(mysqli $conexion, int $idAsignatura, int $idEvaluacion, array $resultado): void
{
    $ef1 = $resultado['efs']['EF1']['pct'] ?? null;
    $ef2 = $resultado['efs']['EF2']['pct'] ?? null;
    $ef3 = $resultado['efs']['EF3']['pct'] ?? null;
    $ef4 = $resultado['efs']['EF4']['pct'] ?? null;
    $valoracionGeneral = $resultado['valoracion_general'];
    $categoria = $resultado['escala'];
    $estadoGeneral = $resultado['estado_general'];

    $sql = "INSERT INTO tutorias_academicas
                (id_asignatura, ef1, ef2, ef3, ef4, valoracion_general, categoria, estado_general, fecha_calculo, id_evaluacion)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)
            ON DUPLICATE KEY UPDATE
                ef1 = VALUES(ef1), ef2 = VALUES(ef2), ef3 = VALUES(ef3), ef4 = VALUES(ef4),
                valoracion_general = VALUES(valoracion_general), categoria = VALUES(categoria),
                estado_general = VALUES(estado_general), fecha_calculo = CURDATE()";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param(
        'iddddsssi',
        $idAsignatura, $ef1, $ef2, $ef3, $ef4, $valoracionGeneral, $categoria, $estadoGeneral, $idEvaluacion
    );
    $stmt->execute();
}