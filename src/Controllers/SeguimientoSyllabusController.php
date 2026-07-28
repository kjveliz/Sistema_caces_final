<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DTOs\EvidenciaSeguimientoItemDTO;
use App\Repositories\SeguimientoSyllabusRepository;
use App\Services\EncuestaEvidenciaService;
use App\Services\EvidenciaStorageResolver;
use App\Services\GoogleDriveService;
use App\Services\SeguimientoSyllabusCalculoService;
use OpenApi\Attributes as OA;
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
 *
 * Las anotaciones OpenAPI de cada método (Fase 3, hallazgo 1.2.7) se
 * generan a openapi.json con `composer generate-openapi` — ver
 * src/OpenApi/Definition.php para la info general del documento. Único
 * endpoint protegido: evidencia-subir (SessionAuthMiddleware, 401), igual
 * chequeo que evidencia-subir de I3 y guardar de I4/I5.
 */
#[OA\Tag(name: 'I2 - Seguimiento Syllabus')]
final class SeguimientoSyllabusController
{
    public function __construct(
        private readonly SeguimientoSyllabusRepository $repositorio,
        private readonly SeguimientoSyllabusCalculoService $calculoService,
        private readonly EncuestaEvidenciaService $encuestaService,
        private readonly GoogleDriveService $driveService,
        private readonly EvidenciaStorageResolver $storageResolver,
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

    /** POST /seguimiento-syllabus/cohortes (json: nombre_cohorte, id_carrera, fecha_inicio?, fecha_fin?) — crea una cohorte nueva. */
    #[OA\Post(
        path: '/seguimiento-syllabus/cohortes',
        summary: 'Crea una cohorte para una carrera.',
        description: 'Parte del flujo de carga de malla curricular en .xlsx al crear una carrera nueva '
            . '(ver plan_malla_curricular_xlsx.txt §7 Parte 2). A diferencia de `asignaturas`, este '
            . 'endpoint no hace get-or-create: cada llamada crea una fila nueva en `cohortes`.',
        tags: ['I2 - Seguimiento Syllabus'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['nombre_cohorte', 'id_carrera'],
                properties: [
                    new OA\Property(property: 'nombre_cohorte', type: 'string'),
                    new OA\Property(property: 'id_carrera', type: 'integer'),
                    new OA\Property(property: 'fecha_inicio', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'fecha_fin', type: 'string', format: 'date', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'ID de la cohorte creada.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', properties: [
                        new OA\Property(property: 'id_cohorte', type: 'integer'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'nombre_cohorte e id_carrera son requeridos.'),
            new OA\Response(response: 401, description: 'La sesión no está activa.'),
            new OA\Response(response: 404, description: 'La carrera indicada no existe.'),
        ],
    )]
    public function cohorteCrear(Request $request, Response $response): Response
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $nombreCohorte = trim((string) ($body['nombre_cohorte'] ?? ''));
        $idCarrera = (int) ($body['id_carrera'] ?? 0);
        $fechaInicio = trim((string) ($body['fecha_inicio'] ?? ''));
        $fechaFin = trim((string) ($body['fecha_fin'] ?? ''));

        if ($nombreCohorte === '' || $idCarrera <= 0) {
            return $this->json($response, false, 'nombre_cohorte e id_carrera son requeridos.', [], 400);
        }

        if (!$this->repositorio->carreraExiste($idCarrera)) {
            return $this->json($response, false, 'La carrera indicada no existe.', [], 404);
        }

        try {
            $idCohorte = $this->repositorio->crearCohorte(
                $nombreCohorte,
                $idCarrera,
                $fechaInicio !== '' ? $fechaInicio : null,
                $fechaFin !== '' ? $fechaFin : null,
            );
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo crear la cohorte.', ['detalle' => $e->getMessage()], 400);
        }

        return $this->json($response, true, 'Cohorte creada.', ['datos' => ['id_cohorte' => $idCohorte]]);
    }

    /** POST /seguimiento-syllabus/periodos (json: id_cohorte, nombre, orden, fecha_inicio?, fecha_fin?) — crea un período académico (PAO). */
    #[OA\Post(
        path: '/seguimiento-syllabus/periodos',
        summary: 'Crea un período académico (PAO) para una cohorte.',
        description: 'Parte del flujo de carga de malla curricular en .xlsx al crear una carrera nueva '
            . '(ver plan_malla_curricular_xlsx.txt §7 Parte 3). `orden` es requerido: '
            . '`GET /periodos` ya ordena por esta columna.',
        tags: ['I2 - Seguimiento Syllabus'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_cohorte', 'nombre', 'orden'],
                properties: [
                    new OA\Property(property: 'id_cohorte', type: 'integer'),
                    new OA\Property(property: 'nombre', type: 'string'),
                    new OA\Property(property: 'orden', type: 'integer'),
                    new OA\Property(property: 'fecha_inicio', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'fecha_fin', type: 'string', format: 'date', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'ID del período académico creado.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', properties: [
                        new OA\Property(property: 'id_periodoacademico', type: 'integer'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'id_cohorte, nombre y orden son requeridos.'),
            new OA\Response(response: 401, description: 'La sesión no está activa.'),
            new OA\Response(response: 404, description: 'La cohorte indicada no existe.'),
        ],
    )]
    public function periodoCrear(Request $request, Response $response): Response
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $idCohorte = (int) ($body['id_cohorte'] ?? 0);
        $nombre = trim((string) ($body['nombre'] ?? ''));
        $orden = isset($body['orden']) && $body['orden'] !== '' ? (int) $body['orden'] : 0;
        $fechaInicio = trim((string) ($body['fecha_inicio'] ?? ''));
        $fechaFin = trim((string) ($body['fecha_fin'] ?? ''));

        if ($idCohorte <= 0 || $nombre === '' || $orden <= 0) {
            return $this->json($response, false, 'id_cohorte, nombre y orden son requeridos.', [], 400);
        }

        if (!$this->repositorio->cohorteExiste($idCohorte)) {
            return $this->json($response, false, 'La cohorte indicada no existe.', [], 404);
        }

        try {
            $idPeriodo = $this->repositorio->crearPeriodo(
                $idCohorte,
                $nombre,
                $orden,
                $fechaInicio !== '' ? $fechaInicio : null,
                $fechaFin !== '' ? $fechaFin : null,
            );
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo crear el período académico.', ['detalle' => $e->getMessage()], 400);
        }

        return $this->json($response, true, 'Período académico creado.', ['datos' => ['id_periodoacademico' => $idPeriodo]]);
    }

    /** GET /seguimiento-syllabus/periodos?id_cohorte= */
    #[OA\Get(
        path: '/seguimiento-syllabus/periodos',
        summary: 'Lista los períodos académicos de una cohorte.',
        tags: ['I2 - Seguimiento Syllabus'],
        parameters: [
            new OA\QueryParameter(
                name: 'id_cohorte',
                description: 'ID de la cohorte.',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Períodos académicos de la cohorte.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'array', items: new OA\Items(type: 'object')),
                ]),
            ),
            new OA\Response(response: 400, description: 'Parámetro id_cohorte es requerido.'),
        ],
    )]
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
    #[OA\Get(
        path: '/seguimiento-syllabus/asignaturas',
        summary: 'Lista las asignaturas de un período académico.',
        tags: ['I2 - Seguimiento Syllabus'],
        parameters: [
            new OA\QueryParameter(
                name: 'id_periodo',
                description: 'ID del período académico.',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Asignaturas del período académico.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'array', items: new OA\Items(type: 'object')),
                ]),
            ),
            new OA\Response(response: 400, description: 'Parámetro id_periodo es requerido.'),
        ],
    )]
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
    #[OA\Post(
        path: '/seguimiento-syllabus/asignaturas',
        summary: 'Crea una asignatura en un período, o devuelve la existente si ya hay una con el mismo nombre.',
        tags: ['I2 - Seguimiento Syllabus'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_periodo', 'nombre'],
                properties: [
                    new OA\Property(property: 'id_periodo', type: 'integer'),
                    new OA\Property(property: 'nombre', type: 'string'),
                    new OA\Property(property: 'docente', type: 'string', nullable: true),
                    new OA\Property(
                        property: 'modulo',
                        type: 'string',
                        nullable: true,
                        description: 'Agrupador visual A/B/C (no entra en la clave de get-or-create; si la asignatura ya existía, se actualiza con este valor).',
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'ID de la asignatura (creada, o ya existente con el mismo nombre en el período).',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', properties: [
                        new OA\Property(property: 'id_asignatura', type: 'integer'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'id_periodo y nombre son requeridos, o modulo tiene más de 1 caracter.'),
            new OA\Response(response: 401, description: 'La sesión no está activa.'),
        ],
    )]
    public function asignaturaCrear(Request $request, Response $response): Response
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $idPeriodo = (int) ($body['id_periodo'] ?? 0);
        $nombre = trim((string) ($body['nombre'] ?? ''));
        $docente = trim((string) ($body['docente'] ?? ''));
        $modulo = trim((string) ($body['modulo'] ?? ''));

        if ($idPeriodo <= 0 || $nombre === '') {
            return $this->json($response, false, 'id_periodo y nombre son requeridos.', [], 400);
        }

        if (strlen($modulo) > 1) {
            return $this->json($response, false, 'modulo debe ser un solo caracter (A/B/C).', [], 400);
        }

        $moduloParam = $modulo !== '' ? $modulo : null;

        $idExistente = $this->repositorio->buscarAsignaturaPorPeriodoYNombre($idPeriodo, $nombre);
        if ($idExistente !== null) {
            if ($moduloParam !== null) {
                $this->repositorio->actualizarModuloAsignatura($idExistente, $moduloParam);
            }

            return $this->json($response, true, 'La asignatura ya existía.', ['datos' => ['id_asignatura' => $idExistente]]);
        }

        $docenteParam = $docente !== '' ? $docente : null;
        $idAsignatura = $this->repositorio->crearAsignatura($idPeriodo, $nombre, $docenteParam, $moduloParam);

        return $this->json($response, true, 'Asignatura creada.', ['datos' => ['id_asignatura' => $idAsignatura]]);
    }

    /** GET /seguimiento-syllabus/resultado-asignatura?id_asignatura=&id_evaluacion= */
    #[OA\Get(
        path: '/seguimiento-syllabus/resultado-asignatura',
        summary: 'Calcula el resultado de seguimiento de syllabus de una asignatura y guarda un snapshot.',
        tags: ['I2 - Seguimiento Syllabus'],
        parameters: [
            new OA\QueryParameter(
                name: 'id_asignatura',
                description: 'ID de la asignatura.',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\QueryParameter(
                name: 'id_evaluacion',
                description: 'ID de la evaluación.',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Resultado calculado (ver ResultadoAsignaturaSeguimientoDTO).',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'Parámetros id_asignatura e id_evaluacion son requeridos.'),
            new OA\Response(response: 404, description: 'Asignatura no encontrada.'),
        ],
    )]
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
    #[OA\Get(
        path: '/seguimiento-syllabus/resultado-cohorte',
        summary: 'Calcula el resultado general de seguimiento de syllabus de una cohorte (todas sus asignaturas).',
        description: 'id_periodo es opcional: si se envía, filtra el cálculo a un único período de la cohorte.',
        tags: ['I2 - Seguimiento Syllabus'],
        parameters: [
            new OA\QueryParameter(
                name: 'id_cohorte',
                description: 'ID de la cohorte.',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\QueryParameter(
                name: 'id_evaluacion',
                description: 'ID de la evaluación.',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\QueryParameter(
                name: 'id_periodo',
                description: 'ID de un período específico dentro de la cohorte (opcional).',
                required: false,
                schema: new OA\Schema(type: 'integer', nullable: true),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Resultado general y detalle por asignatura (ver ResultadoCohorteSeguimientoDTO).',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'Parámetros id_cohorte e id_evaluacion son requeridos.'),
        ],
    )]
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
    #[OA\Get(
        path: '/seguimiento-syllabus/evidencia-listar',
        summary: 'Lista el estado de evidencia (subida o no) de los 4 tipos por-asignatura de I2.',
        description: 'Siempre devuelve los 4 tipos (syllabus, acta_ajuste_curricular, evidencia_difusion, '
            . 'encuesta_csv), marcando subida=false y archivo=null en los que todavía no tienen archivo vigente.',
        tags: ['I2 - Seguimiento Syllabus'],
        parameters: [
            new OA\QueryParameter(
                name: 'id_asignatura',
                description: 'ID de la asignatura.',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Un ítem por cada tipo de evidencia (ver EvidenciaSeguimientoItemDTO).',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'array', items: new OA\Items(type: 'object')),
                ]),
            ),
            new OA\Response(response: 400, description: 'Parámetro id_asignatura es requerido.'),
        ],
    )]
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
    #[OA\Get(
        path: '/seguimiento-syllabus/encuesta-detalle',
        summary: 'Devuelve el detalle de la encuesta (parseada del CSV subido) de una asignatura.',
        description: 'id_evaluacion se acepta por compatibilidad con el frontend, pero ya no se usa para '
            . 'buscar el CSV (ver MEMORIA v18).',
        tags: ['I2 - Seguimiento Syllabus'],
        parameters: [
            new OA\QueryParameter(
                name: 'id_asignatura',
                description: 'ID de la asignatura.',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\QueryParameter(
                name: 'id_evaluacion',
                description: 'Aceptado por compatibilidad; no se usa para buscar el CSV.',
                required: false,
                schema: new OA\Schema(type: 'integer', nullable: true),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Detalle de la encuesta parseada.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'Parámetro id_asignatura es requerido.'),
            new OA\Response(response: 404, description: 'Asignatura no encontrada.'),
            new OA\Response(response: 502, description: 'La asignatura todavía no tiene un CSV de encuesta subido.'),
        ],
    )]
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
    #[OA\Post(
        path: '/seguimiento-syllabus/evidencia-subir',
        summary: 'Sube un archivo de evidencia (PDF, o CSV para encuesta_csv) a Google Drive y lo registra.',
        description: 'La evidencia anterior del mismo tipo para la asignatura queda marcada como no '
            . 'vigente; la nueva pasa a ser la evidencia vigente.',
        security: [['sesionPhp' => []]],
        tags: ['I2 - Seguimiento Syllabus'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['id_asignatura', 'tipo', 'archivo'],
                    properties: [
                        new OA\Property(property: 'id_asignatura', type: 'integer'),
                        new OA\Property(
                            property: 'tipo',
                            type: 'string',
                            enum: ['syllabus', 'acta_ajuste_curricular', 'evidencia_difusion', 'encuesta_csv'],
                        ),
                        new OA\Property(property: 'archivo', type: 'string', format: 'binary'),
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Evidencia subida y registrada.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', properties: [
                        new OA\Property(property: 'id_evidencia_asig', type: 'integer'),
                        new OA\Property(property: 'url_archivo', type: 'string', format: 'uri'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'Faltan datos, el tipo no es válido, o el archivo no pasó la validación.'),
            new OA\Response(response: 401, description: 'La sesión no está activa.'),
            new OA\Response(response: 404, description: 'Asignatura no encontrada.'),
            new OA\Response(response: 500, description: 'No se pudo guardar la evidencia.'),
            new OA\Response(response: 502, description: 'No se pudo subir el archivo de evidencia (Google Drive o almacenamiento local, según la carrera).'),
        ],
    )]
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

        $storage = $this->storageResolver->resolver((int) $contexto['id_carrera']);

        try {
            $subida = $storage->subirArchivo(
                $archivoLegacy['tmp_name'],
                $nombreArchivoDrive,
                $contexto['carrera'],
                $contexto['cohorte'],
                $contexto['pao'],
                $contexto['asignatura'],
                $esCsv ? 'text/csv' : 'application/pdf',
            );
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo subir el archivo de evidencia.', ['detalle' => $e->getMessage()], 502);
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
