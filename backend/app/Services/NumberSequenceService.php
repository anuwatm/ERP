<?php

namespace App\Services;

use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

class NumberSequenceService
{
    public function next(string $orgId, string $docType, ?string $branchId = null): string
    {
        return DB::transaction(function () use ($orgId, $docType, $branchId): string {
            $branchKey = $branchId ?? '00000000-0000-0000-0000-000000000000';

            $sequence = NumberSequence::where('org_id', $orgId)
                ->where('branch_key', $branchKey)
                ->where('doc_type', $docType)
                ->where('year_key', 0)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = NumberSequence::create([
                    'org_id' => $orgId,
                    'branch_id' => $branchId,
                    'branch_key' => $branchKey,
                    'doc_type' => $docType,
                    'year' => null,
                    'year_key' => 0,
                    'last_number' => 0,
                ]);
            }

            $sequence->increment('last_number');

            return str_pad((string) $sequence->last_number, 6, '0', STR_PAD_LEFT);
        });
    }
}
