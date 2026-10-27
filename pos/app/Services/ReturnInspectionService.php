<?php

namespace Pos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Support\CanonicalJson;

class ReturnInspectionService
{
    public function command(array $input): array
    {
        $d = validator($input, ['refund_id' => 'required|uuid', 'source_line_id' => 'required|uuid', 'grant_id' => 'required|uuid', 'request_key' => 'required|string|max:100', 'base_quantity' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,4})?$/D'], 'decision' => 'required|in:restock,destroy', 'packaging_intact' => 'required|boolean', 'storage_verified' => 'required|boolean', 'lot_verified' => 'required|boolean', 'reason' => 'required|string|max:500'])->validate();
        $d['base_quantity'] = bcadd($d['base_quantity'], '0', 4);
        foreach (['packaging_intact', 'storage_verified', 'lot_verified'] as $key) {
            $d[$key] = (bool) $d[$key];
        }
        abort_unless(bccomp($d['base_quantity'], '0', 4) > 0, 422);

        return $d + ['operation' => 'return.inspect'];
    }

    public function inspect(Staff $staff, array $command, ManagerApproval $approval): array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_return_inspections_enabled') === true, 503, 'Local return inspection disabled.');
        $command = $this->command($command);

        return DB::transaction(function () use ($staff, $command, $approval) {
            $current = Staff::whereKey($staff->id)->lockForUpdate()->firstOrFail();
            $approval->assertFor($current, $command);
            DB::table('pos_branches')->where('id', $current->branch_id)->where('active', true)->where('business_type', 'pharmacy')->lockForUpdate()->firstOrFail();
            abort_unless($current->pharmacist_qualified, 403, 'A pharmacist-qualified manager is required.');
            $hash = hash('sha256', CanonicalJson::encode($command));
            $old = DB::table('pos_return_inspections')->where('branch_id', $current->branch_id)->where('request_key', $command['request_key'])->first();
            if ($old) {
                abort_unless($old->inspector_id === $current->id && hash_equals($old->request_hash, $hash), 409, 'Inspection identity conflict.');

                return json_decode($old->snapshot, true);
            }
            $refund = DB::table('pos_refunds')->where('id', $command['refund_id'])->where('branch_id', $current->branch_id)->firstOrFail();
            $order = DB::table('pos_orders')->where('id', $refund->order_id)->where('branch_id', $current->branch_id)->lockForUpdate()->firstOrFail();
            $source = collect(json_decode($order->snapshot, true)['lines'])->firstWhere('line_id', $command['source_line_id']);
            $returned = collect(json_decode($refund->snapshot, true)['lines'])->firstWhere('source_line_id', $command['source_line_id']);
            abort_unless($source && $returned && ($source['stock_effect'] ?? null) !== 'prepared', 422, 'Original stock evidence required.');
            $allocation = collect($source['allocations'])->firstWhere('grant_id', $command['grant_id']);
            $grant = DB::table('pos_grants')->where('id', $command['grant_id'])->where('branch_id', $current->branch_id)->where('product_id', $source['product_id'])->first();
            abort_unless($allocation && $grant && $grant->lot_id && $grant->lot_id === ($allocation['lot_id'] ?? null), 422, 'Traceable original lot is required.');
            abort_unless(isset($source['base_step']) && bccomp(bcmod($command['base_quantity'], $source['base_step'], 4), '0', 4) === 0, 422, 'Inspection violates original unit precision.');
            $prior = DB::table('pos_return_inspections')->where('refund_id', $refund->id)->where('source_line_id', $command['source_line_id'])->get();
            $total = $prior->reduce(fn ($sum, $r) => bcadd($sum, (string) $r->base_quantity, 4), $command['base_quantity']);
            abort_if(bccomp($total, bcmul($returned['returned_quantity'], $source['conversion_factor'], 4), 4) > 0, 422, 'Inspection exceeds quarantined quantity.');
            $used = DB::table('pos_return_inspections')->whereIn('refund_id', DB::table('pos_refunds')->where('order_id', $order->id)->select('id'))->where('source_line_id', $command['source_line_id'])->where('grant_id', $grant->id)->get()->reduce(fn ($sum, $r) => bcadd($sum, (string) $r->base_quantity, 4), $command['base_quantity']);
            abort_if(bccomp($used, $allocation['quantity'], 4) > 0, 422, 'Inspection exceeds original lot allocation.');
            if ($command['decision'] === 'restock') {
                app(LotRecallService::class)->assertAvailable($current->branch_id, $grant->lot_id);
                abort_unless($command['packaging_intact'] && $command['storage_verified'] && $command['lot_verified'] && (! $grant->expires_at || substr($grant->expires_at, 0, 10) >= now('Asia/Bangkok')->toDateString()), 422, 'Restock requires intact packaging, verified storage and unexpired lot.');
            }
            $id = (string) Str::uuid();
            $payload = $command + ['inspection_id' => $id, 'sale_id' => $order->id, 'branch_id' => $current->branch_id, 'staff_id' => $current->id, 'product_id' => $source['product_id'], 'lot_id' => $grant->lot_id, 'occurred_at' => now()->utc()->toIso8601String(), 'business_timezone' => 'Asia/Bangkok'];
            DB::table('pos_return_inspections')->insert(['id' => $id, 'branch_id' => $current->branch_id, 'refund_id' => $refund->id, 'source_line_id' => $command['source_line_id'], 'grant_id' => $grant->id, 'base_quantity' => $command['base_quantity'], 'decision' => $command['decision'], 'inspector_id' => $current->id, 'request_key' => $command['request_key'], 'request_hash' => $hash, 'snapshot' => CanonicalJson::encode($payload), 'created_at' => now(), 'updated_at' => now()]);
            app(OutboxService::class)->append('return.inspected', $id, $payload);
            DB::table('pos_audit')->insert(['actor_id' => $current->id, 'branch_id' => $current->branch_id, 'action' => 'return.inspect', 'source_id' => $id, 'metadata' => CanonicalJson::encode(['decision' => $command['decision'], 'reason' => $command['reason']]), 'created_at' => now()]);

            return $payload;
        });
    }
}
