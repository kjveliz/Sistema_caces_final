<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\EvidenciasRepository;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * Controlador del Grupo C (misceláneos) del plan de migración de PHP
 * suelto a Slim (ver plan_migracion_slim_legacy_v3.txt §1/§3). Arranca en
 * la Parte 9 con obtenerEvidencias() (reemplaza a
 * api/catalogo/obtener_evidencias.php), reusando EvidenciasRepository (ya
 * existente del Grupo B, misma tabla catalogo_evidencias — no se crea un
 * repository nuevo para esta Parte, ver criterio del plan §2.2). La
 * Parte 10 (api/evaluaciones/obtener_evaluacion.php) suma un método acá
 * también, mismo patrón de "un Controller por grupo, no por Parte" ya
 * usado en AuthController y EvidenciasController — aunque a diferencia de
 * esos dos, este Controller termina inyectando dos repositories distintos
 * en su constructor, porque las dos Partes de este grupo (misceláneos, por
 * definición) no comparten dominio de datos.
 */
#[OA\Tag(name: 'Misceláneos')]
final class MiscelaneosController
{
    public function __construct(
        private readonly EvidenciasRepository $evidenciasRepositorio,
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
     * GET /catalogo/obtener-evidencias?id_indicador= — misma lógica exacta
     * que api/catalogo/obtener_evidencias.php: lista el catálogo de
     * evidencias activo de un indicador, ordenado por orden ASC.
     *
     * OJO — mismo desvío intencional que EvidenciasController::guardadas()
     * (Parte 4): el original NO incluye "mensaje" en la respuesta exitosa,
     * solo "ok" y "datos" — se arma a mano para no agregar "mensaje": null
     * de más. En los casos de error sí se usa json(), igual que el
     * original sí trae "mensaje" en esos casos.
     */
    #[OA\Get(
        path: '/catalogo/obtener-evidencias',
        summary: 'Lista el catálogo de evidencias activo de un indicador.',
        tags: ['Misceláneos'],
        parameters: [
            new OA\Parameter(
                name: 'id_indicador',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Catálogo de evidencias del indicador.'),
            new OA\Response(response: 400, description: 'Falta el id del indicador.'),
        ],
    )]
    public function obtenerEvidencias(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idIndicador = isset($params['id_indicador']) ? (int) $params['id_indicador'] : 0;

        if ($idIndicador <= 0) {
            return $this->json($response, false, 'Debe enviar el id del indicador.', [], 400);
        }

        try {
            $evidencias = $this->evidenciasRepositorio->obtenerCatalogoPorIndicador($idIndicador);
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
