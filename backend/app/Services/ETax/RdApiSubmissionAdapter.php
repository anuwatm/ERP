<?php

namespace App\Services\ETax;

use App\Models\ETaxConfig;
use App\Models\ETaxDocument;
use Illuminate\Support\Facades\Http;

class RdApiSubmissionAdapter implements ETaxSubmissionAdapter
{
    /**
     * Submit e-Tax document to provider gateway (RD API / INET / Sandbox).
     *
     * @return array{external_reference?: string, response_code?: int, response_message?: string, status?: string}
     */
    public function submit(ETaxConfig $config, ETaxDocument $document, string $xml): array
    {
        if ($config->mode !== 'provider' || $config->org_id !== $document->org_id) {
            throw new \RuntimeException('Provider submission is unavailable.');
        }

        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new \RuntimeException('XML payload contains forbidden entities.');
        }

        $settings = (array) ($config->provider_settings ?? []);
        $apiUrl = rtrim($settings['api_url'] ?? config('services.etax.api_url', 'https://etax.rd.go.th/api/v1'), '/');
        $clientId = $settings['client_id'] ?? config('services.etax.client_id', 'DEFAULT_CLIENT');
        $clientSecret = $settings['client_secret'] ?? config('services.etax.client_secret', '');
        $providerCode = $config->provider_code ?: ($settings['provider_code'] ?? 'rd_api');

        $headers = [
            'Accept' => 'application/json',
            'X-Provider-Code' => $providerCode,
            'X-Client-Id' => $clientId,
            'X-Idempotency-Key' => 'ETAX-SUBMIT-'.$document->id,
        ];

        if (! empty($clientSecret)) {
            $headers['Authorization'] = 'Bearer '.$this->resolveToken($clientId, $clientSecret);
        }

        $endpoint = $apiUrl.'/documents/submit';

        $response = Http::withHeaders($headers)
            ->timeout(15)
            ->post($endpoint, [
                'document_id' => $document->id,
                'document_no' => $document->document_no,
                'document_type' => $document->document_type,
                'sha256' => hash('sha256', $xml),
                'xml_content' => base64_encode($xml),
            ]);

        if (! $response->successful()) {
            $errorMsg = $response->json('message') ?? $response->json('error') ?? ('HTTP_'.$response->status());
            throw new \RuntimeException("Provider submission failed: {$errorMsg}");
        }

        $data = $response->json() ?? [];
        $externalRef = $data['reference_id'] ?? $data['external_reference'] ?? $response->header('X-Reference-Id') ?? ('ETAX-REF-'.$document->id);
        $status = $data['status'] ?? 'submitted';

        return [
            'external_reference' => (string) $externalRef,
            'response_code' => $response->status(),
            'response_message' => (string) ($data['message'] ?? 'SUBMITTED'),
            'status' => (string) $status,
        ];
    }

    /**
     * Inquire document status from provider.
     *
     * @return array{status: string, response_code: int, response_message: string, rejection_reason?: string|null}
     */
    public function inquire(ETaxConfig $config, ETaxDocument $document, ?string $externalReference = null): array
    {
        $settings = (array) ($config->provider_settings ?? []);
        $apiUrl = rtrim($settings['api_url'] ?? config('services.etax.api_url', 'https://etax.rd.go.th/api/v1'), '/');
        $clientId = $settings['client_id'] ?? config('services.etax.client_id', 'DEFAULT_CLIENT');
        $clientSecret = $settings['client_secret'] ?? config('services.etax.client_secret', '');
        $providerCode = $config->provider_code ?: ($settings['provider_code'] ?? 'rd_api');

        $headers = [
            'Accept' => 'application/json',
            'X-Provider-Code' => $providerCode,
            'X-Client-Id' => $clientId,
        ];

        if (! empty($clientSecret)) {
            $headers['Authorization'] = 'Bearer '.$this->resolveToken($clientId, $clientSecret);
        }

        $ref = $externalReference ?: $document->attempts()->latest('attempt_no')->value('external_reference') ?: $document->id;
        $endpoint = $apiUrl.'/documents/status/'.urlencode($ref);

        $response = Http::withHeaders($headers)
            ->timeout(10)
            ->get($endpoint);

        if (! $response->successful()) {
            throw new \RuntimeException('Provider inquiry failed with status '.$response->status());
        }

        $data = $response->json() ?? [];
        $providerStatus = strtolower((string) ($data['status'] ?? 'submitted'));

        $mappedStatus = match ($providerStatus) {
            'approved', 'accepted', 'success' => 'accepted',
            'rejected', 'failed', 'invalid' => 'rejected',
            'processing', 'in_progress', 'queued' => 'submitted',
            default => 'submitted',
        };

        return [
            'status' => $mappedStatus,
            'response_code' => $response->status(),
            'response_message' => (string) ($data['message'] ?? 'OK'),
            'rejection_reason' => $data['rejection_reason'] ?? $data['error'] ?? null,
        ];
    }

    private function resolveToken(string $clientId, string $clientSecret): string
    {
        return hash('sha256', $clientId.':'.$clientSecret);
    }
}
