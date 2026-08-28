<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\Payment;
use App\Models\StoredFile;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function download(Request $request, StoredFile $file): StreamedResponse
    {
        abort_unless($file->org_id === $request->user()->org_id, 403);
        $this->authorizeParent($request, $file);
        abort_unless(Storage::disk('local')->exists($file->storage_key), 404);

        AuditLog::create([
            'org_id' => $file->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => 'file.download',
            'entity_type' => 'file',
            'entity_id' => $file->id,
            'before_json' => null,
            'after_json' => $file->only(['file_name', 'mime_type', 'size_bytes', 'entity_type', 'entity_id']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return Storage::disk('local')->download($file->storage_key, $file->file_name);
    }

    private function authorizeParent(Request $request, StoredFile $file): void
    {
        if ($file->entity_type === 'payment') {
            abort_unless($request->user()->hasPermissionCode('payments.view'), 403);
            $payment = Payment::where('id', $file->entity_id)->where('org_id', $request->user()->org_id)->firstOrFail();
            abort_unless($payment->attachment_file_id === $file->id, 403);

            return;
        }

        if ($file->entity_type === 'expense') {
            abort_unless($request->user()->hasPermissionCode('expenses.view'), 403);
            $expense = Expense::where('id', $file->entity_id)->where('org_id', $request->user()->org_id)->firstOrFail();
            abort_unless($expense->receipt_file_id === $file->id, 403);

            return;
        }

        if ($file->entity_type === 'voucher') {
            abort_unless($request->user()->hasPermissionCode('vouchers.view'), 403);
            $voucher = Voucher::where('id', $file->entity_id)->where('org_id', $request->user()->org_id)->firstOrFail();
            abort_unless($voucher->attachment_file_id === $file->id, 403);

            return;
        }

        if ($file->entity_type === 'fixed_asset') {
            abort_unless($request->user()->hasPermissionCode('fixed_assets.view'), 403);
            $asset = FixedAsset::where('id', $file->entity_id)->where('org_id', $request->user()->org_id)->firstOrFail();
            abort_unless($asset->attachment_file_id === $file->id, 403);

            return;
        }

        abort(403);
    }
}
