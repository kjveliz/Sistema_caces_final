<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Cálculo puro (sin mysqli ni Drive) de EF1/EF4 a partir de las filas ya
 * parseadas del CSV de la encuesta de heteroevaluación estudiantil (I2).
 * Puerto 1:1 de las funciones puras de api/seguimiento_syllabus/_encuesta.php
 * (parseCsvString, buscarColumnasPregunta, textoPregunta,
 * calcularEfDesdeFilas) — mismas fórmulas, mismo mapeo de puntaje, sin
 * cambios de comportamiento. Ver tests/Unit/SeguimientoSyllabus/EncuestaCalculoTest.php.
 */
final class EncuestaCalculoService
{
    public const PUNTAJE_MAP = [
        'Siempre' => 5,
        'Casi siempre' => 4,
        'Algunas veces' => 3,
        'Pocas veces' => 2,
        'Nunca' => 1,
    ];

    /** @return array<int, array<int, string>> */
    public static function parseCsvString(string $contenido): array
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

    /**
     * @param string[] $preguntas
     * @return string[]
     */
    public static function buscarColumnasPregunta(array $preguntas, int $numero): array
    {
        $patron = '/\[P' . $numero . '[\.\]]/i';

        return array_values(array_filter($preguntas, fn (string $p) => preg_match($patron, $p) === 1));
    }

    public static function textoPregunta(string $header, int $numero): string
    {
        if (preg_match('/\[P' . $numero . '\.?\s*(.*?)\]/i', $header, $m) && trim($m[1]) !== '') {
            return trim($m[1]);
        }

        return trim($header);
    }

    /**
     * Calcula EF1/EF4 a partir de filas YA parseadas del CSV propio de la
     * asignatura (fila 0 = headers, resto = respuestas). $degradado se pasa
     * tal cual desde el caller (ver EncuestaEvidenciaService) y viaja sin
     * tocar hasta el resultado.
     *
     * FORMATO REAL CONFIRMADO (ver MEMORIA, 20 jul 2026): el CSV NO trae
     * columnas de materia/profesor -- la única columna fija es la #0
     * (timestamp); de ahí en adelante van directo las 23 preguntas
     * [P1]..[P23].
     *
     * @param array<int, array<int, string>> $filas
     * @return array{ef1: float, ef4: float, respuestas: int, promedio_general: float, degradado: bool}|null
     */
    public static function calcularEfDesdeFilas(array $filas, bool $degradado = false): ?array
    {
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
                if (isset(self::PUNTAJE_MAP[$valor])) {
                    $totales[$preguntas[$i]] += self::PUNTAJE_MAP[$valor];
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
            self::buscarColumnasPregunta($preguntas, 5),
            self::buscarColumnasPregunta($preguntas, 8),
            self::buscarColumnasPregunta($preguntas, 13),
        );
        $ef4Pregs = self::buscarColumnasPregunta($preguntas, 6);

        foreach ([[5, self::buscarColumnasPregunta($preguntas, 5), 'EF1 (P5)'],
                  [8, self::buscarColumnasPregunta($preguntas, 8), 'EF1 (P8)'],
                  [13, self::buscarColumnasPregunta($preguntas, 13), 'EF1 (P13)'],
                  [6, $ef4Pregs, 'EF4 (P6)']] as [$numero, $cols, $nombreEf]) {
            if (empty($cols)) {
                error_log("EncuestaCalculoService: no se encontró la columna P{$numero} en el CSV (esperada para {$nombreEf}). ¿Cambió el formulario?");
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
            'degradado' => $degradado,
        ];
    }

    /**
     * Detalle de las 23 preguntas (anexo del PDF de I2), a partir de filas
     * YA parseadas. Puerto 1:1 de la parte pura de obtenerDetalleEncuesta().
     *
     * @param array<int, array<int, string>> $filas
     * @return array{respuestas_totales_materia: int, preguntas: array<int, array{numero: int, texto: string|null, es_ef1: bool, es_ef4: bool, conteos: array<string, int>, total: int}>}
     */
    public static function detalleDesdeFilas(array $filas): array
    {
        $headers = array_shift($filas);
        $preguntas = array_slice($headers, 1);
        $opciones = array_keys(self::PUNTAJE_MAP);

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
            self::buscarColumnasPregunta($preguntas, 5),
            self::buscarColumnasPregunta($preguntas, 8),
            self::buscarColumnasPregunta($preguntas, 13),
        );
        $ef4Cols = self::buscarColumnasPregunta($preguntas, 6);

        $detalle = [];
        for ($numero = 1; $numero <= 23; $numero++) {
            $cols = self::buscarColumnasPregunta($preguntas, $numero);
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
                'texto' => self::textoPregunta($col, $numero),
                'es_ef1' => in_array($col, $ef1Cols, true),
                'es_ef4' => in_array($col, $ef4Cols, true),
                'conteos' => $conteos[$col],
                'total' => array_sum($conteos[$col]),
            ];
        }

        return [
            'respuestas_totales_materia' => $totalFilas,
            'preguntas' => $detalle,
        ];
    }
}
