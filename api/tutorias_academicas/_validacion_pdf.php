<?php
/**
 * Validación automática de los PDFs de Tutorías Académicas (Indicador 11.3).
 *
 * Sigue el mismo patrón real ya usado en api/tasa_titulacion/leer_pdf.php:
 * Smalot\PdfParser para extraer texto plano, normalización de espacios/saltos
 * de línea, y una lista de regex con fallback (el primero que matchea gana).
 *
 * IMPORTANTE — calibración pendiente: estos patrones son una PRIMERA VERSIÓN
 * razonable, escrita sin tener un PDF real de plan/registro de tutorías a la
 * vista (pendiente de que el usuario consiga uno). Cuando llegue el ejemplo
 * real, lo esperable es tener que ajustar las regex de "horas" y "encabezado
 * institucional" en esta clase — el resto de la arquitectura (guardado por
 * punto, cálculo de %, etc.) no debería cambiar.
 *
 * Regla de negocio confirmada con el usuario: si un punto no se detecta con
 * confianza, se marca como NO cumplido (false) — nunca se asume cumplido por
 * default.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

/** Definición de los puntos de validación por EF, en el orden del plan. */
function puntosPorEf(): array
{
    return [
        'EF1' => ['encabezado_institucional', 'horas', 'firma_docente'],
        'EF2' => ['encabezado_institucional', 'horas', 'firma_docente'],
        'EF3' => ['encabezado_institucional', 'reporte_mejora', 'firma_docente'],
        'EF4' => ['encabezado_institucional', 'normativa', 'firma_docente', 'firma_director'],
    ];
}

/** Normaliza espacios/saltos de línea igual que leer_pdf.php. */
function normalizarTextoPdf(string $texto): string
{
    $texto = preg_replace('/[ \t]+/', ' ', $texto);
    $texto = preg_replace('/\r\n|\r/', "\n", $texto);
    return $texto;
}

/** Punto 1 (todos los EF): busca nombre de universidad/carrera en el documento. */
function detectarEncabezadoInstitucional(string $texto): array
{
    $patrones = [
        '/Universidad\s+Católica\s+de\s+Santiago\s+de\s+Guayaquil/iu',
        '/UCSG/u',
        '/Desarrollo\s+de\s+Software/iu',
    ];
    foreach ($patrones as $patron) {
        if (preg_match($patron, $texto, $m)) {
            return [true, trim($m[0])];
        }
    }
    return [false, null];
}

/**
 * Puntos "horas" de EF1/EF2: extrae el primer número seguido de "hora(s)".
 * Devuelve el valor numérico (float) además del booleano, para poder comparar
 * EF2 contra EF1 en calcularPuntoHorasEf2().
 */
function detectarHoras(string $texto): array
{
    $patrones = [
        '/(\d+(?:[.,]\d+)?)\s*horas?\s+(?:planificadas|estimadas|programadas)/iu',
        '/(\d+(?:[.,]\d+)?)\s*horas?\s+(?:cumplidas|ejecutadas|evidenciadas|realizadas)/iu',
        '/Total\s+de\s+horas\s*:\s*(\d+(?:[.,]\d+)?)/iu',
        '/(\d+(?:[.,]\d+)?)\s*horas?/iu',
    ];
    foreach ($patrones as $patron) {
        if (preg_match($patron, $texto, $m)) {
            $valor = (float) str_replace(',', '.', $m[1]);
            return [true, $valor, trim($m[0])];
        }
    }
    return [false, null, null];
}

/** Puntos "firma_docente" / "firma_director": busca patrones de firma cercanos al pie del documento. */
function detectarFirma(string $texto, string $rol): array
{
    $etiquetaRol = $rol === 'director' ? '(?:Director(?:a)?\s+de\s+Carrera)' : '(?:Docente|Tutor(?:a)?)';
    $patrones = [
        '/Firma\s*(?:d[ei]l?\s*' . $etiquetaRol . ')?\s*:\s*([^\n]{2,80})/iu',
        '/Firmado\s+electr[oó]nicamente\s+por\s*:?\s*([^\n]{2,80})/iu',
        '/' . $etiquetaRol . '\s*:\s*([^\n]{2,80})/iu',
    ];
    foreach ($patrones as $patron) {
        if (preg_match($patron, $texto, $m)) {
            return [true, trim($m[1] ?? $m[0])];
        }
    }
    return [false, null];
}

/** Punto "reporte_mejora" (EF3): busca keywords de seguimiento/mejora académica. */
function detectarReporteMejora(string $texto): array
{
    $patrones = [
        '/mejora\s+acad[eé]mica/iu',
        '/rendimiento\s+acad[eé]mico/iu',
        '/seguimiento\s+acad[eé]mico/iu',
    ];
    foreach ($patrones as $patron) {
        if (preg_match($patron, $texto, $m)) {
            return [true, trim($m[0])];
        }
    }
    return [false, null];
}

/** Punto "normativa" (EF4): busca referencia a reglamento/normativa vigente. */
function detectarNormativa(string $texto): array
{
    $patrones = [
        '/Reglamento\s+(?:de\s+)?(?:R[eé]gimen\s+)?Acad[eé]mico/iu',
        '/normativa\s+(?:institucional\s+)?vigente/iu',
        '/reglamento\s+institucional/iu',
    ];
    foreach ($patrones as $patron) {
        if (preg_match($patron, $texto, $m)) {
            return [true, trim($m[0])];
        }
    }
    return [false, null];
}

/**
 * Extrae texto del PDF y corre TODOS los puntos de validación de un EF.
 * $horasEf1Previas: solo se usa para el EF2 (regla de "se topa a 100%").
 *
 * @return array{ puntos: array<int, array{nombre:string, cumplido:bool, valor:?string}>, horas_detectadas: ?float }
 */
function validarPdfTutorias(string $rutaTemporal, string $ef, ?float $horasEf1Previas = null): array
{
    $parser = new Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($rutaTemporal);
    $texto = normalizarTextoPdf($pdf->getText());

    $nombresPuntos = puntosPorEf()[$ef] ?? [];
    $puntos = [];
    $horasDetectadas = null;

    foreach ($nombresPuntos as $nombre) {
        switch ($nombre) {
            case 'encabezado_institucional':
                [$cumplido, $valor] = detectarEncabezadoInstitucional($texto);
                break;

            case 'horas':
                [$cumplido, $valorNumerico, $valorTexto] = detectarHoras($texto);
                $horasDetectadas = $valorNumerico;
                $valor = $valorTexto;
                // Regla confirmada: en EF2, si las horas evidenciadas superan
                // (o igualan) las de EF1, el punto se topa a cumplido.
                if ($ef === 'EF2' && $horasEf1Previas !== null && $valorNumerico !== null) {
                    $cumplido = $valorNumerico >= $horasEf1Previas;
                }
                break;

            case 'firma_docente':
                [$cumplido, $valor] = detectarFirma($texto, 'docente');
                break;

            case 'firma_director':
                [$cumplido, $valor] = detectarFirma($texto, 'director');
                break;

            case 'reporte_mejora':
                [$cumplido, $valor] = detectarReporteMejora($texto);
                break;

            case 'normativa':
                [$cumplido, $valor] = detectarNormativa($texto);
                break;

            default:
                $cumplido = false;
                $valor = null;
        }

        $puntos[] = [
            'nombre' => $nombre,
            // Regla confirmada: si no se detecta con confianza, NO cumplido.
            'cumplido' => $cumplido === true,
            'valor' => $valor,
        ];
    }

    return ['puntos' => $puntos, 'horas_detectadas' => $horasDetectadas];
}