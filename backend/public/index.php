<?php

declare(strict_types=1);

use FormularioDorado\Application;
use FormularioDorado\Config\AppConfig;
use FormularioDorado\Http\Request;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

ini_set('display_errors', '0');
header_remove('X-Powered-By');

try {
    $kernel = Application::kernel(AppConfig::fromEnvironment(getenv()));
} catch (Throwable $failure) {
    error_log('bootstrap_failed type=' . $failure::class);
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo 'Service unavailable.';

    return;
}

$kernel->handle(Request::fromGlobals())->send();
