<?php

// Run after stopping the fixture server and exporting evidence. Preserve all ledgers/holds.
$workspace = realpath(dirname(__DIR__, 3));
$temp = realpath($workspace.'/temp');
$results = [];
foreach (['phase2883-offline', 'phase2883-offline-r2', 'phase2883-offline-r3', 'phase2883-offline-r4', 'phase2883-offline-r5'] as $name) {
    $directory = realpath($temp.'/'.$name);
    if (! $directory) {
        continue;
    }
    if (! str_starts_with($directory, $temp.DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Fixture escaped workspace temp');
    }
    $database = realpath($directory.'/database.sqlite');
    if (! $database || dirname($database) !== $directory) {
        throw new RuntimeException('Unexpected fixture database');
    }
    $pdo = new PDO('sqlite:'.$database);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('UPDATE pos_staff SET active = 0');
    foreach (glob($directory.'/sessions/*') ?: [] as $session) {
        $resolved = realpath($session);
        if (! $resolved || dirname($resolved) !== $directory.DIRECTORY_SEPARATOR.'sessions' || ! is_file($resolved)) {
            throw new RuntimeException('Unexpected session path');
        }
        unlink($resolved);
    }
    $key = $directory.'/synthetic-private.pem';
    if (is_file($key)) {
        unlink($key);
    }
    $result = ['active_staff' => (int) $pdo->query('SELECT COUNT(*) FROM pos_staff WHERE active = 1')->fetchColumn(), 'private_key_removed' => ! is_file($key), 'sessions_remaining' => count(glob($directory.'/sessions/*') ?: []), 'inbox_and_audit_retained' => true, 'quota_not_automatically_released' => true];
    file_put_contents($directory.'/cleanup.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    $results[$name] = $result;
}
echo json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
