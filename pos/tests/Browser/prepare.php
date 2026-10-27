<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Pos\Models\Staff;
use Pos\Services\ProvisioningService;

$fixture = require __DIR__.'/environment.php';
require dirname(__DIR__, 2).'/bootstrap/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath($fixture);
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $fixture.'/database.sqlite') {
    throw new RuntimeException('Refusing non-fixture database.');
}
if (! file_exists($fixture.'/database.sqlite')) {
    touch($fixture.'/database.sqlite');
}
Artisan::call('migrate', ['--force' => true]);
$prepareMenu = function (): void {
    $branch = '28000000-0000-4000-8000-000000000002';
    $product = '28000000-0000-4000-8000-000000000004';
    DB::table('pos_branches')->where('id', $branch)->firstOrFail();
    Staff::firstOrCreate(['email' => 'manager@pos.test'], ['id' => '28000000-0000-4000-8000-000000000007', 'branch_id' => $branch, 'name' => 'ผู้จัดการทดสอบ', 'password' => 'local-fixture-password', 'role' => 'manager', 'active' => true]);
    app(ProvisioningService::class)->catalog('browser-menu-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(3)), ['branch_id' => $branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [['product_id' => $product, 'name' => 'น้ำดื่มทดสอบ', 'barcode' => '8850000000001', 'unit' => 'ขวด', 'price' => '10.00', 'tax_rate' => '7.00', 'active' => true, 'online_visible' => true, 'max_manager_discount_pct' => '20.00']]]);
};
if (DB::table('pos_branches')->exists()) {
    $prepareMenu();
    echo "Existing local fixture retained.\n";
    exit;
}
$options = ['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048];
$opensslConfig = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
if (file_exists($opensslConfig)) {
    $options['config'] = $opensslConfig;
}
$key = openssl_pkey_new($options);
openssl_pkey_export($key, $pem, null, $options);
file_put_contents($fixture.'/signing.pem', $pem);
$branch = '28000000-0000-4000-8000-000000000002';
$terminal = '28000000-0000-4000-8000-000000000003';
$product = '28000000-0000-4000-8000-000000000004';
DB::table('pos_branches')->insert(['id' => $branch, 'code' => 'TEST', 'name' => 'ร้านค้าทดสอบ', 'business_type' => 'retail']);
DB::table('pos_terminals')->insert(['id' => $terminal, 'branch_id' => $branch, 'code' => 'PC1']);
Staff::create(['id' => '28000000-0000-4000-8000-000000000005', 'branch_id' => $branch, 'name' => 'พนักงานทดสอบ', 'email' => 'cashier@pos.test', 'password' => 'local-fixture-password', 'role' => 'cashier', 'active' => true]);
app(ProvisioningService::class)->catalog('browser-v1', ['branch_id' => $branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [['product_id' => $product, 'name' => 'น้ำดื่มทดสอบ', 'barcode' => '8850000000001', 'unit' => 'ขวด', 'price' => '10.00', 'tax_rate' => '7.00', 'active' => true]]]);
app(ProvisioningService::class)->grant('28000000-0000-4000-8000-000000000006', ['branch_id' => $branch, 'product_id' => $product, 'epoch' => 1, 'quantity' => '20.0000']);
$prepareMenu();
echo "Isolated POS browser fixture ready; no ERP database or external network used.\n";
