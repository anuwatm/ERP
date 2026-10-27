<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Pos\Services\CheckpointService;
use Pos\Services\LotRecallService;
use Pos\Services\ProvisioningService;
use Pos\Services\TaxDocumentService;
use Pos\Support\CanonicalJson;

Route::middleware(['pos.connector', 'throttle:pos-connector'])->group(function () {
    Route::put('tax-profile', function (Request $r, TaxDocumentService $service) {
        $service->provision($r->all());

        return response()->json(['status' => 'accepted']);
    });
    Route::get('checkpoint', function (Request $r, CheckpointService $service) {
        $d = $r->validate(['branch_id' => 'required|uuid', 'after_sequence' => 'required|integer|min:0']);

        return response()->json($service->create($d['branch_id'], (int) $d['after_sequence']));
    });
    Route::put('checkpoints/{id}/ack', function (Request $r, string $id, CheckpointService $service) {
        $d = $r->validate(['digest' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']]);
        $service->acknowledge($id, $d['digest']);

        return response()->json(['status' => 'recorded']);
    });
    Route::put('recalls/{id}', function (Request $r, string $id, LotRecallService $service) {
        $service->receive($id, $r->all());

        return response()->json(['status' => 'accepted']);
    });
    Route::put('grants/{id}/freeze', function (Request $r, string $id, ProvisioningService $service) {
        abort_unless(Str::isUuid($id), 422);
        $data = $r->validate(['epoch' => 'required|integer|min:1']);

        return response()->json($service->freezeGrant($id, $data['epoch']));
    });
    Route::put('catalog/{version}', function (Request $r, string $version, ProvisioningService $service) {
        abort_unless(preg_match('/^[A-Za-z0-9_-]{1,80}$/D', $version), 422);
        $service->catalog($version, $r->all());

        return response()->json(['status' => 'accepted']);
    });
    Route::put('grants/{id}', function (Request $r, string $id, ProvisioningService $service) {
        abort_unless(Str::isUuid($id), 422);
        $service->grant($id, $r->all());

        return response()->json(['status' => 'accepted']);
    });
    Route::get('events', function (Request $r) {
        $data = $r->validate(['cursor' => 'nullable|integer|min:0', 'limit' => 'nullable|integer|min:1|max:100']);
        $hot = DB::table('pos_outbox')->whereIn('branch_id', config('pos.connector_branch_ids'))->where('sequence', '>', $data['cursor'] ?? 0)->select(['sequence', 'event_id', 'event_type', 'branch_id', 'envelope'])->selectRaw('NULL as archive_path, NULL as envelope_hash');
        $archive = DB::table('pos_outbox_archive')->whereIn('branch_id', config('pos.connector_branch_ids'))->where('sequence', '>', $data['cursor'] ?? 0)->select(['sequence', 'event_id', 'event_type', 'branch_id', 'envelope', 'archive_path', 'envelope_hash']);
        $rows = $hot->unionAll($archive)->orderBy('sequence')->limit($data['limit'] ?? 100)->get();

        return response()->json(['events' => $rows->map(fn ($row) => json_decode(app(\Pos\Services\OutboxArchiveStore::class)->envelope($row), true, flags: JSON_THROW_ON_ERROR))->all(), 'next_cursor' => $rows->last()?->sequence ?? ($data['cursor'] ?? 0)]);
    });
    Route::put('results/{id}', function (Request $r, string $id) {
        $data = $r->validate(['status' => 'required|in:accepted,rejected,needs_review,inventory_pending,dependency_pending', 'revision' => 'required|integer|min:1', 'reason_code' => 'nullable|string|max:80', 'invoice_id' => 'nullable|uuid']);

        return DB::transaction(function () use ($data, $id) {
            $archive = DB::table('pos_outbox_archive')->where('event_id', $id)->whereIn('branch_id', config('pos.connector_branch_ids'))->first();
            if ($archive) {
                abort_unless(CanonicalJson::encode(json_decode($archive->result, true)) === CanonicalJson::encode($data), 409, 'Archived result is immutable.');

                return response()->json(['status' => 'recorded']);
            }
            $row = DB::table('pos_outbox')->where('event_id', $id)->whereIn('branch_id', config('pos.connector_branch_ids'))->lockForUpdate()->firstOrFail();
            $old = $row->result ? json_decode($row->result, true) : null;
            if ($old) {
                abort_if($old['revision'] > $data['revision'] || ($old['revision'] === $data['revision'] && CanonicalJson::encode($old) !== CanonicalJson::encode($data)), 409, 'Result revision conflict.');
            }
            DB::table('pos_outbox')->where('event_id', $id)->update(['result_status' => $data['status'], 'result' => json_encode($data, JSON_THROW_ON_ERROR), 'updated_at' => now()]);

            return response()->json(['status' => 'recorded']);
        });
    });
});
