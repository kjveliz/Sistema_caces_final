<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\TitulacionRepository;
use App\Services\TitulacionCalculoService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Controlador de I5 (Tasa de Titulación). Reemplaza a los 3 archivos
 * sueltos api/tasa_titulacion/{obtener,leer_pdf,guardar}.php — misma
 * lógica de negocio, mismas validaciones de parámetros, misma forma de
 * respuesta JSON ({ok, mensaje, datos}), ahora detrás del router de Slim en
 * vez de ser cada uno un archivo PHP accesible directo por URL (hallazgo
 * 1.2.1 del Plan de Mejora).
 *
 * A diferencia de I3, I5 no maneja subida de evidencia PDF a Google Drive
 * en sus propios endpoints: eso lo hacen los endpoints genéricos
 * api/evidencias/* + api/google_drive/*, que no se tocan en esta
 * migración. Por eso no hay dependencia de GoogleDriveService aquí.
 */
final class TitulacionController
{
    public function __construct(
        private readonly TitulacionRepository $repositorio,
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

    /** GET /tasa-titulacion/obtener?id_evaluacion= */
    public function obtener(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $idEvaluacion = isset($params['id_evaluacion']) ? (int) $params['id_evaluacion'] : 0;

        if ($idEvaluacion <= 0) {
            return $this->json($response, false, 'El identificador de la evaluación no es válido.', [], 400);
        }

        try {
            $filas = $this->repositorio->obtenerPorEvaluacion($idEvaluacion);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudieron consultar los datos.', ['detalle' => $e->getMessage()], 500);
        }

        $datos = array_map(fn ($d) => $d->toArray(), $filas);

        return $this->json($response, true, null, ['datos' => $datos]);
    }

    /** POST /tasa-titulacion/leer-pdf (multipart: archivo, tipo_dato) */
    public function leerPdf(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        $tipoDato = trim((string) ($body['tipo_dato'] ?? ''));

        if (!in_array($tipoDato, ['matriculados', 'graduados'], true)) {
            return $this->json($response, false, 'El tipo de dato debe ser matriculados o graduados.', [], 400);
        }

        $archivosSubidos = $request->getUploadedFiles();
        if (!isset($archivosSubidos['archivo'])) {
            return $this->json($response, false, 'No se recibió ningún archivo PDF.', [], 400);
        }
        $archivoSubido = $archivosSubidos['archivo'];

        if ($archivoSubido->getError() !== UPLOAD_ERR_OK) {
            return $this->json($response, false, 'No se pudo recibir el archivo.', [], 400);
        }

        $rutaTemporal = $archivoSubido->getStream()->getMetadata('uri') ?? '';
        $extension = strtolower(pathinfo($archivoSubido->getClientFilename() ?? '', PATHINFO_EXTENSION));
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($rutaTemporal);

        if ($extension !== 'pdf' || $mime !== 'application/pdf') {
            return $this->json($response, false, 'El archivo debe ser un PDF válido.', [], 400);
        }

        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($rutaTemporal);
            $texto = $pdf->getText();

            $datos = TitulacionCalculoService::extraerDatosTitulacion($texto, $tipoDato);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudo leer la información del PDF.', ['detalle' => $e->getMessage()], 500);
        }

        return $this->json($response, true, 'El PDF fue leído correctamente.', ['datos' => $datos->toArray()]);
    }

    /** POST /tasa-titulacion/guardar (JSON: id_evaluacion, cohorte, matriculados?, graduados?) — requiere sesión. */
    public function guardar(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();

        $idEvaluacion = (int) ($body['id_evaluacion'] ?? 0);
        // Mismo orden exacto que el original: preg_replace (sin /i) sobre el
        // valor todavía en su capitalización original, y recién después
        // strtoupper. Se preserva a propósito, sin "corregir" nada: si el
        // usuario manda una cohorte en minúsculas, las letras se pierden acá
        // igual que en guardar.php original (comportamiento preexistente,
        // fuera del alcance de esta migración 1:1).
        $cohorte = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim((string) ($body['cohorte'] ?? ''))));

        $tieneMatriculados = is_array($body) && array_key_exists('matriculados', $body);
        $tieneGraduados = is_array($body) && array_key_exists('graduados', $body);

        $matriculados = $tieneMatriculados ? (int) $body['matriculados'] : null;
        $graduados = $tieneGraduados ? (int) $body['graduados'] : null;

        if ($idEvaluacion <= 0 || $cohorte === '' || (!$tieneMatriculados && !$tieneGraduados)) {
            return $this->json($response, false, 'Faltan datos para actualizar la tasa.', [], 400);
        }

        if (($tieneMatriculados && $matriculados <= 0) || ($tieneGraduados && $graduados < 0)) {
            return $this->json($response, false, 'Las cantidades recibidas no son válidas.', [], 400);
        }

        try {
            $resultado = $this->repositorio->guardarDato($idEvaluacion, $cohorte, $matriculados, $graduados);
        } catch (Throwable $e) {
            return $this->json($response, false, 'No se pudieron actualizar los datos de titulación.', ['detalle' => $e->getMessage()], 500);
        }

        return $this->json($response, true, 'Datos de titulación actualizados correctamente.', ['datos' => $resultado->toArray()]);
    }
}
