<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Services\ProvisioningService;
use Pos\Services\TaxDocumentService;

$fixture = require __DIR__.'/environment.php'; require dirname(__DIR__, 2).'/bootstrap/autoload.php'; $app = require dirname(__DIR__, 2).'/bootstrap/app.php'; $app->useEnvironmentPath($fixture); $app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $fixture.'/database.sqlite' || !file_exists($fixture.'/signing.pem')) throw new RuntimeException('Synthetic browser fixture required; run prepare.php first.');
$branch = '28000000-0000-4000-8000-000000000032'; $terminal = '28000000-0000-4000-8000-000000000033'; $product = '28000000-0000-4000-8000-000000000034';
DB::table('pos_branches')->insertOrIgnore(['id' => $branch, 'code' => 'TAX-B', 'name' => 'ภาษีจำลอง', 'business_type' => 'retail']);
DB::table('pos_terminals')->insertOrIgnore(['id' => $terminal, 'branch_id' => $branch, 'code' => 'TAX-PC']);
Staff::firstOrCreate(['email' => 'tax@pos.test'], ['id' => '28000000-0000-4000-8000-000000000035', 'branch_id' => $branch, 'name' => 'พนักงานภาษีจำลอง', 'password' => 'local-fixture-password', 'role' => 'manager', 'active' => true]);
config(['pos.connector_branch_ids' => [$branch], 'pos.local_tax_documents_enabled' => true]);
app(TaxDocumentService::class)->provision(['branch_id' => $branch, 'version' => 'synthetic-issuer-v1', 'seller_name' => 'ผู้ขายจำลอง', 'seller_tax_id' => '0000000000000', 'seller_address' => 'ที่อยู่ทดสอบเท่านั้น', 'seller_branch_no' => '00000']);
app(ProvisioningService::class)->catalog('tax-browser-'.Str::uuid(), ['branch_id' => $branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [['product_id' => $product, 'name' => 'สินค้าภาษีจำลอง', 'unit' => 'ชิ้น', 'price' => '10.00', 'tax_rate' => '7.00', 'active' => true]]]);
app(ProvisioningService::class)->grant((string) Str::uuid(), ['branch_id' => $branch, 'product_id' => $product, 'epoch' => 1, 'quantity' => '20.0000']);
echo "Synthetic tax fixture prepared; no provider network or ERP database used.\n";
