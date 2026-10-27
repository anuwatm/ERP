<?php

namespace App\Support;

class PosCanonicalJson
{
    public static function encode(mixed $value): string
    {
        return json_encode(self::sort($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function sort(mixed $value): mixed
    {
        if (is_float($value)) {
            throw new \InvalidArgumentException('Decimal values must be strings.');
        }
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::sort(...), $value);
    }
}
