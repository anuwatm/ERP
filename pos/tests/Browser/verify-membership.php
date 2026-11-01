<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Pos\Models\Staff;
use Pos\Services\MembershipService;

$database = require __DIR__.'/membership-environment.php';
require dirname(__DIR__, 2).'/bootstrap/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath(dirname($database));
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) {
    throw new RuntimeException('Isolated source database required.');
}
$directory = dirname($database);
$browser = json_decode(file_get_contents($directory.'/browser-report.json'), true);
if ($browser['result'] !== 'PASS' || count($browser['cases']) !== 9) {
    throw new RuntimeException('Complete browser acceptance required.');
}
$verified = [];
foreach ($browser['cases'] as $case) {
    if (! isset($case['sale_id'])) {
        continue;
    }
    $sale = DB::table('pos_orders')->where('id', $case['sale_id'])->firstOrFail();
    $snapshot = json_decode($sale->snapshot, true);
    $events = DB::table('pos_outbox')->where('event_type', 'sale.completed')->where('aggregate_id', $sale->id)->count();
    $claims = DB::table('pos_promotion_claims')->where('source_kind', 'sale')->where('source_id', $sale->id)->count();
    $payments = DB::table('pos_payments')->where('order_id', $sale->id)->count();
    if ($events !== 1 || $claims !== 1 || $payments !== 1 || bccomp($sale->total, '9.63', 2) !== 0 || $snapshot['membership']['quote']['total'] !== '9.63') {
        throw new RuntimeException('Source sale, event, payment or promotion claim differs.');
    }
    $verified[] = ['business' => $case['business'], 'width' => $case['width'], 'sale_id' => $sale->id, 'events' => $events, 'claims' => $claims, 'payments' => $payments, 'total' => '9.63'];
}
$identities = json_decode(file_get_contents($directory.'/identities.json'), true);
$branch = $identities['restaurant']['branch'];
$prepared = DB::table('pos_outbox')->where('branch_id', $branch)->where('event_type', 'kitchen.consumed')->count();
$consumed = DB::table('pos_grants')->where('branch_id', $branch)->sum('consumed_quantity');
if (bccomp((string) $consumed, bcmul((string) $prepared, '0.5000', 4), 4) !== 0) {
    throw new RuntimeException('Restaurant consumed ingredients twice or differs from recipe.');
}
$publication = json_decode(file_get_contents($directory.'/review-publication.json'), true);
app(MembershipService::class)->provision($publication['id'], $publication);
$pending = DB::table('pos_member_drafts')->where('branch_id', $identities['retail']['branch'])->where('status', '!=', 'approved')->count();
$options = app(MembershipService::class)->options(Staff::findOrFail($identities['retail']['staff']));
if ($pending !== 0 || count($options['members']) !== 2) {
    throw new RuntimeException('ERP review publication did not activate the explicitly reviewed member.');
}
$report = ['result' => 'PASS', 'scope' => 'Synthetic source ledger and actual ERP review publication applied through source membership service; no mTLS transport', 'sales' => $verified, 'restaurant_preparation_events' => $prepared, 'restaurant_ingredient_consumed' => (string) $consumed, 'retail_reviewed_members' => count($options['members']), 'retail_unapproved_drafts' => $pending];
file_put_contents($directory.'/source-verification.json', json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_THROW_ON_ERROR).PHP_EOL;
