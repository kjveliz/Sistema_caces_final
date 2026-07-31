<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\EvidenciasRepository;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * Controlador del Grupo B (Evidencias genéricas, usadas por I1/I4/I5) del
 * plan de migración de PHP suelto a Slim (ver
 * plan_migracion_slim_legacy_v3.txt §1/§3). Arranca en la Parte 4 con
 * guardadas() (reemplaza a api/evidencias/obtener_evidencias_guardadas.php);
 * las Partes 5-8 del mismo grupo suman métodos acá (mismo criterio que
 * AuthController con el Grupo A: un Controller por grupo, no por Parte).
 * Parte 5 agrega compartidas() (reemplaza a
 * api/evidencias/obtener_compartidas.php).
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
}
