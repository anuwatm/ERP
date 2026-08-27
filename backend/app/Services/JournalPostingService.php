<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JournalPostingService
{
    public function __construct(
        private readonly AccountingPeriodService $periods,
        private readonly ChartOfAccountProvisioner $accounts,
        private readonly NumberSequenceService $numbers,
    ) {}

    /** @param array<int, array{account_code:string,debit?:float|int|string,credit?:float|int|string,description?:string}> $lines */
    public function post(string $orgId, ?string $actorId, string $sourceType, string $sourceId, string $event, string $postingDate, string $description, array $lines): JournalEntry
    {
        return DB::transaction(function () use ($orgId, $actorId, $sourceType, $sourceId, $event, $postingDate, $description, $lines): JournalEntry {
            $existing = JournalEntry::where('org_id', $orgId)
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('posting_event', $event)
                ->lockForUpdate()
                ->first();
            if ($existing) {
                return $existing;
            }

            $this->accounts->ensure($orgId);
            $period = $this->periods->openPeriodFor($orgId, Carbon::parse($postingDate));
            $resolved = $this->resolveLines($orgId, $lines);
            $this->assertBalanced($resolved);

            $entry = JournalEntry::create([
                'org_id' => $orgId,
                'accounting_period_id' => $period->id,
                'entry_no' => $this->numbers->next($orgId, 'journal'),
                'posting_date' => $postingDate,
                'description' => $description,
                'status' => 'posted',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'posting_event' => $event,
                'posted_by' => $actorId,
                'posted_at' => now(),
            ]);

            foreach ($resolved as $index => $line) {
                JournalLine::create(array_merge($line, ['org_id' => $orgId, 'journal_entry_id' => $entry->id, 'sort_order' => $index]));
            }

            $this->audit($orgId, $actorId, 'journal.post', $entry, ['source_type' => $sourceType, 'source_id' => $sourceId, 'posting_event' => $event]);

            return $entry->load('lines');
        });
    }

    public function reverse(JournalEntry $entry, ?string $actorId, string $postingDate, string $reason, string $event): JournalEntry
    {
        return DB::transaction(function () use ($entry, $actorId, $postingDate, $reason, $event): JournalEntry {
            $original = JournalEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            if ($original->org_id !== $entry->org_id || ! $original->source_type || ! $original->source_id) {
                throw ValidationException::withMessages(['journal' => 'Journal entry cannot be reversed.']);
            }
            $existing = JournalEntry::where('org_id', $original->org_id)->where('source_type', $original->source_type)->where('source_id', $original->source_id)->where('posting_event', $event)->first();
            if ($existing) {
                return $existing;
            }
            if ($original->status !== 'posted') {
                throw ValidationException::withMessages(['journal' => 'Only posted journals can be reversed.']);
            }

            $lines = $original->lines()->get()->map(fn (JournalLine $line) => [
                'account_code' => $line->account()->value('code'),
                'debit' => $line->credit,
                'credit' => $line->debit,
                'description' => $line->description,
            ])->all();
            $reversal = $this->post($original->org_id, $actorId, $original->source_type, $original->source_id, $event, $postingDate, 'Reversal: '.$reason, $lines);
            $reversal->update(['reversal_of_id' => $original->id]);
            $original->update(['status' => 'reversed']);
            $this->audit($original->org_id, $actorId, 'journal.reverse', $reversal, ['reversal_of_id' => $original->id, 'reason' => $reason]);

            return $reversal;
        });
    }

    /** @param array<int, array{account_code:string,debit?:float|int|string,credit?:float|int|string,description?:string}> $lines */
    private function resolveLines(string $orgId, array $lines): array
    {
        $accounts = ChartOfAccount::where('org_id', $orgId)->where('status', 'active')->where('is_postable', true)->get()->keyBy('code');

        return collect($lines)->map(function (array $line) use ($accounts): array {
            $account = $accounts->get($line['account_code']);
            if (! $account) {
                throw ValidationException::withMessages(['journal' => 'Posting account is missing or inactive.']);
            }
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            if (($debit <= 0 && $credit <= 0) || ($debit > 0 && $credit > 0)) {
                throw ValidationException::withMessages(['journal' => 'Each journal line must have one positive debit or credit.']);
            }

            return ['chart_of_account_id' => $account->id, 'description' => $line['description'] ?? null, 'debit' => $debit, 'credit' => $credit];
        })->all();
    }

    private function assertBalanced(array $lines): void
    {
        $debit = round(array_sum(array_column($lines, 'debit')), 2);
        $credit = round(array_sum(array_column($lines, 'credit')), 2);
        if ($debit <= 0 || $debit !== $credit) {
            throw ValidationException::withMessages(['journal' => 'Journal debit and credit totals must balance.']);
        }
    }

    private function audit(string $orgId, ?string $actorId, string $action, JournalEntry $entry, array $after): void
    {
        AuditLog::create(['org_id' => $orgId, 'actor_user_id' => $actorId, 'action' => $action, 'entity_type' => 'journal_entry', 'entity_id' => $entry->id, 'after_json' => $after, 'request_id' => (string) Str::uuid()]);
    }
}
