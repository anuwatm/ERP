<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class ExternalUnits
{
    // Column identifiers are fixed source code, never request input.
    public static function expression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => $column.' COLLATE BINARY',
            'mysql', 'mariadb' => 'CAST('.$column.' AS BINARY)',
            default => throw new \LogicException('Unsupported inventory database driver.'),
        };
    }
}
