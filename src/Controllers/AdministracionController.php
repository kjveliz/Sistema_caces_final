<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\SeguimientoSyllabusRepository;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
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

    /**
     * POST /administracion/cohortes/crear (JSON: id_carrera, nombre_cohorte,
     * fecha_inicio, fecha_fin, estado) — requiere sesión + rol administrador.
     * Parte 12 del plan de migración slim-legacy (ver
     * plan_migracion_slim_legacy_v3.txt §3 Grupo E, segunda Parte del
     * subgrupo de cohortes): reemplaza a
     * api/administracion/cohortes/crear.php. Mismas validaciones y mensajes
     * que el original (`nombre_cohorte` normalizado a mayúsculas sin
     * caracteres fuera de A-Z0-9, `estado` limitado al enum de 3 valores,
     * `fecha_fin` no puede ser anterior a `fecha_inicio`), mismo chequeo de
     * rol a mano ($_SESSION['rol'] !== 'administrador', 403) -- el original
     * ya lo hacía así, mismo patrón que CarrerasController::actualizar()/
     * MallaCurricularController::guardar(). Reusa
     * SeguimientoSyllabusRepository::crearCohorteConEvaluacion() en vez de
     * un repository nuevo (ver §1 del plan).
     */
    #[OA\Post(
        path: '/administracion/cohortes/crear',
        summary: 'Crea una cohorte nueva y su evaluación asociada.',
        security: [['sesionPhp' => []]],
        tags: ['Administración (Cohortes)'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_carrera', 'nombre_cohorte', 'fecha_inicio', 'fecha_fin'],
                properties: [
                    new OA\Property(property: 'id_carrera', type: 'integer'),
                    new OA\Property(property: 'nombre_cohorte', type: 'string'),
                    new OA\Property(property: 'fecha_inicio', type: 'string', format: 'date'),
                    new OA\Property(property: 'fecha_fin', type: 'string', format: 'date'),
                    new OA\Property(property: 'estado', type: 'string', enum: ['Activa', 'Pendiente', 'Cerrada']),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cohorte y evaluación creadas correctamente.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'Complete correctamente todos los campos, o la fecha final es anterior a la inicial.'),
            new OA\Response(response: 401, description: 'La sesión no está activa.'),
            new OA\Response(response: 403, description: 'Solo un administrador puede crear cohortes.'),
            new OA\Response(response: 404, description: 'La carrera seleccionada no existe.'),
            new OA\Response(response: 409, description: 'Ya existe esa cohorte para la carrera seleccionada.'),
            new OA\Response(response: 500, description: 'No se pudo crear la cohorte.'),
        ],
    )]
    public function cohortesCrear(Request $request, Response $response): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (($_SESSION['rol'] ?? '') !== 'administrador') {
            return $this->json($response, false, 'Solo un administrador puede crear cohortes.', [], 403);
        }

        $datos = $request->getParsedBody();

        if (!is_array($datos)) {
            $datos = [];
        }

        $idCarrera = (int) ($datos['id_carrera'] ?? 0);
        // Mismo orden que el original: preg_replace (sensible a mayúsculas)
        // ANTES de strtoupper -- cualquier letra minúscula del input se
        // descarta en vez de preservarse y mayuscularse (ej.
        // "cohorte-2027" queda "2027", no "COHORTE2027"). Hallazgo real
        // detectado en esta Parte, preservado a propósito (no se corrige
        // acá, ver plan §4 -- documentado además en la memoria del
        // proyecto y cubierto por testNombreCohorteConMinusculasLasDescarta()).
        $nombreCohorte = strtoupper((string) preg_replace(
            '/[^A-Z0-9]/',
            '',
            trim((string) ($datos['nombre_cohorte'] ?? '')),
        ));
        $fechaInicio = trim((string) ($datos['fecha_inicio'] ?? ''));
        $fechaFin = trim((string) ($datos['fecha_fin'] ?? ''));
        $estado = trim((string) ($datos['estado'] ?? 'Pendiente'));

        $estadosPermitidos = ['Activa', 'Pendiente', 'Cerrada'];

        if (
            $idCarrera <= 0
            || $nombreCohorte === ''
            || $fechaInicio === ''
            || $fechaFin === ''
            || !in_array($estado, $estadosPermitidos, true)
        ) {
            return $this->json($response, false, 'Complete correctamente todos los campos.', [], 400);
        }

        if ($fechaFin < $fechaInicio) {
            return $this->json($response, false, 'La fecha final no puede ser anterior a la fecha inicial.', [], 400);
        }

        $idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);

        try {
            $resultado = $this->repositorio->crearCohorteConEvaluacion(
                $idCarrera,
                $nombreCohorte,
                $fechaInicio,
                $fechaFin,
                $estado,
                $idUsuario,
            );
        } catch (RuntimeException $e) {
            return $this->json($response, false, $e->getMessage(), [], 404);
        } catch (Throwable $e) {
            $codigo = $e->getCode() === 1062 ? 409 : 500;

            return $this->json(
                $response,
                false,
                $codigo === 409
                    ? 'Ya existe esa cohorte para la carrera seleccionada.'
                    : 'No se pudo crear la cohorte.',
                ['detalle' => $e->getMessage()],
                $codigo,
            );
        }

        return $this->json($response, true, 'Cohorte y evaluación creadas correctamente.', ['datos' => $resultado]);
    }
}
