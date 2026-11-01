<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;

$database = require __DIR__.'/remaining-environment.php';
$public = realpath(dirname(__DIR__, 2).'/public');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath($public.'/'.ltrim(rawurldecode($path), '/'));
if ($path !== '/' && $file && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    return false;
}
define('LARAVEL_START', microtime(true));
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath(dirname($database));
$app->booted(function () use ($database) {
    Vite::usePrefetchStrategy(null);
    config(['filesystems.disks.local.root' => dirname($database).'/private', 'filesystems.disks.public.root' => dirname($database).'/public']);
});
$app->handleRequest(Request::capture());
