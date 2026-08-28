<?php

namespace App\Services\ETax;

use App\Models\ETaxDocument;

interface ETaxSignatureAdapter
{
    public function sign(ETaxDocument $document, string $xml): string;
}
