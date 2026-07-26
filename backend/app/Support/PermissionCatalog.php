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

        return [
            'owner' => $all,
            'admin' => array_values(array_diff($all, ['person_id.view_full'])),
            'sales' => $sales,
            'project_manager' => ['dashboard.view'],
            'finance' => ['dashboard.view'],
            'member' => ['dashboard.view'],
            'viewer' => ['dashboard.view'],
        ];
    }
}
