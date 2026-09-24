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

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
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

function remediationFor(array $check, bool $required): string
{
    $label = $check['label'];

    if (str_contains($label, 'PHP extension') || str_contains($label, 'extension')) {
        return 'เปิดใช้งาน extension นี้ใน php.ini แล้ว restart PHP-FPM หรือ web server.';
    }

    return match ($label) {
        'PHP 8.3 or newer' => 'อัปเกรด PHP เป็นเวอร์ชัน 8.3 หรือใหม่กว่า แล้วเลือก PHP version นี้ให้เว็บไซต์.',
        'Composer dependencies (vendor)' => 'deploy release ใหม่โดยรวมโฟลเดอร์ backend/vendor/ หรือรัน composer install --no-dev --optimize-autoloader ก่อน upload.',
        'Storage writable' => 'ตรวจสิทธิ์ของ web-server user ที่ storage/ หรือใช้ปุ่ม Fix หากโฟลเดอร์นี้ยังไม่มี.',
        'Cache writable' => 'ตรวจสิทธิ์ของ web-server user ที่ bootstrap/cache/ หรือใช้ปุ่ม Fix หากโฟลเดอร์นี้ยังไม่มี.',
        'Environment writable' => 'ให้ web-server user อ่าน .env.example และเขียนใน backend/ ได้.',
        'Clean release directory' => 'ใช้ release directory ใหม่ที่ไม่มี .env เพื่อป้องกันการเขียนทับระบบเดิม.',
        'Frontend build present' => 'deploy release ใหม่โดยรวม backend/public/build/ ที่สร้างจาก npm run build แล้ว.',
        'Memory limit at least 128 MB' => 'เพิ่ม memory_limit ใน php.ini เป็นอย่างน้อย 128M แล้ว restart PHP service.',
        'Upload limit at least 10 MB' => 'เพิ่ม upload_max_filesize ใน php.ini เป็นอย่างน้อย 10M แล้ว restart PHP service.',
        'POST limit at least 12 MB' => 'เพิ่ม post_max_size ใน php.ini เป็นอย่างน้อย 12M แล้ว restart PHP service.',
        'Execution time at least 60 seconds' => 'เพิ่ม max_execution_time ใน php.ini เป็นอย่างน้อย 60 วินาที แล้ว restart PHP service.',
        'HTTPS request' => 'ติดตั้ง SSL certificate และตั้ง reverse proxy ให้ส่ง header X-Forwarded-Proto: https.',
        default => $required ? 'แก้ไขการตั้งค่า server ให้ผ่าน requirement นี้ แล้วตรวจซ้ำ.' : 'เป็นส่วนเสริม แนะนำติดตั้งเมื่อ feature ที่เกี่ยวข้องถูกใช้งาน.',
    };
}

$repairTargets = [
    'Storage writable' => $basePath.'/storage',
    'Cache writable' => $basePath.'/bootstrap/cache',
];
$repairNotice = null;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'repair_directory') {
    $csrf = (string) ($_POST['_token'] ?? '');
    $targetName = (string) ($_POST['target'] ?? '');
    $targetPath = $repairTargets[$targetName] ?? null;

    if (! hash_equals((string) ($_SESSION['system_requirements_csrf'] ?? ''), $csrf) || $targetPath === null) {
        http_response_code(400);
        $repairNotice = ['type' => 'fail', 'text' => 'ไม่สามารถยืนยันคำขอแก้ไขได้ กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง.'];
    } elseif (is_dir($targetPath)) {
        $repairNotice = ['type' => 'warn', 'text' => 'โฟลเดอร์มีอยู่แล้ว โปรดแก้ไขสิทธิ์ของ web-server user บน server.'];
    } elseif (! is_writable(dirname($targetPath))) {
        $repairNotice = ['type' => 'fail', 'text' => 'web-server user ไม่มีสิทธิ์สร้างโฟลเดอร์นี้ โปรดแก้ไขสิทธิ์บน server.'];
    } elseif (@mkdir($targetPath, 0750, true) || is_dir($targetPath)) {
        $repairNotice = ['type' => 'pass', 'text' => 'สร้างโฟลเดอร์เรียบร้อยแล้ว กำลังตรวจ requirement อีกครั้ง.'];
    } else {
        $repairNotice = ['type' => 'fail', 'text' => 'สร้างโฟลเดอร์ไม่สำเร็จ โปรดแก้ไขสิทธิ์บน server.'];
    }
}

$_SESSION['system_requirements_csrf'] ??= bin2hex(random_bytes(32));
$csrfToken = $_SESSION['system_requirements_csrf'];

$optionalExtensions = ['zip', 'gd', 'imagick', 'redis'];
$checks = $installer->preflight();
$runtimeChecks = [
    ['label' => 'Memory limit at least 128 MB', 'ok' => memoryBytes((string) ini_get('memory_limit')) >= 128 * 1024 * 1024, 'detail' => (string) ini_get('memory_limit'), 'required' => true],
    ['label' => 'Upload limit at least 10 MB', 'ok' => uploadBytes((string) ini_get('upload_max_filesize')) >= 10 * 1024 * 1024, 'detail' => (string) ini_get('upload_max_filesize'), 'required' => true],
    ['label' => 'POST limit at least 12 MB', 'ok' => uploadBytes((string) ini_get('post_max_size')) >= 12 * 1024 * 1024, 'detail' => (string) ini_get('post_max_size'), 'required' => true],
    ['label' => 'Execution time at least 60 seconds', 'ok' => (int) ini_get('max_execution_time') === 0 || (int) ini_get('max_execution_time') >= 60, 'detail' => (string) ini_get('max_execution_time').' seconds', 'required' => true],
    ['label' => 'HTTPS request', 'ok' => (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'), 'detail' => 'Required before production go-live', 'required' => false],
];

$optionalChecks = array_map(
    fn (string $extension): array => ['label' => 'Optional extension: '.$extension, 'ok' => extension_loaded($extension), 'detail' => extension_loaded($extension) ? 'available' : 'not installed', 'required' => false],
    $optionalExtensions
);

$requiredChecks = array_merge($checks, $runtimeChecks);
$allRequired = ! array_filter($requiredChecks, fn (array $check): bool => ! $check['ok']);
$passedRequired = count(array_filter($requiredChecks, fn (array $check): bool => $check['ok']));
$totalRequired = count($requiredChecks);
$progress = $totalRequired > 0 ? (int) round(($passedRequired / $totalRequired) * 100) : 0;
$groups = [
    ['title' => 'Core requirements', 'detail' => 'PHP, extensions และไฟล์ที่ระบบต้องใช้', 'checks' => $checks],
    ['title' => 'PHP runtime', 'detail' => 'ขีดจำกัดและการตั้งค่า PHP', 'checks' => $runtimeChecks],
    ['title' => 'Optional improvements', 'detail' => 'ส่วนเสริมที่แนะนำ', 'checks' => $optionalChecks],
];
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>ERP System Requirements Check</title>
  <style>
    :root{color-scheme:light;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;color:#10213f;background:#edf2f9;--ink:#10213f;--muted:#53657e;--line:#dce4ef;--surface:#fff;--navy:#142d57;--blue:#2267db;--success:#087a4a;--success-soft:#e8f8ef;--danger:#bd2c2c;--danger-soft:#fff0f0;--warning:#a35e00;--warning-soft:#fff6e4}*{box-sizing:border-box}body{min-width:320px;margin:0;background:radial-gradient(circle at 14% 0%,#dbeafe 0,transparent 31rem),#edf2f9}.shell{width:min(1040px,calc(100% - 32px));margin:clamp(24px,5vw,64px) auto}.masthead{position:relative;overflow:hidden;padding:clamp(28px,5vw,52px);border-radius:16px 16px 0 0;background:var(--navy);color:#fff}.masthead:after{position:absolute;right:-4rem;bottom:-5rem;width:18rem;height:18rem;border:1px solid #8ab1ef55;border-radius:50%;content:""}.masthead:before{position:absolute;right:5rem;top:-9rem;width:13rem;height:13rem;border:1px solid #8ab1ef33;border-radius:50%;content:""}.eyebrow{position:relative;margin:0 0 12px;color:#bad3ff;font-size:.75rem;font-weight:750;letter-spacing:.08em;text-transform:uppercase}.masthead h1{position:relative;max-width:18ch;margin:0;font-size:clamp(2rem,4vw,3.25rem);letter-spacing:-.035em;line-height:1.04;text-wrap:balance}.masthead p{position:relative;max-width:58ch;margin:16px 0 0;color:#d6e4fb;line-height:1.65;text-wrap:pretty}.report{overflow:hidden;border-radius:0 0 16px 16px;background:var(--surface);box-shadow:0 7px 8px #1c355114}.overview{display:flex;align-items:center;gap:24px;padding:28px clamp(24px,5vw,52px);border-bottom:1px solid var(--line)}.state-icon{display:grid;position:relative;flex:0 0 auto;width:54px;height:54px;place-items:center;border:4px solid currentColor;border-radius:50%;font-size:1.45rem;font-weight:800}.state-icon.pass{color:var(--success);background:var(--success-soft)}.state-icon.fail{color:var(--danger);background:var(--danger-soft)}.overview-copy{min-width:0}.overview h2{margin:0;color:var(--ink);font-size:1.15rem;letter-spacing:-.01em}.overview p{margin:5px 0 0;color:var(--muted);line-height:1.55}.progress{margin-left:auto;min-width:140px;text-align:right}.progress-label{display:block;color:var(--muted);font-size:.8rem}.progress-bar{width:140px;height:7px;margin-top:8px;overflow:hidden;border-radius:99px;background:#dce6f3}.progress-bar span{display:block;width:<?= $progress ?>%;height:100%;border-radius:inherit;background:<?= $allRequired ? '#087a4a' : '#bd2c2c' ?>;transform-origin:left;transition:transform .55s cubic-bezier(.16,1,.3,1)}body[data-ready] .progress-bar span{animation:grow .65s cubic-bezier(.16,1,.3,1) both}.group{padding:0 clamp(24px,5vw,52px)}.group+.group{border-top:1px solid var(--line)}.group-head{display:flex;align-items:baseline;justify-content:space-between;gap:16px;padding:28px 0 12px}.group h3{margin:0;font-size:1rem;letter-spacing:-.01em}.group-head p{margin:0;color:var(--muted);font-size:.86rem}.check-list{padding-bottom:20px}.check-row{display:grid;grid-template-columns:28px minmax(0,1fr) auto;align-items:center;gap:12px;padding:12px 0;transition:background-color .18s ease}.check-row+.check-row{border-top:1px solid #edf1f6}.check-mark{display:grid;width:22px;height:22px;place-items:center;border-radius:50%;font-size:.78rem;font-weight:900}.check-mark.pass{color:#fff;background:var(--success)}.check-mark.fail{color:#fff;background:var(--danger)}.check-mark.warn{color:#724300;background:#ffe2a5}.check-name{font-size:.94rem;font-weight:650}.check-detail{margin:3px 0 0;color:var(--muted);font-size:.8rem;line-height:1.35}.status{font-size:.78rem;font-weight:750}.status.pass{color:var(--success)}.status.fail{color:var(--danger)}.status.warn{color:var(--warning)}.advice{grid-column:2/-1;margin:2px 0 2px;padding:10px 12px;border-radius:8px;background:var(--danger-soft);color:#7f1d1d;font-size:.82rem;line-height:1.5}.advice.warn{background:var(--warning-soft);color:#714500}.fix-form{grid-column:2/-1;margin:0}.fix-button{min-height:34px;padding:7px 11px;border:1px solid #b7c9e9;border-radius:7px;background:#fff;color:#164b9f;font:inherit;font-size:.8rem;font-weight:750;cursor:pointer;transition:background-color .18s ease,border-color .18s ease}.fix-button:hover{border-color:#2267db;background:#edf4ff}.fix-button:focus-visible{outline:3px solid #8bb5ff;outline-offset:2px}.notice{margin:20px clamp(24px,5vw,52px) 0;padding:12px 14px;border-radius:8px;font-size:.88rem;font-weight:650;line-height:1.5}.notice.pass{background:var(--success-soft);color:#075f39}.notice.fail{background:var(--danger-soft);color:#8f1f1f}.notice.warn{background:var(--warning-soft);color:#714500}.footnote{margin:0;padding:22px clamp(24px,5vw,52px);background:#f7f9fc;color:var(--muted);font-size:.84rem;line-height:1.6}.scan-line{position:absolute;right:clamp(28px,5vw,52px);top:24px;width:56px;height:2px;overflow:hidden;background:#ffffff33}.scan-line:after{position:absolute;width:26px;height:2px;background:#a8c7ff;content:"";animation:scan 1.6s cubic-bezier(.65,0,.35,1) infinite}@keyframes scan{from{transform:translateX(-30px)}to{transform:translateX(60px)}}@keyframes grow{from{transform:scaleX(0)}to{transform:scaleX(1)}}body[data-ready] .check-row{animation:row-in .38s cubic-bezier(.16,1,.3,1) both;animation-delay:calc(var(--item) * 35ms)}body[data-ready] .check-mark{animation:mark-in .34s cubic-bezier(.16,1,.3,1) both;animation-delay:calc(90ms + var(--item) * 35ms)}body[data-ready] .state-icon{animation:state-in .52s cubic-bezier(.16,1,.3,1) both}body[data-ready] .state-icon:after{position:absolute;inset:-9px;border:1px solid currentColor;border-radius:50%;content:"";animation:signal 1.1s cubic-bezier(.16,1,.3,1) .28s both}.state-icon:after{content:none}@keyframes row-in{from{opacity:.45;transform:translateY(5px)}to{opacity:1;transform:translateY(0)}}@keyframes mark-in{from{opacity:0;transform:scale(.35) rotate(-24deg)}to{opacity:1;transform:scale(1) rotate(0)}}@keyframes state-in{from{opacity:.2;transform:scale(.72)}to{opacity:1;transform:scale(1)}}@keyframes signal{from{opacity:.7;transform:scale(.78)}to{opacity:0;transform:scale(1.4)}}@media (hover:hover){.check-row:hover{background:#f7faff}}@media (prefers-reduced-motion:reduce){*,*:before,*:after{animation-duration:.01ms!important;animation-iteration-count:1!important;scroll-behavior:auto!important;transition-duration:.01ms!important}}@media(max-width:640px){.shell{width:min(100% - 20px,1040px);margin:10px auto}.masthead{border-radius:12px 12px 0 0}.report{border-radius:0 0 12px 12px}.overview{align-items:flex-start}.progress{display:none}.group-head{display:block}.group-head p{margin-top:5px}.check-row{grid-template-columns:25px minmax(0,1fr)}.status{grid-column:2}.advice,.fix-form{grid-column:2}.masthead h1{font-size:2rem}}
  </style>
</head>
<body>
<main class="shell">
  <header class="masthead">
    <span class="scan-line" aria-hidden="true"></span>
    <p class="eyebrow">ERP · Server Readiness</p>
    <h1>System Requirements Check</h1>
    <p>ตรวจความพร้อมของ server สำหรับ ERP โดยแสดงเฉพาะข้อมูลที่จำเป็นต่อการติดตั้ง และไม่เปิดเผย password, token หรือ <code>phpinfo()</code></p>
  </header>
  <article class="report" aria-live="polite">
    <?php if ($repairNotice !== null) { ?>
      <p class="notice <?= esc($repairNotice['type']) ?>"><?= esc($repairNotice['text']) ?></p>
    <?php } ?>
    <section class="overview">
      <div class="state-icon <?= $allRequired ? 'pass' : 'fail' ?>" aria-hidden="true"><?= $allRequired ? '✓' : '!' ?></div>
      <div class="overview-copy">
        <h2><?= $allRequired ? 'Server พร้อมสำหรับการติดตั้ง' : 'พบรายการที่ต้องแก้ไข' ?></h2>
        <p><?= $allRequired ? 'ผ่านครบทุก requirement ที่จำเป็นแล้ว' : 'แก้ไขรายการ Failed ก่อนเริ่มติดตั้งระบบ' ?></p>
      </div>
      <div class="progress" aria-label="ผ่าน <?= $passedRequired ?> จาก <?= $totalRequired ?> รายการ">
        <span class="progress-label">ผ่าน <?= $passedRequired ?>/<?= $totalRequired ?> รายการ</span>
        <div class="progress-bar"><span></span></div>
      </div>
    </section>
    <?php $item = 0;
foreach ($groups as $group) { ?>
      <section class="group">
        <div class="group-head"><h3><?= esc($group['title']) ?></h3><p><?= esc($group['detail']) ?></p></div>
        <div class="check-list">
          <?php foreach ($group['checks'] as $check) {
              $required = $check['required'] ?? true;
              $state = $check['ok'] ? 'pass' : ($required ? 'fail' : 'warn');
              $repairTarget = $repairTargets[$check['label']] ?? null;
              $canRepair = $repairTarget !== null && ! is_dir($repairTarget) && is_writable(dirname($repairTarget)); ?>
            <div class="check-row" style="--item: <?= $item++ ?>">
              <span class="check-mark <?= $state ?>" aria-hidden="true"><?= $check['ok'] ? '✓' : ($required ? '!' : 'i') ?></span>
              <div><div class="check-name"><?= esc($check['label']) ?></div><p class="check-detail"><?= esc($check['detail']) ?></p></div>
              <strong class="status <?= $state ?>"><?= $check['ok'] ? 'Passed' : ($required ? 'Failed' : 'Optional') ?></strong>
              <?php if (! $check['ok']) { ?>
                <p class="advice <?= $state ?>"><?= esc(remediationFor($check, $required)) ?></p>
                <?php if ($canRepair) { ?>
                  <form class="fix-form" method="post">
                    <input type="hidden" name="action" value="repair_directory">
                    <input type="hidden" name="_token" value="<?= esc($csrfToken) ?>">
                    <input type="hidden" name="target" value="<?= esc($check['label']) ?>">
                    <button class="fix-button" type="submit">Fix: สร้างโฟลเดอร์ที่ขาด</button>
                  </form>
                <?php } ?>
              <?php } ?>
            </div>
          <?php } ?>
        </div>
      </section>
    <?php } ?>
    <p class="footnote">ก่อนเปิดใช้งานจริง ให้ตั้งค่า HTTPS, queue worker, scheduler และ backup policy.</p>
  </article>
</main>
<script>requestAnimationFrame(() => requestAnimationFrame(() => document.body.dataset.ready = 'true'));</script>
</body>
</html>
