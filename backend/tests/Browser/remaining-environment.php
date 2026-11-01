<?php

$directory = dirname(__DIR__, 3).'/temp/remaining-20261029';
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$database = $directory.'/database.sqlite';
foreach (['APP_ENV' => 'acceptance', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('r', 32)), 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:8139', 'APP_CONFIG_CACHE' => $directory.'/unused-config.php', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'SESSION_DRIVER' => 'database', 'SESSION_COOKIE' => 'remaining_local_fixture', 'SESSION_DOMAIN' => 'null', 'SESSION_SECURE_COOKIE' => 'false', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'null', 'OPS_HEARTBEATS_ENABLED' => 'false'] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

return $database;
