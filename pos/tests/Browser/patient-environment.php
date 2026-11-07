<?php

$run = getenv('POS_PATIENT_BROWSER_RUN') ?: 'r3';
if (! preg_match('/^[a-z0-9-]+$/D', $run)) {
    throw new RuntimeException('Invalid patient fixture run name.');
}
$fixture = dirname(__DIR__, 3).'/temp/phase2884-patients-'.$run;
if (! is_dir($fixture)) {
    mkdir($fixture, 0700, true);
}
if (is_file($fixture.'/cleanup.json')) {
    throw new RuntimeException('Completed offline fixture is sealed.');
}
$database = $fixture.'/database.sqlite';
$identities = is_file($fixture.'/identities.json') ? json_decode(file_get_contents($fixture.'/identities.json'), true) : [];
foreach (['APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('m', 32)), 'APP_DEBUG' => 'false', 'APP_URL' => 'http://127.0.0.1:8147', 'APP_CONFIG_CACHE' => $fixture.'/unused-config.php', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'patients_fixture_only', 'SESSION_DOMAIN' => 'null', 'SESSION_SECURE_COOKIE' => 'false', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'null', 'POS_SHARED_VENDOR' => '1', 'POS_LOCAL_BROWSER_OFFLINE_ENABLED' => 'true', 'POS_LOCAL_MEMBERSHIP_ENABLED' => 'false', 'POS_LOCAL_PHARMACY_ENABLED' => 'true', 'POS_LOCAL_PATIENT_HISTORY_ENABLED' => 'true', 'POS_LOCAL_RESTAURANT_ENABLED' => 'false', 'POS_INSTANCE_ID' => '44444444-4444-4444-8444-444444444444', 'POS_CONNECTOR_BRANCH_IDS' => implode(',', array_column($identities, 'branch')), 'POS_SIGNING_KEY_ID' => 'patients-fixture', 'POS_SIGNING_KEY_PATH' => $fixture.'/synthetic-private.pem'] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

return $database;
