<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\EvidenciaAsignaturaRepository;
use App\Services\EvidenciaStorageResolver;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * Visor de evidencia de `evidencia_asignatura` (tabla compartida I2/I3),
 * pendiente explícito del paso 5 del plan de interruptor de
 * almacenamiento: `ver_archivo.php` (paso 5) solo ramificó el visor legacy
 * de I4/I5 (tabla `evidencias`) -- I2/I3 no tenían NINGÚN endpoint de
 * visor en el backend, el frontend abría `url_archivo` directo con
 * `window.open()` porque hasta ahora siempre era un link público de
 * Drive. Si una carrera con evidencia I2/I3 se migra a 'local',
 * `url_archivo` pasa a ser una ruta de filesystem que el navegador no
 * puede abrir directamente -- ver MEMORIA §68.1/§68.2.
 *
 * Mismo criterio que `ver_archivo.php` para decidir el origen real
 * (EvidenciaStorageResolver::resolverParaDescarga(), por la FORMA de
 * `url_archivo`, no por el `modo_almacenamiento` actual de la carrera:
 * una evidencia ya subida sigue viviendo donde se subió aunque después
 * se cambie el interruptor sin volver a migrar). A diferencia de
 * `ver_archivo.php`, acá no hace falta duplicar la llamada cruda a la
 * API de Drive: `EvidenciaStorageInterface::descargarContenido()` ya
 * es el método del contrato común, implementado tanto por
 * GoogleDriveService como por AlmacenamientoLocalService.
 */
#[OA\Tag(name: 'Evidencia de asignatura — visor')]
final class EvidenciaAsignaturaVisorController
{
    public function __construct(
        private readonly EvidenciaAsignaturaRepository $repositorio,
        private readonly EvidenciaStorageResolver $storageResolver,
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
     * GET /evidencia-asignatura/ver?id_evidencia_asig= — requiere sesión
     * (SessionAuthMiddleware, 401). Sirve el contenido real del archivo
     * (PDF o CSV), sin importar si terminó en Google Drive o en
     * almacenamiento local.
     */
    #[OA\Get(
        path: '/evidencia-asignatura/ver',
        summary: 'Sirve el contenido de una evidencia de evidencia_asignatura (I2/I3), sin importar si está en Drive o en almacenamiento local.',
        security: [['sesionPhp' => []]],
        tags: ['Evidencia de asignatura — visor'],
        parameters: [
            new OA\QueryParameter(
                name: 'id_evidencia_asig',
                description: 'ID de la fila en evidencia_asignatura.',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contenido del archivo (application/pdf o text/csv según la extensión).'),
            new OA\Response(response: 400, description: 'Parámetro id_evidencia_asig es requerido.'),
            new OA\Response(response: 401, description: 'La sesión no está activa.'),
            new OA\Response(response: 404, description: 'La evidencia no existe, o el archivo ya no se pudo encontrar en su origen.'),
            new OA\Response(response: 500, description: 'No se pudo leer el archivo desde su origen.'),
        ],
    )]
    public function ver(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idEvidenciaAsig = isset($params['id_evidencia_asig']) ? (int) $params['id_evidencia_asig'] : 0;

        if ($idEvidenciaAsig <= 0) {
            return $this->json($response, false, 'Parámetro id_evidencia_asig es requerido.', [], 400);
        }

        $evidencia = $this->repositorio->obtenerPorId($idEvidenciaAsig);
        if ($evidencia === null) {
            return $this->json($response, false, 'La evidencia no existe.', [], 404);
        }

        $urlArchivo = trim($evidencia['url_archivo']);
        $storage = $this->storageResolver->resolverParaDescarga($urlArchivo);

        try {
            $contenido = $storage->descargarContenido($urlArchivo);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo leer el archivo desde su origen.', ['detalle' => $e->getMessage()], 500);
        }

        if ($contenido === null) {
            return $this->json($response, false, 'El archivo de evidencia no se pudo encontrar en su origen.', [], 404);
        }

        $nombreArchivo = $evidencia['nombre_archivo'] !== '' ? $evidencia['nombre_archivo'] : 'evidencia.pdf';
        $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
        $mimeType = $extension === 'csv' ? 'text/csv' : 'application/pdf';

        $response->getBody()->write($contenido);

        return $response
            ->withHeader('Content-Type', $mimeType)
            ->withHeader('Content-Disposition', 'inline; filename="' . basename($nombreArchivo) . '"')
            ->withHeader('Content-Length', (string) strlen($contenido))
            ->withHeader('Cache-Control', 'private, max-age=0, must-revalidate')
            ->withStatus(200);
    }
}
