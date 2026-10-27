<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Services\ProvisioningService;
use Pos\Support\CanonicalJson;

$fixture = require __DIR__.'/environment.php';
require dirname(__DIR__, 2).'/bootstrap/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath($fixture);
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $fixture.'/database.sqlite' || ! file_exists($fixture.'/signing.pem')) {
    throw new RuntimeException('Run prepare.php first; only the synthetic browser database is supported.');
}
$branch = '28000000-0000-4000-8000-000000000012';
$menu = '28000000-0000-4000-8000-000000000014';
$ingredient = '28000000-0000-4000-8000-000000000015';
DB::table('pos_branches')->insertOrIgnore(['id' => $branch, 'code' => 'TEST-REST', 'name' => 'ร้านอาหารจำลอง', 'business_type' => 'restaurant']);
DB::table('pos_terminals')->insertOrIgnore(['id' => '28000000-0000-4000-8000-000000000013', 'branch_id' => $branch, 'code' => 'REST-PC']);
Staff::firstOrCreate(['email' => 'restaurant@pos.test'], ['id' => '28000000-0000-4000-8000-000000000017', 'branch_id' => $branch, 'name' => 'ผู้จัดการร้านอาหารจำลอง', 'password' => 'local-fixture-password', 'role' => 'manager', 'active' => true]);
config(['pos.connector_branch_ids' => [$branch]]);
$recipe = ['kind' => 'recipe', 'recipe_id' => '28000000-0000-4000-8000-000000000016', 'recipe_version' => 'synthetic-v1', 'yield_quantity' => '1.0000', 'yield_unit' => 'จาน', 'ingredients' => [['product_id' => $ingredient, 'unit' => 'kg', 'quantity' => '0.5000']]];
$recipe['recipe_hash'] = hash('sha256', CanonicalJson::encode($recipe));
$parent = ['product_id' => $menu, 'name' => 'อาหารทดสอบ', 'unit' => 'จาน', 'price' => '10.00', 'tax_rate' => '7.00', 'active' => true, 'online_visible' => true, 'restaurant' => $recipe];
$variant = $parent; $variant['product_id'] = '28000000-0000-4000-8000-000000000018'; $variant['name'] = 'อาหารทดสอบ จานใหญ่ เพิ่มไข่'; $variant['price'] = '15.00';
$variant['restaurant_variant'] = ['parent_product_id' => $menu, 'label' => 'จานใหญ่ เพิ่มไข่', 'size' => 'ใหญ่', 'modifiers' => ['ไข่']];
unset($variant['restaurant']['recipe_hash']); $variant['restaurant']['ingredients'][0]['quantity'] = '1.0000'; $variant['restaurant']['recipe_version'] = 'synthetic-large-v1'; $variant['restaurant']['recipe_hash'] = hash('sha256', CanonicalJson::encode($variant['restaurant']));
app(ProvisioningService::class)->catalog('restaurant-browser-'.Str::uuid(), ['branch_id' => $branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [$parent, $variant]]);
app(ProvisioningService::class)->grant((string) Str::uuid(), ['branch_id' => $branch, 'product_id' => $ingredient, 'epoch' => 1, 'quantity' => '10.0000']);
echo "Synthetic restaurant fixture ready. Existing evidence retained.\n";
