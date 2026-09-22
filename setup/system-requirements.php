<?php

declare(strict_types=1);

use Setup\Installer;

require_once __DIR__.'/../backend/setup/Installer.php';

$basePath = dirname(__DIR__).'/backend';
$installer = new Installer($basePath);

if ($installer->isInstalled()) {
    http_response_code(404);
    exit('Installer unavailable.');
}

if (! $installer->isEnabled()) {
    http_response_code(403);
    exit('Setup Wizard ถูกปิดอยู่: กำหนด SETUP_TOKEN หรือสร้าง backend/setup/enable.php ก่อนเปิดหน้านี้');
}

$authorized = false;
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authorized = $installer->verifyToken(trim((string) ($_POST['setup_token'] ?? '')));
    if (! $authorized) {
        $error = 'Setup token ไม่ถูกต้อง หรือยังไม่ได้เปิด installer.';
    }
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function memoryBytes(string $value): int
{
    $value = trim($value);
    if ($value === '-1') {
        return PHP_INT_MAX;
    }
    $unit = strtolower(substr($value, -1));
    $number = (int) $value;

    return match ($unit) {
        'g' => $number * 1024 * 1024 * 1024,
        'm' => $number * 1024 * 1024,
        'k' => $number * 1024,
        default => $number,
    };
}

function uploadBytes(string $value): int
{
    return memoryBytes($value);
}

$requiredExtensions = ['pdo_mysql', 'openssl', 'mbstring', 'fileinfo', 'json', 'dom', 'xml', 'curl'];
$optionalExtensions = ['zip', 'gd', 'imagick', 'redis'];
$checks = $authorized ? $installer->preflight() : [];
$runtimeChecks = $authorized ? [
    ['label' => 'Memory limit at least 128 MB', 'ok' => memoryBytes((string) ini_get('memory_limit')) >= 128 * 1024 * 1024, 'detail' => (string) ini_get('memory_limit'), 'required' => true],
    ['label' => 'Upload limit at least 10 MB', 'ok' => uploadBytes((string) ini_get('upload_max_filesize')) >= 10 * 1024 * 1024, 'detail' => (string) ini_get('upload_max_filesize'), 'required' => true],
    ['label' => 'POST limit at least 12 MB', 'ok' => uploadBytes((string) ini_get('post_max_size')) >= 12 * 1024 * 1024, 'detail' => (string) ini_get('post_max_size'), 'required' => true],
    ['label' => 'Execution time at least 60 seconds', 'ok' => (int) ini_get('max_execution_time') === 0 || (int) ini_get('max_execution_time') >= 60, 'detail' => (string) ini_get('max_execution_time').' seconds', 'required' => true],
    ['label' => 'HTTPS request', 'ok' => (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'), 'detail' => 'Required before production go-live', 'required' => false],
] : [];

$extensionChecks = $authorized ? array_map(
    fn (string $extension): array => ['label' => 'PHP extension: '.$extension, 'ok' => extension_loaded($extension), 'detail' => extension_loaded($extension) ? 'available' : 'missing', 'required' => true],
    $requiredExtensions
) : [];
$optionalChecks = $authorized ? array_map(
    fn (string $extension): array => ['label' => 'Optional extension: '.$extension, 'ok' => extension_loaded($extension), 'detail' => extension_loaded($extension) ? 'available' : 'not installed', 'required' => false],
    $optionalExtensions
) : [];

$allRequired = $authorized && ! array_filter(array_merge($checks, $runtimeChecks, $extensionChecks), fn (array $check): bool => ! $check['ok']);
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ERP System Requirements Check</title>
  <style>
    :root{font-family:system-ui,sans-serif;color:#172033;background:#f4f7fb}body{margin:0}.wrap{width:min(900px,calc(100% - 32px));margin:36px auto;background:#fff;padding:32px;border-radius:16px;box-shadow:0 12px 40px #10204018}h1{margin-top:0}.intro{color:#52627c}.card{margin-top:20px;border:1px solid #e0e7f0;border-radius:12px;overflow:hidden}.card h2{font-size:16px;margin:0;padding:13px 16px;background:#f8fafc}.row{display:flex;gap:16px;justify-content:space-between;padding:11px 16px;border-top:1px solid #e8edf4}.detail{color:#66758c;font-size:13px}.pass{color:#087b43;font-weight:700}.fail{color:#b42318;font-weight:700}.warn{color:#aa6500;font-weight:700}input{width:100%;box-sizing:border-box;padding:10px;border:1px solid #b9c4d5;border-radius:8px;font:inherit}button,a.button{display:inline-block;margin-top:14px;padding:10px 14px;border:0;border-radius:8px;background:#2463eb;color:#fff;text-decoration:none;font:inherit;font-weight:700}.error{padding:12px;background:#fff0ef;color:#b42318;border-radius:8px}.summary{padding:12px 16px;border-radius:8px;font-weight:700}.summary.pass{background:#e9f8ef}.summary.fail{background:#fff0ef}@media(max-width:600px){.row{display:block}.row strong{display:block;margin-top:5px}}
  </style>
</head>
<body><main class="wrap">
  <h1>ERP System Requirements Check</h1>
  <p class="intro">ตรวจความพร้อมของ server ก่อนติดตั้ง ERP โดยไม่แสดง password, token หรือ `phpinfo()`.</p>
  <?php if (! $authorized) { ?>
    <?php if ($error) { ?><p class="error"><?= esc($error) ?></p><?php } ?>
    <form method="post"><label for="setup_token">Setup token</label><input id="setup_token" name="setup_token" type="password" autocomplete="off" required><button>Check server</button></form>
    <p class="intro">ใช้ `SETUP_TOKEN` จาก web-server environment หรือ token ใน `backend/setup/enable.php`.</p>
  <?php } else { ?>
    <p class="summary <?= $allRequired ? 'pass' : 'fail' ?>"><?= $allRequired ? 'Server ผ่าน requirements ที่จำเป็น พร้อมเริ่ม Setup Wizard' : 'Server ยังไม่ผ่าน requirements ที่จำเป็น กรุณาแก้รายการ Failed' ?></p>
    <?php foreach (['Release package' => $checks, 'PHP runtime' => $runtimeChecks, 'Required extensions' => $extensionChecks, 'Optional improvements' => $optionalChecks] as $title => $group) { ?>
      <section class="card"><h2><?= esc($title) ?></h2><?php foreach ($group as $check) { ?><div class="row"><span><?= esc($check['label']) ?><small class="detail"> — <?= esc($check['detail']) ?></small></span><strong class="<?= $check['ok'] ? 'pass' : ($check['required'] ? 'fail' : 'warn') ?>"><?= $check['ok'] ? 'Passed' : ($check['required'] ? 'Failed' : 'Optional') ?></strong></div><?php } ?></section>
    <?php } ?>
    <p class="intro">ก่อน go-live ให้ตั้ง HTTPS, queue worker, scheduler และ backup policy. หน้า check นี้ตรวจ database credential ผ่าน Setup Wizard Step 2.</p>
    <a class="button" href="index.php">Open Setup Wizard</a>
  <?php } ?>
</main></body></html>
