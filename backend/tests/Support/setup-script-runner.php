<?php

declare(strict_types=1);

$payload = json_decode(file_get_contents('php://stdin'), true, flags: JSON_THROW_ON_ERROR);
$resultFile = $argv[1];

if (isset($payload['env']) && is_array($payload['env'])) {
    foreach ($payload['env'] as $k => $v) {
        if ($v === null) {
            putenv((string) $k);
            unset($_ENV[$k], $_SERVER[$k]);
        } else {
            putenv("{$k}={$v}");
            $_ENV[$k] = (string) $v;
            $_SERVER[$k] = (string) $v;
        }
    }
}

if (isset($payload['server']) && is_array($payload['server'])) {
    foreach ($payload['server'] as $k => $v) {
        $_SERVER[$k] = (string) $v;
    }
}

$_GET = $payload['get'] ?? [];
$_POST = $payload['post'] ?? [];
$_COOKIE = $payload['cookies'] ?? [];

$sessionDir = $payload['session_dir'] ?? sys_get_temp_dir();
if (is_dir($sessionDir)) {
    session_save_path($sessionDir);
}

if (! empty($payload['session'])) {
    $sessName = $payload['session_name'] ?? 'erp_setup';
    session_name($sessName);
    if (! empty($payload['session_id'])) {
        session_id((string) $payload['session_id']);
    }
    session_start();
    foreach ($payload['session'] as $k => $v) {
        $_SESSION[$k] = $v;
    }
    $sid = session_id();
    session_write_close();
    $_COOKIE[$sessName] = $sid;
}

register_shutdown_function(function () use ($resultFile) {
    $output = ob_get_clean();
    $code = http_response_code() ?: 200;
    $headers = headers_list();
    $sess = session_status() === PHP_SESSION_ACTIVE ? $_SESSION : [];
    file_put_contents($resultFile, json_encode([
        'status' => $code,
        'output' => $output,
        'headers' => $headers,
        'session' => $sess,
        'session_id' => session_id(),
    ], flags: JSON_THROW_ON_ERROR));
});

ob_start();
require $payload['script'];
