<?php
/**
 * Puerto de views.py (_descargar_csv, _buscar_columna, _calcular_ef_desde_csv,
 * obtener_detalle_encuesta) del módulo Django.
 *
 * CAMBIO DE FUENTE (v1, ver memoria del proyecto): la encuesta ya NO se lee
 * de un CSV público publicado desde Google Sheets. Se sube como evidencia
 * (mismo mecanismo genérico de slots), queda guardada en Google Drive, y
 * este archivo la descarga desde ahí usando el mismo cliente autorizado que
 * ya usa la subida (api/google_drive).
 *
 * CAMBIO DE ALCANCE (v18, ver MEMORIA): el CSV dejó de ser un único archivo
 * evaluation-wide con filas de varias materias mezcladas. La encuesta real
 * siempre tiene el nombre de una materia y de un profesor fijos -- cada CSV
 * YA es de una sola materia. Por eso ahora el CSV se sube por-asignatura
 * (tipo 'encuesta_csv' en evidencia_asignatura, igual que syllabus/actas/
 * ajuste/difusión) y estas funciones ya NO filtran filas por nombre de
 * materia: toman todas las filas del CSV propio de la asignatura. Esto
 * también resuelve de raíz el pendiente de "matching de nombre poco
 * tolerante" (v17 sección 41, pendiente #10): ya no hay comparación de
 * texto que hacer.
 *
 * Requiere mysqli (para encontrar el archivo vigente en evidencia_asignatura)
 * y el cliente de Drive autorizado.
 */

const PUNTAJE_MAP = [
    'Siempre'       => 5,
    'Casi siempre'  => 4,
    'Algunas veces' => 3,
    'Pocas veces'   => 2,
    'Nunca'         => 1,
];

const TIPO_EVIDENCIA_ENCUESTA = 'encuesta_csv';

/**
 * Busca en `evidencia_asignatura` el CSV vigente para esta asignatura
 * (tipo='encuesta_csv', vigente=1) y devuelve su url_archivo de Drive
 * (webViewLink), o null si esta asignatura todavía no tiene uno subido.
 */
function _buscarUrlCsvEncuestaAsignatura(mysqli $conexion, int $idAsignatura): ?string
{
    $sql = "SELECT url_archivo
            FROM evidencia_asignatura
            WHERE id_asignatura = ?
              AND tipo = ?
              AND vigente = 1
            ORDER BY fecha_subida DESC
            LIMIT 1";

    $stmt = $conexion->prepare($sql);
    if (!$stmt) {
        error_log('seguimiento_syllabus: no se pudo preparar la búsqueda del CSV de encuesta por asignatura: ' . $conexion->error);
        return null;
    }

    $tipo = TIPO_EVIDENCIA_ENCUESTA;
    $stmt->bind_param('is', $idAsignatura, $tipo);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();

    return $fila['url_archivo'] ?? null;
}

/**
 * Descarga el contenido del archivo de Drive dado su webViewLink
 * (https://drive.google.com/file/d/{ID}/view...), usando el cliente
 * autorizado que ya usa api/google_drive/subir_archivo.php. Si Drive no está
 * conectado o el token venció, cliente_autorizado.php lanza RuntimeException
 * -- se deja propagar y el llamador la atrapa (ver descargarCsvEncuesta).
 */
function _descargarContenidoDrive(string $urlArchivo): ?string
{
    if (!preg_match('#/d/([a-zA-Z0-9_-]+)#', $urlArchivo, $m)) {
        error_log("seguimiento_syllabus: no se pudo extraer el id de Drive de '{$urlArchivo}'.");
        return null;
    }
    $idArchivo = $m[1];

    require_once __DIR__ . '/../google_drive/drive_helpers.php';
    $cliente = require __DIR__ . '/../google_drive/cliente_autorizado.php';
    $drive = new Google\Service\Drive($cliente);

    $respuesta = $drive->files->get($idArchivo, ['alt' => 'media']);
    return $respuesta->getBody()->getContents();
}

function _rutaCacheCsv(int $idAsignatura): string
{
    return sys_get_temp_dir() . '/seguimiento_syllabus_encuesta_cache_asignatura_' . $idAsignatura . '.csv';
}

// TTL del caché en disco, en segundos. Este caché por asignatura cubre las
// múltiples llamadas por PAO que hace calcularResultadoGeneral() (una por
// asignatura, vía calcularEfDesdeCsv), además de la memoización por
// petición de más abajo.
const TTL_CACHE_CSV_SEGUNDOS = 60;

/**
 * Descarga (con caché) el CSV de encuesta propio de una asignatura. Ya no
 * recibe $idEvaluacion: el CSV está ligado directamente a la asignatura.
 */
function descargarCsvEncuesta(mysqli $conexion, int $idAsignatura): ?array
{
    // Memoización por petición: calcularResultadoGeneral() llama a esta
    // función una vez por cada asignatura del PAO (vía calcularEfDesdeCsv).
    static $cache = [];

    if (array_key_exists($idAsignatura, $cache)) {
        return $cache[$idAsignatura];
    }

    $rutaCache = _rutaCacheCsv($idAsignatura);

    if (is_readable($rutaCache) && (time() - filemtime($rutaCache)) < TTL_CACHE_CSV_SEGUNDOS) {
        $cacheContenido = file_get_contents($rutaCache);
        if ($cacheContenido !== false) {
            $cache[$idAsignatura] = ['filas' => parseCsvString($cacheContenido), 'degradado' => false];
            return $cache[$idAsignatura];
        }
    }

    $urlArchivo = _buscarUrlCsvEncuestaAsignatura($conexion, $idAsignatura);

    if ($urlArchivo === null) {
        // Esta asignatura todavía no tiene CSV de encuesta propio subido.
        // No es un error -- calcularResultadoAsignatura() ya sabe tratar
        // "sin datos de encuesta" como EF1/EF4 pendientes.
        $cache[$idAsignatura] = null;
        return null;
    }

    try {
        $contenido = _descargarContenidoDrive($urlArchivo);
    } catch (Throwable $e) {
        error_log('seguimiento_syllabus: no se pudo descargar el CSV de encuesta desde Drive: ' . $e->getMessage());
        $contenido = null;
    }

    if ($contenido !== null) {
        @file_put_contents($rutaCache, $contenido);
        $cache[$idAsignatura] = ['filas' => parseCsvString($contenido), 'degradado' => false];
        return $cache[$idAsignatura];
    }

    // Descarga fallida (p.ej. Drive desconectado): como último recurso se usa
    // el caché en disco aunque esté vencido -- mejor datos "degradados" que
    // ninguno.
    if (is_readable($rutaCache)) {
        $cacheContenido = file_get_contents($rutaCache);
        if ($cacheContenido !== false) {
            $cache[$idAsignatura] = ['filas' => parseCsvString($cacheContenido), 'degradado' => true];
            return $cache[$idAsignatura];
        }
    }

    $cache[$idAsignatura] = null;
    return null;
}

function parseCsvString(string $contenido): array
{
    $filas = [];
    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $contenido);
    rewind($handle);
    while (($fila = fgetcsv($handle)) !== false) {
        $filas[] = $fila;
    }
    fclose($handle);
    return $filas;
}

function buscarColumnasPregunta(array $preguntas, int $numero): array
{
    $patron = '/\[P' . $numero . '[\.\]]/i';
    return array_values(array_filter($preguntas, fn($p) => preg_match($patron, $p) === 1));
}

function textoPregunta(string $header, int $numero): string
{
    if (preg_match('/\[P' . $numero . '\.?\s*(.*?)\]/i', $header, $m) && trim($m[1]) !== '') {
        return trim($m[1]);
    }
    return trim($header);
}

/**
 * Calcula EF1/EF4 desde el CSV propio de la asignatura. Ya no recibe
 * $materia ni filtra filas por nombre -- el CSV completo pertenece a esta
 * asignatura.
 *
 * FORMATO REAL CONFIRMADO (20 jul 2026, contra el formulario oficial de la
 * universidad, ver MEMORIA): el CSV NO trae columnas de materia/profesor --
 * esos datos son texto fijo en la descripción del formulario, no preguntas
 * con respuesta. La única columna fija es la #0 (timestamp); de ahí en
 * adelante van directo las 23 preguntas [P1]..[P23]. (Antes se asumía,
 * incorrectamente, que había 3 columnas fijas -- timestamp/materia/profesor
 * -- heredado de un CSV de prueba que no correspondía al formulario real.)
 */
function calcularEfDesdeCsv(mysqli $conexion, int $idAsignatura): ?array
{
    $csv = descargarCsvEncuesta($conexion, $idAsignatura);
    if ($csv === null) {
        return null;
    }

    $filas = $csv['filas'];
    if (empty($filas)) {
        return null;
    }

    $headers = array_shift($filas);
    $preguntas = array_slice($headers, 1);

    $totales = array_fill_keys($preguntas, 0);
    $conteos = array_fill_keys($preguntas, 0);
    $totalFilas = 0;

    foreach ($filas as $fila) {
        if (count($fila) < 2) {
            continue;
        }
        $totalFilas++;
        $respuestas = array_slice($fila, 1);
        foreach ($respuestas as $i => $valor) {
            if (!isset($preguntas[$i])) {
                continue;
            }
            $valor = trim($valor);
            if (isset(PUNTAJE_MAP[$valor])) {
                $totales[$preguntas[$i]] += PUNTAJE_MAP[$valor];
                $conteos[$preguntas[$i]]++;
            }
        }
    }

    $promedios = [];
    foreach ($preguntas as $p) {
        $promedios[$p] = $conteos[$p] > 0
            ? round(($totales[$p] / $conteos[$p] / 5) * 100, 1)
            : 0;
    }

    $ef1Pregs = array_merge(
        buscarColumnasPregunta($preguntas, 5),
        buscarColumnasPregunta($preguntas, 8),
        buscarColumnasPregunta($preguntas, 13),
    );
    $ef4Pregs = buscarColumnasPregunta($preguntas, 6);

    foreach ([[5, buscarColumnasPregunta($preguntas, 5), 'EF1 (P5)'],
              [8, buscarColumnasPregunta($preguntas, 8), 'EF1 (P8)'],
              [13, buscarColumnasPregunta($preguntas, 13), 'EF1 (P13)'],
              [6, $ef4Pregs, 'EF4 (P6)']] as [$numero, $cols, $nombreEf]) {
        if (empty($cols)) {
            error_log("seguimiento_syllabus: no se encontró la columna P{$numero} en el CSV (esperada para {$nombreEf}). ¿Cambió el formulario?");
        }
    }

    $promedioEfDecimal = function (array $pregList) use ($promedios): float {
        $vals = array_values(array_intersect_key($promedios, array_flip($pregList)));
        if (empty($vals)) {
            return 0.0;
        }
        return round((array_sum($vals) / count($vals)) / 100, 4);
    };

    return [
        'ef1' => $promedioEfDecimal($ef1Pregs),
        'ef4' => $promedioEfDecimal($ef4Pregs),
        'respuestas' => $totalFilas,
        'promedio_general' => !empty($promedios) ? round(array_sum($promedios) / count($promedios), 1) : 0,
        'degradado' => $csv['degradado'],
    ];
}

/**
 * Detalle de las 23 preguntas para el CSV propio de la asignatura (anexo del
 * PDF de I2). Ya no recibe $materia ni filtra filas.
 */
function obtenerDetalleEncuesta(mysqli $conexion, int $idAsignatura): ?array
{
    $csv = descargarCsvEncuesta($conexion, $idAsignatura);
    if ($csv === null) {
        return null;
    }

    $filas = $csv['filas'];
    $headers = array_shift($filas);
    $preguntas = array_slice($headers, 1);
    $opciones = array_keys(PUNTAJE_MAP);

    $conteos = [];
    foreach ($preguntas as $p) {
        $conteos[$p] = array_fill_keys($opciones, 0);
    }
    $totalFilas = 0;

    foreach ($filas as $fila) {
        if (count($fila) < 2) {
            continue;
        }
        $totalFilas++;
        $respuestas = array_slice($fila, 1);
        foreach ($respuestas as $i => $valor) {
            if (!isset($preguntas[$i])) {
                continue;
            }
            $valor = trim($valor);
            if (in_array($valor, $opciones, true)) {
                $conteos[$preguntas[$i]][$valor]++;
            }
        }
    }

    $ef1Cols = array_merge(
        buscarColumnasPregunta($preguntas, 5),
        buscarColumnasPregunta($preguntas, 8),
        buscarColumnasPregunta($preguntas, 13),
    );
    $ef4Cols = buscarColumnasPregunta($preguntas, 6);

    $detalle = [];
    for ($numero = 1; $numero <= 23; $numero++) {
        $cols = buscarColumnasPregunta($preguntas, $numero);
        if (empty($cols)) {
            $detalle[] = [
                'numero' => $numero,
                'texto' => null,
                'es_ef1' => false,
                'es_ef4' => false,
                'conteos' => array_fill_keys($opciones, 0),
                'total' => 0,
            ];
            continue;
        }
        $col = $cols[0];
        $detalle[] = [
            'numero' => $numero,
            'texto' => textoPregunta($col, $numero),
            'es_ef1' => in_array($col, $ef1Cols, true),
            'es_ef4' => in_array($col, $ef4Cols, true),
            'conteos' => $conteos[$col],
            'total' => array_sum($conteos[$col]),
        ];
    }

    return [
        // Se mantiene el nombre del campo por compatibilidad con el
        // frontend (services/seguimientoSyllabus.ts), aunque ya no filtra
        // nada: ahora es simplemente el total de filas del CSV propio de
        // esta asignatura.
        'respuestas_totales_materia' => $totalFilas,
        'preguntas' => $detalle,
    ];
}