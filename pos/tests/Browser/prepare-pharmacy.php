<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Services\CheckoutService;
use Pos\Services\ManagerApproval;
use Pos\Services\ProvisioningService;
use Pos\Services\RefundService;

$fixture = require __DIR__.'/environment.php';
require dirname(__DIR__, 2).'/bootstrap/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath($fixture);
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $fixture.'/database.sqlite' || ! file_exists($fixture.'/signing.pem')) {
    throw new RuntimeException('Run prepare.php first; synthetic fixture required.');
}
$branch = '28000000-0000-4000-8000-000000000022';
$terminal = '28000000-0000-4000-8000-000000000023';
$product = '28000000-0000-4000-8000-000000000024';
DB::table('pos_branches')->insertOrIgnore(['id' => $branch, 'code' => 'TEST-PHARM', 'name' => 'ร้านยาจำลอง', 'business_type' => 'pharmacy']);
DB::table('pos_terminals')->insertOrIgnore(['id' => $terminal, 'branch_id' => $branch, 'code' => 'PHARM-PC']);
$staff = Staff::firstOrCreate(['email' => 'pharmacy-manager@pos.test'], ['id' => '28000000-0000-4000-8000-000000000025', 'branch_id' => $branch, 'name' => 'ผู้จัดการเภสัชกรจำลอง', 'password' => 'local-fixture-password', 'role' => 'manager', 'active' => true]);
$staff->forceFill(['pharmacist_qualified' => true])->save();
config(['pos.connector_branch_ids' => [$branch]]);
$version = 'pharmacy-browser-'.Str::uuid();
app(ProvisioningService::class)->catalog($version, ['branch_id' => $branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [['product_id' => $product, 'name' => 'สินค้าร้านยาจำลอง', 'unit' => 'เม็ด', 'price' => '10.00', 'tax_rate' => '7.00', 'active' => true]]]);
app(ProvisioningService::class)->grant((string) Str::uuid(), ['branch_id' => $branch, 'product_id' => $product, 'lot_id' => (string) Str::uuid(), 'expires_at' => now()->addMonth()->toDateString(), 'epoch' => 1, 'quantity' => '2.0000']);
app(CheckoutService::class)->openShift($staff, $terminal, '100.00');
$sale = app(CheckoutService::class)->checkout($staff, ['terminal_id' => $terminal, 'request_key' => (string) Str::uuid(), 'catalog_version' => $version, 'items' => [['product_id' => $product, 'quantity' => '2.0000']], 'cash_tendered' => '30.00']);
$refunds = app(RefundService::class);
$command = $refunds->command(['order_id' => $sale['sale_id'], 'terminal_id' => $terminal, 'request_key' => (string) Str::uuid(), 'reason' => 'customer_return', 'items' => [['line_id' => $sale['lines'][0]['line_id'], 'quantity' => '2.0000', 'returned_quantity' => '2.0000']]]);
$refunds->refund($staff, $command, ManagerApproval::verify($staff, 'local-fixture-password', $command));
echo "Synthetic pharmacy quarantine ready. Existing evidence retained.\n";
