<?php

$vendor = dirname(__DIR__).'/vendor/autoload.php';
if (! file_exists($vendor)) {
    if (getenv('POS_SHARED_VENDOR') !== '1') {
        throw new RuntimeException('Install POS dependencies or explicitly enable POS_SHARED_VENDOR=1 for local development.');
    }
    $vendor = dirname(__DIR__, 2).'/backend/vendor/autoload.php';
}
require_once $vendor;
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Pos\\')) {
        $path = dirname(__DIR__).'/app/'.str_replace('\\', '/', substr($class, 4)).'.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
});
