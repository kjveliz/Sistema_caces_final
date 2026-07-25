#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Genera openapi.json a partir de las anotaciones (atributos PHP 8)
 * repartidas en src/ — Fase 3 del Plan de Mejora, tarea "Documentar los
 * endpoints con anotaciones OpenAPI a medida que se migran" (usando
 * zircote/swagger-php sobre Slim, sin mantener la documentación aparte a
 * mano).
 *
 * Cubre únicamente los Controllers que ya existen bajo src/ (hoy: I3 y I5,
 * los dos indicadores migrados a Slim). Cuando se migren I1, I2 o I4, sus
 * Controllers nuevos se recogen automáticamente la próxima vez que se
 * corra este script, sin tocarlo.
 *
 * Uso: composer generate-openapi
 *   (o directamente: php bin/generate-openapi.php)
 */

require __DIR__ . '/../vendor/autoload.php';

$openapi = (new \OpenApi\Generator())->generate([__DIR__ . '/../src']);

$rutaSalida = __DIR__ . '/../openapi.json';
$openapi->saveAs($rutaSalida);

echo "openapi.json generado en: {$rutaSalida}\n";
