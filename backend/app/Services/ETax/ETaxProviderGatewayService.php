<?php

namespace App\Services\ETax;

use App\Models\AuditLog;
use App\Models\ETaxConfig;
use App\Models\ETaxDocument;
use App\Models\ETaxSubmissionAttempt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ETaxProviderGatewayService
{
    public function __construct(
        protected ETaxDocumentService $documents,
        protected ETaxXmlSignatureService $signer,
        protected MockETaxSubmissionAdapter $mockAdapter,
        protected RdApiSubmissionAdapter $providerAdapter
    ) {}

    /**
     * Sign an e-Tax document using XML-DSig enveloped signature.
     */
    public function sign(ETaxDocument $document, ?string $actorId = null): ETaxDocument
    {
        return DB::transaction(function () use ($document, $actorId) {
            $document = ETaxDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();

            if (in_array($document->status, ['submitted', 'accepted'], true)) {
                throw new ImmutableETaxDocumentException('Submitted or accepted documents cannot be re-signed.');
            }

            $rawXml = $this->documents->xml($document);
            $signedXml = $this->signer->sign($document, $rawXml);
            $newSha256 = hash('sha256', $signedXml);

            Storage::disk('local')->put($document->xml_storage_path, $signedXml);

            $document->update([
                'status' => 'signed',
                'signed_at' => now(),
                'xml_sha256' => $newSha256,
            ]);

            AuditLog::create([
                'org_id' => $document->org_id,
                'actor_user_id' => $actorId,
                'action' => 'e_tax.sign',
                'entity_type' => 'e_tax_document',
                'entity_id' => $document->id,
                'after_json' => ['sha256' => $newSha256, 'signature_algorithm' => 'xml-dsig-rsa-sha256'],
            ]);

            return $document;
        });
    }

    /**
     * Process submission for a document (handles both mock and live provider modes).
     */
    public function submit(ETaxDocument $document, ?string $actorId = null): void
    {
        DB::transaction(function () use ($document, $actorId) {
            $doc = ETaxDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();

            if ($doc->attempts()->count() >= 3) {
                $doc->update(['status' => 'error', 'last_error' => 'Max submission retry attempts (3) exceeded.']);

                return;
            }

            $config = ETaxConfig::where('org_id', $doc->org_id)->first();
            if (! $config || ! in_array($config->mode, ['mock', 'provider'], true)) {
                $doc->update(['status' => 'error', 'last_error' => 'Valid e-Tax provider mode is not enabled.']);

                return;
            }

            $providerCode = $config->mode === 'mock' ? 'mock' : ($config->provider_code ?: 'rd_api');

            $attempt = ETaxSubmissionAttempt::create([
                'org_id' => $doc->org_id,
                'e_tax_document_id' => $doc->id,
                'attempt_no' => (int) ETaxSubmissionAttempt::where('e_tax_document_id', $doc->id)->max('attempt_no') + 1,
                'status' => 'processing',
                'provider_code' => $providerCode,
                'started_at' => now(),
            ]);

            try {
                if ($config->mode === 'mock') {
                    $result = $this->mockAdapter->submit($config, $doc, $this->documents->xml($doc));
                    if (($result['response_message'] ?? null) !== 'SIMULATED_ONLY') {
                        throw new \RuntimeException('Unexpected mock response.');
                    }

                    $doc->update(['status' => 'simulated', 'last_error' => null]);
                    $attempt->update([
                        'status' => 'simulated',
                        'external_reference' => $result['external_reference'] ?? ('MOCK-'.$doc->id),
                        'response_code' => 200,
                        'response_message' => 'SIMULATED_ONLY',
                        'finished_at' => now(),
                    ]);

                    return;
                }

                // Provider mode: auto-sign if signature mode is xml_dsig and not yet signed
                if ($config->signature_mode === 'xml_dsig' && ! $doc->signed_at) {
                    $rawXml = $this->documents->xml($doc);
                    $signedXml = $this->signer->sign($doc, $rawXml);
                    $newSha256 = hash('sha256', $signedXml);
                    Storage::disk('local')->put($doc->xml_storage_path, $signedXml);
                    $doc->update([
                        'signed_at' => now(),
                        'xml_sha256' => $newSha256,
                    ]);
                }

                $xmlToSubmit = $this->documents->xml($doc);
                $result = $this->providerAdapter->submit($config, $doc, $xmlToSubmit);

                $newStatus = ($result['status'] ?? '') === 'accepted' ? 'accepted' : 'submitted';
                $docUpdate = [
                    'status' => $newStatus,
                    'submitted_at' => now(),
                    'last_error' => null,
                ];
                if ($newStatus === 'accepted') {
                    $docUpdate['accepted_at'] = now();
                }
                $doc->update($docUpdate);

                $attempt->update([
                    'status' => $newStatus,
                    'external_reference' => $result['external_reference'] ?? null,
                    'response_code' => $result['response_code'] ?? 200,
                    'response_message' => $result['response_message'] ?? 'SUBMITTED',
                    'finished_at' => now(),
                ]);

                AuditLog::create([
                    'org_id' => $doc->org_id,
                    'actor_user_id' => $actorId,
                    'action' => 'e_tax.submit_completed',
                    'entity_type' => 'e_tax_document',
                    'entity_id' => $doc->id,
                    'after_json' => [
                        'provider_code' => $providerCode,
                        'external_reference' => $result['external_reference'] ?? null,
                        'status' => $newStatus,
                    ],
                ]);
            } catch (Throwable $exception) {
                $errorMsg = $config->mode === 'mock'
                    ? 'MOCK_FAILED: check mode, XML integrity and required fields.'
                    : 'PROVIDER_FAILED: '.$exception->getMessage();

                $doc->update(['status' => 'error', 'last_error' => $errorMsg]);
                $attempt->update([
                    'status' => 'failed',
                    'response_message' => $config->mode === 'mock' ? 'MOCK_FAILED' : substr($exception->getMessage(), 0, 255),
                    'finished_at' => now(),
                ]);
            }
        }, 3);
    }

    /**
     * Inquire document status from provider and update local status.
     *
     * @return array{status: string, message: string}
     */
    public function inquire(ETaxDocument $document, ?string $actorId = null): array
    {
        return DB::transaction(function () use ($document, $actorId) {
            $doc = ETaxDocument::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $config = ETaxConfig::where('org_id', $doc->org_id)->first();

            if (! $config || $config->mode !== 'provider') {
                return ['status' => $doc->status, 'message' => 'Provider mode is not enabled for this organization.'];
            }

            $inquiry = $this->providerAdapter->inquire($config, $doc);

            if ($inquiry['status'] === 'accepted' && $doc->status !== 'accepted') {
                $doc->update([
                    'status' => 'accepted',
                    'accepted_at' => now(),
                    'last_error' => null,
                ]);
            } elseif ($inquiry['status'] === 'rejected' && $doc->status !== 'rejected') {
                $doc->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                    'last_error' => $inquiry['rejection_reason'] ?? 'Rejected by Revenue Department provider.',
                ]);
            }

            AuditLog::create([
                'org_id' => $doc->org_id,
                'actor_user_id' => $actorId,
                'action' => 'e_tax.inquiry',
                'entity_type' => 'e_tax_document',
                'entity_id' => $doc->id,
                'after_json' => [
                    'inquiry_status' => $inquiry['status'],
                    'provider_response' => $inquiry['response_message'],
                ],
            ]);

            return ['status' => $doc->status, 'message' => $inquiry['response_message']];
        });
    }

    /**
     * Handle asynchronous webhook callback from provider.
     *
     * @param  array<string, mixed>  $payload
     * @return array{success: bool, message: string}
     */
    public function handleWebhook(ETaxConfig $config, array $payload): array
    {
        $externalRef = $payload['external_reference'] ?? $payload['reference_id'] ?? null;
        $documentNo = $payload['document_no'] ?? null;
        $status = strtolower((string) ($payload['status'] ?? ''));

        if (! $externalRef && ! $documentNo) {
            return ['success' => false, 'message' => 'Missing document identifier in webhook payload.'];
        }

        return DB::transaction(function () use ($config, $externalRef, $documentNo, $status, $payload) {
            $query = ETaxDocument::query()->where('org_id', $config->org_id)->lockForUpdate();

            if ($externalRef) {
                $attemptDocId = ETaxSubmissionAttempt::where('org_id', $config->org_id)
                    ->where('external_reference', $externalRef)
                    ->value('e_tax_document_id');
                if ($attemptDocId) {
                    $query->where('id', $attemptDocId);
                } else {
                    $query->where('document_no', $documentNo);
                }
            } else {
                $query->where('document_no', $documentNo);
            }

            $doc = $query->first();
            if (! $doc) {
                return ['success' => false, 'message' => 'Matching e-Tax document not found.'];
            }

            if (in_array($doc->status, ['accepted', 'rejected'], true)) {
                return ['success' => true, 'message' => 'Document already in final status.'];
            }

            $newStatus = match ($status) {
                'approved', 'accepted', 'success' => 'accepted',
                'rejected', 'failed' => 'rejected',
                default => 'submitted',
            };

            $update = ['status' => $newStatus];
            if ($newStatus === 'accepted') {
                $update['accepted_at'] = now();
                $update['last_error'] = null;
            } elseif ($newStatus === 'rejected') {
                $update['rejected_at'] = now();
                $update['last_error'] = $payload['rejection_reason'] ?? $payload['error'] ?? 'Rejected by provider webhook';
            }

            $doc->update($update);

            // Update latest attempt record
            /** @var ETaxSubmissionAttempt|null $latestAttempt */
            $latestAttempt = $doc->attempts()->first();
            if ($latestAttempt) {
                $latestAttempt->update([
                    'status' => $newStatus,
                    'response_message' => 'WEBHOOK: '.($payload['message'] ?? $newStatus),
                    'finished_at' => now(),
                ]);
            }

            AuditLog::create([
                'org_id' => $doc->org_id,
                'actor_user_id' => null,
                'action' => 'e_tax.webhook_received',
                'entity_type' => 'e_tax_document',
                'entity_id' => $doc->id,
                'after_json' => [
                    'external_reference' => $externalRef,
                    'status' => $newStatus,
                    'rejection_reason' => $payload['rejection_reason'] ?? null,
                ],
            ]);

            return ['success' => true, 'message' => 'Document status updated via webhook.'];
        });
    }
}
