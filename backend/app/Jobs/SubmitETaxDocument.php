<?php

namespace App\Jobs;

use App\Models\ETaxDocument;
use App\Services\ETax\ETaxProviderGatewayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SubmitETaxDocument implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $documentId)
    {
        $this->onQueue('etax');
    }

    public function handle(ETaxProviderGatewayService $gateway): void
    {
        $document = ETaxDocument::find($this->documentId);
        if (! $document || $document->status !== 'queued') {
            return;
        }

        if ($document->attempts()->count() >= 3) {
            $document->update(['status' => 'error', 'last_error' => 'Max submission retry attempts (3) exceeded.']);

            return;
        }

        $gateway->submit($document);
    }
}
