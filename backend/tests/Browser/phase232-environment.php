<?php

// Deliberately separate from both operational ERP and the Phase 23.1 fixture.
$directory = dirname(__DIR__, 3).'/temp/phase232-browser';
if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}
$database = $directory.'/database.sqlite';
foreach ([
    'APP_ENV' => 'phase232', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('d', 32)),
    'APP_DEBUG' => 'true', 'APP_URL' => 'http://127.0.0.1:8012',
    'APP_CONFIG_CACHE' => $directory.'/unused-config.php',
    'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '',
    'SESSION_DRIVER' => 'database', 'SESSION_COOKIE' => 'phase232_session',
    'SESSION_DOMAIN' => 'null', 'SESSION_SECURE_COOKIE' => 'false',
    'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'log',
] as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $_SERVER[$name] = $value;
}

return $database;
