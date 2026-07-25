<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * Punto de entrada de las anotaciones OpenAPI generadas con
 * zircote/swagger-php (Fase 3 del Plan de Mejora, hallazgo 1.2.7 —
 * "Documentar los endpoints con anotaciones OpenAPI a medida que se
 * migran"). No es un controlador real ni se registra en ninguna ruta:
 * solo agrupa la información general (versión, servidor, esquema de
 * seguridad) que el generador necesita para armar el documento completo
 * junto con las anotaciones de cada Controller migrado.
 *
 * Cubre únicamente los indicadores ya migrados a Slim (I3, I5). I1, I2 e
 * I4 siguen como archivos .php sueltos y no aparecen en el
 * openapi.json generado hasta que se migren con el mismo patrón.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'Sistema CACES — API (indicadores migrados a Slim)',
    description: 'Documentación generada automáticamente con zircote/swagger-php a partir de las '
        . 'anotaciones en src/Controllers/. Cubre I3 (Tutorías Académicas) e I5 (Tasa de Titulación); '
        . 'I1, I2 e I4 todavía son archivos .php sueltos (api/*) sin migrar a esta arquitectura y no '
        . 'aparecen en este documento.',
)]
#[OA\Server(
    url: 'http://localhost/sistemacaces/public',
    description: 'Entorno de desarrollo local (XAMPP), sirviendo el nuevo punto de entrada de Slim.',
)]
#[OA\SecurityScheme(
    securityScheme: 'sesionPhp',
    type: 'apiKey',
    name: 'PHPSESSID',
    in: 'cookie',
    description: 'Sesión de PHP iniciada vía api/auth/Login.php (no migrado todavía). Requerida por los '
        . 'endpoints protegidos con SessionAuthMiddleware: evidencia-subir de I3 y guardar de I5.',
)]
final class Definition
{
}
