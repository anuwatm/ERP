<?php

use App\Models\PaymentGatewayConfig;
use App\Services\GatewaySettlementService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || DB::connection()->getDatabaseName() !== 'erp_phase23_test') {
    throw new RuntimeException('Worker is restricted to the isolated test database.');
}
$config = PaymentGatewayConfig::findOrFail($argv[1]);
$raw = json_encode(['event_id' => 'parallel-event', 'reference' => $argv[2], 'status' => 'settled', 'amount_minor' => 10000, 'currency' => 'THB', 'settled_at' => $argv[3]], JSON_THROW_ON_ERROR);
$timestamp = (string) time();
$request = Request::create('/api/gateway/'.$config->id.'/settlement', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SETTLEMENT_TIMESTAMP' => $timestamp, 'HTTP_X_SETTLEMENT_SIGNATURE' => hash_hmac('sha256', $timestamp.'.'.$raw, $config->webhook_secret)], $raw);
echo json_encode($app->make(GatewaySettlementService::class)->receive($request, $config->id), JSON_THROW_ON_ERROR);
