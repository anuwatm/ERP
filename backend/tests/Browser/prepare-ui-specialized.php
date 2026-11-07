<?php

use App\Models\BillOfMaterials;
use App\Models\BomItem;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__, 3);
$database = require __DIR__.'/remaining-environment.php';
require $root.'/backend/vendor/autoload.php';
$app = require $root.'/backend/bootstrap/app.php';
$app->useEnvironmentPath(dirname($database));
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database
    || ! str_starts_with(realpath($database), realpath($root.'/temp').DIRECTORY_SEPARATOR)) {
    throw new RuntimeException('Isolated synthetic SQLite required');
}
$owner = User::where('email', 'remaining-owner@fixture.test')->firstOrFail();
$product = Product::firstOrCreate(['org_id' => $owner->org_id, 'sku' => 'UI-FG-20261107'], [
    'name' => 'Synthetic UI Finished Product', 'type' => 'finished_good', 'unit' => 'pcs', 'price' => '100.00', 'cost' => '10.00', 'is_active' => true,
]);
$raw = Product::firstOrCreate(['org_id' => $owner->org_id, 'sku' => 'UI-RM-20261107'], [
    'name' => 'Synthetic UI Raw Material', 'type' => 'raw_material', 'unit' => 'pcs', 'price' => '10.00', 'cost' => '10.00', 'is_active' => true,
]);
$warehouse = Warehouse::firstOrCreate(['org_id' => $owner->org_id, 'code' => 'UI20261107'], [
    'name' => 'Synthetic UI Warehouse', 'branch_id' => $owner->branch_id, 'status' => 'active',
]);
$bom = BillOfMaterials::updateOrCreate(['org_id' => $owner->org_id, 'bom_no' => 'UI-BOM-20261107'], [
    'product_id' => $product->id, 'name' => 'Synthetic UI Recipe', 'version' => '1.0', 'quantity' => 1,
    'unit' => 'pcs', 'is_active' => true, 'total_cost' => 30.50, 'created_by' => $owner->id,
]);
BomItem::updateOrCreate(['bill_of_materials_id' => $bom->id, 'sequence' => 1], [
    'org_id' => $owner->org_id, 'product_id' => $raw->id, 'quantity' => 1, 'unit' => 'pcs', 'unit_cost' => 10,
    'waste_percent' => 5, 'note' => 'Synthetic material handling note', 'total_cost' => 10.50,
]);
$subProduct = Product::firstOrCreate(['org_id' => $owner->org_id, 'sku' => 'UI-SUB-20261107'], [
    'name' => 'Synthetic UI Subassembly', 'type' => 'finished_good', 'unit' => 'pcs', 'price' => 20, 'cost' => 20, 'is_active' => true,
]);
$subBom = BillOfMaterials::firstOrCreate(['org_id' => $owner->org_id, 'bom_no' => 'UI-SUB-BOM-20261107'], [
    'product_id' => $subProduct->id, 'name' => 'Synthetic Subassembly Recipe', 'version' => '1.0', 'quantity' => 1,
    'unit' => 'pcs', 'is_active' => true, 'total_cost' => 20, 'created_by' => $owner->id,
]);
BomItem::firstOrCreate(['bill_of_materials_id' => $subBom->id, 'sequence' => 1], [
    'org_id' => $owner->org_id, 'product_id' => $raw->id, 'quantity' => 2, 'unit' => 'pcs', 'unit_cost' => 10, 'total_cost' => 20,
]);
BomItem::firstOrCreate(['bill_of_materials_id' => $bom->id, 'sequence' => 2], [
    'org_id' => $owner->org_id, 'product_id' => $subProduct->id, 'sub_bom_id' => $subBom->id,
    'quantity' => 1, 'unit' => 'pcs', 'unit_cost' => 20, 'total_cost' => 20,
]);
foreach (['in_progress', 'quality_check'] as $status) {
    WorkOrder::firstOrCreate(['org_id' => $owner->org_id, 'wo_no' => 'UI-WO-'.strtoupper($status)], [
        'bill_of_materials_id' => $bom->id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
        'source_warehouse_id' => $warehouse->id, 'planned_quantity' => '1.5000', 'actual_quantity' => '1.5000',
        'status' => $status, 'qc_passed' => $status === 'quality_check', 'start_date' => now()->toDateString(),
        'due_date' => now()->addDay()->toDateString(), 'created_by' => $owner->id,
    ]);
}
$organization = Organization::firstOrCreate(['name' => 'Synthetic UI Auth Organization'], [
    'currency' => 'THB', 'timezone' => 'Asia/Bangkok', 'status' => 'active',
]);
Setting::updateOrCreate(['org_id' => $organization->id, 'key' => 'security.two_factor'], [
    'value_json' => ['enabled' => true, 'required_for_privileged_roles' => false, 'allow_trusted_devices' => true, 'trusted_device_days' => 30],
]);
$identities = [];
foreach (['setup', 'setup-320-light', 'setup-320-dark', 'setup-1440-light', 'setup-1440-dark', 'challenge', 'unverified', 'invited'] as $role) {
    $user = User::firstOrCreate(['email' => 'ui-'.$role.'@fixture.test'], [
        'org_id' => $organization->id, 'name' => 'Synthetic UI '.$role, 'display_name' => 'Synthetic UI '.$role,
        'auth_provider' => 'local', 'password' => 'local-acceptance-password',
        'status' => $role === 'invited' ? 'invited' : 'active', 'email_verified_at' => $role === 'unverified' ? null : now(),
    ]);
    if ($role === 'challenge') {
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
    }
    if (str_starts_with($role, 'setup-')) {
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_recovery_codes' => null])->save();
    }
    if ($role === 'invited') {
        $user->forceFill([
            'status' => 'invited',
            'invite_token_hash' => hash('sha256', 'synthetic-ui-invite-v1'),
            'invite_expires_at' => now()->addDay(),
        ])->save();
        $identities['invite'] = '/invites/'.$user->id.'/synthetic-ui-invite-v1';
    }
}
$output = $root.'/temp/ui-redesign-acceptance';
if (! is_dir($output)) {
    mkdir($output, 0700, true);
}
file_put_contents($output.'/ui-specialized-identities.json', json_encode($identities, JSON_THROW_ON_ERROR));
echo "Synthetic manufacturing and auth UI records ready\n";
