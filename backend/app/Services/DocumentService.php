<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\DocumentVersion;
use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\Payment;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    public const MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    public function create(User $user, array $data, UploadedFile $upload): Document
    {
        return DB::transaction(function () use ($user, $data, $upload): Document {
            $document = Document::create($data + ['org_id' => $user->org_id, 'owner_user_id' => $user->id, 'status' => 'active']);
            $version = $this->storeVersion($user, $document, $upload, $data['change_note'] ?? null);
            $document->update(['current_version_id' => $version->id]);

            return $document->refresh()->load('currentVersion', 'category');
        });
    }

    public function appendVersion(User $user, Document $document, UploadedFile $upload, ?string $changeNote): DocumentVersion
    {
        abort_unless($document->org_id === $user->org_id && $document->status === 'active', 404);

        return DB::transaction(fn (): DocumentVersion => $this->storeVersion($user, $document->fresh(), $upload, $changeNote));
    }

    public function link(User $user, Document $document, string $type, string $id, string $role): DocumentLink
    {
        abort_unless($document->org_id === $user->org_id, 404);
        $models = ['customer' => Customer::class, 'deal' => Deal::class, 'supplier' => Supplier::class, 'purchase_order' => PurchaseOrder::class, 'payment' => Payment::class, 'expense' => Expense::class, 'voucher' => Voucher::class, 'project' => Project::class, 'task' => Task::class, 'fixed_asset' => FixedAsset::class, 'bank_account' => BankAccount::class, 'accounting_period' => AccountingPeriod::class, 'user' => User::class];
        abort_unless(isset($models[$type]), 422, 'Unsupported document link type.');
        abort_unless($models[$type]::where('id', $id)->where('org_id', $user->org_id)->exists(), 404);

        return DocumentLink::firstOrCreate(['document_id' => $document->id, 'linkable_type' => $type, 'linkable_id' => $id, 'role' => $role], ['org_id' => $user->org_id, 'linked_by' => $user->id]);
    }

    private function storeVersion(User $user, Document $document, UploadedFile $upload, ?string $changeNote): DocumentVersion
    {
        $extension = strtolower($upload->extension() ?: $upload->getClientOriginalExtension());
        abort_unless(in_array($extension, self::MIMES, true), 422, 'Invalid document file type.');
        $mimeTypes = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'webp' => ['image/webp']];
        abort_unless(in_array($upload->getMimeType(), $mimeTypes[$extension], true), 422, 'Invalid document MIME type.');
        $checksum = hash_file('sha256', $upload->getRealPath());
        $existing = DocumentVersion::where('org_id', $user->org_id)->where('checksum_sha256', $checksum)->first();
        $storageKey = $existing?->storage_key;
        if (! $storageKey) {
            $storageKey = sprintf('tenants/%s/documents/%s/%s.%s', $user->org_id, now()->format('Y/m'), Str::uuid(), $extension);
            Storage::disk('local')->put($storageKey, file_get_contents($upload->getRealPath()));
        }
        $versionNo = ((int) $document->versions()->lockForUpdate()->max('version_no')) + 1;

        return DocumentVersion::create(['org_id' => $user->org_id, 'document_id' => $document->id, 'version_no' => $versionNo, 'storage_key' => $storageKey, 'original_name' => basename($upload->getClientOriginalName()), 'mime_type' => $upload->getMimeType(), 'size_bytes' => $upload->getSize(), 'checksum_sha256' => $checksum, 'scan_status' => app()->environment(['local', 'testing']) ? 'clean' : 'pending_scan', 'change_note' => $changeNote, 'uploaded_by' => $user->id]);
    }
}
