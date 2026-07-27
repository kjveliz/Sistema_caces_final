<?php

declare(strict_types=1);

namespace App\Services;

use Smalot\PdfParser\Parser;

/**
 * Validación automática del PDF de Tutorías Académicas (Indicador 11.3).
 * Migrado originalmente 1:1 desde api/tutorias_academicas/_validacion_pdf.php.
 *
 * DESDE v86: EF1 (Planeación), EF2 (Cumplimiento) y EF3 (Seguimiento
 * académico) dejaron de validarse por PDF — ahora leen CSV, ver
 * TutoriasCsvParserService. Este servicio quedó acotado a EF4
 * (Normativas institucionales), que sigue siendo PDF pero se redujo de 4
 * a 2 puntos: ya no valida "normativa" ni "firma_docente", solo
 * encabezado institucional y firma del director de carrera (confirmado
 * con el usuario).
 *
 * Regla de negocio: si un punto no se detecta con confianza, se marca como
 * NO cumplido (false) — nunca se asume cumplido por default.
 */
final class TutoriasValidacionPdfService
{
    /** Definición de los puntos de validación por EF, en el orden del plan. Solo EF4 sigue vigente (ver arriba). */
    public static function puntosPorEf(): array
    {
        return [
            'EF4' => ['encabezado_institucional', 'firma_director'],
        ];
    }

    /** Normaliza espacios/saltos de línea igual que leer_pdf.php (tasa_titulacion). */
    private static function normalizarTextoPdf(string $texto): string
    {
        $texto = preg_replace('/[ \t]+/', ' ', $texto);

        return preg_replace('/\r\n|\r/', "\n", $texto);
    }

    /** Punto 1 (todos los EF): busca nombre de universidad/carrera en el documento. */
    private static function detectarEncabezadoInstitucional(string $texto): array
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

    /** Punto "firma_director": busca patrones de firma del director de carrera cerca del pie del documento. */
    private static function detectarFirma(string $texto, string $rol): array
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

    /**
     * Extrae texto del PDF y corre los puntos de validación de un EF
     * (en la práctica, siempre EF4 desde v86 — ver puntosPorEf()).
     *
     * @return array{puntos: array<int, array{nombre: string, cumplido: bool, valor: string|null}>}
     */
    public function validarPdfTutorias(string $rutaTemporal, string $ef): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($rutaTemporal);
        $texto = self::normalizarTextoPdf($pdf->getText());

        $nombresPuntos = self::puntosPorEf()[$ef] ?? [];
        $puntos = [];

        foreach ($nombresPuntos as $nombre) {
            switch ($nombre) {
                case 'encabezado_institucional':
                    [$cumplido, $valor] = self::detectarEncabezadoInstitucional($texto);
                    break;

                case 'firma_director':
                    [$cumplido, $valor] = self::detectarFirma($texto, 'director');
                    break;

                default:
                    $cumplido = false;
                    $valor = null;
            }

            $puntos[] = [
                'nombre' => $nombre,
                'cumplido' => $cumplido === true,
                'valor' => $valor,
            ];
        }

        return ['puntos' => $puntos];
    }
}
