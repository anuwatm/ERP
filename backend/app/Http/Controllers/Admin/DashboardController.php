<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Department;
use App\Models\Division;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Support\ProjectAccess;
use App\Support\TaskAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = auth()->user();
        $scope = match ($request->route()?->getName()) {
            'executive.dashboard' => 'executive',
            'finance.dashboard' => 'finance',
            'delivery.dashboard' => 'delivery',
            default => 'admin',
        };

        abort_if($scope === 'executive' && ! $this->canViewExecutiveDashboard($user), 403);
        abort_if($scope === 'finance' && ! $this->canViewFinanceDashboard($user), 403);
        abort_if($scope === 'delivery' && ! $this->canViewDeliveryDashboard($user), 403);

        $orgId = $user->org_id;
        $filters = $this->dashboardFilters($request);
        $inactiveUsers = User::where('org_id', $orgId)->where('status', 'inactive')->count();
        $pendingInvites = User::where('org_id', $orgId)->where('status', 'invited')->count();
        $expiredInvites = User::where('org_id', $orgId)
            ->where('status', 'invited')
            ->where('invite_expires_at', '<', now())
            ->count();
        $sensitiveAuditEvents = AuditLog::where('org_id', $orgId)
            ->whereIn('action', ['user.role_change', 'user.disable', 'user.hierarchy_change'])
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return Inertia::render('Dashboard', [
            'dashboardScope' => $scope,
            'summary' => [
                'branches' => Branch::where('org_id', $orgId)->count(),
                'divisions' => Division::where('org_id', $orgId)->count(),
                'departments' => Department::where('org_id', $orgId)->count(),
                'users' => User::where('org_id', $orgId)->count(),
                'active_users' => User::where('org_id', $orgId)->where('status', 'active')->count(),
                'invited_users' => $pendingInvites,
                'roles' => Role::where('org_id', $orgId)->count(),
                'recent_audits' => AuditLog::where('org_id', $orgId)->count(),
            ],
            'securityAlerts' => [
                'inactive_users' => $inactiveUsers,
                'pending_invites' => $pendingInvites,
                'expired_invites' => $expiredInvites,
                'sensitive_audit_events_24h' => $sensitiveAuditEvents,
                'total' => $inactiveUsers + $expiredInvites + $sensitiveAuditEvents,
            ],
            'dashboardFilters' => $filters,
            'executiveSummary' => in_array($scope, ['admin', 'executive'], true) && $this->canViewExecutiveDashboard($user) ? $this->executiveSummary($user, $filters) : null,
            'financeSummary' => in_array($scope, ['admin', 'finance'], true) && $this->canViewFinanceDashboard($user) ? $this->financeSummary($orgId, $filters) : null,
            'deliverySummary' => in_array($scope, ['admin', 'delivery'], true) && $this->canViewDeliveryDashboard($user) ? $this->deliverySummary($user, $filters) : null,
            'recentAudits' => AuditLog::where('org_id', $orgId)->latest()->limit(8)->get(['action', 'entity_type', 'created_at']),
        ]);
    }

    private function canViewExecutiveDashboard(User $user): bool
    {
        return $user->hasPermissionCode('executive.dashboard.view')
            || $user->roles()->whereIn('code', ['owner', 'admin'])->exists();
    }

    private function canViewFinanceDashboard(User $user): bool
    {
        return $user->hasPermissionCode('expenses.view');
    }

    private function canViewDeliveryDashboard(User $user): bool
    {
        return $user->hasPermissionCode('projects.view') || $user->hasPermissionCode('tasks.view');
    }

    private function executiveSummary(User $user, array $filters): array
    {
        $orgId = $user->org_id;
        $openDealStages = ['new', 'contacted', 'qualified', 'proposal', 'negotiation'];
        $finance = $this->financeSummary($orgId, $filters);
        $projectIds = $this->applyDateRange(Project::where('org_id', $orgId), 'created_at', $filters)->pluck('id');
        $activeProjectStatuses = ['planning', 'active', 'on_hold'];
        $openTaskStatuses = ['todo', 'in_progress', 'blocked'];
        $overdueTaskStatuses = ['todo', 'in_progress'];
        $totalBudget = (float) Project::whereIn('id', $projectIds)->sum('budget_amount');
        $actualCost = (float) $this->applyDateRange(Expense::where('org_id', $orgId)
            ->whereIn('project_id', $projectIds)
            ->whereIn('status', ['approved', 'paid']), 'expense_date', $filters)
            ->sum('amount');
        $overBudgetProjectIds = $this->overBudgetProjectIds($orgId, $projectIds, $filters);
        $pastDueProjectIds = Project::whereIn('id', $projectIds)
            ->whereIn('status', $activeProjectStatuses)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->pluck('id');
        $urgentProjectIds = Task::where('org_id', $orgId)
            ->whereIn('status', $openTaskStatuses)
            ->whereIn('priority', ['high', 'urgent'])
            ->whereNotNull('project_id')
            ->pluck('project_id')
            ->unique()
            ->values();
        $riskProjectIds = $overBudgetProjectIds
            ->merge($pastDueProjectIds)
            ->merge($urgentProjectIds)
            ->unique()
            ->values();

        return [
            'sales' => [
                'customers' => $this->applyDateRange(Customer::where('org_id', $orgId), 'created_at', $filters)->count(),
                'open_deals' => $this->applyDateRange(Deal::where('org_id', $orgId)->whereIn('stage', $openDealStages), 'created_at', $filters)->count(),
                'pipeline_value' => round((float) $this->applyDateRange(Deal::where('org_id', $orgId)->whereIn('stage', $openDealStages), 'created_at', $filters)->sum('value_amount'), 2),
                'won_deals' => $this->applyDateRange(Deal::where('org_id', $orgId)->where('stage', 'won'), 'created_at', $filters)->count(),
                'won_value' => round((float) $this->applyDateRange(Deal::where('org_id', $orgId)->where('stage', 'won'), 'created_at', $filters)->sum('value_amount'), 2),
            ],
            'finance' => [
                'invoiced_revenue' => $finance['invoiced_revenue'],
                'cash_in' => $finance['cash_in'],
                'outstanding_ar' => $finance['outstanding_ar'],
                'overdue_ar' => $finance['overdue_ar'],
                'recognized_expense' => $finance['recognized_expense'],
                'gross_profit' => $finance['gross_profit'],
            ],
            'delivery' => [
                'active_projects' => Project::whereIn('id', $projectIds)->whereIn('status', $activeProjectStatuses)->count(),
                'overdue_tasks' => $this->applyDateRange(Task::where('org_id', $orgId)
                    ->whereIn('status', $overdueTaskStatuses)
                    ->whereNotNull('due_date')
                    ->whereDate('due_date', '<', now()->toDateString()), 'due_date', $filters)
                    ->count(),
                'project_profit' => round($totalBudget - $actualCost, 2),
                'delivery_risk_count' => $riskProjectIds->count(),
            ],
        ];
    }

    private function financeSummary(string $orgId, array $filters): array
    {
        $summary = $this->financeMetricSummary($orgId, $filters);
        $previousFilters = $this->previousDashboardFilters($filters);

        $summary['previous'] = $previousFilters
            ? $this->financeMetricSummary($orgId, $previousFilters, false)
            : null;

        return $summary;
    }

    private function financeMetricSummary(string $orgId, array $filters, bool $includeDetails = true): array
    {
        $invoiceRevenueStatuses = ['sent', 'partially_paid', 'paid', 'overdue'];
        $openInvoiceStatuses = ['sent', 'partially_paid', 'overdue'];

        $invoicedRevenue = (float) $this->applyDateRange(Invoice::where('org_id', $orgId)
            ->whereIn('status', $invoiceRevenueStatuses), 'issue_date', $filters)
            ->sum('total');
        $outstandingAr = (float) $this->applyDateRange(Invoice::where('org_id', $orgId)
            ->whereIn('status', $openInvoiceStatuses), 'issue_date', $filters)
            ->sum('balance_due');
        $overdueAr = (float) $this->applyDateRange(Invoice::where('org_id', $orgId)
            ->whereIn('status', $openInvoiceStatuses)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString()), 'issue_date', $filters)
            ->sum('balance_due');
        $cashInBreakdown = $this->applyDateRange(Payment::where('org_id', $orgId), 'payment_date', $filters)
            ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'receipt' THEN amount ELSE 0 END), 0) as receipts")
            ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'reversal' THEN amount ELSE 0 END), 0) as reversals")
            ->first();
        $receiptTotal = (float) ($cashInBreakdown->receipts ?? 0);
        $reversalTotal = (float) ($cashInBreakdown->reversals ?? 0);
        $cashIn = $receiptTotal - $reversalTotal;
        $recognizedExpense = (float) $this->applyDateRange(Expense::where('org_id', $orgId)
            ->whereIn('status', ['approved', 'paid']), 'expense_date', $filters)
            ->sum('amount');
        $cashOut = (float) $this->applyDateRange(Expense::where('org_id', $orgId)
            ->where('status', 'paid')
            ->whereNotNull('paid_at'), 'paid_at', $filters)
            ->sum('amount');

        $summary = [
            'invoiced_revenue' => round($invoicedRevenue, 2),
            'cash_in' => round($cashIn, 2),
            'cash_in_receipts' => round($receiptTotal, 2),
            'cash_in_reversals' => round($reversalTotal, 2),
            'outstanding_ar' => round($outstandingAr, 2),
            'overdue_ar' => round($overdueAr, 2),
            'recognized_expense' => round($recognizedExpense, 2),
            'cash_out' => round($cashOut, 2),
            'net_cash_flow' => round($cashIn - $cashOut, 2),
            'gross_profit' => round($invoicedRevenue - $recognizedExpense, 2),
        ];

        if (! $includeDetails) {
            return $summary;
        }

        return [
            ...$summary,
            'invoice_status' => $this->applyDateRange(Invoice::where('org_id', $orgId), 'issue_date', $filters)
                ->select('status', DB::raw('count(*) as count'), DB::raw('sum(total) as total'))
                ->groupBy('status')
                ->orderBy('status')
                ->get(),
            'payment_reversals' => [
                'count' => $this->applyDateRange(Payment::where('org_id', $orgId)->where('entry_type', 'reversal'), 'payment_date', $filters)->count(),
                'amount' => round($reversalTotal, 2),
            ],
        ];
    }

    private function deliverySummary(User $user, array $filters): array
    {
        $projectIds = $user->hasPermissionCode('projects.view')
            ? $this->applyDateRange(ProjectAccess::scopeProjects(Project::query(), $user), 'created_at', $filters)->pluck('id')
            : collect();
        $taskQuery = $user->hasPermissionCode('tasks.view')
            ? $this->applyDateRange(TaskAccess::scopeTasks(Task::query(), $user), 'tasks.created_at', $filters)
            : Task::query()->whereRaw('1 = 0');
        $taskIds = (clone $taskQuery)->pluck('tasks.id');
        $openTaskStatuses = ['todo', 'in_progress', 'blocked'];
        $overdueTaskStatuses = ['todo', 'in_progress'];

        $activeProjects = Project::whereIn('id', $projectIds)
            ->whereIn('status', ['planning', 'active', 'on_hold'])
            ->count();
        $totalBudget = (float) Project::whereIn('id', $projectIds)->sum('budget_amount');
        $actualCost = (float) $this->applyDateRange(Expense::where('org_id', $user->org_id)
            ->whereIn('project_id', $projectIds)
            ->whereIn('status', ['approved', 'paid']), 'expense_date', $filters)
            ->sum('amount');
        $overdueTasks = Task::whereIn('id', $taskIds)
            ->whereIn('status', $overdueTaskStatuses)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->count();
        $projectStatus = Project::whereIn('id', $projectIds)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->orderBy('status')
            ->get();
        $taskLoad = Task::whereIn('tasks.id', $taskIds)
            ->leftJoin('users as assignees', 'tasks.assignee_id', '=', 'assignees.id')
            ->whereIn('tasks.status', $openTaskStatuses)
            ->select('tasks.assignee_id', 'assignees.name as assignee_name', DB::raw('count(*) as open_tasks'))
            ->groupBy('tasks.assignee_id', 'assignees.name')
            ->orderByDesc('open_tasks')
            ->limit(8)
            ->get();
        $overBudgetProjectIds = $this->overBudgetProjectIds($user->org_id, $projectIds, $filters);
        $pastDueProjectIds = Project::whereIn('id', $projectIds)
            ->whereIn('status', ['planning', 'active', 'on_hold'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->pluck('id');
        $urgentProjectIds = Task::whereIn('id', $taskIds)
            ->whereIn('status', $openTaskStatuses)
            ->whereIn('priority', ['high', 'urgent'])
            ->whereNotNull('project_id')
            ->pluck('project_id')
            ->unique()
            ->values();
        $riskProjectIds = $overBudgetProjectIds
            ->merge($pastDueProjectIds)
            ->merge($urgentProjectIds)
            ->unique()
            ->values();

        return [
            'active_projects' => $activeProjects,
            'overdue_tasks' => $overdueTasks,
            'total_budget' => round($totalBudget, 2),
            'actual_cost' => round($actualCost, 2),
            'project_profit' => round($totalBudget - $actualCost, 2),
            'project_status' => $projectStatus,
            'task_load' => $taskLoad,
            'delivery_risk' => [
                'count' => $riskProjectIds->count(),
                'over_budget' => $overBudgetProjectIds->count(),
                'past_due_projects' => $pastDueProjectIds->count(),
                'urgent_or_high_open_tasks' => Task::whereIn('id', $taskIds)
                    ->whereIn('status', $openTaskStatuses)
                    ->whereIn('priority', ['high', 'urgent'])
                    ->count(),
            ],
        ];
    }

    private function dashboardFilters(Request $request): array
    {
        $period = in_array($request->string('period')->toString(), ['all_time', 'month', 'year', 'custom'], true)
            ? $request->string('period')->toString()
            : 'all_time';
        $now = CarbonImmutable::now();
        $start = null;
        $end = null;
        $month = $request->string('month')->toString() ?: $now->format('Y-m');
        $year = $request->string('year')->toString() ?: $now->format('Y');
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        if ($period === 'month') {
            try {
                $start = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01')->startOfDay();
                $end = $start->endOfMonth();
            } catch (\Throwable) {
                $period = 'all_time';
            }
        } elseif ($period === 'year') {
            if (preg_match('/^\\d{4}$/', $year) === 1) {
                $start = CarbonImmutable::create((int) $year, 1, 1)->startOfDay();
                $end = $start->endOfYear();
            } else {
                $period = 'all_time';
            }
        } elseif ($period === 'custom') {
            try {
                $start = $from !== '' ? CarbonImmutable::parse($from)->startOfDay() : null;
                $end = $to !== '' ? CarbonImmutable::parse($to)->endOfDay() : null;

                if ($start && $end && $start->gt($end)) {
                    [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
                }
            } catch (\Throwable) {
                $period = 'all_time';
                $start = null;
                $end = null;
            }
        }

        return [
            'period' => $period,
            'month' => $month,
            'year' => $year,
            'from' => $from,
            'to' => $to,
            'start_date' => $start?->toDateString(),
            'end_date' => $end?->toDateString(),
        ];
    }

    private function previousDashboardFilters(array $filters): ?array
    {
        if (empty($filters['start_date']) || empty($filters['end_date'])) {
            return null;
        }

        $start = CarbonImmutable::parse($filters['start_date'])->startOfDay();
        $end = CarbonImmutable::parse($filters['end_date'])->endOfDay();
        $days = $start->diffInDays($end) + 1;
        $previousEnd = $start->subDay()->endOfDay();
        $previousStart = $previousEnd->subDays($days - 1)->startOfDay();

        return [
            ...$filters,
            'start_date' => $previousStart->toDateString(),
            'end_date' => $previousEnd->toDateString(),
        ];
    }

    private function applyDateRange($query, string $column, array $filters)
    {
        if (! empty($filters['start_date'])) {
            $query->whereDate($column, '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate($column, '<=', $filters['end_date']);
        }

        return $query;
    }

    private function overBudgetProjectIds(string $orgId, $projectIds, array $filters)
    {
        if ($projectIds->isEmpty()) {
            return collect();
        }

        $costs = $this->applyDateRange(Expense::where('org_id', $orgId)
            ->whereIn('project_id', $projectIds)
            ->whereIn('status', ['approved', 'paid']), 'expense_date', $filters)
            ->selectRaw('project_id, SUM(amount) as actual_cost')
            ->groupBy('project_id')
            ->pluck('actual_cost', 'project_id');

        return Project::whereIn('id', $projectIds)
            ->get(['id', 'budget_amount'])
            ->filter(fn (Project $project) => (float) ($costs[$project->id] ?? 0) > (float) $project->budget_amount)
            ->pluck('id')
            ->values();
    }
}
