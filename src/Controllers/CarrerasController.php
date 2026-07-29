<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\CarrerasRepository;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * Controlador de administración genérica de Carreras (listar/crear/
 * actualizar/eliminar). Reemplaza a los 4 archivos sueltos
 * api/carreras/{listar,crear,actualizar,eliminar}.php — misma lógica de
 * negocio, mismas validaciones y mensajes, misma forma de respuesta JSON
 * ({ok, mensaje, datos}), ahora detrás del router de Slim en vez de ser
 * cada uno un archivo PHP accesible directo por URL con su propio bloque
 * de headers CORS copiado a mano (hallazgo 1.2.1 y 1.2.2 del Plan de
 * Mejora — pendiente de CORS compartido abierto desde v69). Distinto de
 * MallaCurricularController (I1), que cubre la malla curricular de una
 * carrera, no la entidad Carrera en sí.
 *
 * Mismas asimetrías del código original, preservadas a propósito (no se
 * corrigen acá, fuera de alcance de esta migración): crear() no exige
 * sesión ni rol; actualizar() y eliminar() sí exigen sesión (401, vía
 * SessionAuthMiddleware) y rol administrador (403, chequeado acá dentro,
 * mismo patrón que MallaCurricularController::guardar()).
 */
#[OA\Tag(name: 'Carreras (administración)')]
final class CarrerasController
{
    public function __construct(
        private readonly CarrerasRepository $repositorio,
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

    /** GET /carreras/listar */
    #[OA\Get(
        path: '/carreras/listar',
        summary: 'Lista las carreras activas, con su malla curricular asociada si tiene.',
        tags: ['Carreras (administración)'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Listado de carreras activas.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'array', items: new OA\Items(type: 'object')),
                ]),
            ),
            new OA\Response(response: 500, description: 'No se pudieron consultar las carreras.'),
        ],
    )]
    public function listar(Request $request, Response $response): Response
    {
        try {
            $datos = $this->repositorio->listarActivas();
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudieron consultar las carreras.', ['detalle' => $e->getMessage()], 500);
        }

        return $this->json($response, true, null, ['datos' => array_map(fn ($carrera) => $carrera->toArray(), $datos)]);
    }

    /** POST /carreras/crear (JSON: codigo, nombre, area_conocimiento, modalidad) */
    #[OA\Post(
        path: '/carreras/crear',
        summary: 'Registra una carrera nueva.',
        tags: ['Carreras (administración)'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['codigo', 'nombre', 'area_conocimiento', 'modalidad'],
                properties: [
                    new OA\Property(property: 'codigo', type: 'string'),
                    new OA\Property(property: 'nombre', type: 'string'),
                    new OA\Property(property: 'area_conocimiento', type: 'string'),
                    new OA\Property(property: 'modalidad', type: 'string', enum: ['Presencial', 'En línea', 'Híbrida']),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Carrera registrada correctamente.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'Los datos enviados no son válidos.'),
            new OA\Response(response: 409, description: 'Ya existe una carrera con ese código o nombre.'),
            new OA\Response(response: 422, description: 'Faltan campos obligatorios, modalidad inválida, o código demasiado largo.'),
            new OA\Response(response: 500, description: 'No se pudo registrar la carrera.'),
        ],
    )]
    public function crear(Request $request, Response $response): Response
    {
        $datos = $request->getParsedBody();

        if (!is_array($datos)) {
            return $this->json($response, false, 'Los datos enviados no son válidos.', [], 400);
        }

        $codigo = strtoupper(trim((string) ($datos['codigo'] ?? '')));
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $areaConocimiento = trim((string) ($datos['area_conocimiento'] ?? ''));
        $modalidad = trim((string) ($datos['modalidad'] ?? ''));

        $modalidadesPermitidas = ['Presencial', 'En línea', 'Híbrida'];

        if ($codigo === '' || $nombre === '' || $areaConocimiento === '' || $modalidad === '') {
            return $this->json($response, false, 'Todos los campos de la carrera son obligatorios.', [], 422);
        }

        if (!in_array($modalidad, $modalidadesPermitidas, true)) {
            return $this->json($response, false, 'La modalidad seleccionada no es válida.', [], 422);
        }

        if (strlen($codigo) > 15) {
            return $this->json($response, false, 'El código institucional no puede superar los 15 caracteres.', [], 422);
        }

        try {
            $codigoExiste = $this->repositorio->codigoExiste($codigo);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo preparar la validación del código.', ['detalle' => $e->getMessage()], 500);
        }

        if ($codigoExiste) {
            return $this->json($response, false, 'Ya existe una carrera registrada con ese código institucional.', [], 409);
        }

        try {
            $nombreExiste = $this->repositorio->nombreExiste($nombre);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo preparar la validación del nombre.', ['detalle' => $e->getMessage()], 500);
        }

        if ($nombreExiste) {
            return $this->json($response, false, 'Ya existe una carrera registrada con ese nombre.', [], 409);
        }

        try {
            $resultado = $this->repositorio->insertar($codigo, $nombre, $areaConocimiento, $modalidad);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo registrar la carrera.', ['detalle' => $e->getMessage()], 500);
        }

        return $this->json($response, true, 'Carrera registrada correctamente.', ['datos' => $resultado->toArray()]);
    }

    /** POST /carreras/actualizar (JSON: id_carrera, codigo, nombre, area_conocimiento, modalidad) — requiere sesión + rol administrador. */
    #[OA\Post(
        path: '/carreras/actualizar',
        summary: 'Actualiza los datos de una carrera existente.',
        security: [['sesionPhp' => []]],
        tags: ['Carreras (administración)'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_carrera', 'codigo', 'nombre', 'area_conocimiento', 'modalidad'],
                properties: [
                    new OA\Property(property: 'id_carrera', type: 'integer'),
                    new OA\Property(property: 'codigo', type: 'string'),
                    new OA\Property(property: 'nombre', type: 'string'),
                    new OA\Property(property: 'area_conocimiento', type: 'string'),
                    new OA\Property(property: 'modalidad', type: 'string'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Carrera actualizada correctamente.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'No se recibieron datos JSON válidos, o faltan campos obligatorios.'),
            new OA\Response(response: 401, description: 'La sesión no está activa.'),
            new OA\Response(response: 403, description: 'No tiene permisos para editar carreras.'),
            new OA\Response(response: 404, description: 'La carrera no existe o se encuentra desactivada.'),
            new OA\Response(response: 409, description: 'Ya existe otra carrera con ese código.'),
            new OA\Response(response: 500, description: 'No se pudo actualizar la carrera.'),
        ],
    )]
    public function actualizar(Request $request, Response $response): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (($_SESSION['rol'] ?? '') !== 'administrador') {
            return $this->json($response, false, 'No tiene permisos para editar carreras.', [], 403);
        }

        $datos = $request->getParsedBody();

        if (!is_array($datos)) {
            return $this->json($response, false, 'No se recibieron datos JSON válidos.', [], 400);
        }

        $idCarrera = isset($datos['id_carrera']) ? (int) $datos['id_carrera'] : 0;

        $codigo = strtoupper(trim((string) ($datos['codigo'] ?? '')));
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $areaConocimiento = trim((string) ($datos['area_conocimiento'] ?? ''));
        $modalidad = trim((string) ($datos['modalidad'] ?? ''));

        $codigo = preg_replace('/[^A-Z0-9]/', '', $codigo);

        if ($idCarrera <= 0 || $codigo === '' || $nombre === '' || $areaConocimiento === '' || $modalidad === '') {
            return $this->json($response, false, 'Complete todos los campos obligatorios.', [], 400);
        }

        if (strlen($codigo) > 15) {
            return $this->json($response, false, 'El código no puede superar los 15 caracteres.', [], 400);
        }

        try {
            $existe = $this->repositorio->existeActiva($idCarrera);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo verificar la carrera.', ['detalle' => $e->getMessage()], 500);
        }

        if (!$existe) {
            return $this->json($response, false, 'La carrera no existe o se encuentra desactivada.', [], 404);
        }

        try {
            $codigoDuplicado = $this->repositorio->codigoDuplicadoParaOtraCarrera($codigo, $idCarrera);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo validar el código de la carrera.', ['detalle' => $e->getMessage()], 500);
        }

        if ($codigoDuplicado) {
            return $this->json($response, false, 'Ya existe otra carrera con ese código.', [], 409);
        }

        try {
            $resultado = $this->repositorio->actualizar($idCarrera, $codigo, $nombre, $areaConocimiento, $modalidad);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo actualizar la carrera.', ['detalle' => $e->getMessage()], 500);
        }

        return $this->json($response, true, 'Carrera actualizada correctamente.', ['datos' => $resultado->toArray()]);
    }

    /** POST /carreras/eliminar (JSON: id_carrera) — requiere sesión + rol administrador. */
    #[OA\Post(
        path: '/carreras/eliminar',
        summary: 'Elimina permanentemente una carrera, si no tiene evaluaciones académicas relacionadas.',
        security: [['sesionPhp' => []]],
        tags: ['Carreras (administración)'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_carrera'],
                properties: [new OA\Property(property: 'id_carrera', type: 'integer')],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Carrera eliminada permanentemente.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'El identificador de la carrera no es válido.'),
            new OA\Response(response: 401, description: 'La sesión no está activa.'),
            new OA\Response(response: 403, description: 'No tiene permisos para eliminar carreras.'),
            new OA\Response(response: 404, description: 'La carrera no existe.'),
            new OA\Response(response: 409, description: 'La carrera tiene evaluaciones académicas relacionadas y no puede eliminarse automáticamente.'),
            new OA\Response(response: 500, description: 'No se pudo eliminar permanentemente la carrera.'),
        ],
    )]
    public function eliminar(Request $request, Response $response): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (($_SESSION['rol'] ?? '') !== 'administrador') {
            return $this->json($response, false, 'No tiene permisos para eliminar carreras.', [], 403);
        }

        $datos = $request->getParsedBody();

        $idCarrera = isset($datos['id_carrera']) ? (int) $datos['id_carrera'] : 0;

        if ($idCarrera <= 0) {
            return $this->json($response, false, 'El identificador de la carrera no es válido.', [], 400);
        }

        try {
            $carrera = $this->repositorio->buscarPorId($idCarrera);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo preparar la consulta de la carrera.', ['detalle' => $e->getMessage()], 500);
        }

        if (!$carrera) {
            return $this->json($response, false, 'La carrera no existe.', [], 404);
        }

        try {
            $totalEvaluaciones = $this->repositorio->contarEvaluaciones($idCarrera);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo verificar si la carrera tiene evaluaciones.', ['detalle' => $e->getMessage()], 500);
        }

        if ($totalEvaluaciones > 0) {
            return $this->json(
                $response,
                false,
                'La carrera tiene evaluaciones académicas relacionadas y no puede eliminarse automáticamente.',
                ['detalle' => 'Primero deben eliminarse o trasladarse sus evaluaciones y evidencias para evitar pérdida accidental de información.'],
                409,
            );
        }

        try {
            $this->repositorio->eliminarTransaccional($idCarrera);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo eliminar permanentemente la carrera.', ['detalle' => $e->getMessage()], 500);
        }

        return $this->json($response, true, 'Carrera eliminada permanentemente.', [
            'datos' => [
                'id_carrera' => $idCarrera,
                'nombre' => $carrera['nombre'],
            ],
        ]);
    }

    /**
     * POST /carreras/eliminar-forzada (JSON: id_carrera) — requiere sesión +
     * rol administrador. Herramienta de desarrollo/pruebas: a diferencia de
     * eliminar(), NO se bloquea por contarEvaluaciones() -- borra en
     * cascada evaluaciones, cohortes, períodos, asignaturas y evidencia
     * relacionada (ver CarrerasRepository::eliminarForzadaEnCascada()).
     * Solo borra filas de la BD; los archivos ya subidos a Drive/local
     * quedan huérfanos a propósito. Misma restricción de acceso que
     * eliminar() (rol administrador), sin ninguna restricción adicional
     * sobre qué carreras admite.
     */
    #[OA\Post(
        path: '/carreras/eliminar-forzada',
        summary: 'Elimina forzadamente una carrera y toda su cadena relacionada (evaluaciones, cohortes, períodos, asignaturas, evidencia). Herramienta de desarrollo/pruebas.',
        security: [['sesionPhp' => []]],
        tags: ['Carreras (administración)'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_carrera'],
                properties: [new OA\Property(property: 'id_carrera', type: 'integer')],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Carrera y toda su cadena relacionada eliminadas permanentemente.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'El identificador de la carrera no es válido.'),
            new OA\Response(response: 401, description: 'La sesión no está activa.'),
            new OA\Response(response: 403, description: 'No tiene permisos para eliminar carreras.'),
            new OA\Response(response: 404, description: 'La carrera no existe.'),
            new OA\Response(response: 500, description: 'No se pudo eliminar forzadamente la carrera.'),
        ],
    )]
    public function eliminarForzada(Request $request, Response $response): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (($_SESSION['rol'] ?? '') !== 'administrador') {
            return $this->json($response, false, 'No tiene permisos para eliminar carreras.', [], 403);
        }

        $datos = $request->getParsedBody();

        $idCarrera = isset($datos['id_carrera']) ? (int) $datos['id_carrera'] : 0;

        if ($idCarrera <= 0) {
            return $this->json($response, false, 'El identificador de la carrera no es válido.', [], 400);
        }

        try {
            $carrera = $this->repositorio->buscarPorId($idCarrera);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo preparar la consulta de la carrera.', ['detalle' => $e->getMessage()], 500);
        }

        if (!$carrera) {
            return $this->json($response, false, 'La carrera no existe.', [], 404);
        }

        try {
            $resultado = $this->repositorio->eliminarForzadaEnCascada($idCarrera);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo eliminar forzadamente la carrera.', ['detalle' => $e->getMessage()], 500);
        }

        return $this->json($response, true, 'Carrera y toda su cadena relacionada eliminadas permanentemente.', [
            'datos' => array_merge(
                [
                    'id_carrera' => $idCarrera,
                    'nombre' => $carrera['nombre'],
                ],
                $resultado,
            ),
        ]);
    }
}
