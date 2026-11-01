<?php

use Illuminate\Http\Request;

$database = require __DIR__.'/delivery-environment.php';
$public = realpath(dirname(__DIR__, 2).'/public');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath($public.'/'.ltrim(rawurldecode($path), '/'));
if ($path !== '/' && $file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    return false;
}
define('LARAVEL_START', microtime(true));
require dirname(__DIR__, 2).'/bootstrap/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath(dirname($database));
$sessions = dirname($database).'/sessions';
if (! is_dir($sessions)) {
    mkdir($sessions, 0700, true);
}
$app->booted(fn () => config(['session.files' => $sessions]));
$app->handleRequest(Request::capture());
