<?php

namespace App\Services;

use App\Models\AssetCategory;
use App\Models\AssetDepreciation;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\GoodsReceipt;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class FixedAssetService
{
    public function __construct(
        private readonly JournalPostingService $journals,
        private readonly NumberSequenceService $numbers,
    ) {}

    /** @param array<string, mixed> $data */
    public function capitalize(string $orgId, string $actorId, array $data): FixedAsset
    {
        return DB::transaction(function () use ($orgId, $actorId, $data): FixedAsset {
            $source = $this->source($orgId, $data['source_type'], $data['source_id']);
            $category = AssetCategory::where('org_id', $orgId)->where('status', 'active')->lockForUpdate()->findOrFail($data['asset_category_id']);
            if (FixedAsset::where('org_id', $orgId)->where('capitalization_source_type', $data['source_type'])->where('capitalization_source_id', $data['source_id'])->exists()) {
                throw new RuntimeException('This source has already been capitalized as a fixed asset.');
            }

            $cost = $source['cost'];
            $salvage = round((float) ($data['salvage_value'] ?? 0), 2);
            if ($salvage < 0 || $salvage >= $cost) {
                throw new RuntimeException('Salvage value must be at least zero and below asset cost.');
            }
            $start = Carbon::parse($data['available_for_use_date'])->startOfMonth();
            $asset = FixedAsset::create([
                'org_id' => $orgId,
                'asset_category_id' => $category->id,
                'asset_no' => $this->numbers->next($orgId, 'fixed_asset'),
                'name' => $data['name'],
                'description' => $data['description'] ?? $source['label'],
                'capitalization_source_type' => $data['source_type'],
                'capitalization_source_id' => $data['source_id'],
                'acquisition_date' => $source['date'],
                'available_for_use_date' => $data['available_for_use_date'],
                'depreciation_start_date' => $start->toDateString(),
                'cost' => $cost,
                'salvage_value' => $salvage,
                'useful_life_months' => $data['useful_life_months'] ?? $category->default_useful_life_months,
                'depreciation_method' => 'straight_line',
                'accumulated_depreciation' => 0,
                'net_book_value' => $cost,
                'status' => 'active',
                'location' => $data['location'] ?? null,
                'custodian_user_id' => $data['custodian_user_id'] ?? null,
                'attachment_file_id' => $data['attachment_file_id'] ?? null,
                'created_by' => $actorId,
                'updated_by' => $actorId,
            ]);
            $this->journals->post($orgId, $actorId, 'fixed_asset', $asset->id, 'capitalized', $asset->available_for_use_date->toDateString(), 'Capitalize fixed asset '.$asset->asset_no, [
                ['account_code' => $category->assetAccount()->value('code'), 'debit' => $cost, 'description' => 'Fixed asset capitalization'],
                ['account_code' => $source['credit_account'], 'credit' => $cost, 'description' => 'Reclassify '.$source['label']],
            ]);
            $this->audit($orgId, $actorId, 'fixed_asset.capitalize', $asset, ['source_type' => $data['source_type'], 'source_id' => $data['source_id'], 'cost' => $cost]);

            return $asset->load('category');
        });
    }

    public function runThroughMonth(string $orgId, Carbon|string $month, ?string $actorId = null): int
    {
        $month = Carbon::parse($month)->startOfMonth();
        $count = 0;
        FixedAsset::where('org_id', $orgId)->where('status', 'active')->whereDate('depreciation_start_date', '<=', $month->endOfMonth()->toDateString())->orderBy('id')->chunkById(100, function ($assets) use ($month, $actorId, &$count): void {
            foreach ($assets as $asset) {
                $count += $this->depreciateAssetThroughMonth($asset->id, $month, $actorId);
            }
        });

        return $count;
    }

    /** @param array<string, mixed> $data */
    public function dispose(FixedAsset $asset, string $actorId, array $data): FixedAsset
    {
        $date = Carbon::parse($data['disposed_at']);
        $priorMonth = $date->copy()->startOfMonth()->subMonth();
        if ($priorMonth->greaterThanOrEqualTo($asset->depreciation_start_date->copy()->startOfMonth())) {
            $this->runThroughMonth($asset->org_id, $priorMonth, $actorId);
        }

        return DB::transaction(function () use ($asset, $actorId, $data, $date): FixedAsset {
            $asset = FixedAsset::whereKey($asset->id)->lockForUpdate()->with('category')->firstOrFail();
            if ($asset->status !== 'active') {
                throw new RuntimeException('Only active assets can be disposed or written off.');
            }
            $status = $data['status'];
            $proceeds = $status === 'disposed' ? round((float) ($data['disposal_proceeds'] ?? 0), 2) : 0.0;
            $netBookValue = round((float) $asset->net_book_value, 2);
            $accumulated = round((float) $asset->accumulated_depreciation, 2);
            $difference = round($proceeds - $netBookValue, 2);
            $category = $asset->category;
            $lines = [
                $accumulated > 0 ? ['account_code' => $category->accumulatedDepreciationAccount()->value('code'), 'debit' => $accumulated, 'description' => 'Remove accumulated depreciation'] : null,
                $proceeds > 0 ? ['account_code' => '1110', 'debit' => $proceeds, 'description' => 'Asset disposal proceeds'] : null,
                $difference < 0 ? ['account_code' => '5300', 'debit' => abs($difference), 'description' => 'Loss on asset disposal'] : null,
                ['account_code' => $category->assetAccount()->value('code'), 'credit' => $asset->cost, 'description' => 'Remove fixed asset cost'],
                $difference > 0 ? ['account_code' => '5300', 'credit' => $difference, 'description' => 'Gain on asset disposal'] : null,
            ];
            $this->journals->post($asset->org_id, $actorId, 'fixed_asset', $asset->id, $status, $date->toDateString(), ucfirst(str_replace('_', ' ', $status)).' fixed asset '.$asset->asset_no, array_values(array_filter($lines)));
            $asset->update(['status' => $status, 'disposed_at' => $date->toDateString(), 'disposal_proceeds' => $proceeds, 'disposal_reason' => $data['disposal_reason'], 'updated_by' => $actorId]);
            $this->audit($asset->org_id, $actorId, 'fixed_asset.'.$status, $asset, ['proceeds' => $proceeds, 'reason' => $data['disposal_reason'], 'net_book_value' => $netBookValue]);

            return $asset->fresh('category');
        });
    }

    /** @return array{cost:float,credit_account:string,date:string,label:string} */
    private function source(string $orgId, string $type, string $id): array
    {
        if ($type === 'expense') {
            $expense = Expense::where('org_id', $orgId)->findOrFail($id);
            if (! in_array($expense->status, ['approved', 'paid'], true)) {
                throw new RuntimeException('Only approved or paid expenses can be capitalized.');
            }

            return ['cost' => round((float) $expense->amount, 2), 'credit_account' => '5200', 'date' => $expense->expense_date->toDateString(), 'label' => 'expense '.$expense->expense_no];
        }
        if ($type === 'goods_receipt') {
            $receipt = GoodsReceipt::where('org_id', $orgId)->with('items')->findOrFail($id);
            $cost = round((float) $receipt->items->sum(fn ($item) => (float) $item->line_total - (float) $item->tax_amount), 2);
            if ($cost <= 0) {
                throw new RuntimeException('Goods receipt has no capitalizable cost.');
            }

            return ['cost' => $cost, 'credit_account' => '1140', 'date' => $receipt->received_date->toDateString(), 'label' => 'goods receipt '.$receipt->grn_no];
        }

        throw new RuntimeException('Unsupported capitalization source. Capitalize approved expense or goods receipt.');
    }

    private function depreciateAssetThroughMonth(string $assetId, Carbon $targetMonth, ?string $actorId): int
    {
        return DB::transaction(function () use ($assetId, $targetMonth, $actorId): int {
            $asset = FixedAsset::whereKey($assetId)->lockForUpdate()->with('category')->firstOrFail();
            if ($asset->status !== 'active') {
                return 0;
            }
            $firstMonth = $asset->last_depreciated_for ? $asset->last_depreciated_for->copy()->addMonth()->startOfMonth() : $asset->depreciation_start_date->copy()->startOfMonth();
            $created = 0;
            while ($firstMonth->lessThanOrEqualTo($targetMonth)) {
                $remaining = round((float) $asset->net_book_value - (float) $asset->salvage_value, 2);
                if ($remaining <= 0) {
                    break;
                }
                $amount = min(round(((float) $asset->cost - (float) $asset->salvage_value) / $asset->useful_life_months, 2), $remaining);
                $event = 'depreciation:'.$firstMonth->format('Y-m');
                $journal = $this->journals->post($asset->org_id, $actorId, 'fixed_asset', $asset->id, $event, $firstMonth->copy()->endOfMonth()->toDateString(), 'Depreciation '.$asset->asset_no.' '.$firstMonth->format('Y-m'), [
                    ['account_code' => $asset->category->depreciationExpenseAccount()->value('code'), 'debit' => $amount, 'description' => 'Depreciation expense'],
                    ['account_code' => $asset->category->accumulatedDepreciationAccount()->value('code'), 'credit' => $amount, 'description' => 'Accumulated depreciation'],
                ]);
                $asset->accumulated_depreciation = round((float) $asset->accumulated_depreciation + $amount, 2);
                $asset->net_book_value = round((float) $asset->net_book_value - $amount, 2);
                $asset->last_depreciated_for = $firstMonth->toDateString();
                $asset->save();
                AssetDepreciation::firstOrCreate(['fixed_asset_id' => $asset->id, 'depreciation_month' => $firstMonth->toDateString()], ['org_id' => $asset->org_id, 'amount' => $amount, 'accumulated_depreciation_after' => $asset->accumulated_depreciation, 'net_book_value_after' => $asset->net_book_value, 'journal_entry_id' => $journal->id, 'created_by' => $actorId]);
                $created++;
                $firstMonth->addMonth();
            }

            return $created;
        });
    }

    private function audit(string $orgId, ?string $actorId, string $action, FixedAsset $asset, array $after): void
    {
        AuditLog::create(['org_id' => $orgId, 'actor_user_id' => $actorId, 'action' => $action, 'entity_type' => 'fixed_asset', 'entity_id' => $asset->id, 'after_json' => $after, 'request_id' => (string) Str::uuid()]);
    }
}
