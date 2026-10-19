<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Task;
use App\Models\VendorPayment;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class ExternalSummaryService
{
    public function sales(string $org, string $start, string $end, string $zone, CarbonImmutable $now): array
    {
        $customers = $this->period(Customer::where('org_id', $org), 'created_at', $start, $end, $zone)->count();
        $deals = $this->period(Deal::where('org_id', $org), 'created_at', $start, $end, $zone)
            ->select('stage', 'currency')->selectRaw('COUNT(*) as records_count, SUM(value_amount) as amount')
            ->groupBy('stage', 'currency')->orderBy('stage')->orderBy('currency')->get()
            ->map(fn ($row) => ['status' => $row->stage, 'currency' => $row->currency ?: 'UNSPECIFIED', 'count' => (int) $row->records_count, 'amount' => $this->money($row->amount)])->all();
        $quotations = $this->period(Quotation::where('org_id', $org), 'issue_date', $start, $end, $zone)
            ->select('status', 'currency')->selectRaw('COUNT(*) as records_count, SUM(total) as amount')
            ->groupBy('status', 'currency')->orderBy('status')->orderBy('currency')->get()
            ->map(fn ($row) => ['status' => $row->status, 'currency' => $row->currency ?: 'UNSPECIFIED', 'count' => (int) $row->records_count, 'amount' => $this->money($row->amount)])->all();

        return ['new_customers' => $customers, 'deals_created_by_current_status' => $deals, 'quotations_issued_by_current_status' => $quotations];
    }

    public function finance(string $org, string $start, string $end, string $zone, CarbonImmutable $now): array
    {
        $revenue = $this->period(Invoice::where('org_id', $org)->whereIn('status', ['sent', 'partially_paid', 'paid', 'overdue']), 'issue_date', $start, $end, $zone);
        $payments = $this->period(Payment::where('org_id', $org), 'payment_date', $start, $end, $zone);
        $expenses = $this->period(Expense::where('org_id', $org)->whereIn('status', ['approved', 'partially_paid', 'paid']), 'expense_date', $start, $end, $zone);
        $outgoing = $this->period(VendorPayment::where('org_id', $org), 'payment_date', $start, $end, $zone);
        $ar = Invoice::where('org_id', $org)->whereIn('status', ['sent', 'partially_paid', 'overdue'])->where('base_balance_due', '>', 0);

        return [
            'period' => [
                'invoiced_revenue' => $this->totals($revenue, 'base_total'),
                'cash_received' => $this->flows($payments, 'receipt'),
                'recognized_expense' => $this->totals($expenses, 'base_amount'),
                'ap_cash_paid' => $this->flows($outgoing, 'payment'),
            ],
            'snapshot' => [
                'as_of' => $now->toIso8601String(),
                'outstanding_ar' => $this->totals(clone $ar, 'base_balance_due'),
                'overdue_ar' => $this->totals((clone $ar)->where('due_date', '<', $now->toDateString()), 'base_balance_due'),
            ],
        ];
    }

    public function projects(string $org, string $start, string $end, string $zone, CarbonImmutable $now): array
    {
        $from = CarbonImmutable::parse($start, $zone)->startOfDay()->setTimezone(config('app.timezone'));
        $until = CarbonImmutable::parse($end, $zone)->addDay()->startOfDay()->setTimezone(config('app.timezone'));
        // Each table is aggregated once; only status groups enter PHP memory.
        $projects = Project::where('org_id', $org)->select('status')
            ->selectRaw('COUNT(*) as records_count, SUM(progress_percent) as progress_sum')
            ->selectRaw('SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as period_count', [$from, $until])
            ->selectRaw('SUM(CASE WHEN due_date < ? THEN 1 ELSE 0 END) as overdue_count', [$now->toDateString()])
            ->groupBy('status')->orderBy('status')->get();
        $tasks = Task::query()->join('projects', function ($join) use ($org) {
            $join->on('projects.id', '=', 'tasks.project_id')->where('projects.org_id', $org)->whereNull('projects.deleted_at');
        })->where('tasks.org_id', $org)->select('tasks.status')
            ->selectRaw('COUNT(*) as records_count')
            ->selectRaw('SUM(CASE WHEN tasks.created_at >= ? AND tasks.created_at < ? THEN 1 ELSE 0 END) as period_count', [$from, $until])
            ->selectRaw('SUM(CASE WHEN tasks.completed_at >= ? AND tasks.completed_at < ? THEN 1 ELSE 0 END) as completed_count', [$from, $until])
            ->selectRaw('SUM(CASE WHEN tasks.due_date < ? THEN 1 ELSE 0 END) as overdue_count', [$now->toDateString()])
            ->groupBy('tasks.status')->orderBy('tasks.status')->get();
        $active = $projects->whereIn('status', ['planning', 'active', 'on_hold']);
        $activeCount = (int) $active->sum('records_count');
        $progress = BigDecimal::zero();
        foreach ($active as $row) {
            $progress = $progress->plus((string) ($row->progress_sum ?? '0'));
        }
        $counts = fn ($rows, string $field) => $rows->filter(fn ($row) => (int) $row->$field > 0)
            ->map(fn ($row) => ['status' => $row->status, 'count' => (int) $row->$field])->values()->all();

        return [
            'period' => [
                'projects_created_by_current_status' => $counts($projects, 'period_count'),
                'tasks_created_by_current_status' => $counts($tasks, 'period_count'),
                'tasks_completed' => (int) $tasks->where('status', 'done')->sum('completed_count'),
            ],
            'snapshot' => [
                'as_of' => $now->toIso8601String(),
                'projects_by_status' => $counts($projects, 'records_count'),
                'tasks_by_status' => $counts($tasks, 'records_count'),
                'active_projects' => $activeCount,
                'overdue_projects' => (int) $active->sum('overdue_count'),
                'overdue_tasks' => (int) $tasks->whereIn('status', ['todo', 'in_progress'])->sum('overdue_count'),
                'average_active_project_progress_percent' => $activeCount === 0 ? null
                    : (string) $progress->dividedBy($activeCount, 1, RoundingMode::HalfUp),
            ],
        ];
    }

    private function period(Builder $query, string $column, string $start, string $end, string $zone): Builder
    {
        if (str_ends_with($column, '_at')) {
            return $query->where($column, '>=', CarbonImmutable::parse($start, $zone)->startOfDay()->setTimezone(config('app.timezone')))
                ->where($column, '<', CarbonImmutable::parse($end, $zone)->addDay()->startOfDay()->setTimezone(config('app.timezone')));
        }

        return $query->where($column, '>=', $start)->where($column, '<', CarbonImmutable::parse($end, $zone)->addDay()->toDateString());
    }

    private function totals(Builder $query, string $column): array
    {
        return $query->select('base_currency')->selectRaw("COUNT(*) as records_count, SUM($column) as amount")
            ->groupBy('base_currency')->orderBy('base_currency')->get()
            ->map(fn ($row) => ['currency' => $row->base_currency ?: 'UNSPECIFIED', 'count' => (int) $row->records_count, 'amount' => $this->money($row->amount)])->all();
    }

    private function flows(Builder $query, string $incoming): array
    {
        return $query->select('base_currency')
            ->selectRaw('SUM(CASE WHEN entry_type = ? THEN base_amount ELSE 0 END) as gross', [$incoming])
            ->selectRaw("SUM(CASE WHEN entry_type = 'reversal' THEN base_amount ELSE 0 END) as reversals")
            ->groupBy('base_currency')->orderBy('base_currency')->get()
            ->map(fn ($row) => [
                'currency' => $row->base_currency ?: 'UNSPECIFIED',
                'gross' => $this->money($row->gross), 'reversals' => $this->money($row->reversals),
                'net' => (string) BigDecimal::of($this->money($row->gross))->minus($this->money($row->reversals))->toScale(2),
            ])->all();
    }

    private function money(mixed $value): string
    {
        return (string) BigDecimal::of((string) ($value ?? '0'))->toScale(2, RoundingMode::HalfUp);
    }
}
