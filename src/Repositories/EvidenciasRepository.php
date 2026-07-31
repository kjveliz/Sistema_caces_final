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
 * api/evidencias/obtener_compartidas.php). Parte 7 agrega
 * guardarEvidencia() (reemplaza a api/evidencias/guardar_evidencia.php).
 * Parte 8 agrega obtenerCatalogoParaPreparar() (reemplaza a
 * api/evidencias/preparar_pdf.php), quinta y última Parte del Grupo B.
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

    /**
     * Inserta la evidencia si no existe para esa evaluación/catálogo (según
     * `uk_evidencia_evaluacion_catalogo`, misma unique key que ya usaba el
     * original) o la actualiza si ya existe -- mismo INSERT ... ON
     * DUPLICATE KEY UPDATE ... id_evidencia = LAST_INSERT_ID(id_evidencia)
     * que api/evidencias/guardar_evidencia.php, para que insert_id
     * devuelva el id correcto tanto en el caso de inserción como en el de
     * actualización. Relaciona la evidencia con su indicador de origen
     * (catalogo_evidencias) y, si existen reglas activas de compartición en
     * `compartir_catalogo`, la comparte automáticamente con los
     * indicadores destino -- misma transacción de 3 queries, mismo orden y
     * mismos nombres de columnas que el original. Parte 7 del plan de
     * migración de PHP suelto a Slim (cuarta y última del Grupo B).
     *
     * @return array{id_evidencia: int, relaciones_compartidas: int}
     */
    public function guardarEvidencia(
        int $idCatalogo,
        int $idEvaluacion,
        string $codigoEvidencia,
        string $descripcion,
        string $nombreArchivo,
        string $tipo,
        string $urlArchivo,
        int $idUsuario,
    ): array {
        $this->conexion->begin_transaction();

        try {
            /*
                Inserta la evidencia si no existe.
                Si ya existe para la misma evaluación y catálogo,
                actualiza el registro.
            */
            $sqlEvidencia = "
                INSERT INTO evidencias (
                    id_catalogo,
                    id_evaluacion,
                    codigo_evidencia,
                    descripcion,
                    nombre_archivo,
                    tipo,
                    url_archivo,
                    fecha_subida,
                    id_usuario
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)

                ON DUPLICATE KEY UPDATE
                    codigo_evidencia = VALUES(codigo_evidencia),
                    descripcion = VALUES(descripcion),
                    nombre_archivo = VALUES(nombre_archivo),
                    tipo = VALUES(tipo),
                    url_archivo = VALUES(url_archivo),
                    fecha_subida = NOW(),
                    id_usuario = VALUES(id_usuario),
                    id_evidencia = LAST_INSERT_ID(id_evidencia)
            ";

            $stmtEvidencia = $this->conexion->prepare($sqlEvidencia);

            if (!$stmtEvidencia) {
                throw new \RuntimeException(
                    'No se pudo preparar el registro de la evidencia: ' . $this->conexion->error
                );
            }

            $stmtEvidencia->bind_param(
                'iisssssi',
                $idCatalogo,
                $idEvaluacion,
                $codigoEvidencia,
                $descripcion,
                $nombreArchivo,
                $tipo,
                $urlArchivo,
                $idUsuario,
            );

            if (!$stmtEvidencia->execute()) {
                throw new \RuntimeException(
                    'No se pudo guardar la evidencia: ' . $stmtEvidencia->error
                );
            }

            $idEvidencia = (int) $stmtEvidencia->insert_id;

            /*
                Relacionar la evidencia con su indicador de origen,
                obtenido desde catalogo_evidencias.
            */
            $sqlOrigen = "
                INSERT IGNORE INTO indicador_evidencia (
                    id_indicador,
                    id_evidencia
                )
                SELECT
                    id_indicador,
                    ?
                FROM catalogo_evidencias
                WHERE id_catalogo = ?
                  AND activo = 1
            ";

            $stmtOrigen = $this->conexion->prepare($sqlOrigen);

            if (!$stmtOrigen) {
                throw new \RuntimeException(
                    'No se pudo preparar la relación con el indicador de origen: ' . $this->conexion->error
                );
            }

            $stmtOrigen->bind_param('ii', $idEvidencia, $idCatalogo);

            if (!$stmtOrigen->execute()) {
                throw new \RuntimeException(
                    'No se pudo relacionar la evidencia con su indicador de origen: ' . $stmtOrigen->error
                );
            }

            /*
                Buscar reglas activas de compartición y relacionar
                automáticamente la misma evidencia con otros indicadores.
            */
            $sqlCompartidas = "
                INSERT IGNORE INTO indicador_evidencia (
                    id_indicador,
                    id_evidencia
                )
                SELECT
                    id_indicador_destino,
                    ?
                FROM compartir_catalogo
                WHERE id_catalogo_origen = ?
                  AND activo = 1
            ";

            $stmtCompartidas = $this->conexion->prepare($sqlCompartidas);

            if (!$stmtCompartidas) {
                throw new \RuntimeException(
                    'No se pudo preparar la compartición automática: ' . $this->conexion->error
                );
            }

            $stmtCompartidas->bind_param('ii', $idEvidencia, $idCatalogo);

            if (!$stmtCompartidas->execute()) {
                throw new \RuntimeException(
                    'No se pudo compartir la evidencia automáticamente: ' . $stmtCompartidas->error
                );
            }

            $relacionesCompartidas = (int) $stmtCompartidas->affected_rows;

            $this->conexion->commit();
        } catch (\Throwable $e) {
            $this->conexion->rollback();

            throw $e;
        }

        return [
            'id_evidencia' => $idEvidencia,
            'relaciones_compartidas' => $relacionesCompartidas,
        ];
    }

    /**
     * Misma consulta exacta (mismas columnas, mismo WHERE con activo = 1)
     * que el SELECT original de api/evidencias/preparar_pdf.php: busca el
     * catálogo por id para validar el archivo subido y armar el nombre
     * técnico del archivo. Parte 8 del plan de migración de PHP suelto a
     * Slim (quinta y última del Grupo B).
     *
     * @return array{codigo_evidencia: string, titulo_corto: string, descripcion: string, nombre_archivo_base: string, orden: int}|null
     */
    public function obtenerCatalogoParaPreparar(int $idCatalogo): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT
                codigo_evidencia,
                titulo_corto,
                descripcion,
                nombre_archivo_base,
                orden
            FROM catalogo_evidencias
            WHERE id_catalogo = ?
              AND activo = 1
            LIMIT 1'
        );

        if (!$stmt) {
            throw new \RuntimeException('No se pudo preparar la consulta.');
        }

        $stmt->bind_param('i', $idCatalogo);
        $stmt->execute();

        $resultado = $stmt->get_result();
        $catalogo = $resultado->fetch_assoc();

        if (!$catalogo) {
            return null;
        }

        $catalogo['orden'] = (int) $catalogo['orden'];

        return $catalogo;
    }
}
