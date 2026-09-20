<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\PortalUser;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PortalAccessService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

$database = require __DIR__.'/phase232-environment.php';
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath(dirname($database));
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) {
    throw new RuntimeException('Refusing non-fixture database.');
}
if (! file_exists($database)) {
    touch($database);
}
Artisan::call('migrate', ['--force' => true]);
$permissions = ['dashboard.view', 'executive.dashboard.view', 'sales.dashboard.view', 'customers.view', 'deals.view', 'quotations.view', 'invoices.view', 'payments.view', 'expenses.view', 'projects.view', 'tasks.view', 'users.view', 'roles.view', 'audit.view', 'purchase_orders.view', 'inventory.view', 'workflows.approve', 'workflows.view', 'hr.manage', 'hr.self.view', 'payroll.view'];
foreach (['owner' => $permissions, 'readonly' => ['dashboard.view', 'invoices.view', 'expenses.view'], 'payroll' => ['payroll.view'], 'denied' => ['dashboard.view']] as $name => $codes) {
    $user = User::where('email', $name.'@phase232.test')->first() ?? User::factory()->create(['email' => $name.'@phase232.test', 'name' => 'Fixture '.$name]);
    $role = Role::firstOrCreate(['org_id' => $user->org_id, 'code' => $name], ['name' => $name]);
    $ids = [];
    foreach ($codes as $code) {
        $ids[] = Permission::firstOrCreate(['code' => $code], ['module' => 'test', 'action' => $code])->id;
    }
    $role->permissions()->sync($ids);
    $user->roles()->sync([$role->id]);
    if ($name === 'owner') {
        $owner = $user;
    }
}
$links = [];
foreach (['customer', 'supplier'] as $type) {
    $party = $type === 'customer'
        ? Customer::firstOrCreate(['org_id' => $owner->org_id, 'customer_code' => 'BROWSER'], ['company_name' => 'Visible Customer', 'owner_id' => $owner->id])
        : Supplier::firstOrCreate(['org_id' => $owner->org_id, 'supplier_code' => 'BROWSER'], ['name' => 'Visible Supplier', 'status' => 'active']);
    $portal = PortalUser::firstOrCreate(['org_id' => $owner->org_id, 'party_type' => $type, 'party_id' => $party->id, 'email' => $type.'@phase232.test'], ['name' => 'Fixture '.$type, 'is_active' => true]);
    $token = app(PortalAccessService::class)->issueMagicLink($portal);
    $links[$type] = route('portal.consume', $token);
    if ($type === 'customer') {
        Quotation::firstOrCreate(['org_id' => $owner->org_id, 'quotation_no' => 'VISIBLE-QUOTE'], ['customer_id' => $party->id, 'status' => 'sent', 'tax_mode' => 'no_tax', 'issue_date' => today(), 'valid_until' => today()->addWeek(), 'subtotal' => 100, 'total' => 100, 'currency' => 'THB']);
        Invoice::firstOrCreate(['org_id' => $owner->org_id, 'invoice_no' => 'VISIBLE-INVOICE'], ['customer_id' => $party->id, 'status' => 'sent', 'issue_date' => today(), 'total' => 100, 'balance_due' => 100, 'base_total' => 100, 'base_balance_due' => 100, 'currency' => 'THB']);
        $other = Customer::firstOrCreate(['org_id' => $owner->org_id, 'customer_code' => 'HIDDEN'], ['company_name' => 'Hidden Customer', 'owner_id' => $owner->id]);
        Invoice::firstOrCreate(['org_id' => $owner->org_id, 'invoice_no' => 'HIDDEN-INVOICE'], ['customer_id' => $other->id, 'status' => 'sent', 'issue_date' => today(), 'total' => 999, 'balance_due' => 999, 'currency' => 'THB']);
    }
}
file_put_contents(dirname($database).'/links.json', json_encode($links));
echo "Phase 23.2 browser fixture ready on port 8012.\n";
