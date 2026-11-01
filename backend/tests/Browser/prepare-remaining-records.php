<?php

use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Department;
use App\Models\Division;
use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\PortalUser;
use App\Models\Project;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Models\Voucher;
use App\Services\PortalAccessService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

$database = require __DIR__.'/remaining-environment.php';
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath(dirname($database));
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) {
    throw new RuntimeException('Refusing non-fixture DB.');
}
$user = User::where('email', 'remaining-owner@fixture.test')->firstOrFail();
$customer = Customer::firstOrCreate(['org_id' => $user->org_id, 'customer_code' => 'SYN-ACCEPT'], ['company_name' => 'Synthetic Acceptance Customer', 'status' => 'active']);
Supplier::firstOrCreate(['org_id' => $user->org_id, 'supplier_code' => 'SYN-ACCEPT'], ['name' => 'Synthetic Acceptance Supplier', 'status' => 'active']);
$project = Project::firstOrCreate(['org_id' => $user->org_id, 'project_code' => 'SYN-ACCEPT'], ['name' => 'Synthetic Acceptance Project', 'customer_id' => $customer->id, 'status' => 'active', 'created_by' => $user->id]);
Task::firstOrCreate(['org_id' => $user->org_id, 'title' => 'Synthetic Acceptance Task'], ['project_id' => $project->id, 'assigned_to' => $user->id, 'created_by' => $user->id]);
Deal::firstOrCreate(['org_id' => $user->org_id, 'title' => 'Synthetic Acceptance Deal'], ['customer_id' => $customer->id, 'created_by' => $user->id]);
Invoice::firstOrCreate(['org_id' => $user->org_id, 'invoice_no' => 'SYN-ACCEPT'], ['customer_id' => $customer->id, 'issue_date' => '2026-10-29', 'status' => 'draft', 'currency' => 'THB', 'base_currency' => 'THB', 'created_by' => $user->id]);
$viewer = User::firstOrCreate(['email' => 'remaining-viewer@fixture.test'], [
    'org_id' => $user->org_id, 'name' => 'Synthetic read-only viewer', 'display_name' => 'Synthetic viewer',
    'password' => Hash::make('local-acceptance-password'),
    'email_verified_at' => now(), 'status' => 'active',
]);
$role = Role::firstOrCreate(['org_id' => $user->org_id, 'code' => 'synthetic_read_only'], ['name' => 'Synthetic read only']);
$role->permissions()->sync(Permission::whereIn('code', ['dashboard.view', 'products.view', 'customers.view', 'projects.view', 'tasks.view', 'roles.view', 'users.view', 'settings.structure.view'])->pluck('id'));
$viewer->roles()->sync([$role->id]);
$branch = Branch::firstOrCreate(['org_id' => $user->org_id, 'code' => 'SYN-ACCEPT'], ['name' => 'Synthetic branch', 'status' => 'active']);
$division = Division::firstOrCreate(['org_id' => $user->org_id, 'code' => 'SYN-ACCEPT'], ['branch_id' => $branch->id, 'name' => 'Synthetic division', 'status' => 'active']);
Department::firstOrCreate(['org_id' => $user->org_id, 'code' => 'SYN-ACCEPT'], ['branch_id' => $branch->id, 'division_id' => $division->id, 'name' => 'Synthetic department', 'status' => 'active']);
$expense = Expense::firstOrCreate(['org_id' => $user->org_id, 'expense_no' => 'SYN-ACCEPT'], ['category' => 'misc', 'title' => 'Synthetic UI expense', 'amount' => 50, 'expense_date' => '2026-10-29', 'currency' => 'THB', 'status' => 'draft', 'created_by' => $user->id]);
$accounts = [];
foreach (['asset', 'asset', 'expense'] as $i => $type) {
    $accounts[] = ChartOfAccount::firstOrCreate(['org_id' => $user->org_id, 'code' => 'SYN-'.($i + 1)], ['name' => 'Synthetic UI account '.($i + 1), 'account_type' => $type, 'normal_balance' => $i === 1 ? 'credit' : 'debit', 'is_postable' => true, 'status' => 'active']);
}
$category = AssetCategory::firstOrCreate(['org_id' => $user->org_id, 'code' => 'SYN-ACCEPT'], ['name' => 'Synthetic UI asset category', 'asset_account_id' => $accounts[0]->id, 'accumulated_depreciation_account_id' => $accounts[1]->id, 'depreciation_expense_account_id' => $accounts[2]->id, 'default_useful_life_months' => 12, 'status' => 'active']);
FixedAsset::firstOrCreate(['org_id' => $user->org_id, 'asset_no' => 'SYN-ACCEPT'], ['asset_category_id' => $category->id, 'name' => 'Synthetic UI asset (no posting)', 'capitalization_source_type' => 'expense', 'capitalization_source_id' => $expense->id, 'acquisition_date' => '2026-10-29', 'available_for_use_date' => '2026-10-29', 'depreciation_start_date' => '2026-10-29', 'cost' => 50, 'net_book_value' => 50, 'useful_life_months' => 12, 'status' => 'active']);
Voucher::firstOrCreate(['org_id' => $user->org_id, 'voucher_no' => 'SYN-ACCEPT'], ['type' => 'payment', 'voucher_date' => '2026-10-29', 'amount' => 50, 'partner_name' => 'Synthetic UI party', 'status' => 'draft']);
$supplier = Supplier::where('org_id', $user->org_id)->where('supplier_code', 'SYN-ACCEPT')->firstOrFail();
$portal = PortalUser::firstOrCreate(['org_id' => $user->org_id, 'email' => 'remaining-supplier@fixture.test'], ['party_type' => 'supplier', 'party_id' => $supplier->id, 'name' => 'Synthetic supplier portal', 'is_active' => true]);
$token = app(PortalAccessService::class)->issueMagicLink($portal);
file_put_contents(dirname($database).'/synthetic-portal-link.json', json_encode(['url' => route('portal.consume', ['token' => $token])]));
echo "Synthetic parent records ready.\n";
