<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\MallaCurricularRepository;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * Controlador de I1 (Malla Curricular). Reemplaza a los 2 archivos sueltos
 * api/carreras/{obtener_malla,guardar_malla}.php — misma lógica de
 * negocio, mismas validaciones de parámetros, misma forma de respuesta
 * JSON ({ok, mensaje, datos}), ahora detrás del router de Slim en vez de
 * ser cada uno un archivo PHP accesible directo por URL (hallazgo 1.2.1
 * del Plan de Mejora). Mismo patrón que TitulacionController (I5, v66):
 * el indicador más parecido, sin cálculo propio ni parseo de PDF (la
 * lectura del PDF de la malla, si la hay, la hace el frontend antes de
 * llamar a /malla-curricular/guardar).
 *
 * Los otros 4 endpoints de api/carreras/ (listar/crear/actualizar/
 * eliminar) son administración genérica de la entidad Carrera, no del
 * indicador I1 en sí, y quedan fuera del alcance de esta migración -- ver
 * MallaCurricularRepository.
 *
 * A diferencia de todos los indicadores ya migrados, guardar() exige rol
 * "administrador" además de sesión activa (403 si no lo es), tal como
 * hacía guardar_malla.php original. SessionAuthMiddleware solo cubre el
 * 401 (sesión no activa); el chequeo de rol se hace acá porque ningún
 * otro indicador lo necesita y agregarlo al middleware compartido
 * significaría una condición que solo aplicaría a esta única ruta.
 *
 * Las anotaciones OpenAPI de cada método (Fase 3, hallazgo 1.2.7) se
 * generan a openapi.json con `composer generate-openapi` -- ver
 * src/OpenApi/Definition.php para la info general del documento. Igual
 * que con I2/I4, la regeneración de openapi.json en sí queda pendiente de
 * esta sesión (requiere Composer con acceso a packagist.org, no
 * disponible en el sandbox de Claude).
 */
#[OA\Tag(name: 'I1 - Malla Curricular')]
final class MallaCurricularController
{
    public function __construct(
        private readonly MallaCurricularRepository $repositorio,
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

    /** GET /malla-curricular/obtener?codigo_carrera= */
    #[OA\Get(
        path: '/malla-curricular/obtener',
        summary: 'Consulta la malla curricular activa registrada para una carrera, por código.',
        tags: ['I1 - Malla Curricular'],
        parameters: [
            new OA\QueryParameter(
                name: 'codigo_carrera',
                description: 'Código de la carrera (se compara en mayúsculas).',
                required: true,
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'La malla curricular más reciente de la carrera, o null si no tiene ninguna registrada.',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'object', nullable: true),
                ]),
            ),
            new OA\Response(response: 400, description: 'Debe enviar el código de la carrera.'),
            new OA\Response(response: 500, description: 'No se pudo preparar la consulta de la malla curricular.'),
        ],
    )]
    public function obtener(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $codigoCarrera = strtoupper(trim((string) ($params['codigo_carrera'] ?? '')));

        if ($codigoCarrera === '') {
            return $this->json($response, false, 'Debe enviar el código de la carrera.', [], 400);
        }

        try {
            $malla = $this->repositorio->obtenerPorCodigoCarrera($codigoCarrera);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo preparar la consulta de la malla curricular.', ['detalle' => $e->getMessage()], 500);
        }

        return $this->json($response, true, null, ['datos' => $malla?->toArray()]);
    }

    /** POST /malla-curricular/guardar (JSON: id_carrera, nombre_archivo, id_drive, url_drive) — requiere sesión + rol administrador. */
    #[OA\Post(
        path: '/malla-curricular/guardar',
        summary: 'Registra (crea o actualiza) la malla curricular de una carrera, ya subida previamente a Google Drive.',
        security: [['sesionPhp' => []]],
        tags: ['I1 - Malla Curricular'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['id_carrera', 'nombre_archivo', 'id_drive', 'url_drive'],
                properties: [
                    new OA\Property(property: 'id_carrera', type: 'integer'),
                    new OA\Property(property: 'nombre_archivo', type: 'string'),
                    new OA\Property(property: 'id_drive', type: 'string'),
                    new OA\Property(property: 'url_drive', type: 'string', format: 'uri'),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Malla curricular registrada (ver MallaCurricularGuardadaDTO).',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'No se recibieron datos JSON válidos, faltan datos, o la URL de Drive no es válida.'),
            new OA\Response(response: 401, description: 'La sesión no está activa.'),
            new OA\Response(response: 403, description: 'No tiene permisos para registrar mallas curriculares.'),
            new OA\Response(response: 404, description: 'La carrera no existe o está desactivada.'),
            new OA\Response(response: 500, description: 'No se pudo verificar la carrera o registrar la malla.'),
        ],
    )]
    public function guardar(Request $request, Response $response): Response
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (($_SESSION['rol'] ?? '') !== 'administrador') {
            return $this->json($response, false, 'No tiene permisos para registrar mallas curriculares.', [], 403);
        }

        $datos = $request->getParsedBody();

        if (!is_array($datos)) {
            return $this->json($response, false, 'No se recibieron datos JSON válidos.', [], 400);
        }

        $idCarrera = isset($datos['id_carrera']) ? (int) $datos['id_carrera'] : 0;
        $nombreArchivo = trim((string) ($datos['nombre_archivo'] ?? ''));
        $idDrive = trim((string) ($datos['id_drive'] ?? ''));
        $urlDrive = trim((string) ($datos['url_drive'] ?? ''));

        if ($idCarrera <= 0 || $nombreArchivo === '' || $idDrive === '' || $urlDrive === '') {
            return $this->json($response, false, 'Faltan datos para registrar la malla curricular.', [], 400);
        }

        // Antes exigía que $urlDrive empezara con "https://drive.google.com/".
        // Desde que las carreras nuevas arrancan en modo 'local' por default
        // (migración 20260728120000), api/google_drive/subir_archivo.php
        // devuelve una ruta de filesystem para esos casos -- exigir el
        // prefijo de Drive acá bloqueaba el registro de la malla para
        // TODA carrera nueva. Basta con que no venga vacía (ya validado
        // arriba); el nombre de los campos (id_drive/url_drive) queda
        // igual por ahora para no tocar el esquema de Mallas_Curriculares
        // en esta sesión.

        try {
            $existeCarrera = $this->repositorio->carreraExisteActiva($idCarrera);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo verificar la carrera.', ['detalle' => $e->getMessage()], 500);
        }

        if (!$existeCarrera) {
            return $this->json($response, false, 'La carrera no existe o está desactivada.', [], 404);
        }

        try {
            $resultado = $this->repositorio->guardar($idCarrera, $nombreArchivo, $idDrive, $urlDrive);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo registrar la malla curricular.', ['detalle' => $e->getMessage()], 500);
        }

        return $this->json($response, true, 'Malla curricular registrada correctamente.', ['datos' => $resultado->toArray()]);
    }
}
