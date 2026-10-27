<?php

namespace App\Services\Pos;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use App\Services\JournalPostingService;
use App\Support\PosCanonicalJson;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosCashMovementImportService
{
    public function import(object $instance, array $event): array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_cash_movements_enabled') === true, 503, 'Local cash movements disabled.');
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Cash movement import requires an inbox transaction.');
        }
        $event = app(PosEventVerifier::class)->verify($instance, $event, 'cash.moved');
        $p = validator($event['payload'], ['cash_movement_id' => 'required|uuid', 'branch_id' => 'required|uuid', 'terminal_id' => 'required|uuid', 'shift_id' => 'required|uuid', 'staff_id' => 'required|uuid', 'direction' => 'required|in:in,out', 'amount' => ['required', 'string', 'regex:/^\d{1,10}\.\d{2}$/D'], 'currency' => 'required|string|size:3', 'reference' => 'required|string|max:100', 'reason' => 'required|in:float_added,cash_drop', 'occurred_at' => 'required|date', 'business_timezone' => 'required|in:Asia/Bangkok'])->validate();
        abort_unless($p['cash_movement_id'] === $event['aggregate_id'] && $p['branch_id'] === $instance->branch_id && in_array($p['terminal_id'], json_decode($instance->terminal_ids, true), true) && $p['occurred_at'] === $event['occurred_at'], 403, 'Cash movement scope mismatch.');
        abort_unless(bccomp($p['amount'], '0', 2) > 0 && $p['reason'] === ($p['direction'] === 'in' ? 'float_added' : 'cash_drop'), 422, 'Invalid cash movement.');
        Branch::where('org_id', $instance->org_id)->where('status', 'active')->findOrFail($instance->branch_id);
        User::where('org_id', $instance->org_id)->where('status', 'active')->findOrFail($instance->actor_id);
        abort_unless(Organization::findOrFail($instance->org_id)->currency === $p['currency'], 422, 'Cash movement currency unsupported.');
        abort_unless(DB::table('pos_staff_authorities')->where('pos_instance_id', $instance->id)->where('source_staff_id', $p['staff_id'])->where('role', 'manager')->where('active', true)->exists(), 403, 'POS manager authority unavailable.');
        $occurred = Carbon::parse($p['occurred_at']);
        abort_if($occurred->gt(now()->addMinutes(5)), 422, 'Cash movement time is in the future.');
        $existing = DB::table('pos_import_cash_movements')->where('pos_instance_id', $instance->id)->where('source_cash_movement_id', $p['cash_movement_id'])->first();
        if ($existing) {
            abort_unless(hash_equals($existing->payload_hash, $event['payload_hash']), 409, 'Cash movement source conflict.');

            return ['status' => 'accepted', 'reason_code' => null];
        }
        $mapping = PosAccountMapping::forCashMovement($instance->id);
        $drawer = $mapping->code('drawer', $instance->org_id);
        $clearing = $mapping->code('cash_clearing', $instance->org_id);
        $id = (string) Str::uuid();
        $in = $p['direction'] === 'in';
        $journal = app(JournalPostingService::class)->post($instance->org_id, $instance->actor_id, 'pos_cash_movement', $id, 'recorded', $occurred->setTimezone($p['business_timezone'])->toDateString(), 'POS cash transfer '.$p['reference'], [['account_code' => $drawer, $in ? 'debit' : 'credit' => $p['amount']], ['account_code' => $clearing, $in ? 'credit' : 'debit' => $p['amount']]]);
        DB::table('pos_import_cash_movements')->insert(['id' => $id, 'pos_instance_id' => $instance->id, 'source_cash_movement_id' => $p['cash_movement_id'], 'payload_hash' => $event['payload_hash'], 'source_snapshot' => PosCanonicalJson::encode($p), 'account_snapshot' => PosCanonicalJson::encode($mapping->snapshot($instance->org_id)), 'journal_entry_id' => $journal->id, 'created_at' => now(), 'updated_at' => now()]);
        AuditLog::create(['org_id' => $instance->org_id, 'actor_user_id' => $instance->actor_id, 'action' => 'pos.cash_movement.import', 'entity_type' => 'journal_entry', 'entity_id' => $journal->id, 'after_json' => ['instance_id' => $instance->id, 'source_cash_movement_id' => $p['cash_movement_id'], 'source_shift_id' => $p['shift_id']], 'request_id' => (string) Str::uuid()]);

        return ['status' => 'accepted', 'reason_code' => null];
    }
}
