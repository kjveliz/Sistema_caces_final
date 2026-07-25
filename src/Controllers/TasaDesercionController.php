<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\DesercionRepository;
use App\Services\DesercionCalculoService;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Controlador de I4 (Tasa de Deserción). Reemplaza a los 3 archivos
 * sueltos api/tasa_desercion/{obtener,leer_pdf,guardar}.php — misma
 * lógica de negocio, mismas validaciones de parámetros, misma forma de
 * respuesta JSON ({ok, mensaje, datos}), ahora detrás del router de Slim en
 * vez de ser cada uno un archivo PHP accesible directo por URL (hallazgo
 * 1.2.1 del Plan de Mejora). Mismo patrón que TitulacionController (I5,
 * v66) y TutoriasAcademicasController (I3, v63/v64).
 *
 * Igual que I5, I4 no maneja subida de evidencia PDF a Google Drive en sus
 * propios endpoints: eso lo hacen los endpoints genéricos api/evidencias/*
 * + api/google_drive/*, que no se tocan en esta migración. Por eso no hay
 * dependencia de GoogleDriveService aquí.
 *
 * La escritura cruzada I5→I4 (subir el PDF de matriculados de primer nivel
 * de I5 también registra el dato inicial de I4) vive en el frontend
 * (EvidenceUploadView.tsx, procesarPdf()), no en este controlador -- ver
 * MEMORIA §14.3. No se toca en esta migración; solo cambia la URL base que
 * ese código ya llama (guardarDatoDesercion() en
 * frontend/src/services/evidencias.ts), actualizada junto con este
 * controlador.
 *
 * Las anotaciones OpenAPI de cada método (Fase 3, hallazgo 1.2.7) se
 * generan a openapi.json con `composer generate-openapi` -- ver
 * src/OpenApi/Definition.php para la info general del documento. Nota:
 * igual que con I2 (ver MEMORIA v67 §44.7 punto 1), la regeneración de
 * openapi.json en sí queda pendiente de esta sesión (requiere Composer con
 * acceso a packagist.org, no disponible en el sandbox de Claude).
 */
#[OA\Tag(name: 'I4 - Tasa de Deserción')]
final class TasaDesercionController
{
    private const TIPOS_DATO_PERMITIDOS = ['primer_nivel', 'segundo_anio', 'no_continuaron'];

    public function __construct(
        private readonly DesercionRepository $repositorio,
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

    /** GET /tasa-desercion/obtener?id_evaluacion= */
    #[OA\Get(
        path: '/tasa-desercion/obtener',
        summary: 'Lista los datos de deserción (primer nivel/segundo año/no continuaron/tasa) de una evaluación, por cohorte.',
        tags: ['I4 - Tasa de Deserción'],
        parameters: [
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
                description: 'Filas de datos_tasa_desercion para la evaluación, más recientes primero.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'array', items: new OA\Items(type: 'object')),
                ]),
            ),
            new OA\Response(response: 400, description: 'El identificador de la evaluación no es válido.'),
            new OA\Response(response: 500, description: 'No se pudieron consultar los datos.'),
        ],
    )]
    public function obtener(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idEvaluacion = isset($params['id_evaluacion']) ? (int) $params['id_evaluacion'] : 0;

        if ($idEvaluacion <= 0) {
            return $this->json($response, false, 'El identificador de la evaluación no es válido.', [], 400);
        }

        try {
            $filas = $this->repositorio->obtenerPorEvaluacion($idEvaluacion);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudieron consultar los datos.', ['detalle' => $e->getMessage()], 500);
        }

        $datos = array_map(fn ($d) => $d->toArray(), $filas);

        return $this->json($response, true, null, ['datos' => $datos]);
    }

    /** POST /tasa-desercion/leer-pdf (multipart: archivo, tipo_dato) */
    #[OA\Post(
        path: '/tasa-desercion/leer-pdf',
        summary: 'Extrae de un PDF el total de estudiantes de primer nivel, segundo año o que no continuaron.',
        tags: ['I4 - Tasa de Deserción'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['archivo', 'tipo_dato'],
                    properties: [
                        new OA\Property(property: 'archivo', type: 'string', format: 'binary'),
                        new OA\Property(property: 'tipo_dato', type: 'string', enum: ['primer_nivel', 'segundo_anio', 'no_continuaron']),
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Total detectado, método usado (total_reportado / identificaciones_unicas) y '
                    . 'cohorte/período si se pudieron inferir del texto.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'Falta el archivo o tipo_dato no es válido.'),
            new OA\Response(response: 500, description: 'No se pudo leer la información del PDF.'),
        ],
    )]
    public function leerPdf(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $tipoDato = trim((string) ($body['tipo_dato'] ?? ''));

        if (!in_array($tipoDato, self::TIPOS_DATO_PERMITIDOS, true)) {
            return $this->json($response, false, 'El tipo de dato no es válido.', [], 400);
        }

        $archivosSubidos = $request->getUploadedFiles();
        if (!isset($archivosSubidos['archivo'])) {
            return $this->json($response, false, 'No se recibió ningún archivo PDF.', [], 400);
        }
        $archivoSubido = $archivosSubidos['archivo'];

        if ($archivoSubido->getError() !== UPLOAD_ERR_OK) {
            return $this->json($response, false, 'No se pudo recibir el archivo.', [], 400);
        }

        $rutaTemporal = $archivoSubido->getStream()->getMetadata('uri') ?? '';
        $extension = strtolower(pathinfo($archivoSubido->getClientFilename() ?? '', PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($rutaTemporal);

        if ($extension !== 'pdf' || $mime !== 'application/pdf') {
            return $this->json($response, false, 'El archivo debe ser un PDF válido.', [], 400);
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($rutaTemporal);
            $texto = $pdf->getText();

            $datos = DesercionCalculoService::extraerDatosDesercion($texto, $tipoDato);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo leer la información del PDF.', ['detalle' => $e->getMessage()], 500);
        }

        return $this->json($response, true, 'El PDF fue leído correctamente.', ['datos' => $datos->toArray()]);
    }

    /** POST /tasa-desercion/guardar (JSON: id_evaluacion, cohorte, iniciaron_primer_nivel?, matriculados_segundo_anio?, no_continuaron?) — requiere sesión. */
    #[OA\Post(
        path: '/tasa-desercion/guardar',
        summary: 'Crea/actualiza uno de los 3 datos de una cohorte y recalcula la tasa de deserción.',
        description: 'Solo se envía uno de los 3 campos por llamada (no varios a la vez); la tasa solo se '
            . 'recalcula cuando ya existen iniciaron_primer_nivel y no_continuaron. Devuelve una advertencia '
            . 'si, con los 3 valores ya cargados, primer nivel menos no continuaron no coincide con '
            . 'matriculados de segundo año.',
        security: [['sesionPhp' => []]],
        tags: ['I4 - Tasa de Deserción'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_evaluacion', 'cohorte'],
                properties: [
                    new OA\Property(property: 'id_evaluacion', type: 'integer'),
                    new OA\Property(property: 'cohorte', type: 'string', example: 'B2025'),
                    new OA\Property(property: 'iniciaron_primer_nivel', type: 'integer', nullable: true),
                    new OA\Property(property: 'matriculados_segundo_anio', type: 'integer', nullable: true),
                    new OA\Property(property: 'no_continuaron', type: 'integer', nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Dato guardado, tasa recalculada y advertencia opcional (ver DatoDesercionGuardadoDTO).',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'advertencia', type: 'string', nullable: true),
                    new OA\Property(property: 'datos', type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'Faltan datos o las cantidades recibidas no son válidas.'),
            new OA\Response(response: 401, description: 'La sesión no está activa.'),
            new OA\Response(response: 500, description: 'No se pudieron actualizar los datos de deserción.'),
        ],
    )]
    public function guardar(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        $idEvaluacion = (int) ($body['id_evaluacion'] ?? 0);
        // Mismo orden exacto que el original (y que TitulacionController::guardar):
        // preg_replace (sin /i) sobre el valor todavía en su capitalización
        // original, y recién después strtoupper. Se preserva a propósito.
        $cohorte = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim((string) ($body['cohorte'] ?? ''))));

        $tienePrimerNivel = is_array($body) && array_key_exists('iniciaron_primer_nivel', $body);
        $tieneSegundoAnio = is_array($body) && array_key_exists('matriculados_segundo_anio', $body);
        $tieneNoContinuaron = is_array($body) && array_key_exists('no_continuaron', $body);

        $primerNivel = $tienePrimerNivel ? (int) $body['iniciaron_primer_nivel'] : null;
        $segundoAnio = $tieneSegundoAnio ? (int) $body['matriculados_segundo_anio'] : null;
        $noContinuaron = $tieneNoContinuaron ? (int) $body['no_continuaron'] : null;

        if ($idEvaluacion <= 0 || $cohorte === '' || (!$tienePrimerNivel && !$tieneSegundoAnio && !$tieneNoContinuaron)) {
            return $this->json($response, false, 'Faltan datos para actualizar la tasa de deserción.', [], 400);
        }

        if (
            ($tienePrimerNivel && $primerNivel <= 0)
            || ($tieneSegundoAnio && $segundoAnio < 0)
            || ($tieneNoContinuaron && $noContinuaron < 0)
        ) {
            return $this->json($response, false, 'Las cantidades recibidas no son válidas.', [], 400);
        }

        try {
            $resultado = $this->repositorio->guardarDato($idEvaluacion, $cohorte, $primerNivel, $segundoAnio, $noContinuaron);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudieron actualizar los datos de deserción.', ['detalle' => $e->getMessage()], 500);
        }

        return $this->json($response, true, 'Datos de deserción actualizados correctamente.', [
            'advertencia' => $resultado->advertencia,
            'datos' => $resultado->toArray(),
        ]);
    }
}
