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
 * suelto a Slim (ver plan_migracion_slim_legacy_v3.txt §1/§3). Completo:
 * login() (Parte 1, reemplaza a Login.php), logout() (Parte 2, reemplaza a
 * Logout.php) y me() (Parte 3, reemplaza a Me.php) -- las 3 Partes del
 * grupo.
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

    /**
     * POST /auth/logout — misma lógica exacta que api/auth/Logout.php
     * (Parte 2 del Grupo A): destruye la sesión de PHP, incluida la cookie
     * del navegador, y siempre devuelve 200/ok aunque no haya sesión
     * activa (llamar a logout sin sesión no es un error). Sin
     * SessionAuthMiddleware a propósito: el original nunca valida
     * $_SESSION['id_usuario'] antes de destruir, mismo criterio del plan
     * de no tocar lógica de negocio al migrar arquitectura.
     */
    #[OA\Post(
        path: '/auth/logout',
        summary: 'Cierra la sesión activa (si la hay).',
        tags: ['Autenticación'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sesión cerrada correctamente.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'mensaje', type: 'string'),
                ]),
            ),
        ],
    )]
    public function logout(Request $request, Response $response): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [];

        // Borra también la cookie del navegador, no solo los datos del lado
        // del servidor: sin esto, session_destroy() invalida la sesión en
        // el servidor pero el navegador seguiría mandando el mismo
        // PHPSESSID (ya inválido) en cada pedido siguiente. Misma lógica
        // exacta que el Logout.php original.
        if (ini_get('session.use_cookies')) {
            $parametros = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $parametros['path'],
                $parametros['domain'],
                $parametros['secure'],
                $parametros['httponly'],
            );
        }

        session_destroy();

        return $this->json($response, true, 'Sesión cerrada correctamente.');
    }

    /**
     * GET /auth/me — misma lógica exacta que api/auth/Me.php (Parte 3 del
     * Grupo A, última del grupo): devuelve el usuario de la sesión activa.
     * El chequeo de "hay sesión" (401 si no) lo hace SessionAuthMiddleware
     * en la ruta -- mismo mensaje exacto que el original. Acá solo queda
     * el segundo chequeo, propio de este endpoint: el usuario pudo haber
     * sido eliminado o desactivado después de loguearse (ej. un
     * administrador lo desactiva mientras la cookie del navegador sigue
     * viva) -- en ese caso también se invalida la sesión y se devuelve
     * 401, igual que el original.
     */
    #[OA\Get(
        path: '/auth/me',
        summary: 'Devuelve el usuario de la sesión activa.',
        tags: ['Autenticación'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sesión activa.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'usuario', properties: [
                        new OA\Property(property: 'id_usuario', type: 'integer'),
                        new OA\Property(property: 'nombres', type: 'string'),
                        new OA\Property(property: 'apellidos', type: 'string'),
                        new OA\Property(property: 'correo', type: 'string'),
                        new OA\Property(property: 'rol', type: 'string', enum: ['administrador', 'coordinador', 'evaluador']),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 401, description: 'La sesión no está activa.'),
        ],
    )]
    public function me(Request $request, Response $response): Response
    {
        $idSesion = (int) ($_SESSION['id_usuario'] ?? 0);

        try {
            $usuario = $this->repositorio->usuarioPorId($idSesion);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo preparar la consulta.', ['detalle' => $e->getMessage()], 500);
        }

        if (!$usuario || $usuario['activo'] !== 1) {
            $_SESSION = [];
            session_destroy();

            return $this->json($response, false, 'La sesión no está activa.', [], 401);
        }

        // A diferencia de login()/logout(), el Me.php original NO incluye
        // la clave "mensaje" en la respuesta exitosa -- se arma a mano acá
        // en vez de reusar json() para no agregar "mensaje": null de más
        // (mismo criterio de "misma lógica exacta" del resto de esta
        // migración).
        $response->getBody()->write(json_encode([
            'ok' => true,
            'usuario' => [
                'id_usuario' => $usuario['id_usuario'],
                'nombres' => $usuario['nombres'],
                'apellidos' => $usuario['apellidos'],
                'correo' => $usuario['correo'],
                'rol' => $usuario['rol'],
            ],
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus(200);
    }
}
