<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Document;
use App\Models\RetentionPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentRetentionService
{
    public function applyPolicy(Document $document): Document
    {
        $policy = $document->retention_policy_id
            ? RetentionPolicy::where('org_id', $document->org_id)->find($document->retention_policy_id)
            : null;

        if (! $policy) {
            return $document;
        }

        $retentionStart = ($document->expires_at?->copy() ?? $document->created_at ?? now())->startOfDay();
        $document->update([
            'retention_until' => $retentionStart->addDays($policy->minimum_retention_days)->endOfDay(),
            'legal_hold' => $document->legal_hold || $policy->legal_hold_required,
        ]);

        return $document->refresh();
    }

    public function quarantineFailedDocuments(bool $dryRun = false): int
    {
        $count = 0;
        Document::query()->where('status', 'active')->whereHas('currentVersion', fn ($query) => $query->whereIn('scan_status', ['failed', 'infected']))->orderBy('id')->chunkById(100, function ($documents) use (&$count, $dryRun): void {
            foreach ($documents as $document) {
                $count++;
                if (! $dryRun) {
                    $this->transition($document, 'quarantined', 'document.scan.quarantined');
                }
            }
        });

        return $count;
    }

    public function archiveDueDocuments(bool $dryRun = false): int
    {
        $count = 0;
        Document::query()->where('status', 'active')->where('legal_hold', false)->whereNotNull('retention_until')->where('retention_until', '<=', now())->orderBy('id')->chunkById(100, function ($documents) use (&$count, $dryRun): void {
            foreach ($documents as $document) {
                $count++;
                if (! $dryRun) {
                    $this->transition($document, 'archived', 'document.retention.archive');
                }
            }
        });

        return $count;
    }

    public function purgeArchivedDocuments(bool $dryRun = false): int
    {
        $count = 0;
        Document::query()->where('status', 'archived')->where('legal_hold', false)->whereNotNull('retention_until')->where('retention_until', '<=', now())->orderBy('id')->chunkById(100, function ($documents) use (&$count, $dryRun): void {
            foreach ($documents as $document) {
                $count++;
                if (! $dryRun) {
                    $this->purge($document);
                }
            }
        });

        return $count;
    }

    private function transition(Document $document, string $status, string $action): void
    {
        $before = $document->only(['status', 'retention_until', 'legal_hold']);
        $document->update(['status' => $status]);
        $this->audit($document, $action, $before, $document->fresh()->only(['status', 'retention_until', 'legal_hold']));
    }

    private function purge(Document $document): void
    {
        DB::transaction(function () use ($document): void {
            $document->load('versions', 'links');
            foreach ($document->versions as $version) {
                $isShared = $version->storage_key && Document::withTrashed()->whereKeyNot($document->id)->whereHas('versions', fn ($query) => $query->where('storage_key', $version->storage_key))->exists();
                if (! $isShared) {
                    Storage::disk('local')->delete($version->storage_key);
                }
            }
            $before = $document->only(['status', 'retention_until', 'legal_hold']);
            $document->links()->delete();
            $document->versions()->delete();
            $document->forceDelete();
            $this->audit($document, 'document.retention.purge', $before, null);
        });
    }

    private function audit(Document $document, string $action, ?array $before, ?array $after): void
    {
        AuditLog::create(['org_id' => $document->org_id, 'actor_user_id' => null, 'action' => $action, 'entity_type' => 'document', 'entity_id' => $document->id, 'before_json' => $before, 'after_json' => $after]);
    }
}
