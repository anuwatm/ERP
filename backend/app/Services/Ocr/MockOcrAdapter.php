<?php

namespace App\Services\Ocr;

use App\Models\AiConfiguration;

class MockOcrAdapter implements OcrProviderInterface
{
    protected static ?array $customResponse = null;

    protected static ?\Closure $responseGenerator = null;

    public static function setCustomResponse(?array $response): void
    {
        self::$customResponse = $response;
    }

    public static function setResponseGenerator(?\Closure $generator): void
    {
        self::$responseGenerator = $generator;
    }

    public static function reset(): void
    {
        self::$customResponse = null;
        self::$responseGenerator = null;
    }

    public function extract(string $fileContents, string $mimeType, AiConfiguration $config): array
    {
        if (self::$responseGenerator !== null) {
            $gen = self::$responseGenerator;

            return $gen($fileContents, $mimeType, $config);
        }

        if (self::$customResponse !== null) {
            return self::$customResponse;
        }

        // Default mock payload (Standard Thai Tax Invoice)
        return [
            'document_type' => 'tax_invoice',
            'vendor_name' => 'บริษัท ปตท. น้ำมันและการค้าปลีก จำกัด (มหาชน)',
            'tax_id' => '0107544000108', // Valid Thai Tax ID (PTT Public Company Limited)
            'branch_no' => '00000',
            'invoice_no' => 'INV-2026-9988',
            'document_date' => '2026-09-21',
            'subtotal' => 1000.00,
            'vat_rate' => 7.00,
            'vat_amount' => 70.00,
            'total_amount' => 1070.00,
            'currency' => 'THB',
            'confidence_score' => 0.98,
            'field_confidences' => [
                'vendor_name' => 0.98,
                'tax_id' => 0.99,
                'invoice_no' => 0.95,
                'document_date' => 0.97,
                'subtotal' => 0.98,
                'vat_rate' => 0.99,
                'vat_amount' => 0.98,
                'total_amount' => 0.99,
            ],
            'line_items' => [
                [
                    'description' => 'น้ำมันดีเซล B7',
                    'quantity' => 31.25,
                    'unit_price' => 32.00,
                    'amount' => 1000.00,
                ],
            ],
            'raw_text' => '{"mock": true}',
        ];
    }
}
