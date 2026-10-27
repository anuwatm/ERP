<?php

namespace Pos\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Support\CanonicalJson;

class DiscountService
{
    private function guard(): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_discounts_enabled') === true, 503, 'Local discounts disabled.');
    }

    public function command(Staff $requester, array $input): array
    {
        $d = validator($input, ['terminal_id' => 'required|uuid', 'catalog_version' => 'required|string|max:80', 'request_key' => 'required|string|max:100', 'items' => 'required|array|min:1|max:100', 'items.*.product_id' => 'required|uuid|distinct', 'items.*.quantity' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,4})?$/D'], 'items.*.unit' => 'required|string|max:30', 'items.*.discount_amount' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/D']])->validate();
        $d['operation'] = 'sale.discount';
        $d['requester_id'] = $requester->id;
        $d['branch_id'] = $requester->branch_id;
        foreach ($d['items'] as &$line) {
            $line = ['product_id' => $line['product_id'], 'quantity' => bcadd($line['quantity'], '0', 4), 'unit' => $line['unit'], 'discount_amount' => bcadd($line['discount_amount'], '0', 2)];
        }
        unset($line);
        usort($d['items'], fn ($a, $b) => strcmp($a['product_id'], $b['product_id']));

        return $d;
    }

    public function approve(Staff $requester, Staff $manager, array $command, ManagerApproval $approval): array
    {
        $this->guard();
        $command = $this->command($requester, $command);
        $hash = hash('sha256', CanonicalJson::encode($command));

        return DB::transaction(function () use ($requester, $manager, $command, $approval, $hash) {
            $locked = Staff::whereIn('id', [$requester->id, $manager->id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($locked->has($requester->id) && $locked->has($manager->id), 403);
            $approval->assertFor($locked[$manager->id], $command);
            $current = $locked[$requester->id];
            abort_unless($current->active && in_array($current->role, ['cashier', 'manager', 'pharmacist'], true) && $current->branch_id === $requester->branch_id && $current->branch_id === $locked[$manager->id]->branch_id, 403);
            DB::table('pos_terminals')->where('id', $command['terminal_id'])->where('branch_id', $current->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
            $branch = DB::table('pos_branches')->where('id', $current->branch_id)->where('active', true)->lockForUpdate()->firstOrFail();
            $old = DB::table('pos_discount_approvals')->where('branch_id', $branch->id)->where('request_key', $command['request_key'])->first();
            if ($old) {
                abort_unless(hash_equals($old->command_hash, $hash) && $old->manager_id === $manager->id, 409, 'Discount approval identity conflict.');

                return ['id' => $old->id, 'discounted_total' => bcadd((string) $old->discounted_total, '0', 2), 'expires_at' => Carbon::parse($old->expires_at)->toIso8601String()];
            }
            abort_unless($branch->active_catalog_version === $command['catalog_version'], 409, 'Review the current catalog before discount approval.');
            $catalog = DB::table('pos_catalogs')->where('branch_id', $branch->id)->where('version', $command['catalog_version'])->where('valid_until', '>', now())->firstOrFail();
            $products = collect(json_decode($catalog->items, true))->keyBy('product_id');
            $total = '0.00';
            $discounted = '0.00';
            foreach ($command['items'] as $line) {
                $product = $products->get($line['product_id']);
                abort_unless($product && $product['active'], 422, 'Discount product unavailable.');
                $resolved = app(PharmacyPolicy::class)->resolve($current, $branch->business_type, $product, $line);
                $gross = bcadd(bcmul($line['quantity'], $resolved['unit_price'], 8), '0.005', 2);
                $maximum = bcdiv(bcmul($gross, $product['max_manager_discount_pct'] ?? '0.00', 8), '100', 2);
                abort_if(bccomp($line['discount_amount'], $maximum, 2) > 0 || bccomp($line['discount_amount'], $gross, 2) > 0, 422, 'Discount exceeds published manager policy.');
                $net = bcsub($gross, $line['discount_amount'], 2);
                $tax = bcadd(bcdiv(bcmul($net, $product['tax_rate'], 8), '100', 8), '0.005', 2);
                $total = bcadd($total, bcadd($net, $tax, 2), 2);
                $discounted = bcadd($discounted, $line['discount_amount'], 2);
            }
            abort_unless(bccomp($discounted, '0', 2) > 0 && bccomp($total, '0', 2) > 0, 422, 'A positive discount and payable total are required.');
            $id = (string) Str::uuid();
            $expires = now()->addMinutes(5);
            DB::table('pos_discount_approvals')->insert(['id' => $id, 'branch_id' => $branch->id, 'requester_id' => $current->id, 'manager_id' => $manager->id, 'request_key' => $command['request_key'], 'command_hash' => $hash, 'command' => CanonicalJson::encode($command), 'discounted_total' => $total, 'approved_at' => now()->utc()->toIso8601String(), 'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('pos_audit')->insert(['actor_id' => $manager->id, 'branch_id' => $branch->id, 'action' => 'discount.local_approve', 'source_id' => $id, 'metadata' => CanonicalJson::encode(['requester_id' => $current->id, 'terminal_id' => $command['terminal_id'], 'catalog_version' => $command['catalog_version']]), 'created_at' => now()]);

            return ['id' => $id, 'discounted_total' => $total, 'expires_at' => $expires->toIso8601String()];
        });
    }

    public function claim(Staff $staff, string $id, array $checkout, array $products): array
    {
        $this->guard();
        abort_unless(DB::transactionLevel() > 0, 500);
        $row = DB::table('pos_discount_approvals')->where('id', $id)->where('branch_id', $staff->branch_id)->where('requester_id', $staff->id)->lockForUpdate()->firstOrFail();
        abort_unless($row->sale_id === null && Carbon::parse($row->expires_at)->gt(now()), 409, 'Discount approval expired or already used.');
        $manager = Staff::findOrFail($row->manager_id);
        abort_unless($manager->active && $manager->role === 'manager' && $manager->branch_id === $staff->branch_id, 403, 'Discount manager authority revoked.');
        $command = json_decode($row->command, true);
        abort_unless(hash_equals($row->command_hash, hash('sha256', CanonicalJson::encode($command))), 409, 'Discount approval evidence conflict.');
        $branch = DB::table('pos_branches')->where('id', $staff->branch_id)->firstOrFail();
        abort_unless($checkout['terminal_id'] === $command['terminal_id'] && $checkout['catalog_version'] === $command['catalog_version'] && $branch->active_catalog_version === $command['catalog_version'], 409, 'Discount approval scope changed.');
        $items = array_map(fn ($line) => ['product_id' => $line['product_id'], 'quantity' => bcadd($line['quantity'], '0', 4), 'unit' => $line['unit'] ?? ($products[$line['product_id']]['unit'] ?? '')], $checkout['items']);
        usort($items, fn ($a, $b) => strcmp($a['product_id'], $b['product_id']));
        $expected = array_map(fn ($line) => array_intersect_key($line, array_flip(['product_id', 'quantity', 'unit'])), $command['items']);
        abort_unless(CanonicalJson::encode($items) === CanonicalJson::encode($expected), 409, 'Discount approval does not match this cart.');

        return ['id' => $row->id, 'manager_id' => $row->manager_id, 'approved_at' => $row->approved_at, 'discounts' => array_column($command['items'], 'discount_amount', 'product_id')];
    }
}
