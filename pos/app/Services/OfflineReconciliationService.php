<?php

namespace Pos\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Pos\Models\Staff;
use Pos\Support\CanonicalJson;

final class OfflineReconciliationService
{
    public function close(Staff $manager, array $input): array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_browser_offline_enabled') === true, 503);
        $d = validator($input, ['permit_id' => 'required|uuid', 'accepted_transactions' => 'present|array|max:500', 'accepted_transactions.*' => 'required|uuid|distinct', 'unused_confirmed' => 'required|accepted', 'evidence_note' => 'required|string|min:10|max:500'])->validate();

        return DB::transaction(function () use ($manager, $d) {
            $actor = Staff::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->active && $actor->role === 'manager' && $actor->branch_id === $manager->branch_id, 403);
            DB::table('pos_branches')->where('id', $actor->branch_id)->lockForUpdate()->firstOrFail();
            $permit = DB::table('pos_offline_permits')->where('id', $d['permit_id'])->where('branch_id', $actor->branch_id)->lockForUpdate()->firstOrFail();
            $ids = [];
            foreach (DB::table('pos_offline_inbox')->where('device_id', $permit->device_id)->get() as $row) {
                $e = json_decode(Crypt::decryptString($row->payload), true);
                if (($e['data']['permit_id'] ?? null) !== $permit->id) {
                    continue;
                }
                abort_unless($row->status === 'synced' && $row->sale_id && ! DB::table('pos_offline_conflicts')->where('inbox_id', $row->id)->exists(), 409, 'Unresolved evidence must retain quota.');
                $ids[] = $row->client_transaction_id;
            }
            sort($ids);
            $expected = $d['accepted_transactions'];
            sort($expected);
            abort_unless($ids === $expected, 409, 'Reconciliation manifest mismatch.');
            if ($permit->status === 'closed') {
                return ['permit_id' => $permit->id, 'status' => 'closed'];
            }
            abort_unless(now()->gte(Carbon::parse($permit->expires_at)), 409, 'Permit must expire before unused quota release; an offline device may still hold valid authority.');
            foreach (DB::table('pos_offline_reservations')->where('permit_id', $permit->id)->where('status', 'reserved')->orderBy('grant_id')->lockForUpdate()->get() as $reservation) {
                $unused = bcsub($reservation->quantity, $reservation->consumed_quantity, 4);
                $grant = DB::table('pos_grants')->where('id', $reservation->grant_id)->where('branch_id', $actor->branch_id)->lockForUpdate()->firstOrFail();
                abort_unless($grant->epoch === $reservation->epoch && bccomp($unused, '0', 4) >= 0 && bccomp($grant->reserved_quantity, $unused, 4) >= 0, 409, 'Reservation evidence conflict.');
                DB::table('pos_grants')->where('id', $grant->id)->update(['reserved_quantity' => bcsub($grant->reserved_quantity, $unused, 4), 'updated_at' => now()]);
                DB::table('pos_offline_reservations')->where('id', $reservation->id)->update(['status' => 'reconciled', 'updated_at' => now()]);
            }
            DB::table('pos_offline_slots')->where('permit_id', $permit->id)->where('status', 'allocated')->update(['status' => 'cancelled']);
            DB::table('pos_offline_permits')->where('id', $permit->id)->update(['status' => 'closed', 'active_terminal_id' => null, 'updated_at' => now()]);
            DB::table('pos_audit')->insert(['actor_id' => $actor->id, 'branch_id' => $actor->branch_id, 'action' => 'offline.permit.closed', 'source_id' => $permit->id, 'metadata' => CanonicalJson::encode($d), 'created_at' => now()]);

            return ['permit_id' => $permit->id, 'status' => 'closed'];
        });
    }

    public function accept(Staff $manager, array $input): array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_browser_offline_enabled') === true, 503);
        $d = validator($input, ['inbox_id' => 'required|uuid', 'terminal_id' => 'required|uuid', 'payload_hash' => 'required|string|size:64', 'cash_received' => ['required', 'string', 'regex:/^\d{1,10}\.\d{2}$/D'], 'evidence_note' => 'required|string|min:3|max:500'])->validate();

        return DB::transaction(function () use ($manager, $d) {
            $actor = Staff::whereKey($manager->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->active && $actor->role === 'manager' && $actor->branch_id === $manager->branch_id, 403);
            DB::table('pos_terminals')->where('id', $d['terminal_id'])->where('branch_id', $actor->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
            $branch = DB::table('pos_branches')->where('id', $actor->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
            $row = DB::table('pos_offline_inbox')->where('id', $d['inbox_id'])->where('branch_id', $actor->branch_id)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($row->payload_hash, $d['payload_hash']), 409, 'Evidence changed; refresh review.');
            if ($row->sale_id) {
                return json_decode(DB::table('pos_orders')->where('id', $row->sale_id)->value('snapshot'), true, flags: JSON_THROW_ON_ERROR);
            }
            abort_unless($row->kind === 'sale.draft' && $row->reason_code !== 'identity_conflict' && ! DB::table('pos_offline_conflicts')->where('inbox_id', $row->id)->exists(), 409, 'Conflicting or operational draft requires separate investigation.');
            $e = json_decode(Crypt::decryptString($row->payload), true, flags: JSON_THROW_ON_ERROR);
            validator(['data' => $e['data']], ['data' => 'required|array:permit_id,slot_id,items,total,offline_cash,clock,provisional_receipt'])->validate();
            $data = validator($e['data'], ['permit_id' => 'required|uuid', 'slot_id' => 'required|uuid', 'items' => 'required|array|min:1|max:100', 'items.*' => 'array:product_id,quantity', 'items.*.product_id' => 'required|uuid|distinct', 'items.*.quantity' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,4})?$/D'], 'total' => ['required', 'string', 'regex:/^\d{1,10}\.\d{2}$/D']])->validate();
            $permit = DB::table('pos_offline_permits')->where('id', $data['permit_id'])->where('device_id', $row->device_id)->where('branch_id', $actor->branch_id)->where('staff_id', $row->staff_id)->lockForUpdate()->firstOrFail();
            abort_unless($permit->status === 'active', 409, 'Offline permit is closed.');
            $p = json_decode($permit->payload, true, flags: JSON_THROW_ON_ERROR);
            $frozen = DB::table('pos_catalogs')->where('branch_id', $actor->branch_id)->where('version', $p['catalog_version'])->firstOrFail();
            abort_unless(hash_equals($p['catalog_hash'], $frozen->payload_hash), 409, 'Frozen catalog evidence conflict.');
            $slot = DB::table('pos_offline_slots')->where('id', $data['slot_id'])->where('permit_id', $permit->id)->lockForUpdate()->firstOrFail();
            abort_unless($slot->status === 'allocated' && $slot->client_transaction_id === null, 409, 'Offline document slot already used.');
            $products = collect($p['items'])->keyBy('product_id');
            $liveCatalog = DB::table('pos_catalogs')->where('branch_id', $branch->id)->where('version', $branch->active_catalog_version)->firstOrFail();
            $live = collect(json_decode($liveCatalog->items, true))->keyBy('product_id');
            $total = '0.00';
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                $current = $live->get($item['product_id']);
                abort_unless($product && $current && $current['active'] && ! isset($current['pharmacy']) && ! isset($current['restaurant']) && ! isset($current['restaurant_variant']) && bccomp($item['quantity'], '0', 4) > 0, 422, 'Offline item requires fresh authority.');
                $net = bcadd(bcmul($item['quantity'], $product['unit_price'], 8), '0.005', 2);
                $tax = bcadd(bcdiv(bcmul($net, $product['tax_rate'], 8), '100', 8), '0.005', 2);
                $total = bcadd($total, bcadd($net, $tax, 2), 2);
            }
            abort_unless(bccomp($total, $data['total'], 2) === 0 && bccomp($total, $d['cash_received'], 2) === 0 && bccomp($total, '0', 2) > 0, 422, 'Preserve original paid amount; no automatic surcharge or adjustment.');
            $spent = '0.00';
            foreach (DB::table('pos_offline_inbox')->where('branch_id', $actor->branch_id)->whereNotNull('sale_id')->get() as $accepted) {
                $old = json_decode(Crypt::decryptString($accepted->payload), true);
                if (($old['data']['permit_id'] ?? null) === $permit->id) {
                    $spent = bcadd($spent, $old['data']['total'], 2);
                }
            }
            abort_unless(bccomp(bcadd($spent, $total, 2), $p['policy']['max_offline_amount'], 2) <= 0, 422, 'Offline cash budget exceeded.');
            $proof = ['permit_id' => $permit->id, 'slot_id' => $slot->id, 'device_id' => $row->device_id, 'client_transaction_id' => $row->client_transaction_id, 'payload_hash' => $row->payload_hash, 'client_created_at' => $row->client_created_at, 'server_received_at' => $row->server_received_at, 'permit_issued_at' => $p['issued_at'], 'permit_expires_at' => $p['expires_at'], 'catalog_hash' => $p['catalog_hash'], 'reviewed_by' => $actor->id, 'accepted_at' => now()->utc()->toIso8601String(), 'cash_received' => $total, 'policy' => 'local_manager_cash_review'];
            $sale = app(CheckoutService::class)->checkout($actor, ['terminal_id' => $d['terminal_id'], 'request_key' => 'offline:'.$row->id, 'catalog_version' => $p['catalog_version'], 'items' => $data['items'], 'cash_tendered' => $total], new OfflineSaleAuthority($permit->id, $proof));
            abort_unless(bccomp($sale['total'], $total, 2) === 0, 409, 'Frozen price calculation conflict.');
            DB::table('pos_offline_slots')->where('id', $slot->id)->update(['client_transaction_id' => $row->client_transaction_id, 'status' => 'consumed']);
            DB::table('pos_offline_inbox')->where('id', $row->id)->update(['sale_id' => $sale['sale_id'], 'status' => 'synced', 'reason_code' => 'manager_cash_accepted', 'updated_at' => now()]);
            DB::table('pos_audit')->insert(['actor_id' => $actor->id, 'branch_id' => $actor->branch_id, 'action' => 'offline.cash.accepted', 'source_id' => $row->id, 'metadata' => CanonicalJson::encode($proof + ['evidence_note' => $d['evidence_note']]), 'created_at' => now()]);

            return $sale;
        });
    }
}
