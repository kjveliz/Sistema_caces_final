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
 * Con la migración de los 5 indicadores completa (Fase 3, v69) y las
 * anotaciones OpenAPI de I1/I2/I4 agregadas a sus Controllers, este
 * documento pasa a cubrir los 5 indicadores (I1-I5) la próxima vez que se
 * corra `composer generate-openapi` — ver openapi.json.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'Sistema CACES — API (indicadores migrados a Slim)',
    description: 'Documentación generada automáticamente con zircote/swagger-php a partir de las '
        . 'anotaciones en src/Controllers/. Cubre los 5 indicadores del sistema: I1 (Malla Curricular), '
        . 'I2 (Seguimiento Syllabus), I3 (Tutorías Académicas), I4 (Tasa de Deserción) e I5 (Tasa de '
        . 'Titulación) — los 5 ya migrados a esta arquitectura en capas detrás de Slim.',
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
    description: 'Sesión de PHP iniciada vía POST /auth/login. Requerida por los endpoints protegidos con '
        . 'SessionAuthMiddleware: evidencia-subir de I2/I3, guardar de I4/I5, y guardar de I1 (que además '
        . 'exige rol administrador, 403 si no lo es).',
)]
final class Definition
{
}
