<?php

namespace App\Jobs;

use App\Models\ETaxConfig;
use App\Models\ETaxDocument;
use App\Models\ETaxSubmissionAttempt;
use App\Services\ETax\ETaxDocumentService;
use App\Services\ETax\ETaxSubmissionAdapter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SubmitETaxDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $documentId) {}

    public function handle(ETaxDocumentService $documents, ETaxSubmissionAdapter $adapter): void
    {
        $document = ETaxDocument::findOrFail($this->documentId);
        $config = ETaxConfig::where('org_id', $document->org_id)->first();
        $attempt = ETaxSubmissionAttempt::create([
            'org_id' => $document->org_id,
            'e_tax_document_id' => $document->id,
            'attempt_no' => ETaxSubmissionAttempt::where('e_tax_document_id', $document->id)->max('attempt_no') + 1,
            'status' => 'processing',
            'provider_code' => $config?->provider_code,
            'started_at' => now(),
        ]);

        try {
            if (! $config || $config->mode !== 'provider') {
                throw new \RuntimeException('A certified provider configuration is required before submission.');
            }
            $result = $adapter->submit($config, $document, $documents->xml($document));
            $document->update(['status' => 'submitted', 'submitted_at' => now(), 'last_error' => null]);
            $attempt->update(['status' => 'succeeded', 'external_reference' => $result['external_reference'] ?? null, 'response_code' => $result['response_code'] ?? null, 'response_message' => $result['response_message'] ?? null, 'finished_at' => now()]);
        } catch (Throwable $exception) {
            $document->update(['status' => 'rejected', 'rejected_at' => now(), 'last_error' => $exception->getMessage()]);
            $attempt->update(['status' => 'failed', 'response_message' => $exception->getMessage(), 'finished_at' => now()]);
            throw $exception;
        }
    }
}
