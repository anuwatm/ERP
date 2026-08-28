<?php

namespace App\Services\ETax;

use App\Models\ETaxConfig;
use App\Models\ETaxDocument;
use Illuminate\Validation\ValidationException;

class DisabledETaxSubmissionAdapter implements ETaxSubmissionAdapter
{
    public function submit(ETaxConfig $config, ETaxDocument $document, string $xml): array
    {
        throw ValidationException::withMessages([
            'submission' => 'No certified e-Tax provider adapter is configured. Download the XML or configure a certified provider before submission.',
        ]);
    }
}
