<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
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
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $user = auth()->user();
        $orgId = $user->org_id;
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
            'financeSummary' => $this->canViewFinanceDashboard($user) ? $this->financeSummary($orgId) : null,
            'deliverySummary' => $this->canViewDeliveryDashboard($user) ? $this->deliverySummary($user) : null,
            'recentAudits' => AuditLog::where('org_id', $orgId)->latest()->limit(8)->get(['action', 'entity_type', 'created_at']),
        ]);
    }

    private function canViewFinanceDashboard(User $user): bool
    {
        return $user->hasPermissionCode('expenses.view');
    }

    private function canViewDeliveryDashboard(User $user): bool
    {
        return $user->hasPermissionCode('projects.view') || $user->hasPermissionCode('tasks.view');
    }

    private function financeSummary(string $orgId): array
    {
        $invoiceRevenueStatuses = ['sent', 'partially_paid', 'paid', 'overdue'];
        $openInvoiceStatuses = ['sent', 'partially_paid', 'overdue'];

        $invoicedRevenue = (float) Invoice::where('org_id', $orgId)
            ->whereIn('status', $invoiceRevenueStatuses)
            ->sum('total');
        $outstandingAr = (float) Invoice::where('org_id', $orgId)
            ->whereIn('status', $openInvoiceStatuses)
            ->sum('balance_due');
        $overdueAr = (float) Invoice::where('org_id', $orgId)
            ->whereIn('status', $openInvoiceStatuses)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->sum('balance_due');
        $cashInBreakdown = Payment::where('org_id', $orgId)
            ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'receipt' THEN amount ELSE 0 END), 0) as receipts")
            ->selectRaw("COALESCE(SUM(CASE WHEN entry_type = 'reversal' THEN amount ELSE 0 END), 0) as reversals")
            ->first();
        $receiptTotal = (float) ($cashInBreakdown->receipts ?? 0);
        $reversalTotal = (float) ($cashInBreakdown->reversals ?? 0);
        $cashIn = $receiptTotal - $reversalTotal;
        $recognizedExpense = (float) Expense::where('org_id', $orgId)
            ->whereIn('status', ['approved', 'paid'])
            ->sum('amount');
        $cashOut = (float) Expense::where('org_id', $orgId)
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->sum('amount');

        return [
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
            'invoice_status' => Invoice::where('org_id', $orgId)
                ->select('status', DB::raw('count(*) as count'), DB::raw('sum(total) as total'))
                ->groupBy('status')
                ->orderBy('status')
                ->get(),
            'payment_reversals' => [
                'count' => Payment::where('org_id', $orgId)->where('entry_type', 'reversal')->count(),
                'amount' => round($reversalTotal, 2),
            ],
        ];
    }

    private function deliverySummary(User $user): array
    {
        $projectIds = $user->hasPermissionCode('projects.view')
            ? ProjectAccess::scopeProjects(Project::query(), $user)->pluck('id')
            : collect();
        $taskQuery = $user->hasPermissionCode('tasks.view')
            ? TaskAccess::scopeTasks(Task::query(), $user)
            : Task::query()->whereRaw('1 = 0');
        $taskIds = (clone $taskQuery)->pluck('tasks.id');
        $openTaskStatuses = ['todo', 'in_progress', 'blocked'];
        $overdueTaskStatuses = ['todo', 'in_progress'];

        $activeProjects = Project::whereIn('id', $projectIds)
            ->whereIn('status', ['planning', 'active', 'on_hold'])
            ->count();
        $totalBudget = (float) Project::whereIn('id', $projectIds)->sum('budget_amount');
        $actualCost = (float) Expense::where('org_id', $user->org_id)
            ->whereIn('project_id', $projectIds)
            ->whereIn('status', ['approved', 'paid'])
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
        $overBudgetProjectIds = $this->overBudgetProjectIds($user->org_id, $projectIds);
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

    private function overBudgetProjectIds(string $orgId, $projectIds)
    {
        if ($projectIds->isEmpty()) {
            return collect();
        }

        $costs = Expense::where('org_id', $orgId)
            ->whereIn('project_id', $projectIds)
            ->whereIn('status', ['approved', 'paid'])
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
