<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentLink;
use App\Models\DocumentVersion;
use App\Models\StoredFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackfillLegacyDocuments extends Command
{
    protected $signature = 'documents:backfill-legacy {--dry-run : Report without writing}';

    protected $description = 'Create idempotent DMS records for legacy files without moving storage objects';

    public function handle(): int
    {
        $created = 0;
        $linked = 0;
        $skipped = 0;
        StoredFile::query()->orderBy('id')->chunkById(100, function ($files) use (&$created, &$linked, &$skipped): void {
            foreach ($files as $file) {
                if (! in_array($file->entity_type, ['payment', 'expense', 'voucher', 'fixed_asset'], true)) {
                    $skipped++;

                    continue;
                }
                $document = Document::where('org_id', $file->org_id)->where('document_no', 'LEGACY-'.$file->id)->first();
                if (! $document && ! $this->option('dry-run')) {
                    $document = Document::create(['org_id' => $file->org_id, 'owner_user_id' => $file->uploaded_by, 'document_no' => 'LEGACY-'.$file->id, 'title' => $file->file_name, 'sensitivity' => in_array($file->entity_type, ['payment', 'expense', 'voucher'], true) ? 'finance_confidential' : 'org_internal', 'status' => 'active']);
                    $checksum = Storage::disk('local')->exists($file->storage_key) ? hash('sha256', Storage::disk('local')->get($file->storage_key)) : hash('sha256', $file->storage_key);
                    $version = DocumentVersion::create(['org_id' => $file->org_id, 'document_id' => $document->id, 'version_no' => 1, 'storage_key' => $file->storage_key, 'original_name' => $file->file_name, 'mime_type' => $file->mime_type, 'size_bytes' => $file->size_bytes, 'checksum_sha256' => $checksum, 'scan_status' => 'pending_scan', 'change_note' => 'Legacy file backfill', 'uploaded_by' => $file->uploaded_by]);
                    $document->update(['current_version_id' => $version->id]);
                    $created++;
                }
                if ($document && ! $this->option('dry-run')) {
                    DocumentLink::firstOrCreate(['document_id' => $document->id, 'linkable_type' => $file->entity_type, 'linkable_id' => $file->entity_id, 'role' => 'supporting'], ['org_id' => $file->org_id, 'linked_by' => $file->uploaded_by]);
                    $linked++;
                }
            }
        });
        $this->info("Documents created: {$created}; links reconciled: {$linked}; skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
