<?php

// CLI-only, read-only local server probe. Never prints credentials or selects application data.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$path = dirname(__DIR__, 2).'/.env';
if (! file_exists($path)) {
    echo json_encode(['available' => false, 'reason' => 'not_provisioned']);
    exit;
}
$values = Dotenv\Dotenv::parse(file_get_contents($path));
$host = $values['DB_HOST'] ?? '';
if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || ! in_array($values['DB_CONNECTION'] ?? '', ['mysql', 'mariadb'], true) || ! empty($values['DB_URL'])) {
    echo json_encode(['available' => false, 'reason' => 'local_explicit_configuration_required']);
    exit;
}
try {
    $pdo = new PDO('mysql:host='.$host.';port='.($values['DB_PORT'] ?? '3306').';charset=utf8mb4', $values['DB_USERNAME'] ?? '', $values['DB_PASSWORD'] ?? '', [PDO::ATTR_TIMEOUT => 3, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $row = $pdo->query('SELECT VERSION() AS version')->fetch(PDO::FETCH_ASSOC);
    echo json_encode(['available' => true, 'server_version' => $row['version'], 'application_data_read' => false], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['available' => false, 'reason' => 'connection_unavailable', 'error_class' => get_class($e)]).PHP_EOL;
}
