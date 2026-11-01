<?php

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Pos\PosCheckpointService;
use App\Services\Pos\PosImportService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$directory = dirname(__DIR__, 3).'/temp/phase2881-delivery';
$database = $directory.'/erp.sqlite';
if (! is_dir($directory) || ! is_file($directory.'/database.sqlite')) {
    throw new RuntimeException('Prepare the synthetic POS fixture first.');
}
foreach (['APP_ENV' => 'testing', 'APP_KEY' => 'base64:'.base64_encode(str_repeat('e', 32)), 'APP_DEBUG' => 'false', 'APP_CONFIG_CACHE' => $directory.'/unused-erp-config.php', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '', 'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'null'] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $_SERVER[$key] = $value;
}
if (! is_file($database)) {
    touch($database);
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath($directory);
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) {
    throw new RuntimeException('Isolated ERP fixture required.');
}
$app->make(Kernel::class)->call('migrate', ['--force' => true]);
config(['pos.local_delivery_enabled' => true, 'pos.local_checkpoints_enabled' => true]);
$ids = json_decode(file_get_contents($directory.'/identities.json'), true);
$instance = '11111111-1111-4111-8111-111111111111';
if (! DB::table('pos_instances')->where('id', $instance)->exists()) {
    $user = User::factory()->create();
    $branch = Branch::forceCreate(['id' => $ids['branch_id'], 'org_id' => $user->org_id, 'code' => 'DTEST', 'name' => 'Synthetic delivery', 'status' => 'active']);
    $warehouse = Warehouse::create(['org_id' => $user->org_id, 'code' => 'DTEST', 'name' => 'Synthetic warehouse']);
    $customer = Customer::create(['org_id' => $user->org_id, 'customer_code' => 'DTEST', 'company_name' => 'Synthetic', 'owner_id' => $user->id]);
    $public = openssl_pkey_get_details(openssl_pkey_get_private(file_get_contents($directory.'/synthetic-private.pem')))['key'];
    DB::table('pos_instances')->insert(['id' => $instance, 'org_id' => $user->org_id, 'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'customer_id' => $customer->id, 'actor_id' => $user->id, 'name' => 'Synthetic delivery', 'business_type' => 'retail', 'active' => true, 'terminal_ids' => json_encode(array_values($ids['terminals'])), 'signing_keys' => json_encode(['delivery-fixture' => ['active' => true, 'public_key' => $public]]), 'account_codes' => '{}', 'recovery_generation' => 'delivery-fixture-generation']);
    DB::table('pos_staff_authorities')->insert(['id' => (string) Str::uuid(), 'pos_instance_id' => $instance, 'source_staff_id' => $ids['staff_id'], 'role' => 'manager', 'active' => true]);
}
$registered = DB::table('pos_instances')->where('id', $instance)->first();
if (! Branch::whereKey($ids['branch_id'])->exists()) {
    Branch::forceCreate(['id' => $ids['branch_id'], 'org_id' => $registered->org_id, 'code' => 'DTEST-MAPPED', 'name' => 'Synthetic POS identity', 'status' => 'active']);
}
DB::table('pos_instances')->where('id', $instance)->update(['branch_id' => $ids['branch_id']]);
$pdo = new PDO('sqlite:'.$directory.'/database.sqlite');
$events = array_map(fn ($r) => json_decode($r['envelope'], true, flags: JSON_THROW_ON_ERROR), $pdo->query("select envelope from pos_outbox where event_type = 'delivery.updated' order by sequence")->fetchAll(PDO::FETCH_ASSOC));
if ($events === []) {
    throw new RuntimeException('Browser evidence has no delivery events.');
}
$tables = ['invoices', 'payments', 'journal_entries', 'stock_movements'];
$before = array_combine($tables, array_map(fn ($t) => DB::table($t)->count(), $tables));
$service = app(PosImportService::class);
$accepted = 0;
foreach (array_chunk($events, 100) as $chunk) {
    $service->receive($instance, $chunk, max(array_column($chunk, 'source_sequence')));
    foreach ($chunk as $event) {
        $result = $service->process($instance, $event['event_id']);
        if ($result['status'] !== 'accepted' || ($result['financial_status'] ?? '') !== 'policy_pending') {
            throw new RuntimeException('Delivery roundtrip not accepted as operational evidence.');
        }
        $accepted++;
    }
}
$checkpoint = app(PosCheckpointService::class)->verify($instance, json_decode(file_get_contents($directory.'/checkpoint.json'), true));
if ($checkpoint['issues'] !== []) {
    throw new RuntimeException('Synthetic delivery checkpoint has unresolved issues.');
}
$after = array_combine($tables, array_map(fn ($t) => DB::table($t)->count(), $tables));
if ($before !== $after) {
    throw new RuntimeException('Operational delivery created financial or stock entries.');
}
$report = ['scope' => 'Actual synthetic POS signed outbox and source checkpoint into isolated ERP services; not target two-server HTTP/mTLS', 'accepted_revisions' => $accepted, 'financial_status' => 'policy_pending', 'before' => $before, 'after' => $after, 'checkpoint' => $checkpoint];
file_put_contents($directory.'/roundtrip.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_THROW_ON_ERROR).PHP_EOL;
