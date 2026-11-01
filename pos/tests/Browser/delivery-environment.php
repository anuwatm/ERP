<?php

$fixture = dirname(__DIR__, 3).'/temp/phase2881-delivery';
if (! is_dir($fixture)) {
    mkdir($fixture, 0700, true);
}
$database = $fixture.'/database.sqlite';
foreach (['APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('d', 32)), 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:8143', 'APP_CONFIG_CACHE' => $fixture.'/unused-config.php', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'delivery_fixture_only', 'SESSION_DOMAIN' => 'null', 'SESSION_SECURE_COOKIE' => 'false', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'null', 'POS_SHARED_VENDOR' => '1', 'POS_LOCAL_DELIVERY_ENABLED' => 'true', 'POS_INSTANCE_ID' => '11111111-1111-4111-8111-111111111111', 'POS_SIGNING_KEY_ID' => 'delivery-fixture', 'POS_SIGNING_KEY_PATH' => $fixture.'/synthetic-private.pem'] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

return $database;
