<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\LecturaPdfTitulacionDTO;
use RuntimeException;

/**
 * Cálculo del Indicador 5 (Tasa de Titulación). Migrado 1:1 desde
 * api/tasa_titulacion/_calculo.php (Fase 3 del Plan de Mejora): misma
 * lógica de extracción de texto de PDF (regex de "total reportado" con
 * respaldo de conteo de cédulas, y detección de cohorte/período), sin
 * cambios de comportamiento. Único cambio: el resultado se envuelve en
 * LecturaPdfTitulacionDTO (hallazgo 1.2.4) en vez de un array asociativo
 * suelto.
 *
 * A diferencia de I3, no depende de un repositorio (es puramente texto ->
 * datos, sin BD de por medio): por eso el método es estático, igual que
 * las funciones puras de TutoriasCalculoService.
 */
final class TitulacionCalculoService
{
    /**
     * Recibe el texto ya extraído del PDF (Smalot\PdfParser) y el tipo de
     * dato ("matriculados" o "graduados", ya validado por el caller) y
     * devuelve el total detectado + método usado + cohorte/período si se
     * pudieron inferir.
     *
     * @throws RuntimeException si no se pudo detectar ningún estudiante en el texto.
     */
    public static function extraerDatosTitulacion(string $texto, string $tipoDato): LecturaPdfTitulacionDTO
    {
        $textoNormalizado = preg_replace(
            "/[ \t]+/",
            " ",
            $texto,
        );

        $textoNormalizado = preg_replace(
            "/\r\n|\r/",
            "\n",
            $textoNormalizado,
        );

        /*
         * Primero intenta leer un total escrito
         * explícitamente en el reporte.
         */
        $patronesTotal = $tipoDato === "matriculados"
            ? [
                "/Total\s+alumnos\s+por\s+ciclo\s*:\s*(\d+)/iu",
                "/Total\s+de\s+alumnos\s*:\s*(\d+)/iu",
                "/Total\s+alumnos\s*:\s*(\d+)/iu",
                "/Total\s+matriculados\s*:\s*(\d+)/iu",
              ]
            : [
                "/Total\s+de\s+graduados\s*:\s*(\d+)/iu",
                "/Total\s+graduados\s*:\s*(\d+)/iu",
                "/Total\s+de\s+estudiantes\s+graduados\s*:\s*(\d+)/iu",
                "/Total\s+estudiantes\s+graduados\s*:\s*(\d+)/iu",
                "/Total\s+de\s+alumnos\s+graduados\s*:\s*(\d+)/iu",
                "/Total\s+alumnos\s+graduados\s*:\s*(\d+)/iu",
                "/Número\s+de\s+graduados\s*:\s*(\d+)/iu",
              ];

        $total = null;
        $metodo = null;

        foreach ($patronesTotal as $patron) {
            if (
                preg_match(
                    $patron,
                    $textoNormalizado,
                    $coincidencia,
                )
            ) {
                $total = intval($coincidencia[1]);
                $metodo = "total_reportado";
                break;
            }
        }

        /*
         * Respaldo: contar cédulas ecuatorianas
         * de 10 dígitos sin repetir.
         */
        $identificaciones = [];

        if ($total === null) {
            preg_match_all(
                "/(?<!\d)\d{10}(?!\d)/",
                $textoNormalizado,
                $coincidenciasIdentificacion,
            );

            $identificaciones = array_values(
                array_unique(
                    $coincidenciasIdentificacion[0],
                ),
            );

            $total = count($identificaciones);
            $metodo = "identificaciones_unicas";
        }

        if ($total <= 0) {
            throw new RuntimeException(
                "No se pudo detectar ningún estudiante en el PDF.",
            );
        }

        /*
         * Detectar cohorte del contenido:
         * B2025, B 2025, A2026, A 2026, etc.
         */
        $cohorteDetectada = null;

        if (
            preg_match(
                "/\b([AB])\s*(20\d{2})\b/iu",
                $textoNormalizado,
                $coincidenciaCohorte,
            )
        ) {
            $cohorteDetectada =
                strtoupper($coincidenciaCohorte[1]) .
                $coincidenciaCohorte[2];
        }

        /*
         * Detectar período descriptivo cuando exista.
         */
        $periodoDetectado = null;

        if (
            preg_match(
                "/Periodo\s*:\s*(.+?)(?:Fecha\s+Inicio|Fecha\s*:|\n)/iu",
                $textoNormalizado,
                $coincidenciaPeriodo,
            )
        ) {
            $periodoDetectado = trim(
                preg_replace(
                    "/\s+/",
                    " ",
                    $coincidenciaPeriodo[1],
                ),
            );
        }

        return new LecturaPdfTitulacionDTO(
            tipoDato: $tipoDato,
            total: $total,
            metodo: $metodo,
            cohorteDetectada: $cohorteDetectada,
            periodoDetectado: $periodoDetectado,
            identificacionesDetectadas: count($identificaciones),
        );
    }
}
