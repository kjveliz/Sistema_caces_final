<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as Psr7Response;

/**
 * Mismo comportamiento que iniciarEndpoint([...], requiereSesion: true):
 * exige $_SESSION['id_usuario'] activa, 401 con el mismo formato de
 * respuesta {ok, mensaje} si no lo está. Se aplica solo a la ruta de
 * evidencia-subir, igual que en el evidencia_subir.php original.
 */
final class SessionAuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (!isset($_SESSION['id_usuario'])) {
            $response = new Psr7Response(401);
            $response->getBody()->write(json_encode([
                'ok' => false,
                'mensaje' => 'La sesión no está activa.',
            ], JSON_UNESCAPED_UNICODE));

            return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        return $handler->handle($request);
    }
}
