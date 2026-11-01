<?php

$directory = dirname(__DIR__, 3).'/temp/phase2882-membership';
if (! is_file($directory.'/server-stopped.json') || ! json_decode(file_get_contents($directory.'/server-stopped.json'), true)['stopped']) {
    throw new RuntimeException('Stop the owned fixture server before cleanup.');
}
foreach (['database.sqlite', 'erp.sqlite'] as $name) {
    $path = realpath($directory.'/'.$name);
    if (! $path || dirname($path) !== realpath($directory)) {
        throw new RuntimeException('Isolated fixture database required.');
    }
    $pdo = new PDO('sqlite:'.$path);
    $pdo->exec($name === 'database.sqlite' ? 'update pos_staff set active = 0' : "update users set status = 'inactive'");
}
$removedSessions = 0;
foreach (glob($directory.'/sessions/*') as $path) {
    if (is_file($path) && dirname(realpath($path)) === realpath($directory.'/sessions')) {
        unlink($path);
        $removedSessions++;
    }
}
foreach (['synthetic-private.pem', 'enrollment-private.pem'] as $name) {
    $path = $directory.'/'.$name;
    if (is_file($path) && dirname(realpath($path)) === realpath($directory)) {
        unlink($path);
    }
}
$source = new PDO('sqlite:'.$directory.'/database.sqlite');
$erp = new PDO('sqlite:'.$directory.'/erp.sqlite');
$report = ['sealed' => true, 'server_stopped' => true, 'active_pos_staff' => (int) $source->query('select count(*) from pos_staff where active = 1')->fetchColumn(), 'active_erp_users' => (int) $erp->query("select count(*) from users where status = 'active'")->fetchColumn(), 'sessions_removed' => $removedSessions, 'private_keys_remaining' => count(glob($directory.'/*-private.pem')), 'member_and_audit_rows_deleted' => false, 'configured_databases_changed' => false];
if ($report['active_pos_staff'] || $report['active_erp_users'] || $report['private_keys_remaining']) {
    throw new RuntimeException('Fixture cleanup incomplete.');
}
file_put_contents($directory.'/cleanup.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_THROW_ON_ERROR).PHP_EOL;
