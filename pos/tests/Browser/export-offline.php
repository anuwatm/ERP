<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Pos\Support\CanonicalJson;

$database = require __DIR__.'/offline-environment.php';
require dirname(__DIR__, 2).'/bootstrap/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath(dirname($database));
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) {
    throw new RuntimeException('Isolated fixture required');
}
$ids = json_decode(file_get_contents(dirname($database).'/identities.json'), true);
$orders = DB::table('pos_orders')->get();
$inbox = DB::table('pos_offline_inbox')->get();
if ($orders->count() !== 2 || $inbox->count() !== 3 || $inbox->where('status', 'synced')->count() !== 2 || DB::table('pos_payments')->count() !== 2 || DB::table('pos_outbox')->count() !== 2 || bccomp((string) DB::table('pos_grants')->sum('reserved_quantity'), '0', 4) !== 0) {
    throw new RuntimeException('Offline browser acceptance counters conflict');
}
$row = DB::table('pos_outbox')->where('event_type', 'sale.completed')->orderBy('sequence')->first();
$event = json_decode($row->envelope, true);
if ($event['payload']['branch_id'] !== $ids['retail']['branch']) {
    throw new RuntimeException('Retail first event required');
}
$public = openssl_pkey_get_details(openssl_pkey_get_private(file_get_contents(dirname($database).'/synthetic-private.pem')))['key'];
$catalog = DB::table('pos_catalogs')->where('branch_id', $ids['retail']['branch'])->first();
$catalogPayload = ['branch_id' => $catalog->branch_id, 'valid_until' => Carbon::parse($catalog->valid_until, 'UTC')->setTimezone(config('app.timezone'))->toIso8601String(), 'items' => json_decode($catalog->items, true)];
if (! hash_equals($catalog->payload_hash, hash('sha256', CanonicalJson::encode($catalogPayload)))) {
    throw new RuntimeException('Cannot reconstruct original catalog evidence');
}
$grants = DB::table('pos_grants')->where('branch_id', $ids['retail']['branch'])->get();
$bundle = ['scope' => 'Actual POS offline browser cash -> Manager acceptance -> signed event; synthetic only', 'public_key' => $public, 'event' => $event, 'catalog' => (array) $catalog, 'catalog_payload' => $catalogPayload, 'grants' => $grants->toArray()];
file_put_contents(dirname($database).'/retail-signed-event.json', json_encode($bundle, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
file_put_contents(dirname($database).'/server-report.json', json_encode(['result' => 'PASS', 'orders' => 2, 'payments' => 2, 'outbox' => 2, 'inbox' => 3, 'synced' => 2, 'restaurant_review' => 1, 'reserved_remaining' => '0.0000'], JSON_PRETTY_PRINT));
echo "Browser server counters and signed Retail evidence verified\n";
