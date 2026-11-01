<?php

namespace Pos\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Support\CanonicalJson;

/** Durable evidence intake only. No offline permits, tax documents or sale authority. */
class OfflineIngressService
{
    public function register(Staff $staff, array $input): array
    {
        $this->guard();
        $d = validator($input, ['device_id' => 'required|uuid', 'terminal_id' => 'required|uuid', 'label' => 'required|string|max:64'])->validate();

        return DB::transaction(function () use ($staff, $d) {
            $this->actor($staff);
            DB::table('pos_terminals')->where('id', $d['terminal_id'])->where('branch_id', $staff->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
            DB::table('pos_branches')->where('id', $staff->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
            $old = DB::table('pos_offline_devices')->where('id', $d['device_id'])->lockForUpdate()->first();
            if ($old) {
                abort_unless($old->instance_id === config('pos.instance_id') && $old->branch_id === $staff->branch_id && $old->staff_id === $staff->id && $old->terminal_id === $d['terminal_id'] && $old->status === 'active', 409, 'Offline device binding conflict.');
            } else {
                DB::table('pos_offline_devices')->insert(['id' => $d['device_id'], 'instance_id' => config('pos.instance_id'), 'branch_id' => $staff->branch_id, 'terminal_id' => $d['terminal_id'], 'staff_id' => $staff->id, 'label' => $d['label'], 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            }

            return ['device_id' => $d['device_id'], 'status' => 'active', 'offline_sale_authorized' => false];
        });
    }

    public function receive(Staff $staff, array $input): array
    {
        $this->guard();
        abort_unless(strlen(CanonicalJson::encode($input)) <= 65536, 413, 'Offline evidence exceeds intake limit.');
        // Timestamps and data are evidence, never proof of sale time, paid money or quota.
        $d = validator(['evidence' => $input], ['evidence' => 'required|array:device_id,client_transaction_id,kind,client_created_at,data', 'evidence.device_id' => 'required|uuid', 'evidence.client_transaction_id' => 'required|uuid', 'evidence.kind' => 'required|in:sale.draft,restaurant.order.draft,delivery.request', 'evidence.client_created_at' => 'required|string|max:80', 'evidence.data' => 'present|array'])->validate()['evidence'];
        $bytes = CanonicalJson::encode($d);
        $hash = hash('sha256', $bytes);

        return DB::transaction(function () use ($staff, $d, $bytes, $hash) {
            $this->actor($staff);
            $device = DB::table('pos_offline_devices')->where('id', $d['device_id'])->where('instance_id', config('pos.instance_id'))->where('branch_id', $staff->branch_id)->where('staff_id', $staff->id)->where('status', 'active')->lockForUpdate()->firstOrFail();
            $row = DB::table('pos_offline_inbox')->where('device_id', $device->id)->where('client_transaction_id', $d['client_transaction_id'])->first();
            if ($row) {
                if (! hash_equals($row->payload_hash, $hash)) {
                    DB::table('pos_offline_conflicts')->insertOrIgnore(['id' => (string) Str::uuid(), 'inbox_id' => $row->id, 'payload_hash' => $hash, 'payload' => Crypt::encryptString($bytes), 'server_received_at' => now()->utc()]);
                    DB::table('pos_offline_inbox')->where('id', $row->id)->update(['status' => 'review', 'reason_code' => 'identity_conflict', 'updated_at' => now()]);
                    $row = DB::table('pos_offline_inbox')->where('id', $row->id)->firstOrFail();
                }

                return $this->ack($row);
            }
            $id = (string) Str::uuid();
            DB::table('pos_offline_inbox')->insert(['id' => $id, 'device_id' => $device->id, 'client_transaction_id' => $d['client_transaction_id'], 'branch_id' => $staff->branch_id, 'staff_id' => $staff->id, 'kind' => $d['kind'], 'client_created_at' => $d['client_created_at'], 'server_received_at' => now()->utc(), 'payload_hash' => $hash, 'payload' => Crypt::encryptString($bytes), 'status' => 'review', 'reason_code' => 'offline_authority_not_implemented', 'created_at' => now(), 'updated_at' => now()]);

            return $this->ack(DB::table('pos_offline_inbox')->where('id', $id)->firstOrFail());
        });
    }

    private function ack(object $row): array
    {
        return ['inbox_id' => $row->id, 'client_transaction_id' => $row->client_transaction_id, 'status' => $row->status, 'reason_code' => $row->reason_code, 'server_received_at' => Carbon::parse($row->server_received_at, 'UTC')->toIso8601String(), 'sale_id' => $row->sale_id, 'financial_posted' => $row->sale_id !== null];
    }

    private function guard(): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_browser_offline_enabled') === true && Str::isUuid(config('pos.instance_id')), 503, 'Browser offline capability disabled.');
    }

    private function actor(Staff $staff): void
    {
        $current = Staff::whereKey($staff->id)->lockForUpdate()->first();
        abort_unless($current && $current->active && $current->branch_id === $staff->branch_id && in_array($current->role, ['cashier', 'manager', 'pharmacist'], true), 403);
        DB::table('pos_branches')->where('id', $staff->branch_id)->where('active', true)->firstOrFail();
    }
}
