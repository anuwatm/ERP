<?php

namespace App\Services\Ocr;

use App\Models\AiConfiguration;
use App\Models\OcrExtraction;
use App\Models\OcrFieldResult;
use App\Models\OcrTemplate;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OcrProcessingService
{
    protected ?OcrProviderInterface $customAdapter = null;

    public function __construct(?OcrProviderInterface $adapter = null)
    {
        $this->customAdapter = $adapter;
    }

    public function setAdapter(?OcrProviderInterface $adapter): void
    {
        $this->customAdapter = $adapter;
    }

    /**
     * Process an OCR Extraction record through the AI Vision pipeline.
     */
    public function process(OcrExtraction $extraction): OcrExtraction
    {
        $extraction->update(['status' => OcrExtraction::STATUS_PROCESSING]);

        try {
            $storedFile = $extraction->storedFile;
            if (! $storedFile || ! Storage::disk('local')->exists($storedFile->storage_key)) {
                throw new \RuntimeException('Document storage file not found.');
            }

            $fileContents = Storage::disk('local')->get($storedFile->storage_key);

            // Fetch organization AI configuration or fallback to local default
            $config = AiConfiguration::where('org_id', $extraction->org_id)
                ->where('is_active', true)
                ->first();

            if (! $config) {
                $config = new AiConfiguration([
                    'org_id' => $extraction->org_id,
                    'provider_type' => 'ollama',
                    'base_url' => 'http://localhost:11434/v1',
                    'model_name' => 'qwen2.5-vl:7b',
                    'temperature' => 0.1,
                    'timeout_seconds' => 60,
                    'is_active' => true,
                ]);
            }

            if (UniversalOpenAiVisionAdapter::isCloudProvider($config) && ! UniversalOpenAiVisionAdapter::isCloudAllowed($extraction->org_id)) {
                throw new \DomainException('Cloud AI processing is disabled for this organization. Only on-premise local AI is permitted by compliance policy.');
            }

            $adapter = $this->resolveAdapter($config);
            $extracted = $adapter->extract($fileContents, $storedFile->mime_type, $config);

            // Math reconciliation validation
            $mathMismatch = false;
            if ($extracted['subtotal'] !== null && $extracted['vat_amount'] !== null && $extracted['total_amount'] !== null) {
                $expected = round((float) $extracted['subtotal'] + (float) $extracted['vat_amount'], 2);
                $actual = round((float) $extracted['total_amount'], 2);
                if (abs($expected - $actual) > 0.05) {
                    $mathMismatch = true;
                }
            }

            // Thai Tax ID validation
            $taxIdValid = null;
            if (! empty($extracted['tax_id'])) {
                $taxIdValid = ThaiTaxIdValidator::isValid($extracted['tax_id']);
            }

            // Supplier matching
            $matchedSupplierId = null;
            if (! empty($extracted['tax_id'])) {
                $matchedSupplierId = Supplier::where('org_id', $extraction->org_id)
                    ->where('tax_id', $extracted['tax_id'])
                    ->value('id');
            }

            if (! $matchedSupplierId && ! empty($extracted['vendor_name'])) {
                $matchedSupplierId = Supplier::where('org_id', $extraction->org_id)
                    ->where('name', 'like', '%'.trim($extracted['vendor_name']).'%')
                    ->value('id');
            }

            // Template category matching
            $suggestedCategory = null;
            $template = OcrTemplate::where('org_id', $extraction->org_id)
                ->where('is_active', true)
                ->where(function ($query) use ($extracted) {
                    if (! empty($extracted['tax_id'])) {
                        $query->where('vendor_tax_id', $extracted['tax_id']);
                    }
                    if (! empty($extracted['vendor_name'])) {
                        $query->orWhere('vendor_name', 'like', '%'.trim($extracted['vendor_name']).'%');
                    }
                })
                ->first();

            if ($template && $template->default_category) {
                $suggestedCategory = $template->default_category;
            }

            $extracted['matched_supplier_id'] = $matchedSupplierId;
            $extracted['suggested_category'] = $suggestedCategory;

            // Persist results inside transaction
            DB::transaction(function () use ($extraction, $extracted, $mathMismatch, $taxIdValid) {
                $extraction->update([
                    'status' => OcrExtraction::STATUS_EXTRACTED,
                    'document_type' => $extracted['document_type'] ?? 'receipt',
                    'confidence_score' => $extracted['confidence_score'] ?? 0.85,
                    'raw_response_text' => $extracted['raw_text'] ?? null,
                    'parsed_data_json' => $extracted,
                    'math_mismatch' => $mathMismatch,
                    'tax_id_valid' => $taxIdValid,
                    'error_message' => null,
                ]);

                // Clear previous field results if any
                $extraction->fieldResults()->delete();

                // Save individual field results
                $fields = [
                    'vendor_name' => $extracted['vendor_name'] ?? null,
                    'tax_id' => $extracted['tax_id'] ?? null,
                    'invoice_no' => $extracted['invoice_no'] ?? null,
                    'document_date' => $extracted['document_date'] ?? null,
                    'subtotal' => $extracted['subtotal'] !== null ? number_format((float) $extracted['subtotal'], 2, '.', '') : null,
                    'vat_rate' => $extracted['vat_rate'] !== null ? number_format((float) $extracted['vat_rate'], 2, '.', '') : '7.00',
                    'vat_amount' => $extracted['vat_amount'] !== null ? number_format((float) $extracted['vat_amount'], 2, '.', '') : null,
                    'total_amount' => $extracted['total_amount'] !== null ? number_format((float) $extracted['total_amount'], 2, '.', '') : null,
                ];

                $fieldConfidences = $extracted['field_confidences'] ?? [];

                foreach ($fields as $key => $val) {
                    OcrFieldResult::create([
                        'org_id' => $extraction->org_id,
                        'ocr_extraction_id' => $extraction->id,
                        'field_key' => $key,
                        'extracted_value' => $val !== null ? (string) $val : null,
                        'confidence' => (float) ($fieldConfidences[$key] ?? $extracted['confidence_score'] ?? 0.85),
                        'is_corrected_by_user' => false,
                    ]);
                }
            });

            return $extraction->fresh(['fieldResults', 'storedFile']);
        } catch (\Throwable $e) {
            $sanitized = static::sanitizeErrorMessage($e->getMessage());
            $extraction->update([
                'status' => OcrExtraction::STATUS_FAILED,
                'error_message' => $sanitized,
            ]);

            throw $e;
        }
    }

    public static function sanitizeErrorMessage(?string $message): string
    {
        if (empty($message)) {
            return 'Unknown AI provider error';
        }

        // Redact OpenAI API keys / tokens (sk-..., Bearer ...)
        $sanitized = preg_replace('/sk-[a-zA-Z0-9_\-]{8,}/', '[REDACTED_API_KEY]', $message);
        $sanitized = preg_replace('/Bearer\s+[a-zA-Z0-9_\.\-]{8,}/i', 'Bearer [REDACTED_TOKEN]', $sanitized);

        // Redact any URLs with queries or paths
        $sanitized = preg_replace('/https?:\/\/[^\s\'"<>]+/', '[ENDPOINT_URL]', $sanitized);

        // Redact potential 13-digit Thai Tax / Citizen IDs
        $sanitized = preg_replace('/\b\d{13}\b/', '[REDACTED_ID]', $sanitized);

        return substr($sanitized, 0, 255);
    }

    protected function resolveAdapter(AiConfiguration $config): OcrProviderInterface
    {
        if ($this->customAdapter !== null) {
            return $this->customAdapter;
        }

        // If in test environment or mock provider, return MockOcrAdapter
        if (app()->environment('testing') || $config->provider_type === 'mock') {
            return new MockOcrAdapter;
        }

        return new UniversalOpenAiVisionAdapter;
    }
}
