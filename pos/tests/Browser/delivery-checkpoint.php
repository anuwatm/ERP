<?php

use Illuminate\Support\Facades\DB;
use Pos\Services\CheckpointService;

require __DIR__.'/prepare-delivery.php';
config(['pos.local_checkpoints_enabled' => true, 'pos.recovery_generation' => 'delivery-fixture-generation']);
$branch = DB::table('pos_branches')->where('code', 'DTEST')->value('id');
config(['pos.connector_branch_ids' => [$branch]]);
$body = app(CheckpointService::class)->create($branch, 0);
file_put_contents(dirname($database).'/checkpoint.json', json_encode($body, JSON_THROW_ON_ERROR));
echo "Synthetic source checkpoint generated\n";
