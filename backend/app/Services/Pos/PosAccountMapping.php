<?php

namespace App\Services\Pos;

use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;

final readonly class PosAccountMapping
{
    public static function forProvisioning(string $orgId, array $codes): self
    {
        $base = self::validated($orgId, $codes);
        $mapped = $base->codes;
        foreach (['cash_clearing' => 'asset', 'waste' => 'expense'] as $key => $type) {
            if (! isset($codes[$key]) || $codes[$key] === '') {
                continue;
            }
            abort_unless(is_string($codes[$key]) && ! in_array($codes[$key], $mapped, true) && ChartOfAccount::where('org_id', $orgId)->where('code', $codes[$key])->where('account_type', $type)->where('is_postable', true)->where('status', 'active')->exists(), 422, 'Optional POS account mapping is invalid.');
            $mapped[$key] = $codes[$key];
        }

        return new self($orgId, $mapped);
    }

    private function __construct(public string $orgId, private array $codes) {}

    /** Only persisted ERP provisioning is authoritative; callers cannot construct arbitrary mappings. */
    public static function forInstance(string $instanceId): self
    {
        $instance = DB::table('pos_instances')->where('id', $instanceId)->where('active', true)->firstOrFail();
        $codes = json_decode($instance->account_codes, true, flags: JSON_THROW_ON_ERROR);

        return self::validated($instance->org_id, $codes);
    }

    public static function forSale(string $instanceId, string $saleId): self
    {
        $instance = DB::table('pos_instances')->where('id', $instanceId)->where('active', true)->firstOrFail();
        $sale = DB::table('pos_import_sales')->where('id', $saleId)->where('pos_instance_id', $instanceId)->firstOrFail();
        abort_unless($sale->account_snapshot, 422, 'Original POS account mapping unavailable.');

        return self::validated($instance->org_id, json_decode($sale->account_snapshot, true, flags: JSON_THROW_ON_ERROR));
    }

    public static function forCashMovement(string $instanceId): self
    {
        $base = self::forInstance($instanceId);
        $instance = DB::table('pos_instances')->where('id', $instanceId)->where('active', true)->firstOrFail();
        $codes = json_decode($instance->account_codes, true, flags: JSON_THROW_ON_ERROR);
        $clearing = $codes['cash_clearing'] ?? null;
        abort_unless(is_string($clearing) && ! in_array($clearing, $base->codes, true) && ChartOfAccount::where('org_id', $base->orgId)->where('code', $clearing)->where('account_type', 'asset')->where('is_postable', true)->where('status', 'active')->exists(), 422, 'Explicit POS cash clearing account required.');

        return new self($base->orgId, $base->codes + ['cash_clearing' => $clearing]);
    }

    private static function validated(string $orgId, array $codes): self
    {
        foreach (['drawer' => 'asset', 'receivable' => 'asset', 'revenue' => 'revenue', 'vat' => 'liability', 'inventory' => 'asset', 'cogs' => 'expense'] as $name => $type) {
            abort_unless(isset($codes[$name]) && is_string($codes[$name]) && ChartOfAccount::where('org_id', $orgId)->where('code', $codes[$name])->where('account_type', $type)->where('is_postable', true)->where('status', 'active')->exists(), 422, 'POS account mapping missing or invalid.');
        }
        $codes = array_intersect_key($codes, array_flip(['drawer', 'receivable', 'revenue', 'vat', 'inventory', 'cogs']));
        abort_unless(count(array_unique(array_values($codes))) === count($codes), 422, 'POS account mappings must use distinct accounts.');

        return new self($orgId, $codes);
    }

    public static function forKitchen(string $instanceId, string $consumptionId): self
    {
        $instance = DB::table('pos_instances')->where('id', $instanceId)->where('active', true)->firstOrFail();
        $row = DB::table('pos_import_kitchen_consumptions')->where('id', $consumptionId)->where('pos_instance_id', $instanceId)->firstOrFail();
        $codes = json_decode($row->account_snapshot, true, flags: JSON_THROW_ON_ERROR);
        $base = self::validated($instance->org_id, $codes);

        return isset($codes['waste']) ? self::withWaste($base, $codes['waste']) : $base;
    }

    public static function forWaste(string $instanceId, ?string $consumptionId = null): self
    {
        $base = $consumptionId === null ? self::forInstance($instanceId) : self::forKitchen($instanceId, $consumptionId);
        $codes = json_decode(DB::table('pos_instances')->where('id', $instanceId)->value('account_codes'), true, flags: JSON_THROW_ON_ERROR);

        return self::withWaste($base, $codes['waste'] ?? null);
    }

    private static function withWaste(self $base, mixed $code): self
    {
        abort_unless(is_string($code) && ! in_array($code, $base->codes, true) && ChartOfAccount::where('org_id', $base->orgId)->where('code', $code)->where('account_type', 'expense')->where('is_postable', true)->where('status', 'active')->exists(), 422, 'Explicit POS waste expense account required.');

        return new self($base->orgId, $base->codes + ['waste' => $code]);
    }

    public function snapshot(string $orgId): array
    {
        abort_unless($orgId === $this->orgId, 403, 'Account mapping scope mismatch.');

        return $this->codes;
    }

    public function code(string $name, string $orgId): string
    {
        abort_unless($orgId === $this->orgId && isset($this->codes[$name]), 403, 'Account mapping scope mismatch.');

        return $this->codes[$name];
    }
}
