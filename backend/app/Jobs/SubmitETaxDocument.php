<?php

namespace App\Jobs;

use App\Models\ETaxConfig;
use App\Models\ETaxDocument;
use App\Models\ETaxSubmissionAttempt;
use App\Services\ETax\ETaxDocumentService;
use App\Services\ETax\MockETaxSubmissionAdapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class SubmitETaxDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $documentId)
    {
        $this->onQueue('etax');
    }

    public function handle(ETaxDocumentService $documents, MockETaxSubmissionAdapter $adapter): void
    {
        DB::transaction(function () use ($documents, $adapter) {
            // Local mock only. A live adapter must implement durable claim, network call and inquiry/reconciliation separately.
            $document = ETaxDocument::whereKey($this->documentId)->lockForUpdate()->firstOrFail();
            if ($document->status !== 'queued' || $document->attempts()->count() >= 3) {
                return;
            }
            $config = ETaxConfig::where('org_id', $document->org_id)->first();
            $attempt = ETaxSubmissionAttempt::create([
                'org_id' => $document->org_id,
                'e_tax_document_id' => $document->id,
                'attempt_no' => ETaxSubmissionAttempt::where('e_tax_document_id', $document->id)->max('attempt_no') + 1,
                'status' => 'processing',
                'provider_code' => 'mock',
                'started_at' => now(),
            ]);

            try {
                if (! $config || $config->mode !== 'mock') {
                    throw new \RuntimeException('Mock mode is not enabled.');
                }
                $result = $adapter->submit($config, $document, $documents->xml($document));
                if (($result['response_message'] ?? null) !== 'SIMULATED_ONLY') {
                    throw new \RuntimeException('Unexpected mock response.');
                }
                $document->update(['status' => 'simulated', 'last_error' => null]);
                $attempt->update(['status' => 'simulated', 'external_reference' => 'MOCK-'.$document->id, 'response_code' => 200, 'response_message' => 'SIMULATED_ONLY', 'finished_at' => now()]);
            } catch (Throwable $exception) {
                $document->update(['status' => 'error', 'last_error' => 'MOCK_FAILED: check mode, XML integrity and required fields.']);
                $attempt->update(['status' => 'failed', 'response_message' => 'MOCK_FAILED', 'finished_at' => now()]);
            }
        }, 3);
    }
}
