<?php

require_once __DIR__ . "/../../vendor/autoload.php";

$cliente = \App\Services\GoogleDriveClienteFactory::crear();

$authUrl = $cliente->createAuthUrl();

header("Location: " . $authUrl);
exit;