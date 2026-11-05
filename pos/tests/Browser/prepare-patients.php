<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Services\CheckoutService;
use Pos\Services\ProvisioningService;

$database = require __DIR__.'/patient-environment.php';
if (is_file($database)) {
    throw new RuntimeException('Fresh synthetic patient fixture required');
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
$pharmacy = null;
foreach (['pharmacist', 'cashier', 'manager', 'qualified', 'retail', 'restaurant', 'other'] as $label) {
    $type = in_array($label, ['retail', 'restaurant'], true) ? $label : 'pharmacy';
    if (in_array($label, ['cashier', 'manager', 'qualified'], true)) {
        $branch = $pharmacy;
    } else {
        $branch = (string) Str::uuid();
        DB::table('pos_branches')->insert(['id' => $branch, 'code' => strtoupper($label), 'name' => 'Synthetic '.$label, 'business_type' => $type]);
        if ($label === 'pharmacist') {
            $pharmacy = $branch;
        }
    }
    $terminal = (string) Str::uuid();
    DB::table('pos_terminals')->insert(['id' => $terminal, 'branch_id' => $branch, 'code' => strtoupper(substr($label, 0, 3))]);
    $role = in_array($label, ['pharmacist', 'other'], true) ? 'pharmacist' : ($label === 'cashier' ? 'cashier' : 'manager');
    $staff = Staff::create(['id' => (string) Str::uuid(), 'branch_id' => $branch, 'name' => 'Synthetic '.$label, 'email' => $label.'@patient.fixture.test', 'password' => 'synthetic-patient-password', 'role' => $role, 'active' => true]);
    if ($label === 'qualified') {
        DB::table('pos_staff')->where('id', $staff->id)->update(['pharmacist_qualified' => true]);
    }
    app(CheckoutService::class)->openShift($staff, $terminal, '0.00');
    if (! in_array($label, ['cashier', 'manager', 'qualified'], true)) {
        $product = (string) Str::uuid();
        config(['pos.connector_branch_ids' => [$branch]]);
        app(ProvisioningService::class)->catalog('patient-v1', ['branch_id' => $branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [['product_id' => $product, 'name' => 'Synthetic Patient Shop Item', 'unit' => 'piece', 'price' => '10.00', 'tax_rate' => '7.00', 'active' => true]]]);
        app(ProvisioningService::class)->grant((string) Str::uuid(), ['branch_id' => $branch, 'product_id' => $product, 'epoch' => 1, 'quantity' => '50.0000']);
    } else {
        $product = $ids['pharmacist']['product'];
    }
    $ids[$label] = ['branch' => $branch, 'terminal' => $terminal, 'staff' => $staff->id, 'product' => $product];
}
file_put_contents(dirname($database).'/identities.json', json_encode($ids, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "Patient fixture ready; no patient records seeded\n";
