<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\EvidenciasRepository;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Controlador del Grupo B (Evidencias genéricas, usadas por I1/I4/I5) del
 * plan de migración de PHP suelto a Slim (ver
 * plan_migracion_slim_legacy_v3.txt §1/§3). Arranca en la Parte 4 con
 * guardadas() (reemplaza a api/evidencias/obtener_evidencias_guardadas.php);
 * las Partes 5-8 del mismo grupo suman métodos acá (mismo criterio que
 * AuthController con el Grupo A: un Controller por grupo, no por Parte).
 * Parte 5 agrega compartidas() (reemplaza a
 * api/evidencias/obtener_compartidas.php). Parte 6 agrega
 * leerMatriculados() (reemplaza a api/evidencias/leer_matriculados.php) --
 * a diferencia de las dos anteriores, no usa EvidenciasRepository (no toca
 * BD, es puramente lectura de PDF -> datos), por eso el constructor sigue
 * pidiendo el repositorio aunque este método no lo use. Parte 7 agrega
 * guardar() (reemplaza a api/evidencias/guardar_evidencia.php), cuarta
 * Parte del Grupo B -- vuelve a usar EvidenciasRepository. Parte 8 agrega
 * prepararPdf() (reemplaza a api/evidencias/preparar_pdf.php), quinta y
 * última Parte del Grupo B.
 */
#[OA\Tag(name: 'Evidencias (I1/I4/I5)')]
final class EvidenciasController
{
    public function __construct(
        private readonly EvidenciasRepository $repositorio,
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

    /**
     * GET /evidencias/guardadas?id_evaluacion=&id_indicador= — misma
     * lógica exacta que api/evidencias/obtener_evidencias_guardadas.php:
     * lista las evidencias ya guardadas de una evaluación para un
     * indicador dado, cruzando con su fila en catalogo_evidencias.
     *
     * OJO — desvío intencional del helper json() de arriba, mismo criterio
     * que AuthController::me() (Parte 3): el original NO incluye la clave
     * "mensaje" en la respuesta exitosa (solo "ok" y "datos") — se arma a
     * mano para no agregar "mensaje": null de más. En los casos de error sí
     * se usa json(), igual que el original sí trae "mensaje" en esos casos.
     */
    #[OA\Get(
        path: '/evidencias/guardadas',
        summary: 'Lista las evidencias ya guardadas de una evaluación para un indicador.',
        tags: ['Evidencias (I1/I4/I5)'],
        parameters: [
            new OA\QueryParameter(
                name: 'id_evaluacion',
                description: 'ID de la evaluación.',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\QueryParameter(
                name: 'id_indicador',
                description: 'ID del indicador (catalogo_evidencias.id_indicador).',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de evidencias guardadas (puede venir vacía si no hay ninguna todavía).',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'id_evidencia', type: 'integer'),
                        new OA\Property(property: 'id_catalogo', type: 'integer'),
                        new OA\Property(property: 'id_evaluacion', type: 'integer'),
                        new OA\Property(property: 'codigo_evidencia', type: 'string'),
                        new OA\Property(property: 'descripcion', type: 'string'),
                        new OA\Property(property: 'nombre_archivo', type: 'string'),
                        new OA\Property(property: 'tipo', type: 'string', nullable: true),
                        new OA\Property(property: 'url_archivo', type: 'string'),
                        new OA\Property(property: 'fecha_subida', type: 'string'),
                        new OA\Property(property: 'titulo_corto', type: 'string'),
                        new OA\Property(property: 'nombre_archivo_base', type: 'string'),
                        new OA\Property(property: 'orden', type: 'integer'),
                    ], type: 'object')),
                ]),
            ),
            new OA\Response(response: 400, description: 'Debe enviar la evaluación y el indicador.'),
            new OA\Response(response: 500, description: 'No se pudo preparar la consulta.'),
        ],
    )]
    public function guardadas(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idEvaluacion = isset($params['id_evaluacion']) ? (int) $params['id_evaluacion'] : 0;
        $idIndicador = isset($params['id_indicador']) ? (int) $params['id_indicador'] : 0;

        if ($idEvaluacion <= 0 || $idIndicador <= 0) {
            return $this->json($response, false, 'Debe enviar la evaluación y el indicador.', [], 400);
        }

        try {
            $evidencias = $this->repositorio->obtenerGuardadas($idEvaluacion, $idIndicador);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo preparar la consulta.', ['detalle' => $e->getMessage()], 500);
        }

        $response->getBody()->write(json_encode([
            'ok' => true,
            'datos' => $evidencias,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * GET /evidencias/compartidas?id_evaluacion=&id_indicador_destino= —
     * misma lógica exacta que api/evidencias/obtener_compartidas.php:
     * lista las evidencias de la evaluación que ya están compartidas con
     * el indicador destino (vía indicador_evidencia) pero cuyo indicador
     * de origen es distinto al destino.
     *
     * OJO — mismo desvío intencional que guardadas() (Parte 4): el
     * original NO incluye "mensaje" en la respuesta exitosa, solo "ok" y
     * "datos" — se arma a mano para no agregar "mensaje": null de más. En
     * los casos de error sí se usa json(), igual que el original.
     */
    #[OA\Get(
        path: '/evidencias/compartidas',
        summary: 'Lista las evidencias de otros indicadores ya compartidas con el indicador destino.',
        tags: ['Evidencias (I1/I4/I5)'],
        parameters: [
            new OA\QueryParameter(
                name: 'id_evaluacion',
                description: 'ID de la evaluación.',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
            new OA\QueryParameter(
                name: 'id_indicador_destino',
                description: 'ID del indicador destino (catalogo_evidencias.id_indicador) que quiere reutilizar evidencia de otro indicador.',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lista de evidencias compartidas (puede venir vacía si no hay ninguna todavía).',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'array', items: new OA\Items(properties: [
                        new OA\Property(property: 'id_evidencia', type: 'integer'),
                        new OA\Property(property: 'id_catalogo', type: 'integer'),
                        new OA\Property(property: 'id_evaluacion', type: 'integer'),
                        new OA\Property(property: 'codigo_evidencia', type: 'string'),
                        new OA\Property(property: 'descripcion', type: 'string'),
                        new OA\Property(property: 'nombre_archivo', type: 'string'),
                        new OA\Property(property: 'tipo', type: 'string', nullable: true),
                        new OA\Property(property: 'url_archivo', type: 'string'),
                        new OA\Property(property: 'fecha_subida', type: 'string'),
                        new OA\Property(property: 'titulo_corto', type: 'string'),
                        new OA\Property(property: 'nombre_archivo_base', type: 'string'),
                        new OA\Property(property: 'orden', type: 'integer'),
                        new OA\Property(property: 'id_indicador_origen', type: 'integer'),
                        new OA\Property(property: 'indicador_origen', type: 'string'),
                    ], type: 'object')),
                ]),
            ),
            new OA\Response(response: 400, description: 'Debe enviar la evaluación y el indicador destino.'),
            new OA\Response(response: 500, description: 'No se pudo preparar la consulta.'),
        ],
    )]
    public function compartidas(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idEvaluacion = isset($params['id_evaluacion']) ? (int) $params['id_evaluacion'] : 0;
        $idIndicadorDestino = isset($params['id_indicador_destino']) ? (int) $params['id_indicador_destino'] : 0;

        if ($idEvaluacion <= 0 || $idIndicadorDestino <= 0) {
            return $this->json($response, false, 'Debe enviar la evaluación y el indicador destino.', [], 400);
        }

        try {
            $evidencias = $this->repositorio->obtenerCompartidas($idEvaluacion, $idIndicadorDestino);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo preparar la consulta.', ['detalle' => $e->getMessage()], 500);
        }

        $response->getBody()->write(json_encode([
            'ok' => true,
            'datos' => $evidencias,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }

    /**
     * POST /evidencias/leer-matriculados (multipart: archivo) — misma
     * lógica exacta que api/evidencias/leer_matriculados.php: extrae de un
     * PDF el total de alumnos matriculados (con los mismos 3 patrones de
     * "total reportado", en el mismo orden, y el mismo respaldo de contar
     * identificaciones de 10 dígitos si ninguno matchea), más el período y
     * la cohorte si se pueden inferir del texto. No usa
     * EvidenciasRepository: no hay BD de por medio, es solo texto -> datos
     * (mismo criterio que TitulacionCalculoService/DesercionCalculoService,
     * pero sin extraer un Service aparte porque este endpoint no comparte
     * la lógica de extracción con esos otros dos -- misma decisión de "no
     * sobre-diseñar" del plan, §2 punto 2).
     *
     * A propósito, a diferencia de TasaDesercionController::leerPdf() (que
     * sí valida extensión + mime), acá solo se valida el mime, igual que el
     * original: no se agrega la validación de extensión porque el original
     * no la tenía.
     */
    #[OA\Post(
        path: '/evidencias/leer-matriculados',
        summary: 'Extrae de un PDF el total de alumnos matriculados, el período y la cohorte detectada.',
        tags: ['Evidencias (I1/I4/I5)'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['archivo'],
                    properties: [
                        new OA\Property(property: 'archivo', type: 'string', format: 'binary'),
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Total de matriculados detectado, más período y cohorte si se pudieron inferir del texto.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'mensaje', type: 'string'),
                    new OA\Property(property: 'datos', type: 'object', properties: [
                        new OA\Property(property: 'matriculados', type: 'integer'),
                        new OA\Property(property: 'periodo', type: 'string', nullable: true),
                        new OA\Property(property: 'cohorte_detectada', type: 'string', nullable: true),
                    ]),
                ]),
            ),
            new OA\Response(response: 400, description: 'No se recibió el PDF, no se pudo recibir el archivo, o no es un PDF válido.'),
            new OA\Response(response: 500, description: 'No se pudo leer el contenido del PDF.'),
        ],
    )]
    public function leerMatriculados(Request $request, Response $response): Response
    {
        $archivosSubidos = $request->getUploadedFiles();

        if (!isset($archivosSubidos['archivo'])) {
            return $this->json($response, false, 'No se recibió el PDF.', [], 400);
        }

        $archivoSubido = $archivosSubidos['archivo'];

        if ($archivoSubido->getError() !== UPLOAD_ERR_OK) {
            return $this->json($response, false, 'No se pudo recibir el archivo.', [], 400);
        }

        $rutaTemporal = $archivoSubido->getStream()->getMetadata('uri') ?? '';
        $tipoMime = (new \finfo(FILEINFO_MIME_TYPE))->file($rutaTemporal);

        if ($tipoMime !== 'application/pdf') {
            return $this->json($response, false, 'El archivo debe ser un PDF válido.', [], 400);
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($rutaTemporal);
            $texto = $pdf->getText();

            // Acepta: "Total alumnos por ciclo: 35", "Total alumnos: 35",
            // "Total de alumnos: 35".
            $patrones = [
                '/Total\s+alumnos\s+por\s+ciclo\s*:\s*(\d+)/iu',
                '/Total\s+de\s+alumnos\s*:\s*(\d+)/iu',
                '/Total\s+alumnos\s*:\s*(\d+)/iu',
            ];

            $totalMatriculados = null;

            foreach ($patrones as $patron) {
                if (preg_match($patron, $texto, $coincidencia)) {
                    $totalMatriculados = (int) $coincidencia[1];
                    break;
                }
            }

            // Respaldo: contar números de identificación de diez dígitos
            // si no aparece un total.
            if ($totalMatriculados === null) {
                preg_match_all('/\b\d{10}\b/', $texto, $identificaciones);
                $identificacionesUnicas = array_unique($identificaciones[0]);
                $totalMatriculados = count($identificacionesUnicas);
            }

            $periodo = null;
            $cohorte = null;

            if (preg_match('/Periodo\s*:\s*(.*?)\s+Fecha\s+Inicio/iu', $texto, $coincidenciaPeriodo)) {
                $periodo = trim(preg_replace('/\s+/', ' ', $coincidenciaPeriodo[1]));
            }

            if (preg_match('/\b([AB])\s*(20\d{2})\b/iu', $texto, $coincidenciaCohorte)) {
                $cohorte = strtoupper($coincidenciaCohorte[1]) . $coincidenciaCohorte[2];
            }
        } catch (Throwable $error) {
            return $this->json($response, false, 'No se pudo leer el contenido del PDF.', ['detalle' => $error->getMessage()], 500);
        }

        return $this->json($response, true, 'El PDF fue leído correctamente.', [
            'datos' => [
                'matriculados' => $totalMatriculados,
                'periodo' => $periodo,
                'cohorte_detectada' => $cohorte,
            ],
        ]);
    }

    /**
     * POST /evidencias/guardar (JSON: id_catalogo, id_evaluacion,
     * codigo_evidencia, descripcion, nombre_archivo, tipo, url_archivo) --
     * requiere sesión, mismo criterio que el original (session_start() +
     * chequeo de $_SESSION['id_usuario'], acá delegado a
     * SessionAuthMiddleware). Misma lógica exacta que
     * api/evidencias/guardar_evidencia.php: inserta o actualiza la
     * evidencia (según la unique key evaluación+catálogo), la relaciona
     * con su indicador de origen y, si existen reglas activas en
     * compartir_catalogo, la comparte automáticamente con otros
     * indicadores. Parte 7 del Grupo B (cuarta y última del grupo).
     *
     * OJO -- desvío intencional del helper json() de arriba, mismo criterio
     * que guardadas()/compartidas() (Partes 4/5): el original NO envuelve
     * la respuesta exitosa en "datos" (trae "id_evidencia" y
     * "relaciones_compartidas" como claves de primer nivel, junto a "ok" y
     * "mensaje") -- se preserva tal cual.
     */
    #[OA\Post(
        path: '/evidencias/guardar',
        summary: 'Crea o actualiza una evidencia y la relaciona/comparte automáticamente con sus indicadores.',
        security: [['sesionPhp' => []]],
        tags: ['Evidencias (I1/I4/I5)'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: [
                    'id_catalogo', 'id_evaluacion', 'codigo_evidencia',
                    'descripcion', 'nombre_archivo', 'tipo', 'url_archivo',
                ],
                properties: [
                    new OA\Property(property: 'id_catalogo', type: 'integer'),
                    new OA\Property(property: 'id_evaluacion', type: 'integer'),
                    new OA\Property(property: 'codigo_evidencia', type: 'string', example: 'DOC.SYL.01'),
                    new OA\Property(property: 'descripcion', type: 'string'),
                    new OA\Property(property: 'nombre_archivo', type: 'string'),
                    new OA\Property(property: 'tipo', type: 'string', example: 'application/pdf'),
                    new OA\Property(property: 'url_archivo', type: 'string'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Evidencia guardada correctamente, con el id final y cuántas relaciones de compartición se crearon.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'mensaje', type: 'string'),
                    new OA\Property(property: 'id_evidencia', type: 'integer'),
                    new OA\Property(property: 'relaciones_compartidas', type: 'integer'),
                ]),
            ),
            new OA\Response(response: 400, description: 'Faltan datos obligatorios para registrar la evidencia.'),
            new OA\Response(response: 401, description: 'La sesión no está activa.'),
            new OA\Response(response: 500, description: 'No se pudo completar el registro de la evidencia.'),
        ],
    )]
    public function guardar(Request $request, Response $response): Response
    {
        $datos = $request->getParsedBody();
        $datos = is_array($datos) ? $datos : [];

        $idCatalogo = (int) ($datos['id_catalogo'] ?? 0);
        $idEvaluacion = (int) ($datos['id_evaluacion'] ?? 0);
        $codigoEvidencia = trim((string) ($datos['codigo_evidencia'] ?? ''));
        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        $nombreArchivo = trim((string) ($datos['nombre_archivo'] ?? ''));
        $tipo = trim((string) ($datos['tipo'] ?? ''));
        $urlArchivo = trim((string) ($datos['url_archivo'] ?? ''));

        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        if (
            $idCatalogo <= 0
            || $idEvaluacion <= 0
            || $codigoEvidencia === ''
            || $descripcion === ''
            || $nombreArchivo === ''
            || $tipo === ''
            || $urlArchivo === ''
        ) {
            return $this->json($response, false, 'Faltan datos obligatorios para registrar la evidencia.', [], 400);
        }

        try {
            $resultado = $this->repositorio->guardarEvidencia(
                $idCatalogo,
                $idEvaluacion,
                $codigoEvidencia,
                $descripcion,
                $nombreArchivo,
                $tipo,
                $urlArchivo,
                $idUsuario,
            );
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo completar el registro de la evidencia.', ['detalle' => $e->getMessage()], 500);
        }

        return $this->json($response, true, 'Evidencia guardada correctamente.', [
            'id_evidencia' => $resultado['id_evidencia'],
            'relaciones_compartidas' => $resultado['relaciones_compartidas'],
        ]);
    }

    /**
     * POST /evidencias/preparar-pdf (multipart: archivo, id_catalogo,
     * codigo_carrera, cohorte, criterio, indicador) — misma lógica exacta
     * que api/evidencias/preparar_pdf.php: valida el archivo subido (PDF en
     * general, CSV solo para el slot DOC.SEG.05 -- identificado por
     * codigo_evidencia de servidor, no por lo que mande el cliente, mismo
     * criterio que el original) y arma el nombre técnico
     * CARRERA.COHORTE.C{criterio}.{indicador}.{orden}.{base}.{ext} que
     * después usa subirPdfGoogleDrive() para el nombre final. No requiere
     * sesión, mismo criterio que el original (no valida
     * $_SESSION['id_usuario']). Parte 8 del plan de migración de PHP suelto
     * a Slim (quinta y última del Grupo B).
     */
    #[OA\Post(
        path: '/evidencias/preparar-pdf',
        summary: 'Valida un archivo (PDF o CSV según el slot) y genera su nombre técnico final.',
        tags: ['Evidencias (I1/I4/I5)'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['archivo', 'id_catalogo', 'codigo_carrera', 'cohorte', 'criterio', 'indicador'],
                    properties: [
                        new OA\Property(property: 'archivo', type: 'string', format: 'binary'),
                        new OA\Property(property: 'id_catalogo', type: 'integer'),
                        new OA\Property(property: 'codigo_carrera', type: 'string', example: 'DESSOF'),
                        new OA\Property(property: 'cohorte', type: 'string', example: 'A2026'),
                        new OA\Property(property: 'criterio', type: 'integer'),
                        new OA\Property(property: 'indicador', type: 'integer'),
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Archivo validado y nombre técnico generado correctamente.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'mensaje', type: 'string'),
                    new OA\Property(property: 'datos', type: 'object', properties: [
                        new OA\Property(property: 'id_catalogo', type: 'integer'),
                        new OA\Property(property: 'codigo_evidencia', type: 'string'),
                        new OA\Property(property: 'titulo_corto', type: 'string'),
                        new OA\Property(property: 'descripcion', type: 'string'),
                        new OA\Property(property: 'nombre_original', type: 'string'),
                        new OA\Property(property: 'nombre_generado', type: 'string'),
                        new OA\Property(property: 'tipo', type: 'string'),
                        new OA\Property(property: 'tamano', type: 'integer'),
                    ]),
                ]),
            ),
            new OA\Response(response: 400, description: 'Faltan datos, no se recibió archivo, error al recibirlo, supera 25 MB, o no es un PDF/CSV válido.'),
            new OA\Response(response: 404, description: 'La evidencia seleccionada no existe o está inactiva.'),
            new OA\Response(response: 500, description: 'No se pudo preparar la consulta.'),
        ],
    )]
    public function prepararPdf(Request $request, Response $response): Response
    {
        $datos = $request->getParsedBody();
        $datos = is_array($datos) ? $datos : [];

        $idCatalogo = (int) ($datos['id_catalogo'] ?? 0);

        $codigoCarrera = strtoupper((string) preg_replace(
            '/[^A-Z0-9]/',
            '',
            trim((string) ($datos['codigo_carrera'] ?? '')),
        ));

        $cohorte = strtoupper((string) preg_replace(
            '/[^A-Z0-9]/',
            '',
            trim((string) ($datos['cohorte'] ?? '')),
        ));

        $criterio = (int) ($datos['criterio'] ?? 0);
        $indicador = (int) ($datos['indicador'] ?? 0);

        if (
            $idCatalogo <= 0
            || $codigoCarrera === ''
            || $cohorte === ''
            || $criterio <= 0
            || $indicador <= 0
        ) {
            return $this->json($response, false, 'Faltan datos para procesar el archivo.', [], 400);
        }

        $archivosSubidos = $request->getUploadedFiles();

        if (!isset($archivosSubidos['archivo'])) {
            return $this->json($response, false, 'No se recibió ningún archivo.', [], 400);
        }

        $archivoSubido = $archivosSubidos['archivo'];

        if ($archivoSubido->getError() !== UPLOAD_ERR_OK) {
            return $this->json($response, false, 'Ocurrió un error al recibir el archivo.', [], 400);
        }

        $tamanoMaximo = 25 * 1024 * 1024;

        if ($archivoSubido->getSize() > $tamanoMaximo) {
            return $this->json($response, false, 'El archivo no debe superar los 25 MB.', [], 400);
        }

        try {
            $catalogo = $this->repositorio->obtenerCatalogoParaPreparar($idCatalogo);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo preparar la consulta.', ['detalle' => $e->getMessage()], 500);
        }

        if (!$catalogo) {
            return $this->json($response, false, 'La evidencia seleccionada no existe o está inactiva.', [], 404);
        }

        // Único slot que hoy espera CSV en vez de PDF. Se identifica por
        // codigo_evidencia (dato de servidor, no lo que mande el cliente)
        // para no depender de que el frontend mande el tipo correcto.
        $esCsv = $catalogo['codigo_evidencia'] === 'DOC.SEG.05';

        $nombreOriginal = $archivoSubido->getClientFilename() ?? '';
        $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

        $rutaTemporal = $archivoSubido->getStream()->getMetadata('uri') ?? '';
        $tipoMime = (new \finfo(FILEINFO_MIME_TYPE))->file($rutaTemporal);

        if ($esCsv) {
            $mimeCsvValidos = [
                'text/csv',
                'application/csv',
                'application/vnd.ms-excel',
                'text/plain',
            ];

            if ($extension !== 'csv' || !in_array($tipoMime, $mimeCsvValidos, true)) {
                return $this->json($response, false, 'Solo se aceptan archivos CSV válidos.', [], 400);
            }
        } else {
            if ($extension !== 'pdf' || $tipoMime !== 'application/pdf') {
                return $this->json($response, false, 'Solo se aceptan archivos PDF válidos.', [], 400);
            }
        }

        $nombreBase = preg_replace('/[^A-Za-z0-9_]/', '_', $catalogo['nombre_archivo_base']);
        $nombreBase = preg_replace('/_+/', '_', $nombreBase);
        $nombreBase = trim($nombreBase, '_');

        $numeroEvidencia = $catalogo['orden'];

        $nombreGenerado = sprintf(
            '%s.%s.C%d.%d.%d.%s.%s',
            $codigoCarrera,
            $cohorte,
            $criterio,
            $indicador,
            $numeroEvidencia,
            $nombreBase,
            $esCsv ? 'csv' : 'pdf',
        );

        return $this->json($response, true, ($esCsv ? 'CSV' : 'PDF') . ' validado y nombre generado correctamente.', [
            'datos' => [
                'id_catalogo' => $idCatalogo,
                'codigo_evidencia' => $catalogo['codigo_evidencia'],
                'titulo_corto' => $catalogo['titulo_corto'],
                'descripcion' => $catalogo['descripcion'],
                'nombre_original' => $nombreOriginal,
                'nombre_generado' => $nombreGenerado,
                'tipo' => $tipoMime,
                'tamano' => $archivoSubido->getSize(),
            ],
        ]);
    }
}
