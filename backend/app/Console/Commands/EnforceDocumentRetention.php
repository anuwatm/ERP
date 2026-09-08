<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\DocumentRetentionService;
use Illuminate\Console\Command;

class EnforceDocumentRetention extends Command
{
    protected $signature = 'documents:enforce-retention {--purge : Permanently purge already archived documents after review} {--recalculate : Recalculate retention metadata before enforcement} {--dry-run : Report actions without changing data}';

    protected $description = 'Quarantine failed scans and archive or explicitly purge documents past retention.';

    public function handle(DocumentRetentionService $retention): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $recalculated = 0;
        if ($this->option('recalculate')) {
            Document::query()->whereNotNull('retention_policy_id')->orderBy('id')->chunkById(100, function ($documents) use ($retention, $dryRun, &$recalculated): void {
                foreach ($documents as $document) {
                    $recalculated++;
                    if (! $dryRun) {
                        $retention->applyPolicy($document);
                    }
                }
            });
        }

        $quarantined = $retention->quarantineFailedDocuments($dryRun);
        $archived = $retention->archiveDueDocuments($dryRun);
        $purged = $this->option('purge') ? $retention->purgeArchivedDocuments($dryRun) : 0;

        $this->info("Recalculated: {$recalculated}; quarantined: {$quarantined}; archived: {$archived}; purged: {$purged}.");

        return self::SUCCESS;
    }
}
