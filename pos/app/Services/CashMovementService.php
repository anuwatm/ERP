<?php

namespace Pos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Support\CanonicalJson;

class CashMovementService
{
    public function command(array $input): array
    {
        $d = validator($input, ['terminal_id' => 'required|uuid', 'request_key' => 'required|string|max:100', 'direction' => 'required|in:in,out', 'amount' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/D'], 'reference' => 'required|string|max:100', 'reason' => 'required|in:float_added,cash_drop'])->validate();
        $d['amount'] = bcadd($d['amount'], '0', 2);
        abort_unless(bccomp($d['amount'], '0', 2) > 0 && $d['reason'] === ($d['direction'] === 'in' ? 'float_added' : 'cash_drop'), 422, 'Invalid cash movement.');

        return $d;
    }

    public function move(Staff $staff, array $command, ManagerApproval $approval): array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_cash_movements_enabled') === true, 503, 'Local cash movements disabled.');
        $command = $this->command($command);
        $approval->assertFor($staff, $command);
        $hash = hash('sha256', CanonicalJson::encode($command));

        return DB::transaction(function () use ($staff, $command, $approval, $hash) {
            $approval->assertFor(Staff::where('id', $staff->id)->lockForUpdate()->firstOrFail(), $command);
            $terminal = DB::table('pos_terminals')->where('id', $command['terminal_id'])->where('branch_id', $staff->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
            $branch = DB::table('pos_branches')->where('id', $staff->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
            $old = DB::table('pos_cash_movements')->where('branch_id', $branch->id)->where('request_key', $command['request_key'])->first();
            if ($old) {
                abort_unless(hash_equals($old->request_hash, $hash), 409, 'Cash movement identity conflict.');

                return json_decode($old->snapshot, true);
            }
            $shift = DB::table('pos_shifts')->where('terminal_id', $terminal->id)->where('staff_id', $staff->id)->where('status', 'open')->lockForUpdate()->firstOrFail();
            abort_if($command['direction'] === 'out' && bccomp(app(ShiftCashService::class)->balance($shift), $command['amount'], 2) < 0, 422, 'Insufficient cash in current drawer.');
            $id = (string) Str::uuid();
            $snapshot = ['cash_movement_id' => $id, 'branch_id' => $branch->id, 'terminal_id' => $terminal->id, 'shift_id' => $shift->id, 'staff_id' => $staff->id, 'direction' => $command['direction'], 'amount' => $command['amount'], 'currency' => $branch->currency, 'reference' => $command['reference'], 'reason' => $command['reason'], 'occurred_at' => now()->utc()->toIso8601String(), 'business_timezone' => $branch->timezone];
            DB::table('pos_cash_movements')->insert(['id' => $id, 'branch_id' => $branch->id, 'shift_id' => $shift->id, 'staff_id' => $staff->id, 'request_key' => $command['request_key'], 'request_hash' => $hash, 'direction' => $command['direction'], 'amount' => $command['amount'], 'snapshot' => CanonicalJson::encode($snapshot), 'created_at' => now(), 'updated_at' => now()]);
            app(OutboxService::class)->append('cash.moved', $id, $snapshot);
            DB::table('pos_audit')->insert(['actor_id' => $staff->id, 'branch_id' => $branch->id, 'action' => 'cash.local_move', 'source_id' => $id, 'metadata' => CanonicalJson::encode(['direction' => $command['direction'], 'reference' => $command['reference']]), 'created_at' => now()]);

            return $snapshot;
        });
    }
}
