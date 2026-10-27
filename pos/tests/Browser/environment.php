<?php

$fixture = dirname(__DIR__, 3).'/temp/phase28-pos-browser';
if (! is_dir($fixture)) {
    mkdir($fixture, 0777, true);
}
$values = ['APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('p', 32)), 'APP_URL' => 'http://127.0.0.1:8128', 'APP_CONFIG_CACHE' => $fixture.'/unused-config.php', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $fixture.'/database.sqlite', 'DB_URL' => '', 'CACHE_STORE' => 'file', 'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'phase28_browser_session', 'SESSION_SECURE_COOKIE' => 'false', 'POS_SHARED_VENDOR' => '1', 'POS_INSTANCE_ID' => '28000000-0000-4000-8000-000000000001', 'POS_SIGNING_KEY_ID' => 'browser-fixture-only', 'POS_SIGNING_KEY_PATH' => $fixture.'/signing.pem', 'POS_CONNECTOR_BRANCH_IDS' => '28000000-0000-4000-8000-000000000002', 'POS_LOCAL_PUBLIC_MENU_ENABLED' => 'true'];
$values['POS_LOCAL_REFUNDS_ENABLED'] = 'true';
$values['POS_LOCAL_CASH_MOVEMENTS_ENABLED'] = 'true';
$values['POS_LOCAL_DISCOUNTS_ENABLED'] = 'true';
$values['POS_LOCAL_RESTAURANT_ENABLED'] = 'true';
$values['POS_LOCAL_PHARMACY_ENABLED'] = 'true';
$values['POS_LOCAL_RETURN_INSPECTIONS_ENABLED'] = 'true';
$values['POS_LOCAL_TAX_DOCUMENTS_ENABLED'] = getenv('POS_BROWSER_TAX_FIXTURE') === '1' ? 'true' : 'false';
$values['POS_LOCAL_RECALLS_ENABLED'] = 'true';
foreach ($values as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}

return $fixture;
