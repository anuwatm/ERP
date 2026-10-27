<?php

namespace Pos\Services;

class PrescriptionScanner
{
    public function isClean(string $absolutePath): bool
    {
        throw new \RuntimeException('Prescription malware scanner is not provisioned.');
    }
}
