<?php

declare(strict_types=1);

/**
 * Router para el servidor embebido de `php -S` usado por IntegrationTestCase.
 *
 * En producción (XAMPP), `public/.htaccess` reescribe todo lo que no sea un
 * archivo real hacia `public/index.php` (front controller de Slim) -- el
 * servidor embebido de PHP no lee .htaccess, así que este router hace lo
 * mismo a mano, SOLO para el propósito de los tests:
 *
 *   - Si la URI pedida es un archivo real que existe en el repo (ej.
 *     `/api/auth/Login.php`, todavía un archivo suelto no migrado a Slim),
 *     se devuelve `false` para que el servidor embebido lo sirva tal cual,
 *     exactamente igual que sin este router.
 *   - Si no, se delega a `public/index.php`, que atiende con Slim
 *     (basePath='' en testing, ver IntegrationTestCase -- a diferencia de
 *     producción, acá no hay prefijo de subcarpeta que descontar).
 *
 * Sin este router, los tests de integración de I2/I3 (que le pegan a rutas
 * como `/tutorias-academicas/...` o `/seguimiento-syllabus/...`) recibirían
 * 404 del servidor embebido, porque esas URIs no corresponden a ningún
 * archivo real -- ver MEMORIA v64/§41.1, el pendiente que esto resuelve.
 */
$uri = urldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$docRoot = rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/');
$rutaReal = $docRoot . $uri;

if ($uri !== '/' && is_file($rutaReal)) {
    return false;
}

require $docRoot . '/public/index.php';
