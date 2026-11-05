<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Services\CheckoutService;
use Pos\Services\OfflinePermitService;
use Pos\Services\ProvisioningService;

$database = require __DIR__.'/offline-environment.php';
if (is_file($database)) {
    throw new RuntimeException('Fresh isolated offline fixture required.');
}
touch($database);
require dirname(__DIR__, 2).'/bootstrap/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath(dirname($database));
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) {
    throw new RuntimeException('Isolated SQLite required');
}
$app->make(Kernel::class)->call('migrate', ['--force' => true]);
$options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
$configuration = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
if (is_file($configuration)) {
    $options['config'] = $configuration;
}
$key = openssl_pkey_new($options);
openssl_pkey_export($key, $pem, null, $options);
file_put_contents(dirname($database).'/synthetic-private.pem', $pem);
$ids = [];
foreach (['retail', 'pharmacy', 'restaurant'] as $type) {
    $branch = (string) Str::uuid();
    $terminal = (string) Str::uuid();
    $product = (string) Str::uuid();
    DB::table('pos_branches')->insert(['id' => $branch, 'code' => strtoupper($type), 'name' => 'Synthetic '.$type, 'business_type' => $type]);
    DB::table('pos_terminals')->insert(['id' => $terminal, 'branch_id' => $branch, 'code' => 'O1']);
    $staff = Staff::create(['id' => (string) Str::uuid(), 'branch_id' => $branch, 'name' => 'Synthetic manager', 'email' => $type.'@offline.fixture.test', 'password' => 'synthetic-offline-password', 'role' => 'manager', 'active' => true]);
    config(['pos.connector_branch_ids' => [$branch]]);
    app(ProvisioningService::class)->catalog('offline-v1', ['branch_id' => $branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [['product_id' => $product, 'name' => 'Synthetic ordinary item', 'unit' => 'piece', 'price' => '10.00', 'tax_rate' => '7.00', 'active' => true]]]);
    app(ProvisioningService::class)->grant((string) Str::uuid(), ['branch_id' => $branch, 'product_id' => $product, 'epoch' => 1, 'quantity' => '10.0000']);
    app(CheckoutService::class)->openShift($staff, $terminal, '0.00');
    app(OfflinePermitService::class)->configure($staff, ['expected_version' => 0, 'permit_minutes' => 1, 'max_offline_transactions' => 2, 'max_offline_amount' => '30.00', 'product_ids' => [$product]]);
    $ids[$type] = ['branch' => $branch, 'terminal' => $terminal, 'staff' => $staff->id, 'product' => $product];
}
file_put_contents(dirname($database).'/identities.json', json_encode($ids, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "Offline synthetic fixture ready\n";
