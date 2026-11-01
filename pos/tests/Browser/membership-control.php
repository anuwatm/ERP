<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Services\MembershipService;

$database = require __DIR__.'/membership-environment.php';
require dirname(__DIR__, 2).'/bootstrap/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath(dirname($database));
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) {
    throw new RuntimeException('Isolated membership fixture required.');
}
$ids = json_decode(file_get_contents(dirname($database).'/identities.json'), true)['retail'];
$row = DB::table('pos_membership_publications')->where('branch_id', $ids['branch'])->orderByDesc('version')->firstOrFail();
if (($argv[1] ?? '') === 'refresh') {
    $pub = json_decode($row->payload, true);
    $pub['id'] = (string) Str::uuid();
    $pub['version']++;
    $pub['created_at'] = now()->utc()->toIso8601String();
    $pub['valid_until'] = now()->addMinutes(15)->utc()->toIso8601String();
    app(MembershipService::class)->provision($pub['id'], $pub);
}
echo json_encode(['sales' => DB::table('pos_orders')->where('branch_id', $ids['branch'])->count(), 'claims' => DB::table('pos_promotion_claims')->where('source_kind', 'sale')->whereIn('source_id', DB::table('pos_orders')->where('branch_id', $ids['branch'])->select('id'))->count()], JSON_THROW_ON_ERROR).PHP_EOL;
