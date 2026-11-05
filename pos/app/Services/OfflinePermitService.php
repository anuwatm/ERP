<?php

namespace Pos\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Support\CanonicalJson;

/** Allocate immutable local cash authority; every submitted cash bill requires Manager review. */
class OfflinePermitService
{
    public function current(Staff $staff): array
    {
        $this->guard();
        $current = Staff::findOrFail($staff->id);
        abort_unless($current->active && $current->branch_id === $staff->branch_id && in_array($current->role, ['cashier', 'manager', 'pharmacist'], true), 403);
        DB::table('pos_branches')->where('id', $staff->branch_id)->where('active', true)->firstOrFail();

        return $this->policy($staff->branch_id);
    }

    private function policy(string $branchId): array
    {
        $row = DB::table('pos_offline_policies')->where('branch_id', $branchId)->first();

        return $row ? json_decode($row->policy, true, flags: JSON_THROW_ON_ERROR) : ['version' => 0, 'permit_minutes' => 15, 'max_offline_transactions' => 20, 'max_offline_amount' => '10000.00', 'product_ids' => []];
    }

    public function configure(Staff $manager, array $input): array
    {
        $this->guard();
        $d = validator($input, ['expected_version' => 'required|integer|min:0', 'permit_minutes' => 'required|integer|min:1|max:1440', 'max_offline_transactions' => 'required|integer|min:1|max:500', 'max_offline_amount' => ['required', 'string', 'regex:/^[0-9]{1,10}\.\d{2}$/D'], 'product_ids' => 'present|array|max:100', 'product_ids.*' => 'required|uuid|distinct'])->validate();
        abort_unless(bccomp($d['max_offline_amount'], '0', 2) > 0 && bccomp($d['max_offline_amount'], '9999999999.99', 2) <= 0, 422, 'Invalid offline amount limit.');

        return DB::transaction(function () use ($manager, $d) {
            $current = Staff::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->active && $current->branch_id === $manager->branch_id && $current->role === 'manager', 403);
            $branch = DB::table('pos_branches')->where('id', $manager->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
            $old = DB::table('pos_offline_policies')->where('branch_id', $branch->id)->first();
            abort_unless((int) ($old->version ?? 0) === (int) $d['expected_version'], 409, 'Offline policy changed; refresh before saving.');
            unset($d['expected_version']);
            $d['permit_minutes'] = (int) $d['permit_minutes'];
            $d['max_offline_transactions'] = (int) $d['max_offline_transactions'];
            $d['max_offline_amount'] = bcadd($d['max_offline_amount'], '0', 2);
            $d['version'] = 1 + (int) ($old->version ?? 0);
            DB::table('pos_offline_policies')->updateOrInsert(['branch_id' => $branch->id], ['version' => $d['version'], 'policy' => CanonicalJson::encode($d), 'updated_by' => $manager->id, 'created_at' => $old->created_at ?? now(), 'updated_at' => now()]);
            DB::table('pos_audit')->insert(['actor_id' => $manager->id, 'branch_id' => $branch->id, 'action' => 'offline.policy.updated', 'source_id' => $branch->id, 'metadata' => CanonicalJson::encode(['version' => $d['version']]), 'created_at' => now()]);

            return $d;
        });
    }

    public function issue(Staff $staff, array $input): array
    {
        $this->guard();
        $d = validator($input, ['device_id' => 'required|uuid', 'request_key' => 'required|uuid', 'items' => 'required|array|min:1|max:100', 'items.*' => 'array:product_id,quantity', 'items.*.product_id' => 'required|uuid|distinct', 'items.*.quantity' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,4})?$/D']])->validate();
        $hash = hash('sha256', CanonicalJson::encode($d));

        return DB::transaction(function () use ($staff, $d, $hash) {
            $current = Staff::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            abort_unless($current->active && $current->branch_id === $staff->branch_id && in_array($current->role, ['cashier', 'manager', 'pharmacist'], true), 403);
            $device = DB::table('pos_offline_devices')->where('id', $d['device_id'])->where('instance_id', config('pos.instance_id'))->where('branch_id', $staff->branch_id)->where('staff_id', $staff->id)->where('status', 'active')->firstOrFail();
            DB::table('pos_terminals')->where('id', $device->terminal_id)->where('branch_id', $staff->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
            $branch = DB::table('pos_branches')->where('id', $staff->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
            $device = DB::table('pos_offline_devices')->where('id', $device->id)->lockForUpdate()->firstOrFail();
            $old = DB::table('pos_offline_permits')->where('device_id', $device->id)->where('request_key', $d['request_key'])->first();
            if ($old) {
                abort_unless(hash_equals($old->request_hash, $hash), 409, 'Offline permit request identity conflict.');

                return json_decode($old->payload, true, flags: JSON_THROW_ON_ERROR);
            }
            abort_unless(in_array($branch->business_type, ['retail', 'pharmacy'], true) && $branch->currency === 'THB', 422, 'Offline cash permit unavailable for this business/currency.');
            abort_if(DB::table('pos_offline_permits')->where('active_terminal_id', $device->terminal_id)->exists(), 409, 'Prior offline permit must be reconciled; expiry does not return quota.');
            $shift = DB::table('pos_shifts')->where('terminal_id', $device->terminal_id)->where('staff_id', $staff->id)->where('status', 'open')->lockForUpdate()->firstOrFail();
            $policy = $this->policy($branch->id);
            $catalog = DB::table('pos_catalogs')->where('branch_id', $branch->id)->where('version', $branch->active_catalog_version)->where('valid_until', '>', now())->firstOrFail();
            $products = collect(json_decode($catalog->items, true))->keyBy('product_id');
            $id = (string) Str::uuid();
            $expires = now()->addMinutes($policy['permit_minutes'])->min(Carbon::parse($catalog->valid_until));
            $lines = [];
            foreach ($d['items'] as $item) {
                $product = $products->get($item['product_id']);
                abort_unless($product && $product['active'] && in_array($item['product_id'], $policy['product_ids'], true), 422, 'Item is not explicitly approved for offline cash.');
                abort_if(isset($product['pharmacy']) || isset($product['restaurant']) || isset($product['restaurant_variant']), 422, 'Clinical/kitchen items require online authority.');
                abort_unless(bccomp($item['quantity'], '0', 4) > 0, 422, 'Offline stock quota must be positive.');
                $allocations = app(EntitlementAllocator::class)->take($branch->id, $item['product_id'], $item['quantity'], $branch->timezone, offlinePermitId: $id);
                foreach ($allocations as $allocation) {
                    $expiry = DB::table('pos_grants')->where('id', $allocation['grant_id'])->value('expires_at');
                    if ($expiry) {
                        $expires = $expires->min(Carbon::parse($expiry, $branch->timezone)->endOfDay()->utc());
                    }
                }
                $lines[] = ['product_id' => $item['product_id'], 'name' => $product['name'], 'unit' => $product['unit'], 'unit_price' => $product['price'], 'tax_rate' => $product['tax_rate'], 'quantity' => bcadd($item['quantity'], '0', 4), 'allocations' => $allocations];
            }
            $slots = [];
            for ($i = 1; $i <= $policy['max_offline_transactions']; $i++) {
                $slot = ['id' => (string) Str::uuid(), 'permit_id' => $id, 'ordinal' => $i, 'status' => 'allocated'];
                DB::table('pos_offline_slots')->insert($slot);
                $slots[] = ['id' => $slot['id'], 'ordinal' => $i];
            }
            $payload = ['id' => $id, 'instance_id' => config('pos.instance_id'), 'branch_id' => $branch->id, 'staff_id' => $staff->id, 'device_id' => $device->id, 'terminal_id' => $device->terminal_id, 'shift_id' => $shift->id, 'issued_at' => now()->utc()->toIso8601String(), 'expires_at' => $expires->utc()->toIso8601String(), 'currency' => $branch->currency, 'policy' => $policy, 'catalog_version' => $catalog->version, 'catalog_hash' => $catalog->payload_hash, 'items' => $lines, 'slots' => $slots, 'provisional_only' => true, 'reconciliation_available' => true];
            DB::table('pos_offline_permits')->insert(['id' => $id, 'device_id' => $device->id, 'branch_id' => $branch->id, 'staff_id' => $staff->id, 'terminal_id' => $device->terminal_id, 'active_terminal_id' => $device->terminal_id, 'shift_id' => $shift->id, 'request_key' => $d['request_key'], 'request_hash' => $hash, 'status' => 'active', 'issued_at' => now(), 'expires_at' => $expires, 'payload' => CanonicalJson::encode($payload), 'created_at' => now(), 'updated_at' => now()]);

            return $payload;
        });
    }

    private function guard(): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_browser_offline_enabled') === true && Str::isUuid(config('pos.instance_id')), 503, 'Browser offline capability disabled.');
    }
}
