<?php

namespace App\Services\ETax;

use App\Models\ETaxConfig;
use App\Models\ETaxDocument;

interface ETaxSubmissionAdapter
{
    /** @return array{external_reference?: string, response_code?: int, response_message?: string} */
    public function submit(ETaxConfig $config, ETaxDocument $document, string $xml): array;
}
