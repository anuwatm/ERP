<?php

declare(strict_types=1);

namespace Setup;

function renderInstallerStatus(bool $installed): never
{
    http_response_code($installed ? 404 : 403);
    $title = $installed ? 'Installer unavailable' : 'Installer disabled';
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $title ?> · Company OS ERP</title>
    <style>
        :root { color-scheme: light dark; }
        body { box-sizing: border-box; display: grid; place-items: center; min-height: 100vh; margin: 0; padding: 1rem; font: 16px system-ui, sans-serif; background: light-dark(#f5f2ea, #101a18); color: light-dark(#112e28, #eaf4ef); }
        main { box-sizing: border-box; width: min(100%, 34rem); padding: clamp(1rem, 5vw, 2rem); border: 1px solid light-dark(#aebbb5, #657e73); border-radius: 1rem; background: light-dark(#fffdf8, #192a25); }
        h1 { margin: .25rem 0 .75rem; font-size: clamp(1.5rem, 5vw, 2rem); }
        p { line-height: 1.6; overflow-wrap: anywhere; }
        a { color: light-dark(#17614d, #a0d9bf); text-underline-offset: .2em; }
        a:focus-visible { outline: 2px solid currentColor; outline-offset: 4px; }
    </style>
</head>
<body>
    <main>
        <strong><?= $installed ? '404' : '403' ?></strong>
        <h1><?= $title ?></h1>
        <?php if ($installed) { ?>
            <p>Installation is locked.</p>
            <a href="../login">Go to login</a>
        <?php } else { ?>
            <p>Set a <code>SETUP_TOKEN</code> web-server environment variable, or create <code>backend/setup/enable.php</code> from <code>enable.php.example</code> with a random token, then open this page again.</p>
            <a href="system-requirements.php">Check system requirements</a>
        <?php } ?>
    </main>
</body>
</html>
    <?php
    exit;
}
