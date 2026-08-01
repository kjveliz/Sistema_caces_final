<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\EvidenciasRepository;
use App\Services\EvidenciaStorageResolver;
use App\Services\MimeTypePorExtension;
use OpenApi\Attributes as OA;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

/**
 * Controlador del Grupo D (Google Drive / OAuth) del plan de migración de
 * PHP suelto a Slim (ver plan_migracion_slim_legacy_v3.txt §1/§3) -- las 4
 * Partes de endpoints reales del grupo (17-19, soporte interno sin URL
 * propia, ya migraron a servicios: GoogleDriveClienteFactory,
 * GoogleDriveCarpetas, GoogleDriveClienteAutorizado). Arranca en la Parte
 * 20 con verArchivo() (reemplaza a api/google_drive/ver_archivo.php); las
 * Partes 21-23 del mismo grupo suman métodos acá (mismo criterio que
 * AuthController con el Grupo A: un Controller por grupo, no por Parte).
 *
 * verArchivo() reutiliza EvidenciaStorageResolver::resolverParaDescarga()
 * + EvidenciaStorageInterface::descargarContenido() en vez de duplicar la
 * llamada cruda a la API de Drive que tenía el original -- mismo patrón
 * que EvidenciaAsignaturaVisorController::ver() (paso 6 del plan de
 * interruptor de almacenamiento), que ya resuelve exactamente esta misma
 * bifurcación (Drive vs almacenamiento local) para evidencia_asignatura.
 * A diferencia de ese controller, el formato de respuesta de éxito no es
 * JSON (mismo criterio del plan, §2 punto 3): sirve los bytes reales del
 * archivo con su Content-Type propio.
 */
#[OA\Tag(name: 'Google Drive')]
final class GoogleDriveController
{
    public function __construct(
        private readonly EvidenciasRepository $repositorio,
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
     * GET /google-drive/ver-archivo?id_evidencia= — requiere sesión
     * (SessionAuthMiddleware, 401, mismo mensaje exacto que el original).
     * Sirve el contenido real de una evidencia de `evidencias` (I1/I4/I5),
     * sin importar si terminó en Google Drive o en almacenamiento local
     * (interruptor de almacenamiento, ver
     * EvidenciaStorageResolver::resolverParaDescarga()).
     *
     * Mismo criterio de Content-Type que el original: 'text/csv' para
     * evidencias .csv (a diferencia de EvidenciaAsignaturaVisorController,
     * que usa 'text/plain' para el mismo caso -- ese es un fix posterior
     * de un bug de I2/I3 que no aplica acá, fuera de alcance de esta
     * Parte: no se toca lógica de negocio más allá de mover de
     * arquitectura, ver plan §4).
     *
     * Dos matices heredados de reusar el contrato común
     * (EvidenciaStorageInterface) en vez de la llamada cruda a Drive que
     * tenía el original -- mismo precedente ya aceptado por
     * EvidenciaAsignaturaVisorController, documentado acá aparte en vez de
     * "arreglarlo" en esta Parte (plan §4: no mezclar deuda con el cambio
     * de arquitectura):
     *   1. Un url_archivo con forma de link de Drive pero sin el patrón
     *      "/d/{id}/" ya no distingue 400 ("URL de Drive inválida") de un
     *      archivo simplemente no encontrado -- ambos caen acá en el mismo
     *      404 genérico.
     *   2. El chequeo explícito de "Drive devolvió un archivo vacío" del
     *      original no está en GoogleDriveService::descargarContenidoDrive()
     *      -- un archivo vacío real en Drive se serviría como 200 con
     *      Content-Length: 0 en vez de 500.
     */
    #[OA\Get(
        path: '/google-drive/ver-archivo',
        summary: 'Sirve el contenido de una evidencia (I1/I4/I5), sin importar si está en Drive o en almacenamiento local.',
        security: [['sesionPhp' => []]],
        tags: ['Google Drive'],
        parameters: [
            new OA\QueryParameter(
                name: 'id_evidencia',
                description: 'ID de la fila en evidencias.',
                required: true,
                schema: new OA\Schema(type: 'integer'),
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Contenido del archivo (Content-Type según la extensión real: PDF, CSV, imagen, Office, etc.).'),
            new OA\Response(response: 400, description: 'Identificador de evidencia inválido.'),
            new OA\Response(response: 401, description: 'La sesión no está activa.'),
            new OA\Response(response: 404, description: 'La evidencia no existe, o el archivo ya no se pudo encontrar en su origen.'),
            new OA\Response(response: 500, description: 'No se pudo leer el archivo desde su origen.'),
        ],
    )]
    public function verArchivo(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idEvidencia = isset($params['id_evidencia']) ? (int) $params['id_evidencia'] : 0;

        if ($idEvidencia <= 0) {
            return $this->json($response, false, 'Identificador de evidencia inválido.', [], 400);
        }

        $evidencia = $this->repositorio->obtenerPorId($idEvidencia);
        if ($evidencia === null) {
            return $this->json($response, false, 'La evidencia no existe.', [], 404);
        }

        $urlArchivo = trim($evidencia['url_archivo'] ?? '');
        $storage = $this->storageResolver->resolverParaDescarga($urlArchivo);

        try {
            $contenido = $storage->descargarContenido($urlArchivo);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo mostrar el documento: ' . $e->getMessage(), [], 500);
        }

        if ($contenido === null) {
            return $this->json($response, false, 'El archivo de evidencia no se pudo encontrar en su origen.', [], 404);
        }

        $nombreArchivo = $evidencia['nombre_archivo'] ?? 'evidencia.pdf';
        $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
        $mimeType = $extension === 'csv'
            ? 'text/csv'
            : MimeTypePorExtension::resolver($nombreArchivo);

        $response->getBody()->write($contenido);

        return $response
            ->withHeader('Content-Type', $mimeType)
            ->withHeader('Content-Disposition', 'inline; filename="' . basename($nombreArchivo) . '"')
            ->withHeader('Content-Length', (string) strlen($contenido))
            ->withHeader('Cache-Control', 'private, max-age=0, must-revalidate')
            ->withStatus(200);
    }
}
