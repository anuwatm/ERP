<?php

namespace App\Services\Pos;

use Illuminate\Support\Str;

class PosDependencyPending extends \RuntimeException
{
    public function __construct(public readonly string $sourceKind, public readonly array $sourceIds)
    {
        if (! in_array($sourceKind, ['kitchen_consumption', 'kitchen_posted', 'refund', 'sale', 'tax_document'], true) || count($sourceIds) < 1 || count($sourceIds) > 100 || count(array_filter($sourceIds, fn ($id) => is_string($id) && Str::isUuid($id))) !== count($sourceIds)) {
            throw new \LogicException('Invalid internal POS dependency.');
        }
        parent::__construct('POS source dependency is unavailable.');
    }
}
