<?php

require_once __DIR__ . "/config.php";

$authUrl = $cliente->createAuthUrl();

header("Location: " . $authUrl);
exit;