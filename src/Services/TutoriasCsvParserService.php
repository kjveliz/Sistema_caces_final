<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Validación automática de los CSV de Tutorías Académicas (Indicador 11.3),
 * EF1 (Planeación), EF2 (Cumplimiento) y EF3 (Seguimiento académico).
 * Reemplaza la lectura de PDF por regex que tenía I3 para estos 3 EF —
 * EF4 sigue siendo PDF, ver TutoriasValidacionPdfService.
 *
 * Formato real confirmado (MEMORIA v86, CSV de ejemplo del compañero
 * dueño de I3, "Planificación_de_tutorias.csv" / "seguimiento_de_tutorias.csv"):
 * separador ";", codificación CP850 (NO latin-1/ISO-8859-1 como se pensaba
 * al principio — confirmado byte a byte: "ó" llega como 0xA2, que en
 * latin-1 sería "¢"; 0xA2 es "ó" en CP850, el codepage típico de Excel/DOS
 * en español), salto de línea CRLF, primera fila es un título fusionado
 * con el nombre de asignatura ("Planificación de
 * tutorías de materia: X" / "Seguimiento de tutorías de materia: X" —
 * confirmado tolerante a typos, ej. "detutorias" sin espacio), segunda
 * fila son los encabezados de columna. Aunque el CSV trae una fila por
 * estudiante, el usuario confirmó que SOLO se lee la primera fila con
 * datos (Cohorte/Horas/Pao llenos) como la información de la materia
 * completa — el resto de filas de estudiantes sin esos datos se ignora.
 *
 * Regla de negocio: igual que TutoriasValidacionPdfService, si un punto
 * no se detecta con confianza se marca NO cumplido — nunca se asume
 * cumplido por default.
 *
 * PENDIENTE DE CALIBRAR (documentado a propósito, para no repetir el
 * error que ya tuvo el validador de PDF sin ejemplo real): EF3 valida un
 * tercer CSV de notas/rendimiento de estudiantes (una semana sin
 * tutorías vs. una semana con tutorías) del que TODAVÍA NO tenemos un
 * ejemplo real — validarCsvSeguimientoAcademico() asume la misma
 * estructura de título + columnas Cohorte/Pao que EF1/EF2 solo para el
 * chequeo de credenciales (que es lo único que el usuario pidió validar
 * en EF3 por ahora). Cuando llegue un ejemplo real de ese archivo, hay
 * que confirmar esta suposición.
 */
final class TutoriasCsvParserService
{
    /** Decodifica CP850 a UTF-8 y parsea filas separadas por ";", tolerante a CRLF. */
    public static function parseCsvLatin1(string $contenidoBinario): array
    {
        $texto = @mb_convert_encoding($contenidoBinario, 'UTF-8', 'CP850');
        if ($texto === false || $texto === '') {
            return [];
        }
        $texto = str_replace("\r\n", "\n", $texto);
        $lineas = explode("\n", trim($texto, "\n"));

        $filas = [];
        foreach ($lineas as $linea) {
            if ($linea === '') {
                continue;
            }
            $fila = str_getcsv($linea, ';');
            $filas[] = $fila === false ? [] : $fila;
        }

        return $filas;
    }

    /** Fila 0 = título fusionado ("... de materia: X;;;;"), tolerante a variaciones de espaciado. */
    public static function extraerAsignaturaDeTitulo(array $filas): ?string
    {
        $titulo = $filas[0][0] ?? '';
        if (preg_match('/de\s*materia\s*:\s*(.+)/iu', $titulo, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /** Índice de columna por nombre de encabezado (fila 1), sin distinguir mayúsculas ni espacios sueltos. */
    private static function indiceColumna(array $filas, string $nombreBuscado): ?int
    {
        $headers = $filas[1] ?? [];
        foreach ($headers as $i => $h) {
            if (mb_strtolower(trim($h)) === mb_strtolower($nombreBuscado)) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Primera fila de datos (índice >= 2) donde la columna de Cohorte
     * viene llena — es la fila con la info de la materia completa,
     * confirmado con el usuario.
     */
    private static function primeraFilaConDatos(array $filas, int $colCohorte): ?array
    {
        for ($i = 2; $i < count($filas); $i++) {
            if (trim($filas[$i][$colCohorte] ?? '') !== '') {
                return $filas[$i];
            }
        }

        return null;
    }

    private static function normalizar(string $texto): string
    {
        return mb_strtolower(trim($texto));
    }

    /** "3 horas por semana" -> 3.0 */
    private static function extraerHoras(string $texto): ?float
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*horas?/iu', $texto, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return null;
    }

    /** "3/3 horas cumplidas" -> [cumplidas: 3.0, asignadas_segun_fraccion: 3.0] */
    private static function extraerFraccionCumplidas(string $texto): array
    {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*\/\s*(\d+(?:[.,]\d+)?)\s*horas?\s*cumplidas/iu', $texto, $m)) {
            return [(float) str_replace(',', '.', $m[1]), (float) str_replace(',', '.', $m[2])];
        }

        return [null, null];
    }

    /**
     * EF1 (plan_tutorias) y EF2 (registro_tutorias): mismo layout de
     * columnas (Cohorte/Pao/Horas asignadas), EF2 trae además la columna
     * "Horas cumplidas por semana" con la fracción ya armada en texto
     * (ej. "3/3 horas cumplidas") — se usa esa fracción tal cual viene
     * (confirmado con el usuario: da igual comparar contra el CSV de
     * EF1, así que se toma el camino más simple y autocontenido).
     *
     * @param array{asignatura: string, cohorte: string, pao: string} $contexto Datos reales de la asignatura (BD).
     * @return array{puntos: array<int, array{nombre: string, cumplido: bool, valor: string|null}>}
     */
    public static function validarCsvPlanificacionOSeguimiento(string $contenidoBinario, string $ef, array $contexto): array
    {
        $filas = self::parseCsvLatin1($contenidoBinario);
        $colCohorte = self::indiceColumna($filas, 'Cohorte');
        $colPao = self::indiceColumna($filas, 'Pao');
        $colHoras = self::indiceColumna($filas, 'Horas de tutorias asignadas por semana');
        $colCumplidas = self::indiceColumna($filas, 'Horas cumplidas por semana');

        $filaDatos = $colCohorte !== null ? self::primeraFilaConDatos($filas, $colCohorte) : null;

        $cohorteCsv = $filaDatos !== null && $colCohorte !== null ? trim($filaDatos[$colCohorte] ?? '') : '';
        $paoCsv = $filaDatos !== null && $colPao !== null ? trim($filaDatos[$colPao] ?? '') : '';

        $cohorteCoincide = $cohorteCsv !== '' && self::normalizar($cohorteCsv) === self::normalizar($contexto['cohorte']);
        $paoCoincide = $paoCsv !== '' && self::normalizar($paoCsv) === self::normalizar($contexto['pao']);

        $puntos = [];

        if ($ef === 'EF1') {
            $horasTexto = $filaDatos !== null && $colHoras !== null ? trim($filaDatos[$colHoras] ?? '') : '';
            $horas = $horasTexto !== '' ? self::extraerHoras($horasTexto) : null;
            $puntos[] = [
                'nombre' => 'horas_asignadas_presente',
                'cumplido' => $horas !== null && $horas > 0,
                'valor' => $horasTexto !== '' ? $horasTexto : null,
            ];
        } else {
            $cumplidasTexto = $filaDatos !== null && $colCumplidas !== null ? trim($filaDatos[$colCumplidas] ?? '') : '';
            [$cumplidas, $asignadasSegunFraccion] = $cumplidasTexto !== '' ? self::extraerFraccionCumplidas($cumplidasTexto) : [null, null];
            $ok = $cumplidas !== null && $asignadasSegunFraccion !== null && $asignadasSegunFraccion > 0 && $cumplidas >= $asignadasSegunFraccion;
            $puntos[] = [
                'nombre' => 'horas_cumplidas_ok',
                'cumplido' => $ok,
                'valor' => $cumplidasTexto !== '' ? $cumplidasTexto : null,
            ];
        }

        $puntos[] = ['nombre' => 'cohorte_coincide', 'cumplido' => $cohorteCoincide, 'valor' => $cohorteCsv !== '' ? $cohorteCsv : null];
        $puntos[] = ['nombre' => 'pao_coincide', 'cumplido' => $paoCoincide, 'valor' => $paoCsv !== '' ? $paoCsv : null];

        return ['puntos' => $puntos];
    }

    /**
     * EF3 (informe_tutorias): CSV de notas/rendimiento — formato real
     * TODAVÍA NO CONFIRMADO (ver nota de calibración pendiente arriba).
     * Por decisión explícita del usuario, por ahora solo valida
     * credenciales: asignatura (del título), cohorte y PAO.
     *
     * @param array{asignatura: string, cohorte: string, pao: string} $contexto
     * @return array{puntos: array<int, array{nombre: string, cumplido: bool, valor: string|null}>}
     */
    public static function validarCsvSeguimientoAcademico(string $contenidoBinario, array $contexto): array
    {
        $filas = self::parseCsvLatin1($contenidoBinario);
        $asignaturaCsv = self::extraerAsignaturaDeTitulo($filas) ?? '';
        $colCohorte = self::indiceColumna($filas, 'Cohorte');
        $colPao = self::indiceColumna($filas, 'Pao');

        $filaDatos = $colCohorte !== null ? self::primeraFilaConDatos($filas, $colCohorte) : null;
        $cohorteCsv = $filaDatos !== null && $colCohorte !== null ? trim($filaDatos[$colCohorte] ?? '') : '';
        $paoCsv = $filaDatos !== null && $colPao !== null ? trim($filaDatos[$colPao] ?? '') : '';

        $asignaturaCoincide = $asignaturaCsv !== '' && self::normalizar($asignaturaCsv) === self::normalizar($contexto['asignatura']);
        $cohorteCoincide = $cohorteCsv !== '' && self::normalizar($cohorteCsv) === self::normalizar($contexto['cohorte']);
        $paoCoincide = $paoCsv !== '' && self::normalizar($paoCsv) === self::normalizar($contexto['pao']);

        return [
            'puntos' => [
                ['nombre' => 'asignatura_coincide', 'cumplido' => $asignaturaCoincide, 'valor' => $asignaturaCsv !== '' ? $asignaturaCsv : null],
                ['nombre' => 'cohorte_coincide', 'cumplido' => $cohorteCoincide, 'valor' => $cohorteCsv !== '' ? $cohorteCsv : null],
                ['nombre' => 'pao_coincide', 'cumplido' => $paoCoincide, 'valor' => $paoCsv !== '' ? $paoCsv : null],
            ],
        ];
    }
}
