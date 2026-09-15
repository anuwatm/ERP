<?php

namespace App\Services\ETax;

use App\Models\ETaxConfig;
use App\Models\ETaxDocument;

class MockETaxSubmissionAdapter implements ETaxSubmissionAdapter
{
    public function submit(ETaxConfig $config, ETaxDocument $document, string $xml): array
    {
        if ($config->mode !== 'mock' || $config->org_id !== $document->org_id || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new \RuntimeException('Mock submission is unavailable.');
        }
        $parsed = new \DOMDocument;
        if (! @$parsed->loadXML($xml, LIBXML_NONET) || $parsed->documentElement?->namespaceURI !== 'urn:erp:etax:provider-mapping:v1') {
            throw new \RuntimeException('Invalid internal XML profile.');
        }
        foreach (['DocumentNumber', 'IssueDate', 'Currency', 'Total', 'Seller', 'Buyer', 'Lines'] as $tag) {
            if (trim($parsed->getElementsByTagName($tag)->item(0)?->textContent ?? '') === '') {
                throw new \RuntimeException('Incomplete internal XML.');
            }
        }

        return ['external_reference' => 'MOCK-'.$document->id, 'response_code' => 200, 'response_message' => 'SIMULATED_ONLY'];
    }
}
