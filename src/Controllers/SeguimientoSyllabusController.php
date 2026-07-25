<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DTOs\EvidenciaSeguimientoItemDTO;
use App\Repositories\SeguimientoSyllabusRepository;
use App\Services\EncuestaEvidenciaService;
use App\Services\GoogleDriveService;
use App\Services\SeguimientoSyllabusCalculoService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * Controlador de I2 (Seguimiento Syllabus). Reemplaza a los 8 archivos
 * sueltos de api/seguimiento_syllabus/{periodos,asignaturas,
 * resultado_asignatura,resultado_cohorte,evidencia_asignatura_listar,
 * evidencia_asignatura_subir,encuesta_detalle}.php — misma lógica de
 * negocio, mismas validaciones de parámetros, misma forma de respuesta
 * JSON ({ok, mensaje, datos}), ahora detrás del router de Slim (mismo
 * tratamiento que ya recibió I3).
 *
 * 'materias_encuesta.php' (deprecado, ver MEMORIA v18) NO se migra: ya
 * devolvía una lista vacía sin lógica real y, confirmado contra
 * frontend/src/services/seguimientoSyllabus.ts, ningún llamador del
 * frontend lo usa.
 */
final class SeguimientoSyllabusController
{
    public function __construct(
        private readonly SeguimientoSyllabusRepository $repositorio,
        private readonly SeguimientoSyllabusCalculoService $calculoService,
        private readonly EncuestaEvidenciaService $encuestaService,
        private readonly GoogleDriveService $driveService,
    ) {
    }

    private function json(Response $response, bool $ok, ?string $mensaje, array $extra = [], int $httpCode = 200): Response
    {
        $response->getBody()->write(json_encode(array_merge([
            'ok' => $ok,
            'mensaje' => $mensaje,
        ], $extra), JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus($httpCode);
    }

    /** GET /seguimiento-syllabus/periodos?id_cohorte= */
    public function periodos(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idCohorte = isset($params['id_cohorte']) ? (int) $params['id_cohorte'] : 0;

        if ($idCohorte <= 0) {
            return $this->json($response, false, 'Parámetro id_cohorte es requerido.', [], 400);
        }

        $datos = $this->repositorio->periodosPorCohorte($idCohorte);

        return $this->json($response, true, null, ['datos' => $datos]);
    }

    /** GET /seguimiento-syllabus/asignaturas?id_periodo= */
    public function asignaturasListar(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idPeriodo = isset($params['id_periodo']) ? (int) $params['id_periodo'] : 0;

        if ($idPeriodo <= 0) {
            return $this->json($response, false, 'Parámetro id_periodo es requerido.', [], 400);
        }

        $datos = $this->repositorio->asignaturasPorPeriodo($idPeriodo);

        return $this->json($response, true, null, ['datos' => $datos]);
    }

    /** POST /seguimiento-syllabus/asignaturas (json: id_periodo, nombre, docente) — crear o devolver existente. */
    public function asignaturaCrear(Request $request, Response $response): Response
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $idPeriodo = (int) ($body['id_periodo'] ?? 0);
        $nombre = trim((string) ($body['nombre'] ?? ''));
        $docente = trim((string) ($body['docente'] ?? ''));

        if ($idPeriodo <= 0 || $nombre === '') {
            return $this->json($response, false, 'id_periodo y nombre son requeridos.', [], 400);
        }

        $idExistente = $this->repositorio->buscarAsignaturaPorPeriodoYNombre($idPeriodo, $nombre);
        if ($idExistente !== null) {
            return $this->json($response, true, 'La asignatura ya existía.', ['datos' => ['id_asignatura' => $idExistente]]);
        }

        $docenteParam = $docente !== '' ? $docente : null;
        $idAsignatura = $this->repositorio->crearAsignatura($idPeriodo, $nombre, $docenteParam);

        return $this->json($response, true, 'Asignatura creada.', ['datos' => ['id_asignatura' => $idAsignatura]]);
    }

    /** GET /seguimiento-syllabus/resultado-asignatura?id_asignatura=&id_evaluacion= */
    public function resultadoAsignatura(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idAsignatura = isset($params['id_asignatura']) ? (int) $params['id_asignatura'] : 0;
        $idEvaluacion = isset($params['id_evaluacion']) ? (int) $params['id_evaluacion'] : 0;

        if ($idAsignatura <= 0 || $idEvaluacion <= 0) {
            return $this->json($response, false, 'Parámetros id_asignatura e id_evaluacion son requeridos.', [], 400);
        }

        $asignatura = $this->repositorio->asignaturaPorId($idAsignatura);
        if ($asignatura === null) {
            return $this->json($response, false, 'Asignatura no encontrada.', [], 404);
        }

        $resultado = $this->calculoService->calcularResultadoAsignatura($idAsignatura, $asignatura['nombre'], $idEvaluacion);
        $this->calculoService->guardarSnapshot($idAsignatura, $idEvaluacion, $resultado);

        return $this->json($response, true, null, ['datos' => $resultado->toArray()]);
    }

    /** GET /seguimiento-syllabus/resultado-cohorte?id_cohorte=&id_evaluacion=&id_periodo= */
    public function resultadoCohorte(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idCohorte = isset($params['id_cohorte']) ? (int) $params['id_cohorte'] : 0;
        $idEvaluacion = isset($params['id_evaluacion']) ? (int) $params['id_evaluacion'] : 0;
        $idPeriodo = (isset($params['id_periodo']) && $params['id_periodo'] !== '') ? (int) $params['id_periodo'] : null;

        if ($idCohorte <= 0 || $idEvaluacion <= 0) {
            return $this->json($response, false, 'Parámetros id_cohorte e id_evaluacion son requeridos.', [], 400);
        }

        $resultado = $this->calculoService->calcularResultadoGeneral($idCohorte, $idPeriodo, $idEvaluacion);

        foreach ($resultado->detalleAsignaturas as $r) {
            $this->calculoService->guardarSnapshot($r->idAsignatura, $idEvaluacion, $r);
        }

        return $this->json($response, true, null, ['datos' => $resultado->toArray()]);
    }

    /** GET /seguimiento-syllabus/evidencia-listar?id_asignatura= */
    public function evidenciaListar(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idAsignatura = isset($params['id_asignatura']) ? (int) $params['id_asignatura'] : 0;

        if ($idAsignatura <= 0) {
            return $this->json($response, false, 'Parámetro id_asignatura es requerido.', [], 400);
        }

        $filas = $this->repositorio->evidenciasVigentesPorAsignatura($idAsignatura, SeguimientoSyllabusCalculoService::TIPOS_POR_ASIGNATURA);
        $etiquetas = SeguimientoSyllabusCalculoService::etiquetasEvidencia();

        $porTipo = [];
        foreach (SeguimientoSyllabusCalculoService::TIPOS_POR_ASIGNATURA as $tipo) {
            $porTipo[$tipo] = new EvidenciaSeguimientoItemDTO(tipo: $tipo, label: $etiquetas[$tipo], subida: false, archivo: null);
        }
        foreach ($filas as $f) {
            $porTipo[$f['tipo']] = new EvidenciaSeguimientoItemDTO(
                tipo: $f['tipo'],
                label: $etiquetas[$f['tipo']],
                subida: true,
                archivo: [
                    'id_evidencia_asig' => (int) $f['id_evidencia_asig'],
                    'nombre_archivo' => $f['nombre_archivo'],
                    'url_archivo' => $f['url_archivo'],
                    'subido_por' => $f['subido_por'],
                    'fecha_subida' => $f['fecha_subida'],
                ],
            );
        }

        $datos = array_map(fn (EvidenciaSeguimientoItemDTO $d) => $d->toArray(), array_values($porTipo));

        return $this->json($response, true, null, ['datos' => $datos]);
    }

    /** GET /seguimiento-syllabus/encuesta-detalle?id_asignatura=&id_evaluacion= */
    public function encuestaDetalle(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idAsignatura = isset($params['id_asignatura']) ? (int) $params['id_asignatura'] : 0;

        // id_evaluacion se sigue aceptando por compatibilidad con el
        // frontend, pero ya no se usa para buscar el CSV (ver MEMORIA v18).
        if ($idAsignatura <= 0) {
            return $this->json($response, false, 'Parámetro id_asignatura es requerido.', [], 400);
        }

        $asignatura = $this->repositorio->asignaturaPorId($idAsignatura);
        if ($asignatura === null) {
            return $this->json($response, false, 'Asignatura no encontrada.', [], 404);
        }

        $detalle = $this->encuestaService->obtenerDetalleEncuesta($idAsignatura);
        if ($detalle === null) {
            return $this->json($response, false, 'Esta asignatura todavía no tiene un CSV de encuesta subido.', [], 502);
        }

        return $this->json($response, true, null, ['datos' => $detalle]);
    }

    /** POST /seguimiento-syllabus/evidencia-subir (multipart: id_asignatura, tipo, archivo) — requiere sesión. */
    public function evidenciaSubir(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $idAsignatura = isset($body['id_asignatura']) ? (int) $body['id_asignatura'] : 0;
        $tipo = trim((string) ($body['tipo'] ?? ''));

        if ($idAsignatura <= 0 || !in_array($tipo, SeguimientoSyllabusCalculoService::TIPOS_POR_ASIGNATURA, true)) {
            return $this->json($response, false, 'id_asignatura y tipo (válido) son requeridos.', [], 400);
        }

        $archivosSubidos = $request->getUploadedFiles();
        if (!isset($archivosSubidos['archivo'])) {
            return $this->json($response, false, 'No se recibió ningún archivo.', [], 400);
        }
        $archivoSubido = $archivosSubidos['archivo'];

        $archivoLegacy = [
            'name' => $archivoSubido->getClientFilename() ?? '',
            'type' => $archivoSubido->getClientMediaType() ?? '',
            'tmp_name' => $archivoSubido->getStream()->getMetadata('uri') ?? '',
            'error' => $archivoSubido->getError(),
            'size' => $archivoSubido->getSize() ?? 0,
        ];

        // El slot 'encuesta_csv' (ver MEMORIA v18) es CSV; el resto sigue siendo PDF.
        $esCsv = $tipo === 'encuesta_csv';
        $errorValidacion = $esCsv
            ? $this->driveService->validarCsv($archivoLegacy)
            : $this->driveService->validarArchivoSubido($archivoLegacy);
        if ($errorValidacion !== null) {
            return $this->json($response, false, $errorValidacion, [], 400);
        }

        $contexto = $this->repositorio->contextoParaDrive($idAsignatura);
        if ($contexto === null) {
            return $this->json($response, false, 'Asignatura no encontrada.', [], 404);
        }

        $extension = $esCsv ? 'csv' : 'pdf';
        $nombreArchivoDrive = sprintf(
            '%s_%s_%s.%s',
            $tipo,
            preg_replace('/[^A-Za-z0-9]+/', '_', $contexto['asignatura']),
            date('Ymd_His'),
            $extension,
        );

        try {
            $subida = $this->driveService->subirArchivo(
                $archivoLegacy['tmp_name'],
                $nombreArchivoDrive,
                $contexto['carrera'],
                $contexto['cohorte'],
                $contexto['pao'],
                $contexto['asignatura'],
                $esCsv ? 'text/csv' : 'application/pdf',
            );
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo subir el archivo a Google Drive.', ['detalle' => $e->getMessage()], 502);
        }

        $idUsuario = (string) (int) ($_SESSION['id_usuario'] ?? 0);
        $conexion = $this->repositorio->conexion();

        $conexion->begin_transaction();
        try {
            $this->repositorio->marcarEvidenciaAnteriorNoVigente($idAsignatura, $tipo);
            $idEvidenciaAsig = $this->repositorio->guardarNuevaEvidencia(
                $idAsignatura,
                $tipo,
                $subida['nombre_archivo'],
                $subida['url_archivo'],
                $idUsuario,
            );

            $conexion->commit();
        } catch (Throwable $e) {
            $conexion->rollback();

            return $this->json($response, false, 'No se pudo guardar la evidencia.', ['detalle' => $e->getMessage()], 500);
        }

        return $this->json($response, true, 'Evidencia subida correctamente.', [
            'datos' => [
                'id_evidencia_asig' => $idEvidenciaAsig,
                'url_archivo' => $subida['url_archivo'],
            ],
        ]);
    }
}
