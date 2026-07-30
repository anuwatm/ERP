<?php

namespace App\Support;

class PermissionCatalog
{
    public static function permissions(): array
    {
        return [
            ['code' => 'dashboard.view', 'module' => 'dashboard', 'action' => 'view', 'description' => 'View admin dashboard'],
            ['code' => 'sales.dashboard.view', 'module' => 'sales', 'action' => 'view', 'description' => 'View sales dashboard'],
            ['code' => 'users.view', 'module' => 'users', 'action' => 'view', 'description' => 'View users'],
            ['code' => 'users.create', 'module' => 'users', 'action' => 'create', 'description' => 'Invite users'],
            ['code' => 'users.update', 'module' => 'users', 'action' => 'update', 'description' => 'Update users'],
            ['code' => 'users.disable', 'module' => 'users', 'action' => 'disable', 'description' => 'Disable users'],
            ['code' => 'roles.view', 'module' => 'roles', 'action' => 'view', 'description' => 'View roles'],
            ['code' => 'roles.update', 'module' => 'roles', 'action' => 'update', 'description' => 'Update role assignments'],
            ['code' => 'roles.manage', 'module' => 'roles', 'action' => 'manage', 'description' => 'Manage role permission assignments'],
            ['code' => 'settings.organization.view', 'module' => 'settings', 'action' => 'view', 'description' => 'View organization settings'],
            ['code' => 'settings.organization.update', 'module' => 'settings', 'action' => 'update', 'description' => 'Update organization settings'],
            ['code' => 'settings.structure.view', 'module' => 'settings', 'action' => 'view', 'description' => 'View organization structure'],
            ['code' => 'settings.structure.update', 'module' => 'settings', 'action' => 'update', 'description' => 'Update organization structure'],
            ['code' => 'customers.view', 'module' => 'customers', 'action' => 'view', 'description' => 'View customers'],
            ['code' => 'customers.create', 'module' => 'customers', 'action' => 'create', 'description' => 'Create customers'],
            ['code' => 'customers.update', 'module' => 'customers', 'action' => 'update', 'description' => 'Update customers'],
            ['code' => 'customers.delete', 'module' => 'customers', 'action' => 'delete', 'description' => 'Delete customers safely'],
            ['code' => 'contacts.create', 'module' => 'contacts', 'action' => 'create', 'description' => 'Create contacts'],
            ['code' => 'contacts.update', 'module' => 'contacts', 'action' => 'update', 'description' => 'Update contacts'],
            ['code' => 'contacts.delete', 'module' => 'contacts', 'action' => 'delete', 'description' => 'Delete contacts safely'],
            ['code' => 'deals.view', 'module' => 'deals', 'action' => 'view', 'description' => 'View deals'],
            ['code' => 'deals.create', 'module' => 'deals', 'action' => 'create', 'description' => 'Create deals'],
            ['code' => 'deals.update', 'module' => 'deals', 'action' => 'update', 'description' => 'Update deals'],
            ['code' => 'activities.create', 'module' => 'activities', 'action' => 'create', 'description' => 'Create CRM activities'],
            ['code' => 'activities.update', 'module' => 'activities', 'action' => 'update', 'description' => 'Update CRM activities'],
            ['code' => 'products.view', 'module' => 'products', 'action' => 'view', 'description' => 'View products and services'],
            ['code' => 'products.manage', 'module' => 'products', 'action' => 'manage', 'description' => 'Manage products and services'],
            ['code' => 'invoices.view', 'module' => 'invoices', 'action' => 'view', 'description' => 'View invoices'],
            ['code' => 'invoices.create', 'module' => 'invoices', 'action' => 'create', 'description' => 'Create invoices'],
            ['code' => 'invoices.update', 'module' => 'invoices', 'action' => 'update', 'description' => 'Update invoices'],
            ['code' => 'invoices.void', 'module' => 'invoices', 'action' => 'void', 'description' => 'Void invoices'],
            ['code' => 'payments.view', 'module' => 'payments', 'action' => 'view', 'description' => 'View invoice payments'],
            ['code' => 'payments.create', 'module' => 'payments', 'action' => 'create', 'description' => 'Record invoice payment receipts'],
            ['code' => 'payments.reverse', 'module' => 'payments', 'action' => 'reverse', 'description' => 'Reverse invoice payment receipts'],
            ['code' => 'expenses.view', 'module' => 'expenses', 'action' => 'view', 'description' => 'View expenses'],
            ['code' => 'expenses.create', 'module' => 'expenses', 'action' => 'create', 'description' => 'Create expense drafts'],
            ['code' => 'expenses.update', 'module' => 'expenses', 'action' => 'update', 'description' => 'Update expense drafts'],
            ['code' => 'expenses.approve', 'module' => 'expenses', 'action' => 'approve', 'description' => 'Approve expenses'],
            ['code' => 'expenses.pay', 'module' => 'expenses', 'action' => 'pay', 'description' => 'Mark approved expenses paid'],
            ['code' => 'expenses.reject', 'module' => 'expenses', 'action' => 'reject', 'description' => 'Reject expenses'],
            ['code' => 'projects.view', 'module' => 'projects', 'action' => 'view', 'description' => 'View delivery projects'],
            ['code' => 'projects.create', 'module' => 'projects', 'action' => 'create', 'description' => 'Create delivery projects'],
            ['code' => 'projects.update', 'module' => 'projects', 'action' => 'update', 'description' => 'Update delivery projects'],
            ['code' => 'projects.reassign', 'module' => 'projects', 'action' => 'reassign', 'description' => 'Reassign project owner'],
            ['code' => 'tasks.view', 'module' => 'tasks', 'action' => 'view', 'description' => 'View delivery tasks'],
            ['code' => 'tasks.create', 'module' => 'tasks', 'action' => 'create', 'description' => 'Create delivery tasks'],
            ['code' => 'tasks.update', 'module' => 'tasks', 'action' => 'update', 'description' => 'Update delivery tasks'],
            ['code' => 'tasks.comment', 'module' => 'tasks', 'action' => 'comment', 'description' => 'Comment on delivery tasks'],
            ['code' => 'audit.view', 'module' => 'audit', 'action' => 'view', 'description' => 'View audit logs'],
            ['code' => 'person_id.view_full', 'module' => 'users', 'action' => 'view_full', 'description' => 'View full person_id'],
        ];
    }

    public static function roleNames(): array
    {
        return [
            'owner' => 'Owner',
            'admin' => 'Admin',
            'sales' => 'Sales',
            'project_manager' => 'Project Manager',
            'finance' => 'Finance',
            'member' => 'Member',
            'viewer' => 'Viewer',
        ];
    }

    public static function defaults(): array
    {
        $all = array_column(self::permissions(), 'code');
        $sales = ['dashboard.view', 'sales.dashboard.view', 'customers.view', 'customers.create', 'customers.update', 'contacts.create', 'contacts.update', 'contacts.delete', 'deals.view', 'deals.create', 'deals.update', 'activities.create', 'activities.update'];
        $projects = ['dashboard.view', 'projects.view', 'projects.create', 'projects.update', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.comment'];
        $finance = ['dashboard.view', 'products.view', 'products.manage', 'invoices.view', 'invoices.create', 'invoices.update', 'invoices.void', 'payments.view', 'payments.create', 'payments.reverse', 'expenses.view', 'expenses.create', 'expenses.update', 'expenses.approve', 'expenses.pay', 'expenses.reject'];
        $sales = array_values(array_unique(array_merge($sales, ['products.view', 'invoices.view'])));

        return [
            'owner' => $all,
            'admin' => array_values(array_diff($all, ['person_id.view_full'])),
            'sales' => $sales,
            'project_manager' => $projects,
            'finance' => $finance,
            'member' => ['dashboard.view', 'tasks.view', 'tasks.update', 'tasks.comment'],
            'viewer' => ['dashboard.view'],
        ];
    }
}
