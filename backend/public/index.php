<?php

define('LARAVEL_START', microtime(true));

if (isset($_SERVER['RAILWAY_ENVIRONMENT'])) {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = 443;
}

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle($request = Illuminate\Http\Request::capture());

$response->send();

$kernel->terminate($request, $response);
