<?php

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

$fixtureDatabase = require __DIR__.'/phase231-environment.php';
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath(dirname($fixtureDatabase));
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $fixtureDatabase) {
    throw new RuntimeException('Refusing to prepare any database except the isolated Phase 23.1 fixture.');
}
if (! file_exists($fixtureDatabase)) {
    touch($fixtureDatabase);
}
Artisan::call('migrate', ['--force' => true]);
$user = User::where('email', 'phase231@example.test')->first()
    ?? User::factory()->create(['name' => 'Phase 23.1 Tester', 'email' => 'phase231@example.test']);
$role = Role::firstOrCreate(['org_id' => $user->org_id, 'code' => 'owner'], ['name' => 'Fixture owner', 'is_system' => true]);
foreach (['dashboard.view', 'sales.dashboard.view', 'customers.view', 'customers.create', 'customers.update', 'customers.delete', 'contacts.create', 'contacts.update', 'deals.view', 'deals.create', 'deals.update', 'activities.create', 'activities.update', 'products.manage', 'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete', 'treasury.accounts.view', 'treasury.accounts.manage'] as $code) {
    [$module, $action] = explode('.', $code, 2);
    $permission = Permission::firstOrCreate(['code' => $code], ['module' => $module, 'action' => $action]);
    $role->permissions()->syncWithoutDetaching([$permission->id]);
}
$user->roles()->syncWithoutDetaching([$role->id]);
$customer = Customer::firstOrCreate(['org_id' => $user->org_id, 'customer_code' => 'P231001'], [
    'company_name' => 'ลูกค้าทดสอบ Phase 23.1', 'customer_type' => 'prospect', 'status' => 'active',
    'owner_id' => $user->id, 'email' => 'customer@example.test', 'note' => 'Original fixture note',
]);
Contact::firstOrCreate(['customer_id' => $customer->id, 'name' => 'ผู้ติดต่อทดสอบ'], [
    'org_id' => $user->org_id, 'email' => 'contact@example.test', 'line_id' => 'fixture-line', 'is_primary' => true,
]);
$deal = Deal::firstOrCreate(['org_id' => $user->org_id, 'customer_id' => $customer->id, 'title' => 'Phase 23.1 Deal fixture'], [
    'stage' => 'proposal', 'value_amount' => 1250.50, 'currency' => 'THB', 'probability' => 45,
    'owner_id' => $user->id, 'source' => 'Fixture', 'note' => 'Original deal note',
]);
Activity::firstOrCreate(['org_id' => $user->org_id, 'entity_type' => 'deal', 'entity_id' => $deal->id, 'subject' => 'Phase 23.1 Activity fixture'], [
    'activity_type' => 'meeting', 'activity_at' => '2026-09-11 02:03:04', 'follow_up_at' => '2026-09-12 04:05:06',
    'owner_id' => $user->id, 'body' => 'Original activity body',
]);
echo "Isolated Phase 23.1 browser fixture ready on port 8011.\n";
