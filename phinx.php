<?php

// Configuración de Phinx (Fase 2 del Plan de Mejora — migraciones versionadas).
// Lee las credenciales de BD desde las mismas variables de entorno que ya usa
// api/conexion.php (Fase 0). Si no hay .env presente, cae en los mismos
// valores de siempre (root/vacío/localhost/evaluacion_caces) para que un
// checkout recién clonado sin .env también pueda correr "vendor/bin/phinx".

require_once __DIR__ . '/vendor/autoload.php';

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds',
    ],

    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'local',
        'local' => [
            'adapter' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'name' => $_ENV['DB_NAME'] ?? 'evaluacion_caces',
            'user' => $_ENV['DB_USER'] ?? 'root',
            'pass' => $_ENV['DB_PASS'] ?? '',
            'port' => $_ENV['DB_PORT'] ?? 3306,
            'charset' => 'utf8mb4',
        ],
        // Entorno aislado para la primera corrida de "phinx migrate"/"seed:run".
        // Usa un nombre de base fijo (nunca "evaluacion_caces") para que la
        // prueba inicial de la Fase 2 no pueda tocar la base real por
        // accidente, sin depender de editar el .env a mano. Se corre con
        // "vendor/bin/phinx migrate -e testing" (ver docs/INSTRUCCIONES_fase2.md).
        'testing' => [
            'adapter' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'name' => 'evaluacion_caces_test',
            'user' => $_ENV['DB_USER'] ?? 'root',
            'pass' => $_ENV['DB_PASS'] ?? '',
            'port' => $_ENV['DB_PORT'] ?? 3306,
            'charset' => 'utf8mb4',
        ],
    ],

    'version_order' => 'creation',
];