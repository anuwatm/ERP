<?php

declare(strict_types=1);

use Setup\Installer;

require_once __DIR__.'/../../setup/Installer.php';

session_name('erp_setup');
session_set_cookie_params([
    'httponly' => true,
    'secure' => (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Strict',
    'path' => rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/setup/index.php'), '/') ?: '/',
]);
session_start();

$installer = new Installer(dirname(__DIR__, 2));
$errors = [];
$notice = null;
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function posted(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}
function csrfValid(): bool
{
    return isset($_POST['_token'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['_token']);
}
function redirectSetup(int $step): never
{
    header('Location: ?step='.$step);
    exit;
}

if ($installer->isInstalled()) {
    http_response_code(404);
    echo '<!doctype html><title>Installer unavailable</title><p>Installation is locked. <a href="../login">Go to login</a>.</p>';
    exit;
}

if (! $installer->isEnabled()) {
    http_response_code(403);
    echo '<!doctype html><title>Installer disabled</title><p>Installer is disabled. Set a <code>SETUP_TOKEN</code> web-server environment variable, or create <code>backend/setup/enable.php</code> from <code>enable.php.example</code> with a random token, then open this page again.</p>';
    exit;
}

$step = max(1, min(4, (int) ($_GET['step'] ?? 1)));
$config = $_SESSION['config'] ?? [];
$owner = $_SESSION['owner'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (! csrfValid()) {
        $errors[] = 'The setup session expired. Refresh the page and try again.';
    } else {
        $action = posted('action');
        if ($action === 'authorize') {
            if ($installer->verifyToken((string) ($_POST['setup_token'] ?? ''))) {
                $_SESSION['authorized'] = true;
                redirectSetup(2);
            }
            $errors[] = 'Invalid setup token.';
        }

        if ($action === 'save_config' && ($_SESSION['authorized'] ?? false)) {
            $config = [
                'app_name' => posted('app_name'),
                'app_url' => rtrim(posted('app_url'), '/'),
                'db_host' => posted('db_host'),
                'db_port' => posted('db_port'),
                'db_database' => posted('db_database'),
                'db_username' => posted('db_username'),
                'db_password' => (string) ($_POST['db_password'] ?? ''),
            ];
            foreach (['app_name', 'app_url', 'db_host', 'db_port', 'db_database', 'db_username'] as $field) {
                if ($config[$field] === '') {
                    $errors[] = 'Complete all required application and database fields.';
                }
            }
            if (filter_var($config['app_url'], FILTER_VALIDATE_URL) === false || ! preg_match('#^https?://#i', $config['app_url'])) {
                $errors[] = 'Application URL must begin with http:// or https://.';
            }
            if (! ctype_digit($config['db_port']) || (int) $config['db_port'] < 1 || (int) $config['db_port'] > 65535) {
                $errors[] = 'Database port is invalid.';
            }
            if (! $errors) {
                try {
                    $installer->testDatabase($config);
                    $_SESSION['config'] = $config;
                    redirectSetup(3);
                } catch (Throwable) {
                    $errors[] = 'Unable to connect to the database. Verify the host, port, database, and account.';
                }
            }
        }

        if ($action === 'save_owner' && ($_SESSION['authorized'] ?? false)) {
            $owner = [
                'organization_name' => posted('organization_name'),
                'legal_name' => posted('legal_name'),
                'tax_id' => posted('tax_id'),
                'name' => posted('name'),
                'email' => strtolower(posted('email')),
                'password' => (string) ($_POST['password'] ?? ''),
            ];
            if ($owner['organization_name'] === '' || $owner['name'] === '' || ! filter_var($owner['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Enter organization name, owner name, and a valid email.';
            }
            if (strlen($owner['password']) < 12) {
                $errors[] = 'Owner password must contain at least 12 characters.';
            }
            if ($owner['password'] !== (string) ($_POST['password_confirmation'] ?? '')) {
                $errors[] = 'Owner password confirmation does not match.';
            }
            if (! $errors) {
                $_SESSION['owner'] = $owner;
                redirectSetup(4);
            }
        }

        if ($action === 'install' && ($_SESSION['authorized'] ?? false)) {
            if (! $config || ! $owner || ($_POST['confirm_install'] ?? '') !== 'INSTALL') {
                $errors[] = 'Type INSTALL to confirm the production installation.';
            } else {
                $checks = $installer->preflight();
                if (array_filter($checks, fn (array $check): bool => ! $check['ok'])) {
                    $errors[] = 'Fix all preflight failures before installing.';
                } else {
                    try {
                        $installer->install($config, $owner, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');
                        session_regenerate_id(true);
                        $_SESSION = [];
                        header('Location: ../login?installed=1');
                        exit;
                    } catch (Throwable $exception) {
                        $errors[] = $exception->getMessage();
                    }
                }
            }
        }
    }
}

$checks = $installer->preflight();
$preflightOk = ! array_filter($checks, fn (array $check): bool => ! $check['ok']);
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ERP Setup Wizard</title>
  <style>
    :root { color-scheme: light dark; font-family: system-ui, sans-serif; } body { margin: 0; background: #f4f7fb; color: #172033; } main { width: min(760px, calc(100% - 32px)); margin: 36px auto; background: white; border-radius: 16px; padding: 32px; box-shadow: 0 12px 40px #10204018; } h1 { margin-top: 0; } .steps { display: flex; gap: 8px; margin: 20px 0 28px; } .step { flex: 1; padding: 8px; text-align: center; border-radius: 8px; background: #e7edf7; color: #52627c; font-size: 13px; } .step.active { background: #2463eb; color: white; font-weight: 700; } label { display:block; font-weight: 650; margin: 14px 0 5px; } input { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #b9c4d5; border-radius: 8px; font: inherit; } .grid { display:grid; grid-template-columns: 1fr 1fr; gap: 0 16px; } button { margin-top: 22px; padding: 11px 16px; border: 0; border-radius: 8px; background: #2463eb; color: white; font: inherit; font-weight: 700; cursor:pointer; } button:disabled { background:#9aa8c0; cursor:not-allowed; } .check { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #e5eaf2; } .ok { color:#087b43; } .bad,.errors { color:#b42318; } .errors { background:#fff0ef; padding:12px 16px; border-radius:8px; } .hint { color:#61708a; font-size:14px; line-height:1.5; } code { background:#edf1f7; padding:2px 5px; border-radius:4px; } @media (prefers-color-scheme: dark) { body { background:#101622; color:#e6edf8; } main { background:#182131; } input { background:#111827; color:#fff; border-color:#3a475d; } .step { background:#253247; color:#b7c4d9; } .check { border-color:#2d3a50; } .hint { color:#b3c0d5; } code { background:#263449; } .errors { background:#4a2020; } }
  </style>
</head>
<body><main>
  <h1>ERP Setup Wizard</h1>
  <p class="hint">สำหรับติดตั้ง instance ใหม่เท่านั้น ไม่เขียนทับ database ที่มี migrations อยู่แล้ว</p>
  <div class="steps"><?php foreach ([1 => 'Security', 2 => 'Server & DB', 3 => 'Organization', 4 => 'Install'] as $number => $label) { ?><div class="step <?= $step === $number ? 'active' : '' ?>"><?= $number ?>. <?= e($label) ?></div><?php } ?></div>
  <?php if ($errors) { ?><div class="errors"><ul><?php foreach ($errors as $error) { ?><li><?= e($error) ?></li><?php } ?></ul></div><?php } ?>

  <?php if ($step === 1) { ?>
    <h2>1. Activate and check server</h2>
    <p class="hint">ต้องการดูรายงาน requirement แบบละเอียด? <a href="system-requirements.php">เปิด System Requirements Check</a></p>
    <?php foreach ($checks as $check) { ?><div class="check"><span><?= e($check['label']) ?><small class="hint"> — <?= e($check['detail']) ?></small></span><strong class="<?= $check['ok'] ? 'ok' : 'bad' ?>"><?= $check['ok'] ? 'Passed' : 'Failed' ?></strong></div><?php } ?>
    <form method="post"><input type="hidden" name="_token" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="authorize"><label>Setup token</label><input name="setup_token" type="password" autocomplete="off" required><p class="hint">Token is read from the <code>SETUP_TOKEN</code> web-server environment variable or <code>backend/setup/enable.php</code>; it is never stored in the session.</p><button <?= $preflightOk ? '' : 'disabled' ?>>Continue</button></form>
  <?php } elseif ($step === 2 && ($_SESSION['authorized'] ?? false)) { ?>
    <h2>2. Application and database</h2><form method="post"><input type="hidden" name="_token" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="save_config"><div class="grid"><div><label>Application name</label><input name="app_name" value="<?= e($config['app_name'] ?? 'ERP') ?>" required></div><div><label>Application URL</label><input name="app_url" type="url" value="<?= e($config['app_url'] ?? '') ?>" placeholder="https://erp.example.com" required></div><div><label>Database host</label><input name="db_host" value="<?= e($config['db_host'] ?? '127.0.0.1') ?>" required></div><div><label>Database port</label><input name="db_port" value="<?= e($config['db_port'] ?? '3306') ?>" required></div><div><label>Database name</label><input name="db_database" value="<?= e($config['db_database'] ?? '') ?>" required></div><div><label>Database username</label><input name="db_username" value="<?= e($config['db_username'] ?? '') ?>" required></div></div><label>Database password</label><input name="db_password" type="password" autocomplete="new-password"><button>Test connection and continue</button></form>
  <?php } elseif ($step === 3 && ($_SESSION['authorized'] ?? false) && $config) { ?>
    <h2>3. Organization and Owner</h2><form method="post"><input type="hidden" name="_token" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="save_owner"><div class="grid"><div><label>Organization name</label><input name="organization_name" value="<?= e($owner['organization_name'] ?? '') ?>" required></div><div><label>Legal name</label><input name="legal_name" value="<?= e($owner['legal_name'] ?? '') ?>"></div><div><label>Tax ID</label><input name="tax_id" value="<?= e($owner['tax_id'] ?? '') ?>"></div><div><label>Owner name</label><input name="name" value="<?= e($owner['name'] ?? '') ?>" required></div><div><label>Owner email</label><input name="email" type="email" value="<?= e($owner['email'] ?? '') ?>" required></div></div><label>Owner password</label><input name="password" type="password" autocomplete="new-password" required><label>Confirm password</label><input name="password_confirmation" type="password" autocomplete="new-password" required><button>Review installation</button></form>
  <?php } elseif ($step === 4 && ($_SESSION['authorized'] ?? false) && $config && $owner) { ?>
    <h2>4. Install</h2><p class="hint">Database: <code><?= e($config['db_host']) ?>/<?= e($config['db_database']) ?></code><br>Owner: <code><?= e($owner['email']) ?></code><br>This writes <code>.env</code>, migrates an empty database, seeds permissions, and creates the Owner. Demo data is never added.</p><form method="post"><input type="hidden" name="_token" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="install"><label>Type INSTALL to confirm</label><input name="confirm_install" autocomplete="off" required><button>Install ERP</button></form>
  <?php } else {
      redirectSetup(1);
  } ?>
</main></body></html>
