<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
$requestPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';

if (preg_match('#^(.*)/public/?$#', $requestPath, $matches)) {
    $targetPath = ($matches[1] ?? '') !== '' ? rtrim($matches[1], '/').'/' : '/';
    $queryString = (string) parse_url($requestUri, PHP_URL_QUERY);
    $location = $targetPath.($queryString !== '' ? '?'.$queryString : '');

    header('Location: '.$location, true, 302);
    exit;
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
