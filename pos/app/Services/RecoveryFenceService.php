<?php

namespace Pos\Services;

use Illuminate\Support\Facades\DB;
use Pos\Support\CanonicalJson;

class RecoveryFenceService
{
    public function initialize(): void
    {
        $this->guard();
        DB::transaction(function () {
            abort_if(DB::table('pos_outbox')->exists() || DB::table('pos_outbox_archive')->exists() || DB::table('pos_runtime_fence')->exists() || file_exists(config('pos.recovery_anchor_path')), 409, 'Recovery anchors can only be initialized for a new empty instance. Restore existing anchors instead.');
            DB::table('pos_runtime_fence')->insert(['id' => 1, 'generation' => config('pos.recovery_generation'), 'created_at' => now(), 'updated_at' => now()]);
            $this->write(['generation' => config('pos.recovery_generation'), 'sequence' => 0, 'envelope_hash' => hash('sha256', '')]);
        });
    }

    public function assertCurrent(): void
    {
        if (config('pos.local_recovery_fencing_enabled') !== true) {
            return;
        }
        $this->guard();
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Recovery fencing requires a transaction.');
        }
        $identity = DB::table('pos_runtime_fence')->where('id', 1)->lockForUpdate()->firstOrFail();
        abort_unless($identity->generation === config('pos.recovery_generation'), 423, 'Recovery generation mismatch.');
        $path = config('pos.recovery_anchor_path');
        abort_unless(is_file($path), 423, 'Independent recovery anchor is missing.');
        $anchor = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        $latest = $this->latest();
        abort_unless(is_array($anchor) && $anchor === $latest, 423, 'POS restore or interrupted commit detected; recovery fencing required.');
    }

    public function seal(): void
    {
        if (config('pos.local_recovery_fencing_enabled') !== true) {
            return;
        }
        $sealed = $this->latest();
        $this->write($sealed);
        // Routine rollbacks may compensate only their own marker; crashes retain the fence.
        DB::afterRollBack(function () use ($sealed) {
            $this->write($this->latest(), $sealed);
        });
    }

    private function latest(): array
    {
        $seq = max((int) DB::table('pos_outbox')->max('sequence'), (int) DB::table('pos_outbox_archive')->max('sequence'));
        $event = $seq ? (DB::table('pos_outbox')->where('sequence', $seq)->first() ?? DB::table('pos_outbox_archive')->where('sequence', $seq)->first()) : null;

        return ['generation' => config('pos.recovery_generation'), 'sequence' => $seq, 'envelope_hash' => $event ? hash('sha256', CanonicalJson::encode(json_decode(app(OutboxArchiveStore::class)->envelope($event), true, flags: JSON_THROW_ON_ERROR))) : hash('sha256', '')];
    }

    private function write(array $anchor, ?array $expected = null): void
    {
        $path = config('pos.recovery_anchor_path');
        $stream = fopen($path, 'c+b');
        if (! $stream || ! flock($stream, LOCK_EX)) {
            throw new \RuntimeException('Recovery anchor is not writable.');
        }
        try {
            if ($expected !== null) {
                rewind($stream);
                if (json_decode(stream_get_contents($stream), true) !== $expected) {
                    return;
                }
            }
            $bytes = json_encode($anchor, JSON_THROW_ON_ERROR);
            rewind($stream);
            if (! ftruncate($stream, 0) || fwrite($stream, $bytes) !== strlen($bytes) || ! fflush($stream)) {
                throw new \RuntimeException('Recovery anchor persistence failed.');
            }
            if (function_exists('fsync') && ! fsync($stream)) {
                throw new \RuntimeException('Recovery anchor fsync failed.');
            }
        } finally {
            flock($stream, LOCK_UN);
            fclose($stream);
        }
    }

    private function guard(): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_recovery_fencing_enabled') === true && is_string(config('pos.recovery_generation')) && strlen(config('pos.recovery_generation')) >= 8 && is_string(config('pos.recovery_anchor_path')) && is_dir(dirname(config('pos.recovery_anchor_path'))), 503, 'Independent local recovery anchor is not provisioned.');
    }
}
