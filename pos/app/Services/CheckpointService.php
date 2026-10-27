<?php

namespace Pos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Support\CanonicalJson;

class CheckpointService
{
    public function create(string $branch, int $after): array
    {
        $this->guard($branch);
        abort_if($after < 0, 422);

        return DB::transaction(function () use ($branch, $after) {
            DB::table('pos_branches')->where('id', $branch)->lockForUpdate()->firstOrFail();
            app(RecoveryFenceService::class)->assertCurrent();
            $high = max((int) DB::table('pos_outbox')->where('branch_id', $branch)->max('sequence'), (int) DB::table('pos_outbox_archive')->where('branch_id', $branch)->max('sequence'));
            abort_if($after > $high, 409, 'Source checkpoint regressed; restore fencing required.');
            $query = DB::table('pos_outbox')->where('branch_id', $branch)->where('sequence', '>', $after)->select(['sequence', 'event_id', 'event_type', 'envelope', 'branch_id'])->selectRaw('NULL as archive_path, NULL as envelope_hash');
            $archived = DB::table('pos_outbox_archive')->where('branch_id', $branch)->where('sequence', '>', $after)->select(['sequence', 'event_id', 'event_type', 'envelope', 'branch_id', 'archive_path', 'envelope_hash']);
            $rows = $query->unionAll($archived)->orderBy('sequence')->limit(1000)->get();
            $entries = $rows->map(fn ($r) => ['sequence' => (int) $r->sequence, 'event_id' => $r->event_id, 'event_type' => $r->event_type, 'envelope_hash' => hash('sha256', CanonicalJson::encode(json_decode(app(OutboxArchiveStore::class)->envelope($r), true, flags: JSON_THROW_ON_ERROR)))])->all();
            $through = $rows->isEmpty() ? $after : (int) $rows->last()->sequence;
            $id = (string) Str::uuid();
            $digest = hash('sha256', CanonicalJson::encode($entries));
            $body = ['domain' => 'company-os.pos-checkpoint.v1', 'schema_version' => 1, 'checkpoint_id' => $id, 'branch_id' => $branch, 'recovery_generation' => config('pos.recovery_generation'), 'after_sequence' => $after, 'through_sequence' => $through, 'high_watermark' => $high, 'entries' => $entries, 'digest' => $digest, 'occurred_at' => now()->utc()->toIso8601String()];
            $signed = app(DocumentSigner::class)->sign($body);
            DB::table('pos_source_checkpoints')->insert(['id' => $id, 'branch_id' => $branch, 'after_sequence' => $after, 'through_sequence' => $through, 'digest' => $digest, 'snapshot' => CanonicalJson::encode($signed), 'created_at' => now(), 'updated_at' => now()]);

            return $signed;
        });
    }

    public function acknowledge(string $id, string $digest): void
    {
        DB::transaction(function () use ($id, $digest) {
            $row = DB::table('pos_source_checkpoints')->where('id', $id)->lockForUpdate()->firstOrFail();
            $this->guard($row->branch_id);
            abort_unless(hash_equals($row->digest, $digest) && $row->hold_at === null, 409, 'Checkpoint acknowledgement conflict.');
            $snapshot = json_decode($row->snapshot, true);
            foreach ($snapshot['entries'] as $entry) {
                $event = DB::table('pos_outbox')->where('event_id', $entry['event_id'])->first() ?? DB::table('pos_outbox_archive')->where('event_id', $entry['event_id'])->first();
                abort_unless($event && $event->result_status === 'accepted' && hash_equals($entry['envelope_hash'], hash('sha256', CanonicalJson::encode(json_decode(app(OutboxArchiveStore::class)->envelope($event), true)))), 409, 'Checkpoint includes unaccepted or changed events.');
            }
            DB::table('pos_source_checkpoints')->where('id', $id)->update(['reconciled_at' => now(), 'updated_at' => now()]);
        });
    }

    public function archive(string $id): int
    {
        return DB::transaction(function () use ($id) {
            $checkpoint = DB::table('pos_source_checkpoints')->where('id', $id)->lockForUpdate()->firstOrFail();
            $this->guard($checkpoint->branch_id);
            abort_unless($checkpoint->reconciled_at && ! $checkpoint->hold_at, 409, 'Only reconciled checkpoints without holds may be archived.');
            DB::table('pos_branches')->where('id', $checkpoint->branch_id)->lockForUpdate()->firstOrFail();
            $count = 0;
            $snapshot = json_decode($checkpoint->snapshot, true);
            foreach ($snapshot['entries'] as $entry) {
                $row = DB::table('pos_outbox')->where('event_id', $entry['event_id'])->where('branch_id', $checkpoint->branch_id)->lockForUpdate()->first();
                if (! $row || $row->hold_at || $row->created_at >= now()->subDays(90)->toDateTimeString() || $row->result_status !== 'accepted') {
                    continue;
                }
                if ($row->event_type === 'tax.document_issued' && ! in_array(DB::table('pos_etax_queue')->where('document_id', $row->aggregate_id)->value('status'), ['accepted', 'superseded'], true)) {
                    continue;
                }
                abort_unless(hash_equals($entry['envelope_hash'], hash('sha256', CanonicalJson::encode(json_decode($row->envelope, true)))), 409, 'Archive evidence changed.');
                // Copy and verify in the same transaction before removing the hot outbox row.
                $copy = (array) $row;
                $copy = array_merge($copy, app(OutboxArchiveStore::class)->store($row));
                $copy['checkpoint_id'] = $id;
                $copy['archived_at'] = now();
                DB::table('pos_outbox_archive')->insert($copy);
                $restored = DB::table('pos_outbox_archive')->where('event_id', $entry['event_id'])->firstOrFail();
                abort_unless(app(OutboxArchiveStore::class)->envelope($restored) === $row->envelope && $restored->result === $row->result, 409, 'Archive verification failed.');
                DB::table('pos_outbox')->where('sequence', $row->sequence)->delete();
                $count++;
            }

            return $count;
        });
    }

    public function hold(string $id, string $reason): void
    {
        validator(['reason' => $reason], ['reason' => 'required|string|max:500'])->validate();
        DB::transaction(function () use ($id, $reason) {
            $row = DB::table('pos_source_checkpoints')->where('id', $id)->lockForUpdate()->firstOrFail();
            $this->guard($row->branch_id);
            DB::table('pos_source_checkpoints')->where('id', $id)->update(['hold_at' => now(), 'updated_at' => now()]);
            // Holds follow immutable event identities across overlapping checkpoints and restore.
            foreach (array_chunk(array_column(json_decode($row->snapshot, true)['entries'], 'event_id'), 500) as $ids) {
                foreach (['pos_outbox', 'pos_outbox_archive'] as $table) {
                    DB::table($table)->where('branch_id', $row->branch_id)->whereIn('event_id', $ids)->update(['hold_at' => now()]);
                }
            }
            DB::table('pos_audit')->insert(['actor_id' => null, 'branch_id' => $row->branch_id, 'action' => 'checkpoint.hold', 'source_id' => $id, 'metadata' => json_encode(['reason' => $reason]), 'created_at' => now()]);
        });
    }

    public function restore(string $eventId): void
    {
        DB::transaction(function () use ($eventId) {
            $row = DB::table('pos_outbox_archive')->where('event_id', $eventId)->firstOrFail();
            $this->guard($row->branch_id);
            DB::table('pos_branches')->where('id', $row->branch_id)->lockForUpdate()->firstOrFail();
            $copy = (array) $row;
            $copy['envelope'] = app(OutboxArchiveStore::class)->envelope($row);
            unset($copy['archive_path'], $copy['envelope_hash'], $copy['checkpoint_id'], $copy['archived_at']);
            DB::table('pos_outbox')->insert($copy);
            DB::table('pos_outbox_archive')->where('event_id', $eventId)->delete();
        });
    }

    private function guard(string $branch): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_checkpoints_enabled') === true && is_string(config('pos.recovery_generation')) && strlen(config('pos.recovery_generation')) >= 8 && in_array($branch, config('pos.connector_branch_ids'), true), 403, 'Local checkpoint generation is not provisioned.');
    }
}
