<?php

namespace Pos\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Support\CanonicalJson;

class CheckoutService
{
    public function openShift(Staff $staff, string $terminalId, string $opening): string
    {
        abort_unless(in_array($staff->role, ['cashier', 'manager', 'pharmacist'], true), 403);
        $this->money($opening);

        return DB::transaction(function () use ($staff, $terminalId, $opening) {
            $this->terminal($staff, $terminalId);
            abort_if(DB::table('pos_shifts')->where('terminal_id', $terminalId)->where('status', 'open')->exists(), 409, 'Terminal shift already open.');
            $id = (string) Str::uuid();
            DB::table('pos_shifts')->insert(['id' => $id, 'terminal_id' => $terminalId, 'staff_id' => $staff->id, 'status' => 'open', 'opening_cash' => $opening, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($staff, 'shift.open', $id);

            return $id;
        });
    }

    public function closeShift(Staff $staff, string $shiftId, string $counted): array
    {
        abort_unless(in_array($staff->role, ['cashier', 'manager', 'pharmacist'], true), 403);
        $this->money($counted);

        return DB::transaction(function () use ($staff, $shiftId, $counted) {
            $reference = DB::table('pos_shifts')->where('id', $shiftId)->first();
            abort_unless($reference, 404);
            $this->terminal($staff, $reference->terminal_id);
            $shift = DB::table('pos_shifts')->where('id', $shiftId)->lockForUpdate()->first();
            abort_unless($shift->staff_id === $staff->id || $staff->role === 'manager', 403);
            abort_unless($shift->status === 'open', 409, 'Shift is not open.');
            $expected = app(ShiftCashService::class)->balance($shift);
            $result = ['expected_cash' => $expected, 'counted_cash' => $counted, 'over_short' => bcsub($counted, $expected, 2)];
            DB::table('pos_shifts')->where('id', $shiftId)->update($result + ['status' => 'closed', 'updated_at' => now()]);
            $this->audit($staff, 'shift.close', $shiftId, $result);

            return $result;
        });
    }

    public function checkout(Staff $staff, array $input): array
    {
        abort_if(app()->isProduction() && ! config('pos.release_approved'), 503, 'POS release acceptance is pending.');
        abort_unless($staff->active && in_array($staff->role, ['cashier', 'manager', 'pharmacist'], true), 403);
        $source = validator($input, ['customer_request_id' => 'sometimes|uuid', 'discount_approval_id' => 'sometimes|uuid', 'kitchen_line_ids' => 'sometimes|array|min:1|max:100', 'kitchen_line_ids.*' => 'required|uuid|distinct'])->validate();
        $input = validator($input, ['terminal_id' => 'required|uuid', 'request_key' => 'required|string|max:100', 'catalog_version' => 'required|string|max:80', 'items' => 'required|array|min:1|max:100', 'items.*.product_id' => 'required|uuid|distinct', 'items.*.unit' => 'sometimes|string|max:30', 'items.*.quantity' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,4})?$/D'], 'cash_tendered' => ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/D'], 'prescription_ids' => 'sometimes|array|max:10', 'prescription_ids.*' => 'required|uuid|distinct'])->validate();
        $input = array_merge($input, $source);
        $hash = hash('sha256', CanonicalJson::encode($input));

        return DB::transaction(function () use ($staff, $input, $hash) {
            $managerId = isset($input['discount_approval_id']) ? DB::table('pos_discount_approvals')->where('id', $input['discount_approval_id'])->where('branch_id', $staff->branch_id)->value('manager_id') : null;
            $lockedStaff = Staff::whereIn('id', array_filter([$staff->id, $managerId]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $current = $lockedStaff->get($staff->id);
            abort_unless($current && $current->active && $current->branch_id === $staff->branch_id && $current->role === $staff->role, 403);
            $terminal = $this->terminal($staff, $input['terminal_id']);
            $existing = DB::table('pos_orders')->where('branch_id', $staff->branch_id)->where('request_key', $input['request_key'])->first();
            if ($existing) {
                abort_unless($existing->staff_id === $staff->id && hash_equals($existing->request_hash, $hash), 409, 'Checkout identity conflict.');

                return json_decode($existing->snapshot, true, flags: JSON_THROW_ON_ERROR);
            }
            $branch = DB::table('pos_branches')->where('id', $staff->branch_id)->where('active', true)->lockForUpdate()->first();
            abort_unless($branch && (in_array($branch->business_type, ['retail', 'pharmacy'], true) || ($branch->business_type === 'restaurant' && isset($input['kitchen_line_ids']))), 422, 'Checkout capability unavailable.');
            abort_if(isset($input['kitchen_line_ids']) && $branch->business_type !== 'restaurant', 422, 'Restaurant source required.');
            if (! empty($input['customer_request_id'])) {
                app(PublicMenuService::class)->claim($staff, $input['customer_request_id'], $input['catalog_version'], $input['items']);
            }
            $shift = DB::table('pos_shifts')->where('terminal_id', $terminal->id)->where('staff_id', $staff->id)->where('status', 'open')->lockForUpdate()->first();
            abort_unless($shift, 422, 'Open your shift before checkout.');
            $catalog = DB::table('pos_catalogs')->where('branch_id', $staff->branch_id)->where('version', $input['catalog_version'])->when(! isset($input['kitchen_line_ids']), fn ($q) => $q->where('valid_until', '>', now()))->first();
            abort_unless($catalog, 422, 'Catalog unavailable or stale.');
            $catalogItems = collect(json_decode($catalog->items, true, flags: JSON_THROW_ON_ERROR))->keyBy('product_id');
            $discount = isset($input['discount_approval_id']) ? app(DiscountService::class)->claim($staff, $input['discount_approval_id'], $input, $catalogItems->all()) : null;
            $restaurantRefs = isset($input['kitchen_line_ids']) ? app(RestaurantService::class)->claimBill($staff, $input) : null;
            $id = (string) Str::uuid();
            $lines = [];
            $subtotal = '0.00';
            $tax = '0.00';
            // The terminal and branch locks serialize shared-grant consumers across terminals.
            foreach ($input['items'] as $item) {
                $product = $catalogItems->get($item['product_id']);
                abort_unless($product && ($product['active'] ?? false) && bccomp($item['quantity'], '0', 4) > 0, 422, 'Product unavailable.');
                $resolved = app(PharmacyPolicy::class)->resolve($staff, $branch->business_type, $product, $item);
                $amount = $this->rounded(bcmul($item['quantity'], $resolved['unit_price'], 8));
                $discountAmount = $discount['discounts'][$item['product_id']] ?? '0.00';
                $amount = bcsub($amount, $discountAmount, 2);
                $lineTax = $this->rounded(bcdiv(bcmul($amount, $product['tax_rate'], 8), '100', 8));
                $allocations = $restaurantRefs === null ? $this->consume($staff->branch_id, $item['product_id'], $resolved['base_quantity'], $branch->timezone) : [];
                if ($restaurantRefs !== null) {
                    $resolved += ['stock_effect' => 'prepared', 'kitchen_refs' => $restaurantRefs[$item['product_id']]];
                    $resolved['base_step'] = '1.0000';
                }
                $lines[] = ['line_id' => (string) Str::uuid(), 'product_id' => $item['product_id'], 'name' => $product['name'], 'quantity' => $item['quantity'], 'tax_rate' => $product['tax_rate'], 'subtotal' => $amount, 'discount_amount' => $discountAmount, 'tax_amount' => $lineTax, 'total' => bcadd($amount, $lineTax, 2), 'allocations' => $allocations] + $resolved;
                $subtotal = bcadd($subtotal, $amount, 2);
                $tax = bcadd($tax, $lineTax, 2);
            }
            $total = bcadd($subtotal, $tax, 2);
            abort_if(bccomp($total, '9999999999.99', 2) > 0, 422, 'Checkout total exceeds supported limit.');
            abort_unless(bccomp($total, '0', 2) > 0 && bccomp($input['cash_tendered'], $total, 2) >= 0, 422, 'Insufficient cash tendered.');
            $day = now($branch->timezone)->toDateString();
            DB::table('pos_receipt_sequences')->insertOrIgnore(['terminal_id' => $terminal->id, 'business_date' => $day, 'last_number' => 0]);
            $sequence = DB::table('pos_receipt_sequences')->where('terminal_id', $terminal->id)->where('business_date', $day)->lockForUpdate()->firstOrFail();
            $count = $sequence->last_number + 1;
            DB::table('pos_receipt_sequences')->where('id', $sequence->id)->update(['last_number' => $count]);
            $receipt = $branch->code.'-'.$terminal->code.'-'.str_replace('-', '', $day).'-'.str_pad((string) $count, 8, '0', STR_PAD_LEFT);
            $snapshot = ['sale_id' => $id, 'branch_id' => $branch->id, 'terminal_id' => $terminal->id, 'shift_id' => $shift->id, 'staff_id' => $staff->id, 'receipt_no' => $receipt, 'catalog_version' => $catalog->version, 'currency' => $branch->currency, 'subtotal' => $subtotal, 'tax_amount' => $tax, 'total' => $total, 'cash_tendered' => $input['cash_tendered'], 'change' => bcsub($input['cash_tendered'], $total, 2), 'lines' => $lines, 'occurred_at' => now()->utc()->toIso8601String(), 'business_timezone' => $branch->timezone] + ($discount ? ['discount_approval_id' => $discount['id'], 'discount_approved_by' => $discount['manager_id'], 'discount_approved_at' => $discount['approved_at']] : []);
            DB::table('pos_orders')->insert(['id' => $id, 'branch_id' => $branch->id, 'terminal_id' => $terminal->id, 'shift_id' => $shift->id, 'staff_id' => $staff->id, 'request_key' => $input['request_key'], 'request_hash' => $hash, 'receipt_no' => $receipt, 'status' => 'completed', 'catalog_version' => $catalog->version, 'currency' => $branch->currency, 'subtotal' => $subtotal, 'tax_amount' => $tax, 'total' => $total, 'snapshot' => CanonicalJson::encode($snapshot), 'created_at' => now(), 'updated_at' => now()]);
            if ($discount) {
                abort_unless(DB::table('pos_discount_approvals')->where('id', $discount['id'])->whereNull('sale_id')->update(['sale_id' => $id, 'updated_at' => now()]) === 1, 409, 'Discount approval already consumed.');
            }
            if ($restaurantRefs !== null) {
                app(RestaurantService::class)->markBilled($staff, $restaurantRefs, $id);
            }
            foreach ($lines as $line) {
                DB::table('pos_order_items')->insert(['id' => $line['line_id'], 'order_id' => $id, 'product_id' => $line['product_id'], 'quantity' => $line['quantity'], 'snapshot' => CanonicalJson::encode($line), 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('pos_payments')->insert(['id' => (string) Str::uuid(), 'order_id' => $id, 'method' => 'cash', 'amount' => $total, 'tendered' => $input['cash_tendered'], 'change' => $snapshot['change'], 'created_at' => now(), 'updated_at' => now()]);
            if (! empty($input['prescription_ids'])) {
                app(PrescriptionService::class)->attach($staff, $input['prescription_ids'], $id);
            }
            app(OutboxService::class)->append('sale.completed', $id, $snapshot);
            if (! empty($input['customer_request_id'])) {
                DB::table('pos_customer_requests')->where('id', $input['customer_request_id'])->where('branch_id', $staff->branch_id)->update(['status' => 'completed', 'sale_id' => $id, 'updated_at' => now()]);
            }
            $this->audit($staff, 'sale.completed', $id);
            if (config('pos.local_tax_documents_enabled') === true) {
                app(TaxDocumentService::class)->issue($staff, ['sale_id' => $id, 'terminal_id' => $terminal->id, 'kind' => 'ABB', 'request_key' => 'auto-abb-'.$id]);
            }

            return $snapshot;
        });
    }

    private function terminal(Staff $staff, string $id): object
    {
        abort_unless($staff->active, 403);

        return DB::table('pos_terminals')->where('branch_id', $staff->branch_id)->where('active', true)->where('id', $id)->lockForUpdate()->firstOrFail();
    }

    private function consume(string $branchId, string $productId, string $quantity, string $timezone): array
    {
        return app(EntitlementAllocator::class)->take($branchId, $productId, $quantity, $timezone);
    }

    private function money(string $value): void
    {
        abort_unless(preg_match('/^\d{1,10}(\.\d{1,2})?$/D', $value), 422, 'Invalid money precision.');
    }

    private function rounded(string $value): string
    {
        return bcadd($value, '0.005', 2);
    }

    private function audit(Staff $staff, string $action, string $source, array $metadata = []): void
    {
        DB::table('pos_audit')->insert(['actor_id' => $staff->id, 'branch_id' => $staff->branch_id, 'action' => $action, 'source_id' => $source, 'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
