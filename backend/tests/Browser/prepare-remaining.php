<?php

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationProvisioner;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

$database = require __DIR__.'/remaining-environment.php';
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath(dirname($database));
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) {
    throw new RuntimeException('Refusing non-fixture DB.');
}
if (! file_exists($database)) {
    touch($database);
}
Artisan::call('migrate', ['--force' => true]);
$user = User::where('email', 'remaining-owner@fixture.test')->first() ?? app(OrganizationProvisioner::class)->registerOwner(['name' => 'Local Acceptance Tester', 'email' => 'remaining-owner@fixture.test', 'password' => 'local-acceptance-password', 'organization_name' => 'Synthetic Acceptance Org'], Request::create('/'));
$user->forceFill(['email_verified_at' => now()])->save();
$role = Role::firstOrCreate(['org_id' => $user->org_id, 'code' => 'owner'], ['name' => 'Fixture owner', 'is_system' => true]);
$role->permissions()->sync(Permission::pluck('id')->all());
$user->roles()->syncWithoutDetaching([$role->id]);
for ($i = 1; $i <= 1005; $i++) {
    Product::firstOrCreate(['org_id' => $user->org_id, 'sku' => 'LOCAL-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT)], ['name' => 'Synthetic product '.$i, 'type' => 'product', 'unit' => 'piece', 'price' => 10, 'cost' => 5, 'is_active' => true]);
}
echo "Isolated fixture ready (1005 synthetic products).\n";
