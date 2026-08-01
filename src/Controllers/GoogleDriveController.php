<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\EvidenciasRepository;
use App\Services\AlmacenamientoLocalService;
use App\Services\EvidenciaStorageResolver;
use App\Services\GoogleDriveClienteFactory;
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
 *
 * subirArchivo() (Parte 21, sesión 2) suma el segundo método del grupo:
 * reemplaza a api/google_drive/subir_archivo.php, el endpoint genérico de
 * subida compartido por I1/I4/I5 (y 3 slots evaluation-wide de I2). Wiring
 * con GoogleDriveService::subirArchivoCatalogo() y
 * AlmacenamientoLocalService::subirArchivo() (sesión 1, ver MEMORIA §143.2)
 * vía $storageResolver, ya inyectado para verArchivo().
 *
 * conectar() (Parte 22) suma el tercer método del grupo: reemplaza a
 * api/google_drive/conectar.php, el disparador manual (desde el navegador
 * del administrador, no desde fetch()) del flujo de autorización OAuth de
 * Google. Es la Parte previa a la última del plan (callback.php, Parte 23)
 * -- se prueba aislada primero porque el redirect_uri que arma
 * GoogleDriveClienteFactory sigue apuntando al callback legacy todavía sin
 * migrar (ver docblock de conectar() para el detalle).
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

    /**
     * POST /google-drive/subir-archivo (multipart) — sin sesión, mismo
     * criterio que api/evidencias/{leer-matriculados,preparar-pdf} (POST
     * migrados sin SessionAuthMiddleware): el original tampoco validaba
     * $_SESSION['id_usuario'] (a diferencia de POST /evidencias/guardar,
     * que sí lo hacía y sí lleva el middleware) — ver plan §2 punto 4.
     *
     * Sube (o reemplaza) el archivo genérico de evidencia de I1/I4/I5 (y 3
     * slots evaluation-wide de I2), resolviendo el interruptor de
     * almacenamiento por carrera igual que evidenciaSubir() de I2/I3
     * (EvidenciaStorageResolver::resolver()). A diferencia de ese método
     * (5 niveles: Carrera/Cohorte/PAO/Asignatura), preserva el árbol
     * propio de este endpoint: 3 niveles en Drive (Carrera/Cohorte, vía
     * GoogleDriveService::subirArchivoCatalogo()) y 5 niveles en local con
     * los 2 segmentos fijos "Evaluacion"/"I{indicador}" (vía
     * AlmacenamientoLocalService::subirArchivo(), mismo método que ya usa
     * evidenciaSubir() — el árbol de 5 niveles SÍ es común a ambas rutas
     * del lado local; solo Drive difiere entre este endpoint y I2/I3, ver
     * GoogleDriveService::subirArchivoCatalogo() para el detalle completo
     * de esa discrepancia ya existente).
     *
     * Dos simplificaciones respecto a la respuesta del original,
     * documentadas acá en vez de "corregirlas" (plan §4) porque no son un
     * bug: son el mismo criterio de forma ya usado por
     * GoogleDriveService::subirArchivo()/subirArchivoCatalogo() y por
     * evidenciaSubir() (I2/I3) — confirmado con el usuario que el frontend
     * (subirPdfGoogleDrive() y subirMallaCurricular(), ver
     * frontend/src/shared/services/{evidencias,carreras}.ts) solo lee
     * `url_archivo`/`nombre_archivo`/`id_archivo` de la respuesta; el resto
     * de los campos venían tipados pero sin uso real:
     *   1. `datos` ya no trae `url_descarga`/`id_carpeta` (el original los
     *      calculaba a partir de webContentLink y de la carpeta destino).
     *   2. El mensaje de éxito en Drive ya no distingue "creado" de
     *      "actualizado" (subirArchivoCatalogo() no expone esa
     *      información, igual que subirArchivo() para I2/I3).
     */
    #[OA\Post(
        path: '/google-drive/subir-archivo',
        summary: 'Sube (o reemplaza) un archivo de evidencia genérico (I1/I4/I5) a Drive o almacenamiento local, según el interruptor de la carrera.',
        tags: ['Google Drive'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['id_carrera', 'codigo_carrera', 'nombre_carrera', 'cohorte', 'indicador', 'nombre_archivo', 'archivo'],
                    properties: [
                        new OA\Property(property: 'id_carrera', type: 'integer'),
                        new OA\Property(property: 'codigo_carrera', type: 'string', example: 'SOFT'),
                        new OA\Property(property: 'nombre_carrera', type: 'string', example: 'Desarrollo de Software'),
                        new OA\Property(property: 'cohorte', type: 'string', example: 'B2025'),
                        new OA\Property(property: 'indicador', type: 'integer', example: 4),
                        new OA\Property(property: 'nombre_archivo', type: 'string'),
                        new OA\Property(property: 'tipo_esperado', type: 'string', enum: ['pdf', 'csv', 'xlsx'], example: 'pdf'),
                        new OA\Property(property: 'archivo', type: 'string', format: 'binary'),
                    ],
                ),
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Archivo guardado (en Drive o en almacenamiento local, según la carrera).',
                content: new OA\JsonContent(properties: [
                    new OA\Property(property: 'ok', type: 'boolean'),
                    new OA\Property(property: 'datos', properties: [
                        new OA\Property(property: 'id_archivo', type: 'string'),
                        new OA\Property(property: 'nombre_archivo', type: 'string'),
                        new OA\Property(property: 'url_archivo', type: 'string'),
                        new OA\Property(property: 'codigo_carrera', type: 'string'),
                        new OA\Property(property: 'nombre_carrera', type: 'string'),
                        new OA\Property(property: 'cohorte', type: 'string'),
                        new OA\Property(property: 'indicador', type: 'integer'),
                    ], type: 'object'),
                ]),
            ),
            new OA\Response(response: 400, description: 'Faltan datos, no se recibió archivo, o el archivo no pasó la validación.'),
            new OA\Response(response: 500, description: 'No se pudo subir el archivo de evidencia.'),
        ],
    )]
    public function subirArchivo(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $body = is_array($body) ? $body : [];

        $tipoEsperado = trim((string) ($body['tipo_esperado'] ?? 'pdf'));
        if (!in_array($tipoEsperado, ['pdf', 'csv', 'xlsx'], true)) {
            $tipoEsperado = 'pdf';
        }
        $etiquetaTipo = match ($tipoEsperado) {
            'csv' => 'CSV',
            'xlsx' => 'Excel',
            default => 'PDF',
        };

        $idCarrera = (int) ($body['id_carrera'] ?? 0);
        $codigoCarrera = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', trim((string) ($body['codigo_carrera'] ?? ''))));
        $nombreCarrera = trim((string) ($body['nombre_carrera'] ?? ''));
        $cohorte = strtoupper((string) preg_replace('/[^A-Z0-9]/', '', trim((string) ($body['cohorte'] ?? ''))));
        $indicador = (int) ($body['indicador'] ?? 0);
        $nombreArchivo = trim((string) ($body['nombre_archivo'] ?? ''));

        if ($idCarrera <= 0 || $codigoCarrera === '' || $nombreCarrera === '' || $cohorte === '' || $indicador <= 0 || $nombreArchivo === '') {
            return $this->json($response, false, 'Faltan datos para subir el archivo.', [], 400);
        }

        $archivosSubidos = $request->getUploadedFiles();
        if (!isset($archivosSubidos['archivo'])) {
            return $this->json($response, false, 'No se recibió ningún archivo.', [], 400);
        }
        $archivoSubido = $archivosSubidos['archivo'];

        $archivoLegacy = [
            'name' => $archivoSubido->getClientFilename() ?? '',
            'type' => $archivoSubido->getClientMediaType() ?? '',
            'tmp_name' => $archivoSubido->getStream()->getMetadata('uri') ?? '',
            'error' => $archivoSubido->getError(),
            'size' => $archivoSubido->getSize() ?? 0,
        ];

        $storage = $this->storageResolver->resolver($idCarrera);

        $errorValidacion = match ($tipoEsperado) {
            'csv' => $storage->validarCsv($archivoLegacy),
            'xlsx' => $storage->validarXlsx($archivoLegacy),
            default => $storage->validarArchivoSubido($archivoLegacy),
        };
        if ($errorValidacion !== null) {
            return $this->json($response, false, $errorValidacion, [], 400);
        }

        $mimeSubida = match ($tipoEsperado) {
            'csv' => 'text/csv',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/pdf',
        };

        try {
            if ($storage instanceof AlmacenamientoLocalService) {
                $subida = $storage->subirArchivo(
                    $archivoLegacy['tmp_name'],
                    $nombreArchivo,
                    $nombreCarrera,
                    $cohorte,
                    'Evaluacion',
                    "I{$indicador}",
                    $mimeSubida,
                );
                $mensaje = "{$etiquetaTipo} guardado correctamente en almacenamiento local.";
            } else {
                $subida = $storage->subirArchivoCatalogo(
                    $archivoLegacy['tmp_name'],
                    $nombreArchivo,
                    $nombreCarrera,
                    $cohorte,
                    $mimeSubida,
                );
                $mensaje = "{$etiquetaTipo} guardado correctamente en Google Drive.";
            }
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo subir el ' . $etiquetaTipo . ' de evidencia.', ['detalle' => $e->getMessage()], 500);
        }

        return $this->json($response, true, $mensaje, [
            'datos' => [
                'id_archivo' => $subida['id_archivo'],
                'nombre_archivo' => $subida['nombre_archivo'],
                'url_archivo' => $subida['url_archivo'],
                'codigo_carrera' => $codigoCarrera,
                'nombre_carrera' => $nombreCarrera,
                'cohorte' => $cohorte,
                'indicador' => $indicador,
            ],
        ]);
    }

    /**
     * GET /google-drive/conectar — redirect real (302) hacia la pantalla
     * de consentimiento OAuth de Google, no JSON (mismo criterio del plan
     * §2 punto 3). Puerto 1:1 de api/google_drive/conectar.php (Parte 22):
     * arma la URL de autorización vía
     * GoogleDriveClienteFactory::crear()->createAuthUrl() y la devuelve
     * como header Location + status 302 -- Slim expresa el redirect así,
     * a diferencia del script original que hacía header()+exit() directo.
     *
     * Sin SessionAuthMiddleware: el original tampoco validaba sesión (lo
     * abre manualmente el administrador desde su navegador, vía
     * window.open() en StorageSettingsModal.tsx -- no es un fetch() del
     * SPA, ver frontend actualizado).
     *
     * El redirect_uri que arma GoogleDriveClienteFactory::crear() sigue
     * apuntando a la URL legacy de callback.php
     * (api/google_drive/callback.php), todavía sin migrar (Parte 23, la
     * última del plan). No se cambia acá: Google Cloud Console tiene
     * registrado ese redirect_uri exacto, y apuntar ya al futuro
     * /google-drive/callback antes de migrar (y probar) callback.php
     * dejaría el flujo de conexión real roto a mitad de camino -- por eso
     * el plan (Fase 4, §3) prueba conectar.php aislado primero, con el
     * callback legacy todavía sirviendo.
     *
     * Seam de testing (mismo criterio que GoogleDriveService::
     * subirArchivo() y demás métodos del grupo): bajo APP_ENV=testing se
     * evita crear el cliente real de Google (no hay credenciales.json de
     * prueba disponibles) y se redirige a una URL fake determinística.
     */
    #[OA\Get(
        path: '/google-drive/conectar',
        summary: 'Redirige a la pantalla de consentimiento OAuth de Google para conectar Google Drive.',
        tags: ['Google Drive'],
        responses: [
            new OA\Response(response: 302, description: 'Redirect hacia la pantalla de autorización de Google.'),
        ],
    )]
    public function conectar(Request $request, Response $response): Response
    {
        if ((getenv('APP_ENV') ?: '') === 'testing') {
            return $response
                ->withHeader('Location', 'https://accounts.google.com/o/oauth2/fake-test-double')
                ->withStatus(302);
        }

        $cliente = GoogleDriveClienteFactory::crear();
        $authUrl = $cliente->createAuthUrl();

        return $response
            ->withHeader('Location', $authUrl)
            ->withStatus(302);
    }
}
