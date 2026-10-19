<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Models\VendorPayment;
use App\Support\DashboardAccess;
use App\Support\DashboardDate;
use App\Support\ProjectAccess;
use App\Support\SalesAccess;
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
        $capabilities = DashboardAccess::capabilities($user);
        $canViewFinanceComposite = $capabilities['finance_invoices']
            && $capabilities['finance_payments']
            && $capabilities['finance_expenses'];

        return Inertia::render('Dashboard', [
            'dashboardScope' => $scope,
            'dashboardCapabilities' => $capabilities,
            'dashboardWidgets' => $this->dashboardWidgets($user, $filters, $scope),
            'summary' => null,
            'securityAlerts' => null,
            'dashboardFilters' => $filters,
            'executiveSummary' => null,
            'financeSummary' => $scope === 'finance' && $this->canViewFinanceDashboard($user) && $canViewFinanceComposite ? $this->financeSummary($orgId, $filters) : null,
            // The legacy delivery composite includes approved expense cost. Keep it
            // absent unless that source is authorized; partial project/task views
            // use dashboardWidgets and never serialize finance data.
            'deliverySummary' => $scope === 'delivery'
                && $this->canViewDeliveryDashboard($user)
                && DashboardAccess::canViewWidget($user, 'finance.expense')
                ? $this->deliverySummary($user, $filters)
                : null,
            'recentAudits' => [],
        ]);
    }

    private function canViewExecutiveDashboard(User $user): bool
    {
        return DashboardAccess::canViewScope($user, 'executive');
    }

    private function canViewFinanceDashboard(User $user): bool
    {
        return DashboardAccess::canViewScope($user, 'finance');
    }

    private function canViewDeliveryDashboard(User $user): bool
    {
        return DashboardAccess::canViewScope($user, 'delivery');
    }

    /**
     * Widget data is evaluated behind its source permission before any query is
     * executed. New dashboard pages consume this map during the 23.2 rollout;
     * legacy composite props below remain deliberately all-or-nothing.
     *
     * @return array<string, array<string, mixed>>
     */
    private function dashboardWidgets(User $user, array $filters, string $scope): array
    {
        $orgId = $user->org_id;
        $widgets = [];

        if ($scope === 'admin' && DashboardAccess::canViewWidget($user, 'admin.users')) {
            $counts = User::where('org_id', $orgId)
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active")
                ->selectRaw("SUM(CASE WHEN status = 'invited' AND invite_expires_at >= ? THEN 1 ELSE 0 END) as invited", [now()])
                ->selectRaw("SUM(CASE WHEN status = 'invited' AND invite_expires_at < ? THEN 1 ELSE 0 END) as expired_invites", [now()])
                ->selectRaw("SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive")
                ->first();
            $widgets['admin']['users'] = [
                'total' => (int) $counts->total,
                'active' => (int) $counts->active,
                'invited' => (int) $counts->invited,
                'expired_invites' => (int) $counts->expired_invites,
                'inactive' => (int) $counts->inactive,
            ];
            $widgets['admin']['recent_invites'] = User::where('org_id', $orgId)
                ->where('status', 'invited')
                ->where('invite_expires_at', '>=', now())
                ->latest('invited_at')
                ->limit(8)
                ->get(['id', 'name', 'email', 'invite_expires_at']);
        }

        if ($scope === 'admin' && DashboardAccess::canViewWidget($user, 'admin.roles')) {
            $widgets['admin']['roles'] = [
                'total' => Role::where('org_id', $orgId)->count(),
                'distribution' => Role::where('org_id', $orgId)
                    ->withCount('users')
                    ->orderByDesc('users_count')
                    ->orderBy('code')
                    ->limit(12)
                    ->get(['id', 'code', 'name']),
            ];
        }

        if ($scope === 'admin' && DashboardAccess::canViewWidget($user, 'admin.audit')) {
            $audit = AuditLog::where('org_id', $orgId);
            $widgets['admin']['audit'] = [
                'today' => DashboardDate::range(clone $audit, 'created_at', DashboardDate::now()->toDateString(), DashboardDate::now()->toDateString())->count(),
                'today_date' => DashboardDate::now()->toDateString(),
                'sensitive_24h' => (clone $audit)
                    ->whereIn('action', ['user.role_change', 'user.disable', 'user.hierarchy_change'])
                    ->where('created_at', '>=', now()->subDay())
                    ->count(),
            ];
            $widgets['admin']['recent_audits'] = (clone $audit)
                ->latest()
                ->limit(8)
                ->get(['id', 'action', 'entity_type', 'created_at']);
        }

        if ($scope !== 'delivery' && DashboardAccess::canViewWidget($user, 'finance.revenue')) {
            $widgets['finance']['revenue'] = [
                'invoiced_revenue' => round((float) $this->applyDateRange(
                    Invoice::where('org_id', $orgId)->whereIn('status', ['sent', 'partially_paid', 'paid', 'overdue']),
                    'issue_date',
                    $filters,
                )->sum('base_total'), 2),
            ];
        }

        if ($scope !== 'delivery' && DashboardAccess::canViewWidget($user, 'finance.ar')) {
            $openInvoices = Invoice::where('org_id', $orgId)->whereIn('status', ['sent', 'partially_paid', 'overdue'])->where('base_balance_due', '>', 0);
            $today = DashboardDate::now()->toDateString();
            $day30 = DashboardDate::now()->subDays(30)->toDateString();
            $day60 = DashboardDate::now()->subDays(60)->toDateString();
            $aging = (clone $openInvoices)->selectRaw('SUM(base_balance_due) as outstanding_ar')
                ->selectRaw('SUM(CASE WHEN due_date < ? THEN base_balance_due ELSE 0 END) as overdue_ar', [$today])
                ->selectRaw('SUM(CASE WHEN due_date IS NULL THEN base_balance_due ELSE 0 END) as no_due_date')
                ->selectRaw('SUM(CASE WHEN due_date >= ? THEN base_balance_due ELSE 0 END) as current_amount', [$today])
                ->selectRaw('SUM(CASE WHEN due_date < ? AND due_date >= ? THEN base_balance_due ELSE 0 END) as days_1_30', [$today, $day30])
                ->selectRaw('SUM(CASE WHEN due_date < ? AND due_date >= ? THEN base_balance_due ELSE 0 END) as days_31_60', [$day30, $day60])
                ->selectRaw('SUM(CASE WHEN due_date < ? THEN base_balance_due ELSE 0 END) as days_61_plus', [$day60])->first();
            $widgets['finance']['ar'] = [
                'outstanding_ar' => round((float) $aging->outstanding_ar, 2),
                'overdue_ar' => round((float) $aging->overdue_ar, 2),
                'aging' => [
                    'no_due_date' => round((float) $aging->no_due_date, 2),
                    'current' => round((float) $aging->current_amount, 2),
                    'days_1_30' => round((float) $aging->days_1_30, 2),
                    'days_31_60' => round((float) $aging->days_31_60, 2),
                    'days_61_plus' => round((float) $aging->days_61_plus, 2),
                ],
            ];
            $widgets['finance']['overdue_invoices'] = (clone $openInvoices)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', DashboardDate::now()->toDateString())
                ->orderBy('due_date')
                ->limit(8)
                ->get(['id', 'invoice_no', 'due_date', 'base_balance_due']);
            $widgets['finance']['invoice_status'] = $scope === 'finance' ? $this->applyDateRange(Invoice::where('org_id', $orgId), 'issue_date', $filters)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->orderBy('status')
                ->get() : collect();
        }

        if ($scope !== 'delivery' && DashboardAccess::canViewWidget($user, 'finance.cash_in')) {
            $cashIn = $this->applyDateRange(Payment::where('org_id', $orgId), 'payment_date', $filters)
                ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'receipt' THEN base_amount ELSE 0 END), 0) as receipts")
                ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'reversal' THEN base_amount ELSE 0 END), 0) as reversals")
                ->first();
            $widgets['finance']['cash_in'] = [
                'receipts' => round((float) ($cashIn->receipts ?? 0), 2),
                'reversals' => round((float) ($cashIn->reversals ?? 0), 2),
            ];
            $widgets['finance']['recent_payments'] = $scope === 'finance' ? $this->applyDateRange(Payment::where('org_id', $orgId), 'payment_date', $filters)
                ->latest('payment_date')
                ->limit(8)
                ->get(['id', 'invoice_id', 'entry_type', 'base_amount', 'payment_date']) : collect();
        }

        if ($scope !== 'delivery' && DashboardAccess::canViewWidget($user, 'finance.expense')) {
            $cashOut = $this->applyDateRange(VendorPayment::where('org_id', $orgId), 'payment_date', $filters)
                ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'payment' THEN base_amount ELSE 0 END), 0) as payments")
                ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'reversal' THEN base_amount ELSE 0 END), 0) as reversals")
                ->first();
            $widgets['finance']['expense'] = [
                'recognized_expense' => round((float) $this->applyDateRange(Expense::where('org_id', $orgId)->whereIn('status', ['approved', 'partially_paid', 'paid']), 'expense_date', $filters)->sum('base_amount'), 2),
                'ap_cash_out' => round((float) ($cashOut->payments ?? 0) - (float) ($cashOut->reversals ?? 0), 2),
            ];
            $widgets['finance']['expenses_awaiting_approval'] = $scope === 'finance' ? $this->applyDateRange(Expense::where('org_id', $orgId)->where('status', 'draft'), 'expense_date', $filters)
                ->orderBy('expense_date')
                ->limit(8)
                ->get(['id', 'expense_no', 'title', 'expense_date']) : collect();
        }

        if (isset($widgets['finance']['cash_in']) || isset($widgets['finance']['expense'])) {
            $widgets['finance']['cash_flow_trend'] = $this->financeCashFlowTrend(
                $orgId,
                $filters,
                isset($widgets['finance']['cash_in']),
                isset($widgets['finance']['expense']),
            );
        }

        if (in_array($scope, ['admin', 'executive'], true) && DashboardAccess::canViewWidget($user, 'sales.pipeline')) {
            $deals = SalesAccess::scopeDeals(Deal::query(), $user)->whereIn('stage', Deal::OPEN_STAGES);
            $widgets['sales']['pipeline'] = [
                'open_deals' => (clone $deals)->count(),
                'by_currency' => (clone $deals)->select('currency', DB::raw('sum(value_amount) as amount'))
                    ->groupBy('currency')->orderBy('currency')->get()
                    ->map(fn ($row) => ['currency' => $row->currency ?? 'Unspecified', 'amount' => (float) $row->amount]),
            ];
        }

        if ($scope !== 'finance' && DashboardAccess::canViewWidget($user, 'delivery.projects')) {
            $projects = ProjectAccess::scopeProjects(Project::query(), $user);
            $activeProjects = (clone $projects)->whereIn('status', ['planning', 'active', 'on_hold']);
            $widgets['delivery']['projects'] = [
                'active_projects' => (clone $activeProjects)->count(),
                'average_progress' => ($average = (clone $activeProjects)->avg('progress_percent')) === null ? null : round((float) $average, 1),
                'status' => (clone $projects)->select('status', DB::raw('count(*) as count'))->groupBy('status')->orderBy('status')->get(),
            ];
            $widgets['delivery']['past_due_projects'] = $scope === 'delivery' ? (clone $activeProjects)
                ->whereNotNull('due_date')->whereDate('due_date', '<', DashboardDate::now()->toDateString())
                ->orderBy('due_date')->limit(8)
                ->get(['id', 'project_code', 'name', 'due_date']) : collect();
        }

        if ($scope !== 'finance' && DashboardAccess::canViewWidget($user, 'delivery.tasks')) {
            $tasks = TaskAccess::scopeTasks(Task::query(), $user);
            $overdueTasks = (clone $tasks)->whereIn('status', ['todo', 'in_progress'])
                ->whereNotNull('due_date')->whereDate('due_date', '<', DashboardDate::now()->toDateString());
            $dueThisWeek = (clone $tasks)->whereIn('status', ['todo', 'in_progress', 'blocked'])
                ->whereDate('due_date', '>=', DashboardDate::now()->startOfWeek()->toDateString())
                ->whereDate('due_date', '<=', DashboardDate::now()->endOfWeek()->toDateString());
            $widgets['delivery']['tasks'] = [
                'overdue_tasks' => (clone $overdueTasks)->count(),
                'due_this_week' => (clone $dueThisWeek)->count(),
            ];
            $widgets['delivery']['overdue_tasks'] = $scope === 'delivery' ? (clone $overdueTasks)->orderBy('due_date')->limit(8)->get(['id', 'title', 'due_date', 'status']) : collect();
            $widgets['delivery']['due_this_week'] = $scope === 'delivery' ? (clone $dueThisWeek)->orderBy('due_date')->limit(8)->get(['id', 'title', 'due_date', 'status']) : collect();
            $load = $scope === 'delivery' ? (clone $tasks)->whereIn('status', ['todo', 'in_progress', 'blocked'])
                ->select('assignee_id', DB::raw('count(*) as open_tasks'))
                ->groupBy('assignee_id')->orderByDesc('open_tasks')->limit(8)->get() : collect();
            $assigneeNames = $load->isNotEmpty() ? User::where('org_id', $orgId)->whereIn('id', $load->pluck('assignee_id')->filter())->pluck('name', 'id') : collect();
            $widgets['delivery']['assignee_load'] = $load->map(fn ($row) => [
                'assignee_id' => $row->assignee_id,
                'name' => $row->assignee_id ? ($assigneeNames[$row->assignee_id] ?? 'Unknown') : 'Unassigned',
                'open_tasks' => (int) $row->open_tasks,
            ]);
        }

        if (in_array($scope, ['admin', 'executive'], true) && $this->canViewExecutiveDashboard($user)) {
            $widgets['executive'] = $this->executiveWidgets($user, $filters, $widgets);
        }

        return $widgets;
    }

    private function executiveWidgets(User $user, array $filters, array $widgets): array
    {
        $orgId = $user->org_id;
        $executive = [];

        if (DashboardAccess::canViewWidget($user, 'executive.sales')) {
            $won = $this->applyDateRange(SalesAccess::scopeDeals(Deal::query(), $user)->where('stage', 'won'), 'won_at', $filters);
            $executive['sales'] = [
                'open_deals' => $widgets['sales']['pipeline']['open_deals'],
                'pipeline_by_currency' => $widgets['sales']['pipeline']['by_currency'],
                'won_deals' => (clone $won)->count(),
                'won_by_currency' => (clone $won)->select('currency', DB::raw('sum(value_amount) as amount'))
                    ->groupBy('currency')->orderBy('currency')->get()
                    ->map(fn ($row) => ['currency' => $row->currency ?? 'Unspecified', 'amount' => (float) $row->amount]),
            ];
        }

        if (isset($widgets['finance']['revenue']) || isset($widgets['finance']['expense'])) {
            $end = CarbonImmutable::parse($filters['end_date'] ?? DashboardDate::now()->toDateString())->endOfMonth();
            $start = $end->subMonths(11)->startOfMonth();
            if (! empty($filters['start_date'])) {
                $start = max($start, CarbonImmutable::parse($filters['start_date'])->startOfMonth());
            }
            $trendFilters = ['start_date' => $start->toDateString(), 'end_date' => $filters['end_date'] ?? DashboardDate::now()->toDateString()];
            $revenue = isset($widgets['finance']['revenue'])
                ? $this->applyDateRange(Invoice::where('org_id', $orgId)->whereIn('status', ['sent', 'partially_paid', 'paid', 'overdue']), 'issue_date', $trendFilters)
                    ->selectRaw('SUBSTR(issue_date, 1, 7) as bucket')->selectRaw('SUM(base_total) as amount')
                    ->groupBy('bucket')->pluck('amount', 'bucket') : collect();
            $expense = isset($widgets['finance']['expense'])
                ? $this->applyDateRange(Expense::where('org_id', $orgId)->whereIn('status', ['approved', 'partially_paid', 'paid']), 'expense_date', $trendFilters)
                    ->selectRaw('SUBSTR(expense_date, 1, 7) as bucket')->selectRaw('SUM(base_amount) as amount')
                    ->groupBy('bucket')->pluck('amount', 'bucket') : collect();
            $executive['revenue_expense_trend'] = [];
            for ($month = $start; $month->lte($end); $month = $month->addMonth()) {
                $bucket = $month->format('Y-m');
                $executive['revenue_expense_trend'][] = [
                    'label' => $bucket,
                    'revenue' => isset($widgets['finance']['revenue']) ? round((float) ($revenue[$bucket] ?? 0), 2) : null,
                    'expense' => isset($widgets['finance']['expense']) ? round((float) ($expense[$bucket] ?? 0), 2) : null,
                ];
            }
        }

        if (isset($widgets['finance']['revenue']) && $user->hasPermissionCode('customers.view')) {
            $customerIds = SalesAccess::scopeCustomers(Customer::query(), $user)->pluck('id');
            $executive['top_customers'] = $this->applyDateRange(Invoice::where('org_id', $orgId)
                ->whereIn('customer_id', $customerIds)
                ->whereIn('status', ['sent', 'partially_paid', 'paid', 'overdue']), 'issue_date', $filters)
                ->with('customer:id,company_name')
                ->select('customer_id', DB::raw('sum(base_total) as amount'))
                ->groupBy('customer_id')->orderByDesc('amount')->limit(8)->get()
                ->map(fn ($row) => [
                    'customer_id' => $row->customer_id,
                    'name' => $row->customer?->company_name ?? 'Unknown',
                    'amount' => round((float) $row->amount, 2),
                ]);
        }

        if (isset($widgets['delivery']['projects'])) {
            $executive['project_risks'] = $this->executiveProjectRisks($user);
        }

        return $executive;
    }

    private function executiveProjectRisks(User $user): array
    {
        $projects = ProjectAccess::scopeProjects(Project::query(), $user)
            ->whereIn('status', ['planning', 'active', 'on_hold'])
            ->get(['id', 'project_code', 'name', 'due_date', 'budget_amount', 'currency']);
        if ($projects->isEmpty()) {
            return ['count' => 0, 'items' => []];
        }

        $projectIds = $projects->pluck('id');
        $urgentIds = $user->hasPermissionCode('tasks.view')
            ? TaskAccess::scopeTasks(Task::query(), $user)
                ->whereIn('project_id', $projectIds)
                ->whereIn('status', ['todo', 'in_progress', 'blocked'])
                ->whereIn('priority', ['high', 'urgent'])
                ->pluck('project_id')->unique()
            : collect();
        $costs = $user->hasPermissionCode('expenses.view')
            ? Expense::where('org_id', $user->org_id)
                ->whereIn('project_id', $projectIds)
                ->whereIn('status', ['approved', 'partially_paid', 'paid'])
                ->select('project_id', 'currency', DB::raw('sum(amount) as actual_cost'))
                ->groupBy('project_id', 'currency')->get()->groupBy('project_id')
            : collect();
        $today = DashboardDate::now()->toDateString();

        $risks = $projects->map(function (Project $project) use ($urgentIds, $costs, $today): ?array {
            $reasons = [];
            if ($project->due_date && $project->due_date->toDateString() < $today) {
                $reasons[] = 'Past due';
            }
            if ($urgentIds->contains($project->id)) {
                $reasons[] = 'High-priority open task';
            }
            $projectCosts = $costs->get($project->id, collect());
            if ($project->budget_amount !== null
                && $projectCosts->isNotEmpty()
                && $projectCosts->every(fn ($row) => $row->currency === $project->currency)
                && (float) $projectCosts->sum('actual_cost') > (float) $project->budget_amount) {
                $reasons[] = 'Over budget';
            }

            return $reasons === [] ? null : [
                'id' => $project->id,
                'project_code' => $project->project_code,
                'name' => $project->name,
                'reasons' => $reasons,
            ];
        })->filter()->sortByDesc(fn ($risk) => count($risk['reasons']))->values();

        return ['count' => $risks->count(), 'items' => $risks->take(8)->all()];
    }

    private function financeCashFlowTrend(string $orgId, array $filters, bool $canViewCashIn, bool $canViewCashOut): array
    {
        $end = CarbonImmutable::parse($filters['end_date'] ?? DashboardDate::now()->toDateString())->endOfMonth();
        $start = $end->startOfMonth()->subMonths(11);
        if (! empty($filters['start_date'])) {
            $selectedStart = CarbonImmutable::parse($filters['start_date'])->startOfMonth();
            if ($selectedStart->gt($start)) {
                $start = $selectedStart;
            }
        }
        $trendFilters = [
            'start_date' => $start->toDateString(),
            'end_date' => $filters['end_date'] ?? DashboardDate::now()->toDateString(),
        ];
        $cashIn = collect();
        $cashOut = collect();

        if ($canViewCashIn) {
            $cashIn = $this->applyDateRange(Payment::where('org_id', $orgId), 'payment_date', $trendFilters)
                ->selectRaw('SUBSTR(payment_date, 1, 7) as bucket')
                ->selectRaw("SUM(CASE WHEN entry_type = 'receipt' THEN base_amount WHEN entry_type = 'reversal' THEN -base_amount ELSE 0 END) as amount")
                ->groupBy('bucket')
                ->pluck('amount', 'bucket');
        }
        if ($canViewCashOut) {
            $cashOut = $this->applyDateRange(VendorPayment::where('org_id', $orgId), 'payment_date', $trendFilters)
                ->selectRaw('SUBSTR(payment_date, 1, 7) as bucket')
                ->selectRaw("SUM(CASE WHEN entry_type = 'payment' THEN base_amount WHEN entry_type = 'reversal' THEN -base_amount ELSE 0 END) as amount")
                ->groupBy('bucket')
                ->pluck('amount', 'bucket');
        }

        $points = [];
        for ($month = $start; $month->lte($end); $month = $month->addMonth()) {
            $bucket = $month->format('Y-m');
            $points[] = [
                'label' => $bucket,
                'cash_in' => $canViewCashIn ? round((float) ($cashIn[$bucket] ?? 0), 2) : null,
                'ap_cash_out' => $canViewCashOut ? round((float) ($cashOut[$bucket] ?? 0), 2) : null,
            ];
        }

        return $points;
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
            ->whereDate('due_date', '<', DashboardDate::now()->toDateString()), 'issue_date', $filters)
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
            'billed_minus_expense_estimate' => round($invoicedRevenue - $recognizedExpense, 2),
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
            ->whereDate('due_date', '<', DashboardDate::now()->toDateString())
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
            ->whereDate('due_date', '<', DashboardDate::now()->toDateString())
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
            'budget_remaining' => round($totalBudget - $actualCost, 2),
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
        $period = in_array($request->string('period')->toString(), ['all_time', 'today', 'this_week', 'month', 'year', 'custom'], true)
            ? $request->string('period')->toString()
            : 'all_time';
        $now = DashboardDate::now();
        $start = null;
        $end = null;
        $month = $request->string('month')->toString() ?: $now->format('Y-m');
        $year = $request->string('year')->toString() ?: $now->format('Y');
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        if ($period === 'today') {
            $start = $now->startOfDay();
            $end = $now->endOfDay();
        } elseif ($period === 'this_week') {
            $start = $now->startOfWeek();
            $end = $now->endOfWeek();
        } elseif ($period === 'month') {
            try {
                $start = CarbonImmutable::createFromFormat('Y-m-d', $month.'-01', $now->getTimezone())->startOfDay();
                $end = $start->endOfMonth();
            } catch (\Throwable) {
                $period = 'all_time';
            }
        } elseif ($period === 'year') {
            if (preg_match('/^\\d{4}$/', $year) === 1) {
                $start = CarbonImmutable::create((int) $year, 1, 1, 0, 0, 0, $now->getTimezone())->startOfDay();
                $end = $start->endOfYear();
            } else {
                $period = 'all_time';
            }
        } elseif ($period === 'custom') {
            try {
                $start = $from !== '' ? CarbonImmutable::parse($from, $now->getTimezone())->startOfDay() : null;
                $end = $to !== '' ? CarbonImmutable::parse($to, $now->getTimezone())->endOfDay() : null;

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
        $days = (int) $start->diffInDays($end->startOfDay()) + 1;
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
        return DashboardDate::range($query, $column, $filters['start_date'] ?? null, $filters['end_date'] ?? null);
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
