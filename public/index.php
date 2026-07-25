<?php

declare(strict_types=1);

use App\Controllers\TitulacionController;
use App\Controllers\TutoriasAcademicasController;
use App\Infra\Database;
use App\Middleware\CorsMiddleware;
use App\Middleware\SessionAuthMiddleware;
use App\Repositories\TitulacionRepository;
use App\Repositories\TutoriasRepository;
use App\Services\GoogleDriveService;
use App\Services\TutoriasCalculoService;
use App\Services\TutoriasValidacionPdfService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response as Psr7Response;

require __DIR__ . '/../vendor/autoload.php';

// Carga .env desde la raíz del repo, igual que api/conexion.php (misma
// fuente de configuración, sin duplicarla).
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$app = AppFactory::create();

// Slim necesita conocer la base path cuando se sirve desde una subcarpeta
// (ej. XAMPP con htdocs/sistemacaces/public), para que el enrutamiento no
// se confunda con el prefijo real de la URL.
$app->setBasePath('/sistemacaces/public');

$app->addBodyParsingMiddleware();

$origenPermitido = $_ENV['CORS_ALLOWED_ORIGIN'] ?? 'http://localhost:5173';
$app->add(new CorsMiddleware($origenPermitido));

// Manejador de errores de Slim, con el mismo formato {ok, mensaje} que usa
// el resto del backend en vez del HTML por defecto de Slim.
$mostrarDetalleErrores = ($_ENV['APP_ENV'] ?? 'production') !== 'production';
$errorMiddleware = $app->addErrorMiddleware($mostrarDetalleErrores, true, true);
$errorMiddleware->setDefaultErrorHandler(function (
    Request $request,
    Throwable $exception,
) use ($mostrarDetalleErrores): Response {
    $response = new Psr7Response(500);
    $response->getBody()->write(json_encode([
        'ok' => false,
        'mensaje' => 'Ocurrió un error inesperado.',
        'detalle' => $mostrarDetalleErrores ? $exception->getMessage() : null,
    ], JSON_UNESCAPED_UNICODE));

    return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
});

// --- Composición manual de dependencias (sin contenedor DI: el proyecto no
// lo tiene, y agregar uno para un solo controlador sería sobre-ingeniería
// para este POC). Cuando se migren más indicadores en las próximas
// sesiones, este bloque es el primer candidato a mover a un contenedor
// (ej. PHP-DI) si el copy-paste entre indicadores empieza a doler.
$conexion = Database::conectar();
$repositorio = new TutoriasRepository($conexion);
$calculoService = new TutoriasCalculoService($repositorio);
$validacionService = new TutoriasValidacionPdfService();
$driveService = new GoogleDriveService();
$controller = new TutoriasAcademicasController($repositorio, $calculoService, $validacionService, $driveService);

// --- Rutas de I3 (Tutorías Académicas) ---------------------------------
// Mismos 4 endpoints que consumía frontend/src/services/tutoriasAcademicas.ts
// contra los archivos sueltos originales; ver INSTRUCCIONES_fase3_i3.md
// para el cambio de URL base que requiere el frontend.
$app->group('/tutorias-academicas', function ($grupo) use ($controller) {
    $grupo->get('/evidencia-listar', [$controller, 'evidenciaListar']);
    $grupo->get('/resultado-asignatura', [$controller, 'resultadoAsignatura']);
    $grupo->get('/resultado-cohorte', [$controller, 'resultadoCohorte']);
    $grupo->post('/evidencia-subir', [$controller, 'evidenciaSubir'])->add(new SessionAuthMiddleware());
});

// --- Composición de dependencias de I5 (Tasa de Titulación) ------------
// Reusa la misma conexión mysqli ya abierta arriba para I3 (una sola
// conexión por request, igual de barato que abrir una nueva y evita
// duplicar Database::conectar()).
$titulacionRepositorio = new TitulacionRepository($conexion);
$titulacionController = new TitulacionController($titulacionRepositorio);

// --- Rutas de I5 (Tasa de Titulación) -----------------------------------
// Mismos 3 endpoints que consumían obtenerDatosTasa/leerPdfTitulacion/
// guardarDatoTitulacion en frontend/src/services/evidencias.ts contra los
// archivos sueltos originales. I5 no maneja subida de evidencia a Drive en
// sus propios endpoints (eso sigue en api/evidencias/*, sin tocar), por eso
// son solo 3 rutas y no 4 como I3.
$app->group('/tasa-titulacion', function ($grupo) use ($titulacionController) {
    $grupo->get('/obtener', [$titulacionController, 'obtener']);
    $grupo->post('/leer-pdf', [$titulacionController, 'leerPdf']);
    $grupo->post('/guardar', [$titulacionController, 'guardar'])->add(new SessionAuthMiddleware());
});

$app->run();
