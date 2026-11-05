<?php

namespace App\Services\Pos;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class PosOfflineSalePolicy
{
    public function validate(object $instance, array $p, object $catalog): array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_browser_offline_enabled') === true, 403, 'Local offline acceptance disabled.');
        $o = validator($p['offline_receipt'], [
            'permit_id' => 'required|uuid', 'slot_id' => 'required|uuid', 'device_id' => 'required|uuid', 'client_transaction_id' => 'required|uuid',
            'payload_hash' => 'required|string|size:64', 'catalog_hash' => 'required|string|size:64', 'client_created_at' => 'required|string|max:80',
            'server_received_at' => 'required|date', 'permit_issued_at' => 'required|date', 'permit_expires_at' => 'required|date',
            'reviewed_by' => 'required|uuid', 'accepted_at' => 'required|date', 'cash_received' => ['required', 'string', 'regex:/^\d{1,10}\.\d{2}$/D'],
            'policy' => 'required|in:local_manager_cash_review',
        ])->validate();
        $issued = Carbon::parse($o['permit_issued_at'])->utc();
        $expires = Carbon::parse($o['permit_expires_at'])->utc();
        $accepted = Carbon::parse($o['accepted_at'])->utc();
        abort_unless(in_array($instance->business_type, ['retail', 'pharmacy'], true) && ! isset($p['membership']) && ! isset($p['discount_approval_id']) && $o['reviewed_by'] === $p['staff_id'] && $accepted->eq(Carbon::parse($p['occurred_at'])) && $issued->lte($accepted) && $issued->gte(Carbon::parse($catalog->created_at)) && $issued->lt($expires) && $expires->lte(Carbon::parse($catalog->valid_until)) && Carbon::parse($o['server_received_at'])->lte($accepted) && hash_equals($catalog->payload_hash, $o['catalog_hash']) && bccomp($o['cash_received'], $p['total'], 2) === 0 && bccomp($p['change'], '0', 2) === 0, 422, 'Offline review evidence conflict.');
        abort_unless(DB::table('pos_staff_authorities')->where('pos_instance_id', $instance->id)->where('source_staff_id', $o['reviewed_by'])->where('role', 'manager')->where('active', true)->exists(), 403, 'Registered offline reviewing manager required.');

        return $o;
    }
}
