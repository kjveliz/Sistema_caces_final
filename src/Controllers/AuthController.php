<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuthRepository;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * Controlador del Grupo A (Autenticación) del plan de migración de PHP
 * suelto a Slim (ver plan_migracion_slim_legacy_v3.txt §1/§3). Arranca en
 * la Parte 1 reemplazando solo a api/auth/Login.php; logout() y me()
 * llegan en las Partes 2 y 3 del mismo grupo (mismo criterio ya usado con
 * AuthController -> Controller nuevo por grupo, no por Parte).
 *
 * OJO — desvío intencional del formato {ok, mensaje, datos} que usan el
 * resto de los controllers ya migrados: login() sigue devolviendo la clave
 * "usuario" (no "datos"), igual que el Login.php original, porque
 * frontend/src/shared/services/auth.ts y tests/Integration/Auth/*.php ya
 * leen esa clave específica. Cambiarla a "datos" sería tocar un contrato
 * que ningún llamador pidió cambiar (fuera de alcance del plan, ver §4:
 * "NO se toca la lógica de negocio de ningún endpoint más allá de lo
 * estrictamente necesario para moverlo de arquitectura").
 */
#[OA\Tag(name: 'Autenticación')]
final class AuthController
{
    public function __construct(
        private readonly AuthRepository $repositorio,
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
     * POST /auth/login (JSON: correo, contrasena) — misma lógica exacta
     * que api/auth/Login.php: valida credenciales, rechaza cuentas
     * desactivadas, y si todo está bien arranca la sesión de PHP
     * ($_SESSION['id_usuario']/['correo']/['rol']) con
     * session_regenerate_id(true), igual que el original.
     */
    #[OA\Post(
        path: '/auth/login',
        summary: 'Inicia sesión con correo y contraseña.',
        tags: ['Autenticación'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['correo', 'contrasena'],
                properties: [
                    new OA\Property(property: 'correo', type: 'string', format: 'email'),
                    new OA\Property(property: 'contrasena', type: 'string', format: 'password'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Inicio de sesión correcto.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'mensaje', type: 'string'),
                    new OA\Property(property: 'usuario', properties: [
                        new OA\Property(property: 'id_usuario', type: 'integer'),
                        new OA\Property(property: 'nombres', type: 'string'),
                        new OA\Property(property: 'apellidos', type: 'string'),
                        new OA\Property(property: 'correo', type: 'string'),
                        new OA\Property(property: 'rol', type: 'string', enum: ['administrador', 'coordinador', 'evaluador']),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'Ingrese el correo y la contraseña.'),
            new OA\Response(response: 401, description: 'Usuario o contraseña incorrectos.'),
            new OA\Response(response: 403, description: 'La cuenta se encuentra desactivada.'),
            new OA\Response(response: 500, description: 'No se pudo preparar la consulta.'),
        ],
    )]
    public function login(Request $request, Response $response): Response
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $correo = trim((string) ($body['correo'] ?? ''));
        $contrasena = (string) ($body['contrasena'] ?? '');

        if ($correo === '' || $contrasena === '') {
            return $this->json($response, false, 'Ingrese el correo y la contraseña.', [], 400);
        }

        try {
            $usuario = $this->repositorio->usuarioPorCorreo($correo);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo preparar la consulta.', ['detalle' => $e->getMessage()], 500);
        }

        if (!$usuario || !password_verify($contrasena, $usuario['contrasena'])) {
            return $this->json($response, false, 'Usuario o contraseña incorrectos.', [], 401);
        }

        if ($usuario['activo'] !== 1) {
            return $this->json($response, false, 'La cuenta se encuentra desactivada.', [], 403);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        session_regenerate_id(true);

        $_SESSION['id_usuario'] = $usuario['id_usuario'];
        $_SESSION['correo'] = $usuario['correo'];
        $_SESSION['rol'] = $usuario['rol'];

        return $this->json($response, true, 'Inicio de sesión correcto.', [
            'usuario' => [
                'id_usuario' => $usuario['id_usuario'],
                'nombres' => $usuario['nombres'],
                'apellidos' => $usuario['apellidos'],
                'correo' => $usuario['correo'],
                'rol' => $usuario['rol'],
            ],
        ]);
    }
}
