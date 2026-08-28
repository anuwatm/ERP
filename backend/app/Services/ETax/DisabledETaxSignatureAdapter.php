<?php

namespace App\Services\ETax;

use App\Models\ETaxDocument;
use Illuminate\Validation\ValidationException;

class DisabledETaxSignatureAdapter implements ETaxSignatureAdapter
{
    public function sign(ETaxDocument $document, string $xml): string
    {
        throw ValidationException::withMessages([
            'signature' => 'No configured signing adapter. Complete certificate and provider onboarding before signing e-Tax documents.',
        ]);
    }
}
