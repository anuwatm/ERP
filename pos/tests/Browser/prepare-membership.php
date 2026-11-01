<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Services\MembershipService;
use Pos\Services\ProvisioningService;
use Pos\Support\CanonicalJson;

$database = require __DIR__.'/membership-environment.php';
if (is_file($database)) {
    throw new RuntimeException('Fresh membership database required.');
}
touch($database);
require dirname(__DIR__, 2).'/bootstrap/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath(dirname($database));
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database || ! str_ends_with(str_replace('\\', '/', $database), '/temp/phase2882-membership/database.sqlite')) {
    throw new RuntimeException('Isolated membership fixture required.');
}
$app->make(Kernel::class)->call('migrate', ['--force' => true]);
$options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
$configuration = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
if (is_file($configuration)) {
    $options['config'] = $configuration;
}
foreach (['synthetic-private', 'enrollment-private'] as $name) {
    $key = openssl_pkey_new($options);
    $pem = '';
    openssl_pkey_export($key, $pem, null, $options);
    file_put_contents(dirname($database).'/'.$name.'.pem', $pem);
}
$public = openssl_pkey_get_details($key)['key'];
$ids = [];
foreach (['retail', 'restaurant', 'pharmacy'] as $type) {
    $branch = (string) Str::uuid();
    $product = (string) Str::uuid();
    $ingredient = (string) Str::uuid();
    $member = (string) Str::uuid();
    $terminal = (string) Str::uuid();
    DB::table('pos_branches')->insert(['id' => $branch, 'code' => strtoupper($type), 'name' => 'Synthetic '.$type, 'business_type' => $type]);
    DB::table('pos_terminals')->insert(['id' => $terminal, 'branch_id' => $branch, 'code' => 'M1']);
    $staff = Staff::create(['id' => (string) Str::uuid(), 'branch_id' => $branch, 'name' => 'Synthetic '.$type, 'email' => $type.'@membership.fixture.test', 'password' => 'synthetic-membership-password', 'role' => $type === 'pharmacy' ? 'pharmacist' : 'manager', 'active' => true]);
    $item = ['product_id' => $product, 'name' => 'Synthetic '.$type.' item', 'unit' => 'piece', 'price' => '10.00', 'tax_rate' => '7.00', 'active' => true];
    if ($type === 'restaurant') {
        $recipe = ['kind' => 'recipe', 'recipe_id' => (string) Str::uuid(), 'recipe_version' => 'recipe-1', 'yield_quantity' => '1.0000', 'yield_unit' => 'piece', 'ingredients' => [['product_id' => $ingredient, 'unit' => 'kg', 'quantity' => '0.5000']]];
        $recipe['recipe_hash'] = hash('sha256', CanonicalJson::encode($recipe));
        $item['restaurant'] = $recipe;
    }
    config(['pos.connector_branch_ids' => [$branch]]);
    app(ProvisioningService::class)->catalog('member-v1', ['branch_id' => $branch, 'valid_until' => now()->addDay()->toIso8601String(), 'items' => [$item]]);
    app(ProvisioningService::class)->grant((string) Str::uuid(), ['branch_id' => $branch, 'product_id' => $type === 'restaurant' ? $ingredient : $product, 'epoch' => 1, 'quantity' => '100.0000']);
    $policy = ['id' => (string) Str::uuid(), 'version' => 1, 'mode' => 'percent', 'value' => '10.00', 'currency' => 'THB', 'starts_at' => now()->subDay()->utc()->format('Y-m-d\TH:i:s\Z'), 'ends_at' => now()->addDay()->utc()->format('Y-m-d\TH:i:s\Z'), 'branch_ids' => [$branch], 'business_types' => [$type], 'member_required' => true, 'member_tiers' => ['standard'], 'product_ids' => [], 'minimum_spend' => '0.00', 'minimum_eligible_only' => true, 'minimum_components' => ['merchandise'], 'discount_components' => ['merchandise'], 'max_uses' => null, 'per_member_limit' => null, 'combination' => 'exclusive', 'refund_entitlement' => 'full_only'];
    $pub = ['id' => (string) Str::uuid(), 'instance_id' => config('pos.instance_id'), 'branch_id' => $branch, 'version' => 1, 'created_at' => now()->utc()->toIso8601String(), 'valid_until' => now()->addMinutes(15)->utc()->toIso8601String(), 'members' => [['member_id' => $member, 'code' => 'SYNTHETIC-'.$type, 'name_masked' => 'S***', 'phone_masked' => '***0101', 'tier' => 'standard', 'status' => 'active']], 'policies' => [['name' => 'Synthetic ten percent', 'policy' => $policy]], 'rights' => [], 'enrollment_key' => ['id' => hash('sha256', $public), 'public_key' => $public]];
    app(MembershipService::class)->provision($pub['id'], $pub);
    $ids[$type] = ['branch' => $branch, 'staff' => $staff->id, 'terminal' => $terminal, 'product' => $product, 'member' => $member, 'promotion' => $policy['id'], 'publication' => $pub['id']];
}
file_put_contents(dirname($database).'/identities.json', json_encode($ids, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "Synthetic membership fixture ready\n";
