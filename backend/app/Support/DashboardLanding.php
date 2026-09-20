<?php

namespace App\Support;

use App\Models\User;

final class DashboardLanding
{
    public static function routeFor(User $user): string
    {
        if ($user->hasPermissionCode('dashboard.view')) {
            return route('dashboard', absolute: false);
        }

        foreach ([
            'sales.dashboard.view' => 'sales.dashboard',
            'expenses.view' => 'finance.dashboard',
            'projects.view' => 'projects.index',
            'tasks.view' => 'my-work.dashboard',
            'purchase_orders.view' => 'procurement.dashboard',
            'inventory.view' => 'procurement.dashboard',
            'workflows.approve' => 'procurement.dashboard',
            'payroll.view' => 'hr.dashboard',
            'hr.self.view' => 'hr.index',
            'workflows.view' => 'workflows.index',
            'invoices.view' => 'invoices.index',
            'customers.view' => 'customers.index',
        ] as $permission => $routeName) {
            if ($user->hasPermissionCode($permission)) {
                return route($routeName, absolute: false);
            }
        }

        return route('profile.edit', absolute: false);
    }
}
