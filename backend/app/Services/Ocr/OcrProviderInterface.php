<?php

namespace App\Services\Ocr;

use App\Models\AiConfiguration;

interface OcrProviderInterface
{
    /**
     * Extract structured financial data from an image/pdf binary content.
     *
     * @param  string  $fileContents  Raw binary of image or PDF
     * @param  string  $mimeType  e.g. image/jpeg, image/png, application/pdf
     * @param  AiConfiguration  $config  Organization AI configuration
     * @return array Standardized extraction structure
     */
    public function extract(string $fileContents, string $mimeType, AiConfiguration $config): array;
}
