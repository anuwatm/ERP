<?php

namespace App\Support;

use App\Models\User;

final class DashboardAccess
{
    /**
     * Dashboard permissions are evaluated from the effective permission set.
     * Role names intentionally do not grant dashboard access.
     *
     * @return array<string, bool>
     */
    public static function capabilities(User $user): array
    {
        // Inertia has already loaded this request's effective permissions. Never
        // reuse another actor's snapshot or retain it across HTTP requests.
        $request = request();
        $permissions = $request->user()?->id === $user->id
            && $request->user()?->org_id === $user->org_id
            ? $request->attributes->get('auth.permissions') : null;
        $can = fn (string $code): bool => is_array($permissions)
            ? in_array($code, $permissions, true)
            : $user->hasPermissionCode($code);

        return [
            'dashboard' => $can('dashboard.view'),
            'executive' => $can('executive.dashboard.view'),
            'sales' => $can('sales.dashboard.view'),
            'finance_invoices' => $can('invoices.view'),
            'finance_payments' => $can('payments.view'),
            'finance_expenses' => $can('expenses.view'),
            'delivery_projects' => $can('projects.view'),
            'delivery_tasks' => $can('tasks.view'),
            'admin_users' => $can('users.view'),
            'admin_roles' => $can('roles.view'),
            'admin_audit' => $can('audit.view'),
            'workflow_inbox' => $can('workflows.approve'),
            'hr' => $can('hr.manage'),
            'payroll' => $can('payroll.view'),
            'inventory' => $can('inventory.view'),
            'procurement' => $can('purchase_orders.view'),
            'sales_deals' => $can('deals.view'),
        ];
    }

    public static function canViewScope(User $user, string $scope): bool
    {
        $capabilities = self::capabilities($user);

        return match ($scope) {
            'admin' => $capabilities['dashboard'],
            'executive' => $capabilities['dashboard'] && $capabilities['executive'],
            'sales' => $capabilities['sales'],
            // Keep the existing Finance dashboard route contract. New widgets are
            // additionally gated by canViewWidget() during the 23.2 rollout.
            'finance' => $capabilities['dashboard'] && $capabilities['finance_expenses'],
            'delivery' => $capabilities['dashboard']
                && ($capabilities['delivery_projects'] || $capabilities['delivery_tasks']),
            default => false,
        };
    }

    public static function canViewWidget(User $user, string $widget): bool
    {
        $capabilities = self::capabilities($user);

        return match ($widget) {
            'finance.revenue', 'finance.ar' => $capabilities['finance_invoices'],
            'finance.cash_in' => $capabilities['finance_payments'],
            'finance.expense' => $capabilities['finance_expenses'],
            'delivery.projects' => $capabilities['delivery_projects'],
            'delivery.tasks' => $capabilities['delivery_tasks'],
            'admin.users' => $capabilities['admin_users'],
            'admin.roles' => $capabilities['admin_roles'],
            'admin.audit' => $capabilities['admin_audit'],
            'workflow.inbox' => $capabilities['workflow_inbox'],
            'hr.summary' => $capabilities['hr'],
            'payroll.summary' => $capabilities['payroll'],
            'inventory.stock' => $capabilities['inventory'],
            'procurement.summary' => $capabilities['procurement'],
            'sales.pipeline' => $capabilities['sales_deals'],
            'executive.sales' => $capabilities['sales_deals'],
            'executive.finance' => $capabilities['finance_invoices']
                || $capabilities['finance_payments']
                || $capabilities['finance_expenses'],
            'executive.delivery' => $capabilities['delivery_projects']
                || $capabilities['delivery_tasks'],
            default => false,
        };
    }
}
