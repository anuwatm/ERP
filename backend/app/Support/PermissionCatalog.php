<?php

namespace App\Support;

class PermissionCatalog
{
    public static function permissions(): array
    {
        return [
            ['code' => 'dashboard.view', 'module' => 'dashboard', 'action' => 'view', 'description' => 'View admin dashboard'],
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

        return [
            'owner' => $all,
            'admin' => array_values(array_diff($all, ['person_id.view_full'])),
            'sales' => ['dashboard.view'],
            'project_manager' => ['dashboard.view'],
            'finance' => ['dashboard.view'],
            'member' => ['dashboard.view'],
            'viewer' => ['dashboard.view'],
        ];
    }
}
