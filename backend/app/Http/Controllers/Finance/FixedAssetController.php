<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AssetCategory;
use App\Models\AuditLog;
use App\Models\ChartOfAccount;
use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\GoodsReceipt;
use App\Models\User;
use App\Services\ChartOfAccountProvisioner;
use App\Services\FixedAssetService;
use App\Support\FileAttachmentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class FixedAssetController extends Controller
{
    public function index(Request $request, ChartOfAccountProvisioner $accounts): Response
    {
        $orgId = $request->user()->org_id;
        $accounts->ensure($orgId);

        return Inertia::render('Finance/FixedAssets', [
            'categories' => AssetCategory::where('org_id', $orgId)->with(['assetAccount:id,code,name', 'accumulatedDepreciationAccount:id,code,name', 'depreciationExpenseAccount:id,code,name'])->orderBy('code')->get(),
            'assets' => FixedAsset::where('org_id', $orgId)->with(['category:id,code,name', 'custodian:id,name', 'attachment:id,file_name,mime_type,size_bytes', 'depreciations'])->latest('available_for_use_date')->get(),
            'accounts' => ChartOfAccount::where('org_id', $orgId)->where('status', 'active')->where('is_postable', true)->orderBy('code')->get(['id', 'code', 'name', 'account_type']),
            'users' => User::where('org_id', $orgId)->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'expenses' => Expense::where('org_id', $orgId)->whereIn('status', ['approved', 'paid'])->latest('expense_date')->take(100)->get(['id', 'expense_no', 'title', 'amount', 'expense_date', 'status']),
            'goodsReceipts' => GoodsReceipt::where('org_id', $orgId)->with('items:id,goods_receipt_id,line_total,tax_amount')->latest('received_date')->take(100)->get(['id', 'grn_no', 'received_date']),
            'summary' => [
                'cost' => (string) FixedAsset::where('org_id', $orgId)->where('status', 'active')->sum('cost'),
                'accumulated_depreciation' => (string) FixedAsset::where('org_id', $orgId)->where('status', 'active')->sum('accumulated_depreciation'),
                'net_book_value' => (string) FixedAsset::where('org_id', $orgId)->where('status', 'active')->sum('net_book_value'),
            ],
        ]);
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('asset_categories')->where('org_id', $orgId)],
            'name' => ['required', 'string', 'max:255'],
            'asset_account_id' => ['required', 'uuid', Rule::exists('chart_of_accounts', 'id')->where('org_id', $orgId)->where('account_type', 'asset')],
            'accumulated_depreciation_account_id' => ['required', 'uuid', Rule::exists('chart_of_accounts', 'id')->where('org_id', $orgId)->where('account_type', 'asset')],
            'depreciation_expense_account_id' => ['required', 'uuid', Rule::exists('chart_of_accounts', 'id')->where('org_id', $orgId)->where('account_type', 'expense')],
            'default_useful_life_months' => ['required', 'integer', 'min:1', 'max:1200'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        abort_if($data['asset_account_id'] === $data['accumulated_depreciation_account_id'], 422, 'Asset and accumulated depreciation accounts must be different.');
        $category = AssetCategory::create(array_merge($data, ['org_id' => $orgId, 'status' => 'active']));
        AuditLog::create(['org_id' => $orgId, 'actor_user_id' => $request->user()->id, 'action' => 'asset_category.create', 'entity_type' => 'asset_category', 'entity_id' => $category->id, 'after_json' => $category->only(['code', 'name', 'asset_account_id', 'accumulated_depreciation_account_id', 'depreciation_expense_account_id', 'default_useful_life_months']), 'request_id' => (string) Str::uuid()]);

        return back()->with('success', 'Asset category created.');
    }

    public function storeAsset(Request $request, FixedAssetService $assets): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate([
            'asset_category_id' => ['required', 'uuid', Rule::exists('asset_categories', 'id')->where('org_id', $orgId)->where('status', 'active')],
            'source_type' => ['required', Rule::in(['expense', 'goods_receipt'])],
            'source_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'available_for_use_date' => ['required', 'date'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_months' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'location' => ['nullable', 'string', 'max:255'],
            'custodian_user_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('org_id', $orgId)],
        ]);
        try {
            $assets->capitalize($orgId, $request->user()->id, $data);
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        return back()->with('success', 'Fixed asset capitalized and posted to GL.');
    }

    public function depreciate(Request $request, FixedAssetService $assets): RedirectResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);
        try {
            $count = $assets->runThroughMonth($request->user()->org_id, $data['month'].'-01', $request->user()->id);
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        return back()->with('success', "Posted {$count} depreciation record(s).");
    }

    public function dispose(Request $request, FixedAsset $fixedAsset, FixedAssetService $assets): RedirectResponse
    {
        abort_unless($fixedAsset->org_id === $request->user()->org_id, 404);
        $data = $request->validate([
            'status' => ['required', Rule::in(['disposed', 'written_off'])],
            'disposed_at' => ['required', 'date'],
            'disposal_proceeds' => ['nullable', 'numeric', 'min:0'],
            'disposal_reason' => ['required', 'string', 'max:2000'],
        ]);
        try {
            $assets->dispose($fixedAsset, $request->user()->id, $data);
        } catch (RuntimeException $exception) {
            abort(422, $exception->getMessage());
        }

        return back()->with('success', 'Fixed asset disposal posted to GL.');
    }

    public function storeAttachment(Request $request, FixedAsset $fixedAsset, FileAttachmentManager $files): RedirectResponse
    {
        abort_unless($fixedAsset->org_id === $request->user()->org_id, 404);
        $request->validate(['attachment' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:'.FileAttachmentManager::MAX_KILOBYTES]]);
        DB::transaction(function () use ($request, $fixedAsset, $files): void {
            $asset = FixedAsset::where('org_id', $request->user()->org_id)->lockForUpdate()->findOrFail($fixedAsset->id);
            $previous = $asset->attachment_file_id;
            $files->delete($asset->attachment);
            $file = $files->store($request, $request->file('attachment'), 'fixed_asset', $asset->id, 'fixed_asset_proof');
            $asset->update(['attachment_file_id' => $file->id, 'updated_by' => $request->user()->id]);
            AuditLog::create(['org_id' => $asset->org_id, 'actor_user_id' => $request->user()->id, 'action' => 'fixed_asset.attachment_upload', 'entity_type' => 'fixed_asset', 'entity_id' => $asset->id, 'before_json' => ['attachment_file_id' => $previous], 'after_json' => ['attachment_file_id' => $file->id], 'request_id' => (string) Str::uuid()]);
        });

        return back()->with('success', 'Fixed asset proof uploaded.');
    }
}
