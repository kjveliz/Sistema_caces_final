<?php

declare(strict_types=1);

namespace App\Repositories;

use mysqli;

/**
 * Repository del Grupo B (Evidencias genéricas, usadas por I1/I4/I5) del
 * plan de migración de PHP suelto a Slim (ver
 * plan_migracion_slim_legacy_v3.txt §1/§3). Arranca en la Parte 4 con
 * obtenerGuardadas() (reemplaza a api/evidencias/obtener_evidencias_guardadas.php);
 * las Partes 5-8 del mismo grupo (obtener_compartidas.php,
 * leer_matriculados.php, guardar_evidencia.php, preparar_pdf.php) suman
 * métodos acá en vez de crear un repository nuevo por archivo. Parte 5
 * agrega obtenerCompartidas() (reemplaza a
 * api/evidencias/obtener_compartidas.php).
 */
final class EvidenciasRepository
{
    public function __construct(private readonly mysqli $conexion)
    {
    }

    /**
     * Misma consulta exacta (mismas columnas, mismo JOIN, mismo ORDER BY)
     * que el SELECT original de api/evidencias/obtener_evidencias_guardadas.php.
     *
     * @return list<array{
     *     id_evidencia: int, id_catalogo: int, id_evaluacion: int,
     *     codigo_evidencia: string, descripcion: string, nombre_archivo: string,
     *     tipo: ?string, url_archivo: string, fecha_subida: string,
     *     titulo_corto: string, nombre_archivo_base: string, orden: int
     * }>
     */
    public function obtenerGuardadas(int $idEvaluacion, int $idIndicador): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT
                e.id_evidencia,
                e.id_catalogo,
                e.id_evaluacion,
                e.codigo_evidencia,
                e.descripcion,
                e.nombre_archivo,
                e.tipo,
                e.url_archivo,
                e.fecha_subida,
                ce.titulo_corto,
                ce.nombre_archivo_base,
                ce.orden
            FROM evidencias e
            INNER JOIN catalogo_evidencias ce
                ON ce.id_catalogo = e.id_catalogo
            WHERE e.id_evaluacion = ?
              AND ce.id_indicador = ?
            ORDER BY ce.orden ASC'
        );

        if (!$stmt) {
            throw new \RuntimeException('No se pudo preparar la consulta.');
        }

        $stmt->bind_param('ii', $idEvaluacion, $idIndicador);
        $stmt->execute();

        $resultado = $stmt->get_result();
        $evidencias = [];

        while ($fila = $resultado->fetch_assoc()) {
            $fila['id_evidencia'] = (int) $fila['id_evidencia'];
            $fila['id_catalogo'] = (int) $fila['id_catalogo'];
            $fila['id_evaluacion'] = (int) $fila['id_evaluacion'];
            $fila['orden'] = (int) $fila['orden'];

            $evidencias[] = $fila;
        }

        return $evidencias;
    }

    /**
     * Misma consulta exacta (mismas columnas, mismos 3 JOIN, mismo WHERE y
     * ORDER BY) que el SELECT original de
     * api/evidencias/obtener_compartidas.php: evidencias ya guardadas para
     * la evaluación en OTRO indicador (relacion_destino.id_indicador =
     * $idIndicadorDestino) pero cuyo indicador de origen (el de su propio
     * catalogo_evidencias) es distinto al destino -- son las evidencias
     * "compartidas" que un indicador puede reutilizar de otro.
     *
     * @return list<array{
     *     id_evidencia: int, id_catalogo: int, id_evaluacion: int,
     *     codigo_evidencia: string, descripcion: string, nombre_archivo: string,
     *     tipo: ?string, url_archivo: string, fecha_subida: string,
     *     titulo_corto: string, nombre_archivo_base: string, orden: int,
     *     id_indicador_origen: int, indicador_origen: string
     * }>
     */
    public function obtenerCompartidas(int $idEvaluacion, int $idIndicadorDestino): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT
                e.id_evidencia,
                e.id_catalogo,
                e.id_evaluacion,
                e.codigo_evidencia,
                e.descripcion,
                e.nombre_archivo,
                e.tipo,
                e.url_archivo,
                e.fecha_subida,
                ce.titulo_corto,
                ce.nombre_archivo_base,
                ce.orden,
                indicador_origen.id_indicador AS id_indicador_origen,
                indicador_origen.nombre AS indicador_origen
            FROM indicador_evidencia relacion_destino
            INNER JOIN evidencias e
                ON e.id_evidencia = relacion_destino.id_evidencia
            INNER JOIN catalogo_evidencias ce
                ON ce.id_catalogo = e.id_catalogo
            INNER JOIN indicadores indicador_origen
                ON indicador_origen.id_indicador = ce.id_indicador
            WHERE e.id_evaluacion = ?
              AND relacion_destino.id_indicador = ?
              AND ce.id_indicador <> ?
            ORDER BY ce.orden ASC'
        );

        if (!$stmt) {
            throw new \RuntimeException('No se pudo preparar la consulta.');
        }

        $stmt->bind_param('iii', $idEvaluacion, $idIndicadorDestino, $idIndicadorDestino);
        $stmt->execute();

        $resultado = $stmt->get_result();
        $evidencias = [];

        while ($fila = $resultado->fetch_assoc()) {
            $fila['id_evidencia'] = (int) $fila['id_evidencia'];
            $fila['id_catalogo'] = (int) $fila['id_catalogo'];
            $fila['id_evaluacion'] = (int) $fila['id_evaluacion'];
            $fila['orden'] = (int) $fila['orden'];
            $fila['id_indicador_origen'] = (int) $fila['id_indicador_origen'];

            $evidencias[] = $fila;
        }

        return $evidencias;
    }
}
