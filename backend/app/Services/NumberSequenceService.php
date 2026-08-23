<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\NumberSequence;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class NumberSequenceService
{
    private const DEFAULT_FORMAT = '{SEQ:6}';

    private const ORGANIZATION_BRANCH_KEY = '00000000-0000-0000-0000-000000000000';

    public function next(string $orgId, string $docType, ?string $branchId = null, ?CarbonInterface $date = null): string
    {
        $date ??= Carbon::now();
        $config = $this->configFor($orgId, $docType, $branchId);
        $periodKey = $this->periodKey($config['reset'], $date);

        return DB::transaction(function () use ($orgId, $docType, $branchId, $date, $config, $periodKey): string {
            $sequence = NumberSequence::where('org_id', $orgId)
                ->where('branch_key', $config['branch_key'])
                ->where('doc_type', $docType)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $sequence = NumberSequence::create([
                    'org_id' => $orgId,
                    'branch_id' => $config['scope'] === 'branch' ? $branchId : null,
                    'branch_key' => $config['branch_key'],
                    'doc_type' => $docType,
                    'year' => $date->year,
                    'year_key' => 0,
                    'period_key' => $periodKey,
                    'last_number' => 0,
                ]);
            }

            $sequence->increment('last_number');

            return $this->render($config['format'], $sequence->last_number, $date, $config['branch_code']);
        });
    }

    public function preview(string $orgId, string $docType, ?string $branchId = null, ?CarbonInterface $date = null): string
    {
        $date ??= Carbon::now();
        $config = $this->configFor($orgId, $docType, $branchId);
        $periodKey = $this->periodKey($config['reset'], $date);

        $lastNumber = NumberSequence::where('org_id', $orgId)
            ->where('branch_key', $config['branch_key'])
            ->where('doc_type', $docType)
            ->where('period_key', $periodKey)
            ->value('last_number') ?? 0;

        return $this->render($config['format'], (int) $lastNumber + 1, $date, $config['branch_code']);
    }

    private function configFor(string $orgId, string $docType, ?string $branchId): array
    {
        $setting = Setting::where('org_id', $orgId)
            ->where('key', 'document_numbering.formats')
            ->value('value_json') ?? [];
        $config = $setting[$docType] ?? [];
        $isConfigured = (bool) ($config['enabled'] ?? false);
        $scope = $isConfigured ? ($config['scope'] ?? 'organization') : ($branchId ? 'branch' : 'organization');
        $format = $isConfigured ? ($config['format'] ?? self::DEFAULT_FORMAT) : self::DEFAULT_FORMAT;
        $reset = $isConfigured ? ($config['reset'] ?? 'none') : 'none';
        $branch = $branchId ? Branch::where('org_id', $orgId)->whereKey($branchId)->first(['id', 'code']) : null;

        if ($scope === 'branch' && ! $branch) {
            throw new InvalidArgumentException('Branch-scoped document number requires a valid branch.');
        }

        $branchKey = $scope === 'branch' ? $branch->id : self::ORGANIZATION_BRANCH_KEY;
        $branchCode = $scope === 'branch' ? $branch->code : '';

        $this->validateConfig($format, $reset, $scope);

        return [
            'format' => $format,
            'reset' => $reset,
            'scope' => $scope,
            'branch_key' => $branchKey,
            'branch_code' => $branchCode,
        ];
    }

    private function validateConfig(string $format, string $reset, string $scope): void
    {
        if (! in_array($reset, ['none', 'yearly', 'monthly', 'daily'], true)) {
            throw new InvalidArgumentException('Invalid document number reset period.');
        }

        if (! in_array($scope, ['organization', 'branch'], true)) {
            throw new InvalidArgumentException('Invalid document number scope.');
        }

        preg_match_all('/\{SEQ:(\d+)\}/', $format, $matches);
        if (count($matches[0]) !== 1) {
            throw new InvalidArgumentException('Document number format must contain exactly one {SEQ:n} token.');
        }

        $padding = (int) $matches[1][0];
        if ($padding < 1 || $padding > 10) {
            throw new InvalidArgumentException('Document number sequence padding must be between 1 and 10.');
        }

        $knownTokensRemoved = preg_replace('/\{(YYYY|YY|MM|DD|BRANCH|SEQ:\d+)\}/', '', $format);
        if (preg_match('/\{[^}]+\}/', $knownTokensRemoved ?? '')) {
            throw new InvalidArgumentException('Document number format contains an unknown token.');
        }
    }

    private function periodKey(string $reset, CarbonInterface $date): string
    {
        return match ($reset) {
            'yearly' => $date->format('Y'),
            'monthly' => $date->format('Ym'),
            'daily' => $date->format('Ymd'),
            default => 'all',
        };
    }

    private function render(string $format, int $sequenceNumber, CarbonInterface $date, string $branchCode): string
    {
        preg_match('/\{SEQ:(\d+)\}/', $format, $match);
        $padding = (int) $match[1];
        $number = str_replace([
            '{YYYY}',
            '{YY}',
            '{MM}',
            '{DD}',
            '{BRANCH}',
            $match[0],
        ], [
            $date->format('Y'),
            $date->format('y'),
            $date->format('m'),
            $date->format('d'),
            $branchCode,
            str_pad((string) $sequenceNumber, $padding, '0', STR_PAD_LEFT),
        ], $format);

        if (strlen($number) > 30) {
            throw new InvalidArgumentException('Document number must not exceed 30 characters.');
        }

        return $number;
    }
}
