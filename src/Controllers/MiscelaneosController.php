<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\EvaluacionesRepository;
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
 * usado en AuthController y EvidenciasController — con este Controller
 * quedan inyectados dos repositories distintos en el constructor, porque
 * las dos Partes de este grupo (misceláneos, por definición) no comparten
 * dominio de datos: EvaluacionesRepository es un repository nuevo (no hay
 * ninguno existente con este SELECT — CarrerasRepository y
 * SeguimientoSyllabusRepository solo tocan `evaluaciones` para DELETE en
 * cascada), a diferencia de la Parte 9 que reusó EvidenciasRepository.
 */
#[OA\Tag(name: 'Misceláneos')]
final class MiscelaneosController
{
    public function __construct(
        private readonly EvidenciasRepository $evidenciasRepositorio,
        private readonly EvaluacionesRepository $evaluacionesRepositorio,
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

    /**
     * GET /evaluaciones/obtener-evaluacion?codigo_carrera=&cohorte= —
     * misma lógica exacta que api/evaluaciones/obtener_evaluacion.php:
     * busca la evaluación más reciente (id_evaluacion DESC) para una
     * carrera y cohorte dadas, normalizando codigo_carrera y cohorte a
     * mayúsculas y sin espacios igual que el original (el original hace
     * esa normalización con strtoupper/preg_replace inline; acá se hace
     * en el Controller antes de llamar al Repository, no en el Repository,
     * para que el Repository quede como el resto -- solo SQL).
     */
    #[OA\Get(
        path: '/evaluaciones/obtener-evaluacion',
        summary: 'Obtiene la evaluación más reciente de una carrera y cohorte.',
        tags: ['Misceláneos'],
        parameters: [
            new OA\Parameter(
                name: 'codigo_carrera',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
            new OA\Parameter(
                name: 'cohorte',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Evaluación encontrada.'),
            new OA\Response(response: 400, description: 'Falta el código de carrera o la cohorte.'),
            new OA\Response(response: 404, description: 'No existe evaluación para la carrera y cohorte.'),
        ],
    )]
    public function obtenerEvaluacion(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $codigoCarrera = strtoupper(trim((string) ($params['codigo_carrera'] ?? '')));
        $cohorte = strtoupper(preg_replace('/\s+/', '', trim((string) ($params['cohorte'] ?? ''))) ?? '');

        if ($codigoCarrera === '' || $cohorte === '') {
            return $this->json($response, false, 'Debe enviar el código de la carrera y la cohorte.', [], 400);
        }

        try {
            $evaluacion = $this->evaluacionesRepositorio->obtenerPorCarreraYCohorte($codigoCarrera, $cohorte);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo preparar la consulta.', ['detalle' => $e->getMessage()], 500);
        }

        if (!$evaluacion) {
            return $this->json($response, false, 'No existe una evaluación para la carrera y cohorte seleccionadas.', [], 404);
        }

        $response->getBody()->write(json_encode([
            'ok' => true,
            'datos' => $evaluacion,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }
}
