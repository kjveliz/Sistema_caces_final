<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DTOs\EvidenciaTutoriasItemDTO;
use App\Repositories\TutoriasRepository;
use App\Services\GoogleDriveService;
use App\Services\TutoriasCalculoService;
use App\Services\TutoriasValidacionPdfService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * Controlador de I3 (Tutorías Académicas). Reemplaza a los 4 archivos
 * sueltos api/tutorias_academicas/{evidencia_listar,evidencia_subir,
 * resultado_asignatura,resultado_cohorte}.php — misma lógica de negocio,
 * mismas validaciones de parámetros, misma forma de respuesta JSON
 * ({ok, mensaje, datos}), ahora detrás del router de Slim en vez de ser
 * cada uno un archivo PHP accesible directo por URL (hallazgo 1.2.1).
 */
final class TutoriasAcademicasController
{
    public function __construct(
        private readonly TutoriasRepository $repositorio,
        private readonly TutoriasCalculoService $calculoService,
        private readonly TutoriasValidacionPdfService $validacionService,
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

    /** GET /tutorias-academicas/evidencia-listar?id_asignatura= */
    public function evidenciaListar(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idAsignatura = isset($params['id_asignatura']) ? (int) $params['id_asignatura'] : 0;

        if ($idAsignatura <= 0) {
            return $this->json($response, false, 'Parámetro id_asignatura es requerido.', [], 400);
        }

        $filas = $this->repositorio->evidenciasVigentesPorAsignatura($idAsignatura);
        $tipoAEf = TutoriasCalculoService::tipoAEf();
        $validacionesPorEf = $this->repositorio->obtenerValidacionesPorEf($idAsignatura);

        $porTipo = [];
        foreach (TutoriasCalculoService::TIPOS_TUTORIAS as $tipo) {
            $ef = $tipoAEf[$tipo];
            $porTipo[$tipo] = new EvidenciaTutoriasItemDTO(
                tipo: $tipo,
                ef: $ef,
                subida: false,
                archivo: null,
                validacion: $validacionesPorEf[$ef] ?? null,
            );
        }
        foreach ($filas as $f) {
            $anterior = $porTipo[$f['tipo']];
            $porTipo[$f['tipo']] = new EvidenciaTutoriasItemDTO(
                tipo: $anterior->tipo,
                ef: $anterior->ef,
                subida: true,
                archivo: [
                    'id_evidencia_asig' => (int) $f['id_evidencia_asig'],
                    'nombre_archivo' => $f['nombre_archivo'],
                    'url_archivo' => $f['url_archivo'],
                    'subido_por' => $f['subido_por'],
                    'fecha_subida' => $f['fecha_subida'],
                ],
                validacion: $anterior->validacion,
            );
        }

        $datos = array_map(fn (EvidenciaTutoriasItemDTO $d) => $d->toArray(), array_values($porTipo));

        return $this->json($response, true, null, ['datos' => $datos]);
    }

    /** GET /tutorias-academicas/resultado-asignatura?id_asignatura=&id_evaluacion= */
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

        // Se recalcula en cada consulta (no se cachea): mismo comportamiento
        // esperado que el original ("recalcula también al ver resultados").
        $resultado = $this->calculoService->calcularResultadoAsignatura($idAsignatura, $asignatura['nombre']);
        $this->calculoService->guardarSnapshot($idAsignatura, $idEvaluacion, $resultado);

        return $this->json($response, true, null, ['datos' => $resultado->toArray()]);
    }

    /** GET /tutorias-academicas/resultado-cohorte?id_cohorte=&id_evaluacion=&id_periodo= */
    public function resultadoCohorte(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idCohorte = isset($params['id_cohorte']) ? (int) $params['id_cohorte'] : 0;
        $idEvaluacion = isset($params['id_evaluacion']) ? (int) $params['id_evaluacion'] : 0;
        $idPeriodo = (isset($params['id_periodo']) && $params['id_periodo'] !== '') ? (int) $params['id_periodo'] : null;

        if ($idCohorte <= 0 || $idEvaluacion <= 0) {
            return $this->json($response, false, 'Parámetros id_cohorte e id_evaluacion son requeridos.', [], 400);
        }

        $resultado = $this->calculoService->calcularResultadoGeneral($idCohorte, $idPeriodo);

        foreach ($resultado->detalleAsignaturas as $r) {
            $this->calculoService->guardarSnapshot($r->idAsignatura, $idEvaluacion, $r);
        }

        return $this->json($response, true, null, ['datos' => $resultado->toArray()]);
    }

    /** POST /tutorias-academicas/evidencia-subir (multipart: id_asignatura, tipo, archivo) — requiere sesión. */
    public function evidenciaSubir(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $idAsignatura = isset($body['id_asignatura']) ? (int) $body['id_asignatura'] : 0;
        $tipo = trim((string) ($body['tipo'] ?? ''));

        if ($idAsignatura <= 0 || !in_array($tipo, TutoriasCalculoService::TIPOS_TUTORIAS, true)) {
            return $this->json($response, false, 'id_asignatura y tipo (válido) son requeridos.', [], 400);
        }

        $archivosSubidos = $request->getUploadedFiles();
        if (!isset($archivosSubidos['archivo'])) {
            return $this->json($response, false, 'No se recibió ningún archivo.', [], 400);
        }
        $archivoSubido = $archivosSubidos['archivo'];

        // El GoogleDriveService/validación heredada esperan el shape clásico
        // de $_FILES; se arma acá para no tocar esa lógica compartida con I2.
        $archivoLegacy = [
            'name' => $archivoSubido->getClientFilename() ?? '',
            'type' => $archivoSubido->getClientMediaType() ?? '',
            'tmp_name' => $archivoSubido->getStream()->getMetadata('uri') ?? '',
            'error' => $archivoSubido->getError(),
            'size' => $archivoSubido->getSize() ?? 0,
        ];

        $errorValidacion = $this->driveService->validarArchivoSubido($archivoLegacy);
        if ($errorValidacion !== null) {
            return $this->json($response, false, $errorValidacion, [], 400);
        }

        $ef = TutoriasCalculoService::tipoAEf()[$tipo];

        $contexto = $this->repositorio->contextoParaDrive($idAsignatura);
        if ($contexto === null) {
            return $this->json($response, false, 'Asignatura no encontrada.', [], 404);
        }

        // EF2 se topa a 100% si las horas evidenciadas >= horas planeadas en EF1.
        $horasEf1Previas = $ef === 'EF2' ? $this->repositorio->horasEf1Previas($idAsignatura) : null;

        try {
            $resultadoValidacion = $this->validacionService->validarPdfTutorias($archivoLegacy['tmp_name'], $ef, $horasEf1Previas);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo leer el contenido del PDF.', ['detalle' => $e->getMessage()], 422);
        }

        $nombreArchivoDrive = sprintf(
            '%s_%s_%s.pdf',
            $tipo,
            preg_replace('/[^A-Za-z0-9]+/', '_', $contexto['asignatura']),
            date('Ymd_His'),
        );

        try {
            $subida = $this->driveService->subirArchivo(
                $archivoLegacy['tmp_name'],
                $nombreArchivoDrive,
                $contexto['carrera'],
                $contexto['cohorte'],
                $contexto['pao'],
                $contexto['asignatura'],
            );
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo subir el PDF a Google Drive.', ['detalle' => $e->getMessage()], 502);
        }

        $idUsuario = (string) (int) ($_SESSION['id_usuario'] ?? 0);
        $conexion = $this->repositorio->conexion();

        $conexion->begin_transaction();
        try {
            // Igual que I2: la evidencia anterior del mismo tipo/asignatura
            // pasa a vigente=0 (se conserva como historial), la nueva se
            // marca vigente=1.
            $this->repositorio->marcarEvidenciaAnteriorNoVigente($idAsignatura, $tipo);

            $idEvidenciaAsig = $this->repositorio->guardarNuevaEvidencia(
                $idAsignatura,
                $tipo,
                $subida['nombre_archivo'],
                $subida['url_archivo'],
                $idUsuario,
            );

            foreach ($resultadoValidacion['puntos'] as $orden => $punto) {
                $this->repositorio->guardarPuntoValidacion(
                    $idEvidenciaAsig,
                    $ef,
                    $orden + 1,
                    $punto['nombre'],
                    $punto['cumplido'],
                    $punto['valor'],
                );
            }

            $conexion->commit();
        } catch (Throwable $e) {
            $conexion->rollback();

            return $this->json($response, false, 'No se pudo guardar la evidencia o su validación.', ['detalle' => $e->getMessage()], 500);
        }

        return $this->json($response, true, 'Evidencia subida y validada correctamente.', [
            'datos' => [
                'id_evidencia_asig' => $idEvidenciaAsig,
                'url_archivo' => $subida['url_archivo'],
                'ef' => $ef,
                'puntos' => $resultadoValidacion['puntos'],
                'cumplidos' => count(array_filter($resultadoValidacion['puntos'], fn (array $p) => $p['cumplido'])),
                'total_puntos' => count($resultadoValidacion['puntos']),
            ],
        ]);
    }
}
