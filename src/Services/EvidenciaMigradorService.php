<?php

declare(strict_types=1);

namespace App\Services;

use mysqli;
use RuntimeException;
use Throwable;

/**
 * Migra la evidencia ya subida de una carrera al cambiar su interruptor de
 * almacenamiento (Drive <-> local) — ver plan_interruptor_almacenamiento.txt
 * §4.4. Recorre las dos tablas donde puede vivir evidencia de esa carrera:
 *
 *   - `evidencia_asignatura` (I2/I3 — ruta moderna, sí respeta el flag de
 *     la carrera al subir evidencia nueva): asignatura -> periodo_academico
 *     -> cohortes -> carreras, mismo join que
 *     TutoriasRepository/SeguimientoSyllabusRepository::contextoParaDrive().
 *   - `evidencias` (I4/I5 — catálogo genérico, ruta legacy que NO se toca
 *     en este cambio: sigue subiendo siempre a Drive sin mirar el flag;
 *     ver plan §5 "Fuera de alcance"). Esta tabla no tiene PAO/asignatura
 *     real (son evidencias a nivel de evaluación, no de asignatura), así
 *     que para reutilizar el mismo árbol de carpetas de 4 niveles que ya
 *     usa AlmacenamientoLocalService se usa 'General' como PAO fijo y
 *     `evaluaciones.nombre_evaluacion` como "asignatura" — decisión
 *     tomada explícitamente con el usuario en la sesión de v89 §67.
 *
 * Contrato: SÍNCRONO y TODO O NADA (decisiones ya tomadas con el usuario,
 * ver plan §3.6/§3.7). Si un solo archivo falla al migrar, se elimina del
 * destino nuevo todo lo que ya se había subido en esta llamada y no se
 * toca ni `url_archivo`/`nombre_archivo` de ninguna fila ni
 * `carreras.modo_almacenamiento` — la carrera queda exactamente como
 * estaba. Solo si los N archivos migran sin error se actualiza la BD
 * (todas las filas afectadas + la carrera) dentro de una única
 * transacción.
 */
final class EvidenciaMigradorService
{
    private const MODOS_VALIDOS = ['drive', 'local'];

    public function __construct(
        private readonly mysqli $conexion,
        private readonly GoogleDriveService $driveService,
        private readonly EvidenciaStorageResolver $resolver,
    ) {
    }

    /**
     * @return array{total_migrados: int, modo_anterior: string, modo_nuevo: string}
     *
     * @throws RuntimeException Si la carrera no existe/está desactivada, si
     *   la ruta local nueva no es escribible, o si algún archivo falla al
     *   migrar (en cuyo caso ya se revirtió lo que se alcanzó a subir antes
     *   de lanzar).
     */
    public function migrar(int $idCarrera, string $nuevoModo, ?string $nuevaRutaLocal): array
    {
        if (!in_array($nuevoModo, self::MODOS_VALIDOS, true)) {
            throw new RuntimeException("modo_almacenamiento debe ser 'drive' o 'local'.");
        }

        $carrera = $this->buscarCarreraActiva($idCarrera);
        if ($carrera === null) {
            throw new RuntimeException('La carrera no existe o se encuentra desactivada.');
        }

        $rutaLocalNormalizada = $this->normalizarRuta($nuevaRutaLocal);

        $sinCambios = $nuevoModo === $carrera['modo_almacenamiento']
            && ($nuevoModo !== 'local' || $rutaLocalNormalizada === $this->normalizarRuta($carrera['ruta_almacenamiento_local']));
        if ($sinCambios) {
            throw new RuntimeException('La carrera ya está en ese modo de almacenamiento (y esa ruta, si aplica); no hay nada que migrar.');
        }

        $destino = $nuevoModo === 'local'
            ? new AlmacenamientoLocalService($rutaLocalNormalizada)
            : $this->driveService;

        if ($nuevoModo === 'local') {
            $this->validarRutaLocalEscribible($rutaLocalNormalizada);
        }

        $items = [
            ...$this->recolectarEvidenciaAsignatura($idCarrera),
            ...$this->recolectarEvidenciasCatalogo($idCarrera),
        ];

        $migrados = $this->migrarArchivos($items, $carrera['nombre'], $destino);

        $this->confirmarEnBaseDeDatos($migrados, $idCarrera, $nuevoModo, $rutaLocalNormalizada);

        return [
            'total_migrados' => count($migrados),
            'modo_anterior' => $carrera['modo_almacenamiento'],
            'modo_nuevo' => $nuevoModo,
        ];
    }

    /**
     * Migra archivo por archivo. Si alguno falla, revierte (borra del
     * destino) todo lo ya migrado en esta llamada y lanza -- no toca la
     * BD en ningún punto de este método.
     *
     * @param list<array{tabla: string, id: int, nombre_archivo: string, url_archivo: string, cohorte: string, pao: string, asignatura: string}> $items
     *
     * @return list<array{tabla: string, id: int, nombre_archivo: string, url_archivo: string}>
     */
    private function migrarArchivos(array $items, string $nombreCarrera, EvidenciaStorageInterface $destino): array
    {
        $migrados = [];

        foreach ($items as $item) {
            $tmp = null;

            try {
                $origen = $this->resolver->resolverParaDescarga($item['url_archivo']);
                $contenido = $origen->descargarContenido($item['url_archivo']);
                if ($contenido === null) {
                    throw new RuntimeException("no se pudo leer el archivo de origen ('{$item['url_archivo']}').");
                }

                $tmp = tempnam(sys_get_temp_dir(), 'migracion_evidencia_');
                if ($tmp === false || file_put_contents($tmp, $contenido) === false) {
                    throw new RuntimeException('no se pudo preparar el archivo temporal para la subida.');
                }

                $mimeType = str_ends_with(strtolower($item['nombre_archivo']), '.csv') ? 'text/csv' : 'application/pdf';

                $subida = $destino->subirArchivo(
                    $tmp,
                    $item['nombre_archivo'],
                    $nombreCarrera,
                    $item['cohorte'],
                    $item['pao'],
                    $item['asignatura'],
                    $mimeType,
                );
            } catch (Throwable $e) {
                if ($tmp !== null && is_file($tmp)) {
                    @unlink($tmp);
                }

                foreach ($migrados as $yaMigrado) {
                    $destino->eliminarArchivo($yaMigrado['url_archivo']);
                }

                throw new RuntimeException(
                    "Migración revertida: falló '{$item['nombre_archivo']}' ({$item['tabla']} #{$item['id']}): " . $e->getMessage(),
                );
            }

            if (is_file($tmp)) {
                @unlink($tmp);
            }

            $migrados[] = [
                'tabla' => $item['tabla'],
                'id' => $item['id'],
                'nombre_archivo' => $subida['nombre_archivo'],
                'url_archivo' => $subida['url_archivo'],
            ];
        }

        return $migrados;
    }

    /**
     * Todos los archivos ya migraron sin error: confirma en una única
     * transacción las N filas afectadas + el nuevo modo de la carrera. Si
     * la transacción falla acá, los archivos ya quedaron físicamente en el
     * destino nuevo (riesgo aceptado y documentado en el plan §6) -- se
     * relanza con el detalle para que quede como pendiente de resolución
     * manual, en vez de fingir que no pasó nada.
     *
     * @param list<array{tabla: string, id: int, nombre_archivo: string, url_archivo: string}> $migrados
     */
    private function confirmarEnBaseDeDatos(array $migrados, int $idCarrera, string $nuevoModo, ?string $rutaLocalNormalizada): void
    {
        $this->conexion->begin_transaction();

        try {
            foreach ($migrados as $fila) {
                $this->actualizarUrlArchivo($fila['tabla'], $fila['id'], $fila['nombre_archivo'], $fila['url_archivo']);
            }

            $this->actualizarModoCarrera($idCarrera, $nuevoModo, $rutaLocalNormalizada);

            $this->conexion->commit();
        } catch (Throwable $e) {
            $this->conexion->rollback();

            throw new RuntimeException(
                'Los archivos se migraron al nuevo destino pero no se pudo actualizar la base de datos: ' . $e->getMessage(),
            );
        }
    }

    private function normalizarRuta(?string $ruta): ?string
    {
        $limpia = $ruta !== null ? trim($ruta) : '';

        return $limpia !== '' ? $limpia : null;
    }

    private function validarRutaLocalEscribible(?string $ruta): void
    {
        $raiz = $ruta ?? AlmacenamientoLocalService::raizPorDefecto();

        if (!is_dir($raiz) && !@mkdir($raiz, 0775, true) && !is_dir($raiz)) {
            throw new RuntimeException("La ruta de almacenamiento local '{$raiz}' no existe y no se pudo crear.");
        }

        if (!is_writable($raiz)) {
            throw new RuntimeException("La ruta de almacenamiento local '{$raiz}' no tiene permisos de escritura.");
        }
    }

    /** @return array{nombre: string, modo_almacenamiento: string, ruta_almacenamiento_local: ?string}|null */
    private function buscarCarreraActiva(int $idCarrera): ?array
    {
        $stmt = $this->conexion->prepare(
            'SELECT nombre, modo_almacenamiento, ruta_almacenamiento_local FROM carreras WHERE id_carrera = ? AND activo = 1',
        );
        $stmt->bind_param('i', $idCarrera);
        $stmt->execute();
        $fila = $stmt->get_result()->fetch_assoc();

        return $fila ?: null;
    }

    /**
     * Evidencia de I2/I3 (`evidencia_asignatura`) — mismo join que
     * contextoParaDrive(), pero por carrera completa en vez de por una
     * sola asignatura.
     *
     * @return list<array{tabla: string, id: int, nombre_archivo: string, url_archivo: string, cohorte: string, pao: string, asignatura: string}>
     */
    private function recolectarEvidenciaAsignatura(int $idCarrera): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT ea.id_evidencia_asig, ea.nombre_archivo, ea.url_archivo,
                    co.nombre_cohorte AS cohorte, p.nombre AS pao, a.nombre AS asignatura
             FROM evidencia_asignatura ea
             JOIN asignatura a ON a.id_asignatura = ea.id_asignatura
             JOIN periodo_academico p ON p.id_periodoacademico = a.id_periodoacademico
             JOIN cohortes co ON co.id_cohorte = p.id_cohorte
             WHERE co.id_carrera = ?',
        );
        $stmt->bind_param('i', $idCarrera);
        $stmt->execute();
        $resultado = $stmt->get_result();

        $items = [];
        while ($fila = $resultado->fetch_assoc()) {
            $items[] = [
                'tabla' => 'evidencia_asignatura',
                'id' => (int) $fila['id_evidencia_asig'],
                'nombre_archivo' => $fila['nombre_archivo'],
                'url_archivo' => $fila['url_archivo'],
                'cohorte' => $fila['cohorte'],
                'pao' => $fila['pao'],
                'asignatura' => $fila['asignatura'],
            ];
        }

        return $items;
    }

    /**
     * Evidencia de I4/I5 (`evidencias`, catálogo genérico) — sin PAO ni
     * asignatura real. Decisión tomada con el usuario (v89 §67): PAO fijo
     * 'General', "asignatura" = nombre de la evaluación.
     *
     * @return list<array{tabla: string, id: int, nombre_archivo: string, url_archivo: string, cohorte: string, pao: string, asignatura: string}>
     */
    private function recolectarEvidenciasCatalogo(int $idCarrera): array
    {
        $stmt = $this->conexion->prepare(
            'SELECT e.id_evidencia, e.nombre_archivo, e.url_archivo,
                    co.nombre_cohorte AS cohorte, ev.nombre_evaluacion AS asignatura
             FROM evidencias e
             JOIN evaluaciones ev ON ev.id_evaluacion = e.id_evaluacion
             JOIN cohortes co ON co.id_cohorte = ev.id_cohorte
             WHERE ev.id_carrera = ?',
        );
        $stmt->bind_param('i', $idCarrera);
        $stmt->execute();
        $resultado = $stmt->get_result();

        $items = [];
        while ($fila = $resultado->fetch_assoc()) {
            $items[] = [
                'tabla' => 'evidencias',
                'id' => (int) $fila['id_evidencia'],
                'nombre_archivo' => $fila['nombre_archivo'],
                'url_archivo' => $fila['url_archivo'],
                'cohorte' => $fila['cohorte'],
                'pao' => 'General',
                'asignatura' => $fila['asignatura'],
            ];
        }

        return $items;
    }

    private function actualizarUrlArchivo(string $tabla, int $id, string $nombreArchivo, string $urlArchivo): void
    {
        // Whitelist explícita (match): $tabla siempre viene de este mismo
        // archivo (recolectarEvidenciaAsignatura/recolectarEvidenciasCatalogo),
        // nunca de datos externos -- no hay concatenación de nombres de
        // tabla/columna con entrada del usuario.
        $sql = match ($tabla) {
            'evidencia_asignatura' => 'UPDATE evidencia_asignatura SET nombre_archivo = ?, url_archivo = ? WHERE id_evidencia_asig = ?',
            'evidencias' => 'UPDATE evidencias SET nombre_archivo = ?, url_archivo = ? WHERE id_evidencia = ?',
            default => throw new RuntimeException("Tabla de evidencia desconocida: '{$tabla}'."),
        };

        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('ssi', $nombreArchivo, $urlArchivo, $id);

        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
    }

    private function actualizarModoCarrera(int $idCarrera, string $nuevoModo, ?string $rutaLocalNormalizada): void
    {
        $stmt = $this->conexion->prepare(
            'UPDATE carreras SET modo_almacenamiento = ?, ruta_almacenamiento_local = ? WHERE id_carrera = ?',
        );
        $stmt->bind_param('ssi', $nuevoModo, $rutaLocalNormalizada, $idCarrera);

        if (!$stmt->execute()) {
            throw new RuntimeException($stmt->error);
        }
    }
}
