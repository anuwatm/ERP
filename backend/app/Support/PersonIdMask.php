<?php

namespace App\Support;

class PersonIdMask
{
    public static function mask(?string $personId): ?string
    {
        if (! $personId) {
            return null;
        }

        return substr($personId, 0, 1).'-'.substr($personId, 1, 4).'-xxxxx-xx-'.substr($personId, -1);
    }
}
