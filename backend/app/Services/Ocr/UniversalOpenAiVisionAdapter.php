<?php

namespace App\Services\Ocr;

use App\Models\AiConfiguration;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UniversalOpenAiVisionAdapter implements OcrProviderInterface
{
    public function extract(string $fileContents, string $mimeType, AiConfiguration $config): array
    {
        if (static::isCloudProvider($config) && ! static::isCloudAllowed($config->org_id)) {
            throw new \DomainException('Cloud AI processing is disabled for this organization by compliance policy.');
        }

        [$imageData, $effectiveMime] = $this->prepareImagePayload($fileContents, $mimeType);

        $base64Image = base64_encode($imageData);
        $dataUri = "data:{$effectiveMime};base64,{$base64Image}";

        $systemPrompt = <<<'PROMPT'
You are a precise Thai Financial Document OCR and Accounting Assistant for an ERP system.
Analyze the provided document (receipt, full tax invoice, or bank transfer slip).
Extract all key financial fields and return ONLY a valid JSON object matching the following structure:
{
  "document_type": "receipt" | "tax_invoice" | "bank_slip" | "unknown",
  "vendor_name": "Full legal name or merchant name in Thai/English",
  "tax_id": "13-digit Thai Tax ID without hyphens or null if not found",
  "branch_no": "Branch number e.g. 00000 or null",
  "invoice_no": "Invoice or receipt number e.g. INV-2026-001 or null",
  "document_date": "YYYY-MM-DD format e.g. 2026-09-21 or null",
  "subtotal": 100.00 (Amount before VAT as a number),
  "vat_rate": 7.00 (VAT percentage rate as a number e.g. 7.00),
  "vat_amount": 7.00 (VAT amount as a number),
  "total_amount": 107.00 (Grand total amount as a number),
  "currency": "THB",
  "confidence_score": 0.95 (Overall document confidence score from 0.00 to 1.00),
  "field_confidences": {
    "vendor_name": 0.95,
    "tax_id": 0.98,
    "invoice_no": 0.90,
    "document_date": 0.95,
    "subtotal": 0.92,
    "vat_rate": 0.99,
    "vat_amount": 0.95,
    "total_amount": 0.98
  },
  "line_items": [
    {
      "description": "Item description",
      "quantity": 1,
      "unit_price": 100.00,
      "amount": 100.00
    }
  ]
}

Important Rules:
1. Ensure numbers (subtotal, vat_amount, total_amount) are accurate numeric values, not strings.
2. If tax_id is present, extract all 13 digits.
3. For Thai Buddhist calendar years (e.g. 2569), convert to Christian Era (e.g. 2026) by subtracting 543.
4. Output strictly raw JSON without markdown or explanations.
PROMPT;

        $endpoint = AiConnectionTesterService::resolveChatEndpoint($config->base_url ?? '');

        $headers = [
            'Content-Type' => 'application/json',
        ];

        $apiKey = trim((string) ($config->api_key ?? ''));
        if (! empty($apiKey)) {
            $headers['Authorization'] = 'Bearer '.$apiKey;
        }

        $model = trim((string) ($config->model_name ?: 'gpt-4o-mini'));
        $isReasoningModel = str_starts_with($model, 'o1') || str_starts_with($model, 'o3');
        $isOpenAi = $config->provider_type === 'openai' || str_contains($endpoint, 'api.openai.com');

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt,
                ],
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'Extract financial metadata from this receipt/invoice and output JSON.',
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $dataUri,
                                'detail' => 'high',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        if ($isReasoningModel) {
            $payload['max_completion_tokens'] = 2500;
        } else {
            $payload['temperature'] = (float) ($config->temperature ?? 0.1);
            $payload['max_tokens'] = 2500;
        }

        // For OpenAI official endpoint, enforce JSON object output
        if ($isOpenAi && ! $isReasoningModel) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout((int) ($config->timeout_seconds ?? 60))
                ->post($endpoint, $payload);

            if ($response->failed()) {
                $json = $response->json();
                $errMsg = null;
                if (is_array($json) && isset($json['error'])) {
                    if (is_array($json['error']) && isset($json['error']['message'])) {
                        $errMsg = $json['error']['message'];
                    } elseif (is_string($json['error'])) {
                        $errMsg = $json['error'];
                    }
                }

                if (! $errMsg) {
                    $errMsg = 'HTTP '.$response->status().' error';
                }

                $sanitizedError = OcrProcessingService::sanitizeErrorMessage((string) $errMsg);

                Log::warning('UniversalOpenAiVisionAdapter request failed', [
                    'status' => $response->status(),
                    'error' => $sanitizedError,
                    'provider' => $config->provider_type,
                    'model' => $model,
                ]);

                throw new \RuntimeException("AI OCR provider returned error HTTP {$response->status()}: {$sanitizedError}");
            }

            $responseJson = $response->json();
            $content = $responseJson['choices'][0]['message']['content'] ?? '';

            $extracted = RobustJsonCleaner::extractJson($content);

            return $this->normalizeResult($extracted, $content);
        } catch (\Throwable $e) {
            Log::error('UniversalOpenAiVisionAdapter exception', ['error' => OcrProcessingService::sanitizeErrorMessage($e->getMessage())]);
            throw $e;
        }
    }

    public static function isCloudProvider(AiConfiguration $config): bool
    {
        if (in_array($config->provider_type, ['openai', 'openrouter', 'gemini'], true)) {
            return true;
        }

        $host = parse_url($config->base_url, PHP_URL_HOST);
        if ($host && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true) && ! str_ends_with($host, '.local')) {
            return true;
        }

        return false;
    }

    public static function isCloudAllowed(?string $orgId): bool
    {
        if (! $orgId) {
            return true;
        }

        $setting = Setting::where('org_id', $orgId)
            ->where('key', 'ai.compliance')
            ->value('value_json');

        if ($setting !== null && isset($setting['allow_cloud_ai_processing'])) {
            return (bool) $setting['allow_cloud_ai_processing'];
        }

        return true;
    }

    /**
     * Prepares image payload, auto-extracting embedded JPEG if a scanned PDF is uploaded.
     */
    protected function prepareImagePayload(string $fileContents, string $mimeType): array
    {
        if (str_starts_with($mimeType, 'image/')) {
            return [$fileContents, $mimeType];
        }

        if ($mimeType === 'application/pdf' || str_starts_with($fileContents, '%PDF-')) {
            $extractedJpeg = $this->extractJpegFromPdf($fileContents);
            if ($extractedJpeg !== null) {
                return [$extractedJpeg, 'image/jpeg'];
            }

            throw new \InvalidArgumentException(
                'The uploaded PDF cannot be processed directly by Vision AI. Please upload the receipt/invoice as an image file (JPG, PNG, or WEBP).'
            );
        }

        return [$fileContents, $mimeType];
    }

    /**
     * Extracts an embedded JPEG from a scanned PDF document if present.
     */
    protected function extractJpegFromPdf(string $pdfContent): ?string
    {
        $startPos = strpos($pdfContent, "\xFF\xD8\xFF");
        if ($startPos === false) {
            return null;
        }

        $endPos = strrpos($pdfContent, "\xFF\xD9");
        if ($endPos === false || $endPos <= $startPos) {
            return null;
        }

        $jpeg = substr($pdfContent, $startPos, ($endPos - $startPos) + 2);
        if (strlen($jpeg) > 1024) {
            return $jpeg;
        }

        return null;
    }

    protected function normalizeResult(array $data, string $rawText): array
    {
        return [
            'document_type' => $data['document_type'] ?? 'receipt',
            'vendor_name' => ! empty($data['vendor_name']) ? (string) $data['vendor_name'] : null,
            'tax_id' => ! empty($data['tax_id']) ? preg_replace('/[^\d]/', '', (string) $data['tax_id']) : null,
            'branch_no' => ! empty($data['branch_no']) ? (string) $data['branch_no'] : null,
            'invoice_no' => ! empty($data['invoice_no']) ? (string) $data['invoice_no'] : null,
            'document_date' => ! empty($data['document_date']) ? (string) $data['document_date'] : null,
            'subtotal' => isset($data['subtotal']) ? (float) $data['subtotal'] : null,
            'vat_rate' => isset($data['vat_rate']) ? (float) $data['vat_rate'] : 7.00,
            'vat_amount' => isset($data['vat_amount']) ? (float) $data['vat_amount'] : null,
            'total_amount' => isset($data['total_amount']) ? (float) $data['total_amount'] : null,
            'currency' => ! empty($data['currency']) ? strtoupper((string) $data['currency']) : 'THB',
            'confidence_score' => isset($data['confidence_score']) ? (float) $data['confidence_score'] : 0.85,
            'field_confidences' => is_array($data['field_confidences'] ?? null) ? $data['field_confidences'] : [],
            'line_items' => is_array($data['line_items'] ?? null) ? $data['line_items'] : [],
            'raw_text' => $rawText,
        ];
    }
}
