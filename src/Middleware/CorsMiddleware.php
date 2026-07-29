<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as Psr7Response;

/**
 * Mismo comportamiento CORS que la parte de headers de iniciarEndpoint()
 * (api/seguimiento_syllabus/_helpers.php), ahora como middleware PSR-15
 * reusable por el router de Slim en vez de copiarse archivo por archivo
 * (hallazgo 1.2.2 del Plan de Mejora — "config CORS repetida en 28
 * archivos"). El origen permitido sigue viniendo de la misma variable de
 * entorno CORS_ALLOWED_ORIGIN ya usada por el resto del backend.
 */
final class CorsMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $origenPermitido)
    {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($request->getMethod() === 'OPTIONS') {
            $response = new Psr7Response(204);

            return $this->conHeaders($response);
        }

        $response = $handler->handle($request);

        return $this->conHeaders($response);
    }

    private function conHeaders(Response $response): Response
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', $this->origenPermitido)
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    }
}
