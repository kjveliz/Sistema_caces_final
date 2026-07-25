<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\LecturaPdfDesercionDTO;
use RuntimeException;

/**
 * Cálculo del Indicador 4 (Tasa de Deserción). Migrado 1:1 desde
 * api/tasa_desercion/_calculo.php (Fase 3 del Plan de Mejora, mismo patrón
 * que TitulacionCalculoService/I5 en v66): misma lógica de extracción de
 * texto de PDF (regex de "total reportado" con respaldo de conteo de
 * cédulas, y detección de cohorte/período), sin cambios de comportamiento.
 * Único cambio: el resultado se envuelve en LecturaPdfDesercionDTO
 * (hallazgo 1.2.4) en vez de un array asociativo suelto.
 *
 * Igual que TitulacionCalculoService, no depende de un repositorio (es
 * puramente texto -> datos, sin BD de por medio): por eso el método es
 * estático.
 */
final class DesercionCalculoService
{
    /**
     * Recibe el texto ya extraído del PDF (Smalot\PdfParser) y el tipo de
     * dato (validado por el caller: "primer_nivel", "segundo_anio" o
     * "no_continuaron") y devuelve el total detectado + método usado +
     * cohorte/período si se pudieron inferir.
     *
     * @throws RuntimeException si no se pudo detectar ningún estudiante en el texto.
     */
    public static function extraerDatosDesercion(string $texto, string $tipoDato): LecturaPdfDesercionDTO
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

        $patronesTotal = [
            "/Total\s+alumnos\s+por\s+ciclo\s*:\s*(\d+)/iu",
            "/Total\s+de\s+alumnos\s*:\s*(\d+)/iu",
            "/Total\s+alumnos\s*:\s*(\d+)/iu",
            "/Total\s+matriculados\s*:\s*(\d+)/iu",
            "/Total\s+estudiantes\s*:\s*(\d+)/iu",
            "/Total\s+que\s+no\s+continuaron\s*:\s*(\d+)/iu",
            "/Total\s+no\s+continuaron\s*:\s*(\d+)/iu",
            "/Total\s+desertados\s*:\s*(\d+)/iu",
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

        return new LecturaPdfDesercionDTO(
            tipoDato: $tipoDato,
            total: $total,
            metodo: $metodo,
            cohorteDetectada: $cohorteDetectada,
            periodoDetectado: $periodoDetectado,
            identificacionesDetectadas: count($identificaciones),
        );
    }
}
