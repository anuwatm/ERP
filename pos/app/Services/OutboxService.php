<?php

namespace Pos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Support\CanonicalJson;

class OutboxService
{
    public function append(string $type, string $aggregateId, array $payload): string
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Outbox requires sale transaction.');
        }
        $instance = (string) config('pos.instance_id');
        $keyId = (string) config('pos.signing_key_id');
        $keyPath = config('pos.signing_key_path');
        if ($instance === '' || $keyId === '' || ! $keyPath || ! file_exists($keyPath)) {
            throw new \RuntimeException('POS event signing is not configured.');
        }
        $key = openssl_pkey_get_private(file_get_contents($keyPath));
        $details = $key ? openssl_pkey_get_details($key) : false;
        if (! $details || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 2048) {
            throw new \RuntimeException('A valid RSA signing key of at least 2048 bits is required.');
        }
        $id = (string) Str::uuid();
        app(RecoveryFenceService::class)->assertCurrent();
        $sequence = DB::table('pos_outbox')->insertGetId(['event_id' => $id, 'branch_id' => $payload['branch_id'], 'aggregate_id' => $aggregateId, 'event_type' => $type, 'envelope' => '', 'result_status' => 'pending', 'created_at' => now(), 'updated_at' => now()], 'sequence');
        $envelope = ['domain' => 'company-os.pos-event.v1', 'schema_version' => 1, 'instance_id' => $instance, 'event_id' => $id, 'aggregate_id' => $aggregateId, 'aggregate_version' => 1, 'source_sequence' => $sequence, 'event_type' => $type, 'occurred_at' => $payload['occurred_at'], 'key_id' => $keyId, 'payload_hash' => hash('sha256', CanonicalJson::encode($payload)), 'payload' => $payload];
        if (! openssl_sign(CanonicalJson::encode($envelope), $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Event signing failed.');
        }
        $envelope['signature'] = base64_encode($signature);
        abort_if(strlen(CanonicalJson::encode($envelope)) > 262144, 413, 'POS event exceeds the ERP inbox contract limit.');
        DB::table('pos_outbox')->where('sequence', $sequence)->update(['envelope' => CanonicalJson::encode($envelope)]);
        // Written before commit: a rollback or crash fences conservatively instead of reusing identity.
        app(RecoveryFenceService::class)->seal();

        return $id;
    }
}
