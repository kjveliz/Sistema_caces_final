<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\EvidenciaMigradorService;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RuntimeException;
use Throwable;

/**
 * Controlador del interruptor de almacenamiento por carrera (Google Drive
 * / local) — paso 4 de plan_interruptor_almacenamiento.txt. Un solo
 * endpoint (PUT /carreras/{id}/almacenamiento) que dispara la migración
 * síncrona y todo-o-nada de EvidenciaMigradorService.
 *
 * Restringido a administrador/coordinador, mismo patrón de chequeo de rol
 * en el controller (vía $_SESSION['rol']) que ya usa CarrerasController
 * para actualizar()/eliminar(), pero con `in_array` en vez de una sola
 * comparación porque acá SÍ son dos roles permitidos (ver plan §3.2).
 */
#[OA\Tag(name: 'Carreras — almacenamiento')]
final class CarrerasAlmacenamientoController
{
    private const ROLES_PERMITIDOS = ['administrador', 'coordinador'];

    public function __construct(
        private readonly EvidenciaMigradorService $migrador,
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
     * PUT /carreras/{id}/almacenamiento (JSON: modo_almacenamiento, ruta_local?)
     * — requiere sesión (SessionAuthMiddleware) + rol administrador o
     * coordinador. Migra TODA la evidencia ya subida de la carrera al
     * nuevo destino antes de confirmar el cambio; ver
     * EvidenciaMigradorService para el contrato síncrono/todo-o-nada.
     */
    #[OA\Put(
        path: '/carreras/{id}/almacenamiento',
        summary: 'Cambia el modo de almacenamiento (Drive/local) de una carrera y migra su evidencia ya subida al nuevo destino.',
        security: [['sesionPhp' => []]],
        tags: ['Carreras — almacenamiento'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['modo_almacenamiento'],
                properties: [
                    new OA\Property(property: 'modo_almacenamiento', type: 'string', enum: ['drive', 'local']),
                    new OA\Property(property: 'ruta_local', type: 'string', nullable: true, description: 'Solo aplica si modo_almacenamiento es local; vacío/omitido usa la ruta local por defecto del sistema.'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Almacenamiento migrado y actualizado correctamente.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'Los datos enviados no son válidos, o no hay nada que migrar.'),
            new OA\Response(response: 401, description: 'La sesión no está activa.'),
            new OA\Response(response: 403, description: 'No tiene permisos para cambiar el almacenamiento de la carrera.'),
            new OA\Response(response: 404, description: 'La carrera no existe o se encuentra desactivada.'),
            new OA\Response(response: 422, description: 'La migración falló (se revirtió; la carrera queda como estaba) o la ruta local no es escribible.'),
        ],
    )]
    public function almacenamiento(Request $request, Response $response, array $args): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['id_usuario'])) {
            return $this->json($response, false, 'La sesión no está activa.', [], 401);
        }

        if (!in_array($_SESSION['rol'] ?? '', self::ROLES_PERMITIDOS, true)) {
            return $this->json($response, false, 'No tiene permisos para cambiar el almacenamiento de la carrera.', [], 403);
        }

        $idCarrera = isset($args['id']) ? (int) $args['id'] : 0;

        $datos = $request->getParsedBody();
        if (!is_array($datos)) {
            return $this->json($response, false, 'Los datos enviados no son válidos.', [], 400);
        }

        $modoAlmacenamiento = trim((string) ($datos['modo_almacenamiento'] ?? ''));
        $rutaLocal = isset($datos['ruta_local']) ? (string) $datos['ruta_local'] : null;

        if ($idCarrera <= 0 || !in_array($modoAlmacenamiento, ['drive', 'local'], true)) {
            return $this->json($response, false, 'id de carrera y modo_almacenamiento (drive|local) son requeridos.', [], 400);
        }

        try {
            $resultado = $this->migrador->migrar($idCarrera, $modoAlmacenamiento, $rutaLocal);
        } catch (RuntimeException $e) {
            $httpCode = str_contains($e->getMessage(), 'no existe o se encuentra desactivada') ? 404 : 422;

            return $this->json($response, false, 'No se pudo cambiar el almacenamiento de la carrera.', ['detalle' => $e->getMessage()], $httpCode);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo cambiar el almacenamiento de la carrera.', ['detalle' => $e->getMessage()], 500);
        }

        return $this->json($response, true, 'Almacenamiento actualizado y evidencia migrada correctamente.', ['datos' => $resultado]);
    }
}
