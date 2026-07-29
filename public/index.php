<?php

declare(strict_types=1);

use App\Controllers\CarrerasAlmacenamientoController;
use App\Controllers\CarrerasController;
use App\Controllers\EvidenciaAsignaturaVisorController;
use App\Controllers\MallaCurricularController;
use App\Controllers\SeguimientoSyllabusController;
use App\Controllers\TasaDesercionController;
use App\Controllers\TitulacionController;
use App\Controllers\TutoriasAcademicasController;
use App\Infra\Database;
use App\Middleware\CorsMiddleware;
use App\Middleware\SessionAuthMiddleware;
use App\Repositories\CarrerasRepository;
use App\Repositories\DesercionRepository;
use App\Repositories\EvidenciaAsignaturaRepository;
use App\Repositories\MallaCurricularRepository;
use App\Repositories\SeguimientoSyllabusRepository;
use App\Repositories\TitulacionRepository;
use App\Repositories\TutoriasRepository;
use App\Services\EncuestaEvidenciaService;
use App\Services\EvidenciaMigradorService;
use App\Services\EvidenciaStorageResolver;
use App\Services\GoogleDriveService;
use App\Services\SeguimientoSyllabusCalculoService;
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

// Configurable vía APP_BASE_PATH (pendiente que había quedado abierto en
// la migración de I3 -- ver MEMORIA v64/§41.1): el valor por defecto sigue
// siendo '/sistemacaces/public' para no cambiar nada en la máquina real del
// usuario (XAMPP, htdocs/sistemacaces). Los tests de integración necesitan
// basePath='' (ver tests/Integration/IntegrationTestCase.php +
// tests/Integration/router-testing.php), porque ahí Slim se sirve desde la
// raíz del servidor embebido de PHP, sin el prefijo de carpeta de XAMPP.
//
// A propósito NO se decide con `$_ENV['APP_BASE_PATH'] ?? '...'`: en
// Windows, una variable de entorno con valor vacío ('') se pierde al pasar
// por CreateProcess/proc_open (el bloque de entorno de Windows no
// distingue "vacía" de "no seteada"), así que ese valor nunca le llegaba
// al proceso hijo -- confirmado en vivo con logging temporal en
// router-testing.php: `APP_BASE_PATH=(unset)` tanto en `php -S` manual
// como dentro de IntegrationTestCase::setUpBeforeClass(), lo que hacía que
// Slim intentara descontar '/sistemacaces/public' de rutas que no lo
// tenían y devolviera 404 (HttpNotFoundException) en TODOS los tests de
// integración migrados a Slim (I2). Se decide en base a APP_ENV en su
// lugar, que sí es un valor no vacío y viaja bien en Windows.
$basePath = ($_ENV['APP_ENV'] ?? 'production') === 'testing' ? '' : ($_ENV['APP_BASE_PATH'] ?? '/sistemacaces/public');
$app->setBasePath($basePath);

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
// lo tiene, y agregar uno sería sobre-ingeniería para este POC). Con 2
// indicadores ya migrados (I3, I2), este bloque es el primer candidato a
// mover a un contenedor (ej. PHP-DI) si el copy-paste entre indicadores
// empieza a doler al migrar I1/I4/I5.
$conexion = Database::conectar();
$driveService = new GoogleDriveService();
// Interruptor de almacenamiento por carrera (ver
// plan_interruptor_almacenamiento.txt): decide, al subir o descargar
// evidencia, si el destino real es Drive o el almacenamiento local de la
// carrera. $driveService se sigue usando tal cual para la validación de
// archivo (formato, no depende del destino).
$storageResolver = new EvidenciaStorageResolver($conexion, $driveService);

// --- I3 (Tutorías Académicas) -------------------------------------------
$tutoriasRepositorio = new TutoriasRepository($conexion);
$tutoriasCalculoService = new TutoriasCalculoService($tutoriasRepositorio);
$tutoriasValidacionService = new TutoriasValidacionPdfService();
$tutoriasController = new TutoriasAcademicasController($tutoriasRepositorio, $tutoriasCalculoService, $tutoriasValidacionService, $driveService, $storageResolver);

// --- I2 (Seguimiento Syllabus) -------------------------------------------
$seguimientoRepositorio = new SeguimientoSyllabusRepository($conexion);
$encuestaService = new EncuestaEvidenciaService($seguimientoRepositorio, $storageResolver);
$seguimientoCalculoService = new SeguimientoSyllabusCalculoService($seguimientoRepositorio, $encuestaService);
$seguimientoController = new SeguimientoSyllabusController($seguimientoRepositorio, $seguimientoCalculoService, $encuestaService, $driveService, $storageResolver);

// --- Rutas de I3 (Tutorías Académicas) ---------------------------------
// Mismos 4 endpoints que consumía frontend/src/services/tutoriasAcademicas.ts
// contra los archivos sueltos originales; ver INSTRUCCIONES_fase3_i3.md
// para el cambio de URL base que requiere el frontend.
$app->group('/tutorias-academicas', function ($grupo) use ($tutoriasController) {
    $grupo->get('/evidencia-listar', [$tutoriasController, 'evidenciaListar']);
    $grupo->get('/resultado-asignatura', [$tutoriasController, 'resultadoAsignatura']);
    $grupo->get('/resultado-cohorte', [$tutoriasController, 'resultadoCohorte']);
    $grupo->post('/evidencia-subir', [$tutoriasController, 'evidenciaSubir'])->add(new SessionAuthMiddleware());
});

// --- Rutas de I2 (Seguimiento Syllabus) ---------------------------------
// Los 7 endpoints reales que consumía frontend/src/services/seguimientoSyllabus.ts
// contra los 8 archivos sueltos originales (el 8vo, materias_encuesta.php,
// era un endpoint deprecado sin llamadores reales -- ver MEMORIA e
// INSTRUCCIONES_fase3_i2.md), más POST /cohortes, POST /periodos y el
// modulo opcional de POST /asignaturas (ver plan_malla_curricular_xlsx.txt
// §7 Partes 2/3/4). SessionAuthMiddleware agregado a los 3 POST de
// creación que quedaron sin auth al cerrar esas partes (hallazgo de la
// sesión de preguntas abiertas, MEMORIA §82.2) -- mismo criterio que
// /evidencia-subir, que ya lo tenía.
$app->group('/seguimiento-syllabus', function ($grupo) use ($seguimientoController) {
    $grupo->post('/cohortes', [$seguimientoController, 'cohorteCrear'])->add(new SessionAuthMiddleware());
    $grupo->delete('/cohortes/{id}', [$seguimientoController, 'cohorteEliminar'])->add(new SessionAuthMiddleware());
    $grupo->get('/periodos', [$seguimientoController, 'periodos']);
    $grupo->post('/periodos', [$seguimientoController, 'periodoCrear'])->add(new SessionAuthMiddleware());
    $grupo->get('/asignaturas', [$seguimientoController, 'asignaturasListar']);
    $grupo->post('/asignaturas', [$seguimientoController, 'asignaturaCrear'])->add(new SessionAuthMiddleware());
    $grupo->get('/resultado-asignatura', [$seguimientoController, 'resultadoAsignatura']);
    $grupo->get('/resultado-cohorte', [$seguimientoController, 'resultadoCohorte']);
    $grupo->get('/evidencia-listar', [$seguimientoController, 'evidenciaListar']);
    $grupo->get('/encuesta-detalle', [$seguimientoController, 'encuestaDetalle']);
    $grupo->post('/evidencia-subir', [$seguimientoController, 'evidenciaSubir'])->add(new SessionAuthMiddleware());
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

// --- Composición de dependencias de I4 (Tasa de Deserción) --------------
// Misma conexión mysqli reusada del bloque inicial, igual que I5.
$desercionRepositorio = new DesercionRepository($conexion);
$desercionController = new TasaDesercionController($desercionRepositorio);

// --- Rutas de I4 (Tasa de Deserción) ------------------------------------
// Mismos 3 endpoints que consumían obtenerDatosDesercion/leerPdfDesercion/
// guardarDatoDesercion en frontend/src/services/evidencias.ts contra los
// archivos sueltos originales. Igual que I5, I4 no maneja subida de
// evidencia a Drive en sus propios endpoints, por eso son solo 3 rutas.
$app->group('/tasa-desercion', function ($grupo) use ($desercionController) {
    $grupo->get('/obtener', [$desercionController, 'obtener']);
    $grupo->post('/leer-pdf', [$desercionController, 'leerPdf']);
    $grupo->post('/guardar', [$desercionController, 'guardar'])->add(new SessionAuthMiddleware());
});

// --- Composición de dependencias de I1 (Malla Curricular) ---------------
// Misma conexión mysqli reusada del bloque inicial, igual que I4/I5.
$mallaCurricularRepositorio = new MallaCurricularRepository($conexion);
$mallaCurricularController = new MallaCurricularController($mallaCurricularRepositorio);

// --- Rutas de I1 (Malla Curricular) -------------------------------------
// Mismos 2 endpoints que consumían obtenerMallaCurricular/subirMallaCurricular
// en frontend/src/services/{evidencias,carreras}.ts contra los archivos
// sueltos originales (api/carreras/{obtener_malla,guardar_malla}.php). Los
// otros 4 endpoints de api/carreras/ (listar/crear/actualizar/eliminar) son
// administración genérica de Carreras, no del indicador I1, y quedan sin
// tocar -- ver MallaCurricularRepository. `guardar` exige sesión
// (SessionAuthMiddleware, 401) y además rol administrador (403, chequeado
// dentro del controlador -- ver MallaCurricularController).
$app->group('/malla-curricular', function ($grupo) use ($mallaCurricularController) {
    $grupo->get('/obtener', [$mallaCurricularController, 'obtener']);
    $grupo->post('/guardar', [$mallaCurricularController, 'guardar'])->add(new SessionAuthMiddleware());
});

// --- Composición de dependencias de Carreras (administración genérica) --
// Misma conexión mysqli reusada del bloque inicial, igual que I1/I4/I5.
$carrerasRepositorio = new CarrerasRepository($conexion);
$carrerasController = new CarrerasController($carrerasRepositorio);

// --- Rutas de Carreras (administración genérica) -------------------------
// Reemplaza a api/carreras/{listar,crear,actualizar,eliminar}.php, los 4
// endpoints que quedaban fuera de la migración de I1 (ver
// MallaCurricularController) por ser administración genérica de la
// entidad Carrera, no del indicador en sí. Cierra el pendiente de
// middleware de CORS compartido abierto desde v69 (hallazgo 1.2.2 del
// Plan de Mejora): estos 4 archivos eran los últimos que seguían
// repitiendo el bloque de headers CORS a mano en vez de pasar por
// CorsMiddleware. Mismos 4 endpoints que consumía
// frontend/src/shared/services/carreras.ts contra los archivos sueltos
// originales; ver INSTRUCCIONES_cors_carreras.md para el cambio de URL
// base que requiere el frontend. crear() no exige sesión ni rol (igual
// que el original); actualizar()/eliminar() sí (401 vía
// SessionAuthMiddleware, 403 chequeado dentro del controlador).
$app->group('/carreras', function ($grupo) use ($carrerasController) {
    $grupo->get('/listar', [$carrerasController, 'listar']);
    $grupo->post('/crear', [$carrerasController, 'crear']);
    $grupo->post('/actualizar', [$carrerasController, 'actualizar'])->add(new SessionAuthMiddleware());
    $grupo->post('/eliminar', [$carrerasController, 'eliminar'])->add(new SessionAuthMiddleware());
    $grupo->post('/eliminar-forzada', [$carrerasController, 'eliminarForzada'])->add(new SessionAuthMiddleware());
});

// --- Composición e interruptor de almacenamiento por carrera (paso 4 de --
// plan_interruptor_almacenamiento.txt) -------------------------------------
// Reusa $conexion/$driveService/$storageResolver ya armados arriba para
// I2/I3 -- mismo criterio de "una sola conexión, un solo resolver" que ya
// usan TutoriasAcademicasController/SeguimientoSyllabusController.
$evidenciaMigradorService = new EvidenciaMigradorService($conexion, $driveService, $storageResolver);
$carrerasAlmacenamientoController = new CarrerasAlmacenamientoController($evidenciaMigradorService);

// PUT /carreras/{id}/almacenamiento — 401 vía SessionAuthMiddleware, 403
// (rol administrador/coordinador) chequeado dentro del controlador, igual
// que el resto de endpoints protegidos de este archivo.
$app->put('/carreras/{id}/almacenamiento', [$carrerasAlmacenamientoController, 'almacenamiento'])
    ->add(new SessionAuthMiddleware());

// --- Visor de evidencia_asignatura (I2/I3) -- parte 1 del paso 6 de -----
// plan_interruptor_almacenamiento.txt (ver MEMORIA §68.2/§68.5): el paso 5
// solo ramificó ver_archivo.php (I4/I5, tabla `evidencias`); I2/I3 no tenían
// ningún visor en el backend porque el frontend abría url_archivo directo
// con window.open(), lo que se rompe si la carrera está en modo 'local'.
// Reusa $conexion/$storageResolver ya armados arriba para I2/I3.
$evidenciaAsignaturaRepositorio = new EvidenciaAsignaturaRepository($conexion);
$evidenciaAsignaturaVisorController = new EvidenciaAsignaturaVisorController($evidenciaAsignaturaRepositorio, $storageResolver);

// GET /evidencia-asignatura/ver?id_evidencia_asig= — 401 vía SessionAuthMiddleware
// (mismo requisito que ver_archivo.php, que exige sesión activa).
$app->get('/evidencia-asignatura/ver', [$evidenciaAsignaturaVisorController, 'ver'])
    ->add(new SessionAuthMiddleware());

$app->run();