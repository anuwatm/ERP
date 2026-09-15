<?php

// Disposable browser fixture only. Never load the operational .env or config cache.
$fixtureDirectory = dirname(__DIR__, 3).'/temp/phase231-browser';
if (! is_dir($fixtureDirectory)) {
    mkdir($fixtureDirectory, 0777, true);
}
$fixtureDatabase = $fixtureDirectory.'/database.sqlite';
foreach ([
    'APP_ENV' => 'phase231',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('p', 32)),
    'APP_DEBUG' => 'true',
    'APP_URL' => 'http://127.0.0.1:8011',
    'APP_CONFIG_CACHE' => $fixtureDirectory.'/unused-config.php',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $fixtureDatabase,
    'DB_URL' => '',
    'SESSION_DRIVER' => 'database',
    'SESSION_COOKIE' => 'phase231_browser_session',
    'SESSION_DOMAIN' => 'null',
    'SESSION_SECURE_COOKIE' => 'false',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'log',
] as $name => $value) {
    putenv("{$name}={$value}");
    $_ENV[$name] = $_SERVER[$name] = $value;
}

return $fixtureDatabase;
