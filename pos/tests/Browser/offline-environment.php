<?php

$run = getenv('POS_OFFLINE_BROWSER_RUN') ?: 'r5';
if (! preg_match('/^[a-z0-9-]+$/D', $run)) {
    throw new RuntimeException('Invalid offline fixture run name.');
}
$fixture = dirname(__DIR__, 3).'/temp/phase2883-offline-'.$run;
if (! is_dir($fixture)) {
    mkdir($fixture, 0700, true);
}
if (is_file($fixture.'/cleanup.json')) {
    throw new RuntimeException('Completed offline fixture is sealed.');
}
$database = $fixture.'/database.sqlite';
$identities = is_file($fixture.'/identities.json') ? json_decode(file_get_contents($fixture.'/identities.json'), true) : [];
foreach (['APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('m', 32)), 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:8146', 'APP_CONFIG_CACHE' => $fixture.'/unused-config.php', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'offline_fixture_only', 'SESSION_DOMAIN' => 'null', 'SESSION_SECURE_COOKIE' => 'false', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'null', 'POS_SHARED_VENDOR' => '1', 'POS_LOCAL_BROWSER_OFFLINE_ENABLED' => 'true', 'POS_LOCAL_MEMBERSHIP_ENABLED' => 'true', 'POS_LOCAL_PHARMACY_ENABLED' => 'true', 'POS_LOCAL_RESTAURANT_ENABLED' => 'true', 'POS_INSTANCE_ID' => '33333333-3333-4333-8333-333333333333', 'POS_CONNECTOR_BRANCH_IDS' => implode(',', array_column($identities, 'branch')), 'POS_SIGNING_KEY_ID' => 'offline-fixture', 'POS_SIGNING_KEY_PATH' => $fixture.'/synthetic-private.pem'] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

return $database;
