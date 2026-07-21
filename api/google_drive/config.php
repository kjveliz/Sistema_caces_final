<?php

require_once __DIR__ . "/../../vendor/autoload.php";

$cliente = new Google\Client();

$cliente->setAuthConfig(__DIR__ . "/credenciales.json");

$cliente->setApplicationName("Sistema CACES");

$cliente->setScopes([
    Google\Service\Drive::DRIVE_FILE,
]);

$cliente->setAccessType("offline");

$cliente->setPrompt("consent");

$cliente->setRedirectUri(
    "http://localhost/sistemacaces/api/google_drive/callback.php"
);