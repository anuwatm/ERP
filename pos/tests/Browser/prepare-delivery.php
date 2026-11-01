<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;

$database = require __DIR__.'/delivery-environment.php';
if (is_file(dirname($database).'/cleanup.json')) {
    throw new RuntimeException('Completed fixture is sealed. Prepare a fresh fixture directory.');
}
if (! is_file($database)) {
    touch($database);
}
require dirname(__DIR__, 2).'/bootstrap/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath(dirname($database));
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database || ! str_ends_with(str_replace('\\', '/', $database), '/temp/phase2881-delivery/database.sqlite')) {
    throw new RuntimeException('Isolated delivery fixture required.');
}
$app->make(Kernel::class)->call('migrate', ['--force' => true]);
$keyPath = dirname($database).'/synthetic-private.pem';
if (! is_file($keyPath)) {
    $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
    $path = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
    if (is_file($path)) {
        $options['config'] = $path;
    }
    $key = openssl_pkey_new($options);
    openssl_pkey_export($key, $pem, null, $options);
    file_put_contents($keyPath, $pem);
}
$branch = DB::table('pos_branches')->where('code', 'DTEST')->first();
if (! $branch) {
    $id = (string) Str::uuid();
    DB::table('pos_branches')->insert(['id' => $id, 'code' => 'DTEST', 'name' => 'Synthetic delivery browser', 'business_type' => 'retail', 'active_catalog_version' => 'delivery-v1']);
    $product = (string) Str::uuid();
    DB::table('pos_catalogs')->insert(['id' => (string) Str::uuid(), 'branch_id' => $id, 'version' => 'delivery-v1', 'payload_hash' => str_repeat('a', 64), 'valid_until' => now()->addDay(), 'items' => json_encode([['product_id' => $product, 'name' => 'Synthetic delivery item', 'unit' => 'piece', 'price' => '10.00', 'tax_rate' => '7.00', 'active' => true]])]);
    DB::table('pos_grants')->insert(['id' => (string) Str::uuid(), 'branch_id' => $id, 'product_id' => $product, 'epoch' => 1, 'granted_quantity' => '100.0000', 'payload_hash' => str_repeat('b', 64), 'status' => 'active']);
    Staff::create(['id' => (string) Str::uuid(), 'branch_id' => $id, 'name' => 'Synthetic delivery manager', 'email' => 'delivery-manager@fixture.test', 'password' => 'synthetic-delivery-password', 'role' => 'manager', 'active' => true]);
    foreach (['D1280', 'D375'] as $code) {
        DB::table('pos_terminals')->insert(['id' => (string) Str::uuid(), 'branch_id' => $id, 'code' => $code]);
    }
}
$manager = Staff::where('email', 'delivery-manager@fixture.test')->firstOrFail();
$manager->update(['active' => true, 'password' => 'synthetic-delivery-password']);
file_put_contents(dirname($database).'/identities.json', json_encode(['branch_id' => $manager->branch_id, 'staff_id' => $manager->id, 'terminals' => DB::table('pos_terminals')->where('branch_id', $manager->branch_id)->pluck('id', 'code')]));
echo "Synthetic delivery fixture ready\n";
