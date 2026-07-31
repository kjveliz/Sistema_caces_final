<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SeguimientoSyllabusRepository;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * Controlador del Grupo E (Administración) del plan de migración de PHP
 * suelto a Slim -- ver plan_migracion_slim_legacy_v3.txt §3. Reemplaza a
 * los 6 archivos de api/administracion/{cohortes,usuarios}/*.php, en la
 * misma Fase 3b, un Controller nuevo por grupo (no por Parte) tal como
 * pide el patrón (§2 punto 3 del plan).
 *
 * El subgrupo de cohortes (Partes 11-13) extiende SeguimientoSyllabusRepository
 * en vez de crear un repository nuevo: pega directo contra la tabla
 * `cohortes`, mismo dominio que ya cubren
 * SeguimientoSyllabusRepository::crearCohorte()/cohorteExiste() (ver §1 del
 * plan). El subgrupo de usuarios (Partes 14-16) va a necesitar un
 * UsuariosRepository nuevo (no hay nada existente que cubra esa tabla) --
 * se agrega como dependencia de este mismo Controller cuando arranque la
 * Parte 14, no antes.
 */
#[OA\Tag(name: 'Administración (Cohortes)')]
final class AdministracionController
{
    public function __construct(
        private readonly SeguimientoSyllabusRepository $repositorio,
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
     * GET /administracion/cohortes/listar — requiere sesión activa. Parte 11
     * del plan de migración slim-legacy (ver plan_migracion_slim_legacy_v3.txt
     * §3 Grupo E, primera Parte): reemplaza a
     * api/administracion/cohortes/listar.php. Mismo chequeo de sesión que
     * el original ($_SESSION['id_usuario']), ahora vía SessionAuthMiddleware
     * en la ruta (public/index.php) en vez de a mano dentro del archivo.
     */
    #[OA\Get(
        path: '/administracion/cohortes/listar',
        summary: 'Lista las cohortes con su evaluación asociada y cantidad de períodos cargados.',
        security: [['sesionPhp' => []]],
        tags: ['Administración (Cohortes)'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cohortes con evaluación asociada (si tiene) y total de períodos académicos.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'array', items: new OA\Items(type: 'object')),
                ]),
            ),
            new OA\Response(response: 401, description: 'La sesión no está activa.'),
            new OA\Response(response: 500, description: 'No se pudieron consultar las cohortes.'),
        ],
    )]
    public function cohortesListar(Request $request, Response $response): Response
    {
        try {
            $datos = $this->repositorio->cohortesConEvaluacion();
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudieron consultar las cohortes.', ['detalle' => $e->getMessage()], 500);
        }

        return $this->json($response, true, null, ['datos' => $datos]);
    }
}
