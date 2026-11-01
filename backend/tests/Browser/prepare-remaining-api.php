<?php

use App\Models\IntegrationApiKey;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

$database = require __DIR__.'/remaining-environment.php';
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath(dirname($database));
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) {
    throw new RuntimeException('Refusing non-fixture DB.');
}
$user = User::where('email', 'remaining-owner@fixture.test')->firstOrFail();
$credentials = [];
foreach (['read' => ['products:read'], 'denied' => ['customers:read'], 'expired' => ['products:read'], 'revoked' => ['products:read']] as $name => $scopes) {
    $prefix = 'erp_'.bin2hex(random_bytes(12));
    $secret = $prefix.'.'.bin2hex(random_bytes(32));
    IntegrationApiKey::create([
        'org_id' => $user->org_id, 'name' => 'Synthetic HTTP acceptance '.$name,
        'prefix' => $prefix, 'secret_hash' => hash('sha256', $secret), 'scopes' => $scopes,
        'created_by' => $user->id, 'expires_at' => $name === 'expired' ? now()->subDay() : now()->addHour(),
        'revoked_at' => $name === 'revoked' ? now() : null,
    ]);
    $credentials[$name] = $secret;
}
file_put_contents(dirname($database).'/synthetic-api-credentials.json', json_encode($credentials));
echo "Synthetic fixture credentials prepared; not printed.\n";
