<?php

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\PosMember;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ChartOfAccountProvisioner;
use App\Services\Pos\PosImportService;
use App\Services\Pos\PosMemberRequestImportService;
use App\Services\Pos\PosMembershipPublicationService;
use App\Support\PosCanonicalJson;
use Company\Pos\PromotionCalculator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$directory = dirname(__DIR__, 3).'/temp/phase2882-membership';
$database = $directory.'/erp.sqlite';
$fresh = ! is_file($database);
if (! is_file($directory.'/database.sqlite') || (! $fresh && ! in_array('--append', $argv, true)) || is_file($directory.'/cleanup.json')) {
    throw new RuntimeException('Fresh isolated ERP membership fixture required.');
}
foreach (['APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('n', 32)), 'APP_DEBUG' => 'false', 'APP_CONFIG_CACHE' => $directory.'/unused-erp-config.php', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'null', 'POS_LOCAL_MEMBERSHIP_ENABLED' => 'true', 'POS_MEMBER_LOOKUP_KEY' => str_repeat('lookup-fixture-only-', 3), 'POS_MEMBER_ENROLLMENT_PRIVATE_KEY_PATH' => $directory.'/enrollment-private.pem'] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $_SERVER[$key] = $value;
}
if ($fresh) {
    touch($database);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath($directory);
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) {
    throw new RuntimeException('Isolated ERP database required.');
}
$app->make(Kernel::class)->call('migrate', ['--force' => true]);
$ids = json_decode(file_get_contents($directory.'/identities.json'), true)['retail'];
$source = new PDO('sqlite:'.$directory.'/database.sqlite');
if ($fresh) {
    $user = User::factory()->create(['email' => 'erp@membership.fixture.test']);
    $user->organization->update(['currency' => 'THB']);
    $role = Role::create(['org_id' => $user->org_id, 'code' => 'membership-fixture', 'name' => 'Synthetic membership manager']);
    $permission = Permission::firstOrCreate(['code' => 'pos.manage'], ['module' => 'pos', 'action' => 'manage']);
    $role->permissions()->attach($permission->id);
    $user->roles()->attach($role->id);
    DB::table('branches')->insert(['id' => $ids['branch'], 'org_id' => $user->org_id, 'code' => 'MEMTEST', 'name' => 'Synthetic membership branch', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    $warehouse = Warehouse::create(['org_id' => $user->org_id, 'code' => 'MEMTEST', 'name' => 'Synthetic warehouse']);
    $customer = Customer::create(['org_id' => $user->org_id, 'customer_code' => 'MEMTEST', 'company_name' => 'Synthetic walk-in', 'owner_id' => $user->id]);
    $product = Product::forceCreate(['id' => $ids['product'], 'org_id' => $user->org_id, 'sku' => 'MEMTEST', 'name' => 'Synthetic retail item', 'type' => 'product', 'unit' => 'piece', 'price' => '10.00', 'is_active' => true]);
    StockMovement::create(['org_id' => $user->org_id, 'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'movement_type' => 'adjustment_in', 'movement_date' => now()->toDateString(), 'quantity' => '100.0000', 'base_unit_cost' => '3.00', 'base_total_cost' => '300.00']);
    app(ChartOfAccountProvisioner::class)->ensure($user->org_id);
    ChartOfAccount::firstOrCreate(['org_id' => $user->org_id, 'code' => '5100'], ['name' => 'Synthetic explicit COGS mapping', 'account_type' => 'expense', 'normal_balance' => 'debit', 'is_postable' => true, 'status' => 'active']);
    $instance = '22222222-2222-4222-8222-222222222222';
    $public = openssl_pkey_get_details(openssl_pkey_get_private(file_get_contents($directory.'/synthetic-private.pem')))['key'];
    DB::table('pos_instances')->insert(['id' => $instance, 'org_id' => $user->org_id, 'branch_id' => $ids['branch'], 'warehouse_id' => $warehouse->id, 'customer_id' => $customer->id, 'actor_id' => $user->id, 'name' => 'Synthetic membership', 'business_type' => 'retail', 'active' => true, 'terminal_ids' => json_encode([$ids['terminal']]), 'signing_keys' => json_encode(['membership-fixture' => ['active' => true, 'public_key' => $public]]), 'account_codes' => json_encode(['drawer' => '1100', 'receivable' => '1120', 'revenue' => '4100', 'vat' => '2130', 'inventory' => '1140', 'cogs' => '5100'])]);
    DB::table('pos_staff_authorities')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $instance, 'source_staff_id' => $ids['staff'], 'role' => 'manager', 'active' => true]);
    $query = $source->prepare('select * from pos_grants where branch_id = ?');
    $query->execute([$ids['branch']]);
    foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $grant) {
        DB::table('pos_stock_entitlements')->insert(['id' => $grant['id'], 'org_id' => $user->org_id, 'pos_instance_id' => $instance, 'warehouse_id' => $warehouse->id, 'product_id' => $ids['product'], 'epoch' => $grant['epoch'], 'granted_quantity' => $grant['granted_quantity'], 'consumed_quantity' => '0.0000', 'status' => 'active']);
    }
    $query = $source->prepare('select * from pos_catalogs where branch_id = ?');
    $query->execute([$ids['branch']]);
    $catalog = $query->fetch(PDO::FETCH_ASSOC);
    $body = ['branch_id' => $ids['branch'], 'valid_until' => $catalog['valid_until'], 'items' => json_decode($catalog['items'], true)];
    DB::table('pos_catalog_publications')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $instance, 'version' => $catalog['version'], 'payload' => PosCanonicalJson::encode($body), 'payload_hash' => hash('sha256', PosCanonicalJson::encode($body)), 'valid_until' => $catalog['valid_until'], 'created_at' => $catalog['created_at'], 'updated_at' => $catalog['updated_at']]);
    $query = $source->prepare('select * from pos_membership_publications where branch_id = ? order by version');
    $query->execute([$ids['branch']]);
    $publication = $query->fetch(PDO::FETCH_ASSOC);
    $pub = json_decode($publication['payload'], true);
    DB::table('pos_membership_publications')->insert(['id' => $pub['id'], 'org_id' => $user->org_id, 'pos_instance_id' => $instance, 'created_by' => $user->id, 'version' => $pub['version'], 'payload' => $publication['payload'], 'payload_hash' => $publication['payload_hash'], 'created_at' => now(), 'updated_at' => now()]);
    $policy = $pub['policies'][0]['policy'];
    DB::table('pos_promotion_rules')->insert(['id' => (string) Str::uuid(), 'org_id' => $user->org_id, 'promotion_id' => $policy['id'], 'version' => $policy['version'], 'name' => $pub['policies'][0]['name'], 'policy' => PosCanonicalJson::encode($policy), 'policy_hash' => (new PromotionCalculator)->fingerprint($policy), 'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
    PosMember::forceCreate(['id' => $ids['member'], 'org_id' => $user->org_id, 'public_code' => $pub['members'][0]['code'], 'tier' => 'standard', 'status' => 'active', 'contact' => ['name' => 'Synthetic member', 'phone' => '0000000101'], 'phone_fingerprint' => hash_hmac('sha256', $user->org_id.'|0000000101', config('pos.member_lookup_key')), 'request_key' => (string) Str::uuid(), 'request_hash' => str_repeat('a', 64), 'created_by' => $user->id]);
} else {
    $user = User::where('email', 'erp@membership.fixture.test')->firstOrFail();
    $instance = '22222222-2222-4222-8222-222222222222';
}
// The CLI-only refresh simulates a newer authority publication; seed that test authority too.
$query = $source->prepare('select * from pos_membership_publications where branch_id = ? order by version');
$query->execute([$ids['branch']]);
foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $publication) {
    if (! DB::table('pos_membership_publications')->where('id', $publication['id'])->exists()) {
        DB::table('pos_membership_publications')->insert(['id' => $publication['id'], 'org_id' => $user->org_id, 'pos_instance_id' => $instance, 'created_by' => $user->id, 'version' => $publication['version'], 'payload' => $publication['payload'], 'payload_hash' => $publication['payload_hash'], 'created_at' => now(), 'updated_at' => now()]);
    }
}
$query = $source->prepare("select envelope from pos_outbox where branch_id = ? and event_type in ('sale.completed', 'member.requested') order by sequence");
$query->execute([$ids['branch']]);
$events = array_map(fn ($r) => json_decode($r['envelope'], true), $query->fetchAll(PDO::FETCH_ASSOC));
$service = app(PosImportService::class);
$service->receive($instance, $events, max(array_column($events, 'source_sequence')));
foreach ($events as $event) {
    $result = $service->process($instance, $event['event_id']);
    if ($result['status'] !== 'accepted') {
        throw new RuntimeException('Signed membership roundtrip failed: '.json_encode($result));
    }
}
$counts = fn () => array_combine(['invoices', 'payments', 'journal_entries', 'stock_movements', 'pos_import_promotion_claims'], array_map(fn ($t) => DB::table($t)->count(), ['invoices', 'payments', 'journal_entries', 'stock_movements', 'pos_import_promotion_claims']));
$beforeReplay = $counts();
foreach ($events as $event) {
    $service->process($instance, $event['event_id']);
}
if ($beforeReplay !== $counts() || DB::table('invoices')->where('subtotal', '!=', 9)->exists() || DB::table('invoices')->where('tax_amount', '!=', 0.63)->exists()) {
    throw new RuntimeException('Replay duplicated postings or net/VAT differs.');
}
$newMember = null;
foreach (DB::table('pos_import_member_requests')->get() as $request) {
    $decision = app(PosMemberRequestImportService::class)->decide($user, $request->id, 'approve', $newMember);
    $newMember = $decision['member_id'];
}
$review = app(PosMembershipPublicationService::class)->forSync($instance, true, $user);
file_put_contents($directory.'/review-publication.json', PosCanonicalJson::encode($review));
$report = ['scope' => 'Actual Retail POS signed envelopes into isolated ERP services; central test authority seeded from fixture publications; no HTTP/mTLS acceptance', 'accepted_events' => count($events), 'sales' => DB::table('pos_import_sales')->count(), 'pending_then_reviewed_requests' => DB::table('pos_import_member_requests')->count(), 'net_per_sale' => '9.00', 'vat_per_sale' => '0.63', 'before_replay' => $beforeReplay, 'after_replay' => $counts(), 'review_publication_version' => $review['version']];
file_put_contents($directory.'/roundtrip.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_THROW_ON_ERROR).PHP_EOL;
