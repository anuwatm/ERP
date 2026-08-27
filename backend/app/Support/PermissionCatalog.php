<?php

namespace App\Support;

class PermissionCatalog
{
    public static function permissions(): array
    {
        return [
            ['code' => 'dashboard.view', 'module' => 'dashboard', 'action' => 'view', 'description' => 'View admin dashboard'],
            ['code' => 'sales.dashboard.view', 'module' => 'sales', 'action' => 'view', 'description' => 'View sales dashboard'],
            ['code' => 'executive.dashboard.view', 'module' => 'executive', 'action' => 'view', 'description' => 'View executive dashboard'],
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
            ['code' => 'quotations.view', 'module' => 'quotations', 'action' => 'view', 'description' => 'View quotations'],
            ['code' => 'quotations.create', 'module' => 'quotations', 'action' => 'create', 'description' => 'Create quotations'],
            ['code' => 'quotations.update', 'module' => 'quotations', 'action' => 'update', 'description' => 'Update draft quotations'],
            ['code' => 'quotations.approve', 'module' => 'quotations', 'action' => 'approve', 'description' => 'Approve or reject quotations'],
            ['code' => 'quotations.convert', 'module' => 'quotations', 'action' => 'convert', 'description' => 'Convert approved quotations to invoices'],
            ['code' => 'credit_debit_notes.view', 'module' => 'credit_debit_notes', 'action' => 'view', 'description' => 'View credit and debit notes'],
            ['code' => 'credit_debit_notes.create', 'module' => 'credit_debit_notes', 'action' => 'create', 'description' => 'Create credit and debit notes'],
            ['code' => 'credit_debit_notes.update', 'module' => 'credit_debit_notes', 'action' => 'update', 'description' => 'Update credit and debit notes'],
            ['code' => 'credit_debit_notes.approve', 'module' => 'credit_debit_notes', 'action' => 'approve', 'description' => 'Approve credit and debit notes'],
            ['code' => 'billing_notes.view', 'module' => 'billing_notes', 'action' => 'view', 'description' => 'View billing notes'],
            ['code' => 'billing_notes.create', 'module' => 'billing_notes', 'action' => 'create', 'description' => 'Create billing notes'],
            ['code' => 'billing_notes.update', 'module' => 'billing_notes', 'action' => 'update', 'description' => 'Update billing notes'],
            ['code' => 'billing_notes.approve', 'module' => 'billing_notes', 'action' => 'approve', 'description' => 'Approve billing notes'],
            ['code' => 'delivery_orders.view', 'module' => 'delivery_orders', 'action' => 'view', 'description' => 'View delivery orders'],
            ['code' => 'delivery_orders.create', 'module' => 'delivery_orders', 'action' => 'create', 'description' => 'Create delivery orders'],
            ['code' => 'delivery_orders.update', 'module' => 'delivery_orders', 'action' => 'update', 'description' => 'Update delivery orders'],
            ['code' => 'delivery_orders.approve', 'module' => 'delivery_orders', 'action' => 'approve', 'description' => 'Approve delivery orders'],
            ['code' => 'purchase_requests.view', 'module' => 'purchase_requests', 'action' => 'view', 'description' => 'View purchase requests'],
            ['code' => 'purchase_requests.create', 'module' => 'purchase_requests', 'action' => 'create', 'description' => 'Create purchase requests'],
            ['code' => 'purchase_requests.update', 'module' => 'purchase_requests', 'action' => 'update', 'description' => 'Update purchase requests'],
            ['code' => 'purchase_requests.approve', 'module' => 'purchase_requests', 'action' => 'approve', 'description' => 'Approve and convert purchase requests'],
            ['code' => 'vouchers.view', 'module' => 'vouchers', 'action' => 'view', 'description' => 'View payment and receipt vouchers'],
            ['code' => 'vouchers.create', 'module' => 'vouchers', 'action' => 'create', 'description' => 'Create payment and receipt vouchers'],
            ['code' => 'vouchers.update', 'module' => 'vouchers', 'action' => 'update', 'description' => 'Update payment and receipt vouchers'],
            ['code' => 'vouchers.approve', 'module' => 'vouchers', 'action' => 'approve', 'description' => 'Approve payment and receipt vouchers'],
            ['code' => 'treasury.accounts.view', 'module' => 'treasury_accounts', 'action' => 'view', 'description' => 'View bank and cash accounts'],
            ['code' => 'treasury.accounts.manage', 'module' => 'treasury_accounts', 'action' => 'manage', 'description' => 'Manage bank and cash accounts'],
            ['code' => 'treasury.reconciliation.view', 'module' => 'treasury_reconciliation', 'action' => 'view', 'description' => 'View bank statements and reconciliation'],
            ['code' => 'treasury.reconciliation.manage', 'module' => 'treasury_reconciliation', 'action' => 'manage', 'description' => 'Import bank statements and manage reconciliation'],
            ['code' => 'treasury.reports.view', 'module' => 'treasury_reports', 'action' => 'view', 'description' => 'View treasury reports'],
            ['code' => 'petty_cash.view', 'module' => 'petty_cash', 'action' => 'view', 'description' => 'View petty cash'],
            ['code' => 'petty_cash.manage', 'module' => 'petty_cash', 'action' => 'manage', 'description' => 'Manage petty cash funds and requests'],
            ['code' => 'petty_cash.approve', 'module' => 'petty_cash', 'action' => 'approve', 'description' => 'Approve petty cash requests'],
            ['code' => 'cheques.view', 'module' => 'cheques', 'action' => 'view', 'description' => 'View cheques and PDC'],
            ['code' => 'cheques.manage', 'module' => 'cheques', 'action' => 'manage', 'description' => 'Manage cheques and PDC'],
            ['code' => 'accounting.chart_accounts.view', 'module' => 'accounting_chart_accounts', 'action' => 'view', 'description' => 'View chart of accounts'],
            ['code' => 'accounting.chart_accounts.manage', 'module' => 'accounting_chart_accounts', 'action' => 'manage', 'description' => 'Manage chart of accounts'],
            ['code' => 'accounting.periods.view', 'module' => 'accounting_periods', 'action' => 'view', 'description' => 'View accounting periods'],
            ['code' => 'accounting.periods.manage', 'module' => 'accounting_periods', 'action' => 'manage', 'description' => 'Manage accounting periods'],
            ['code' => 'accounting.journals.view', 'module' => 'accounting_journals', 'action' => 'view', 'description' => 'View journals'],
            ['code' => 'accounting.journals.create', 'module' => 'accounting_journals', 'action' => 'create', 'description' => 'Create draft journals'],
            ['code' => 'accounting.journals.post', 'module' => 'accounting_journals', 'action' => 'post', 'description' => 'Post journals'],
            ['code' => 'accounting.journals.reverse', 'module' => 'accounting_journals', 'action' => 'reverse', 'description' => 'Reverse posted journals'],
            ['code' => 'accounting.reports.view', 'module' => 'accounting_reports', 'action' => 'view', 'description' => 'View general ledger reports'],
            ['code' => 'payments.view', 'module' => 'payments', 'action' => 'view', 'description' => 'View invoice payments'],
            ['code' => 'payments.create', 'module' => 'payments', 'action' => 'create', 'description' => 'Record invoice payment receipts'],
            ['code' => 'payments.reverse', 'module' => 'payments', 'action' => 'reverse', 'description' => 'Reverse invoice payment receipts'],
            ['code' => 'tax_reports.view', 'module' => 'tax_reports', 'action' => 'view', 'description' => 'View tax and accounting reports'],
            ['code' => 'expenses.view', 'module' => 'expenses', 'action' => 'view', 'description' => 'View expenses'],
            ['code' => 'expenses.create', 'module' => 'expenses', 'action' => 'create', 'description' => 'Create expense drafts'],
            ['code' => 'expenses.update', 'module' => 'expenses', 'action' => 'update', 'description' => 'Update expense drafts'],
            ['code' => 'expenses.approve', 'module' => 'expenses', 'action' => 'approve', 'description' => 'Approve expenses'],
            ['code' => 'expenses.pay', 'module' => 'expenses', 'action' => 'pay', 'description' => 'Mark approved expenses paid'],
            ['code' => 'expenses.reject', 'module' => 'expenses', 'action' => 'reject', 'description' => 'Reject expenses'],
            ['code' => 'suppliers.view', 'module' => 'suppliers', 'action' => 'view', 'description' => 'View suppliers'],
            ['code' => 'suppliers.create', 'module' => 'suppliers', 'action' => 'create', 'description' => 'Create suppliers'],
            ['code' => 'suppliers.update', 'module' => 'suppliers', 'action' => 'update', 'description' => 'Update suppliers'],
            ['code' => 'suppliers.delete', 'module' => 'suppliers', 'action' => 'delete', 'description' => 'Delete suppliers safely'],
            ['code' => 'purchase_orders.view', 'module' => 'purchase_orders', 'action' => 'view', 'description' => 'View purchase orders'],
            ['code' => 'purchase_orders.create', 'module' => 'purchase_orders', 'action' => 'create', 'description' => 'Create purchase orders'],
            ['code' => 'purchase_orders.update', 'module' => 'purchase_orders', 'action' => 'update', 'description' => 'Update draft purchase orders'],
            ['code' => 'purchase_orders.approve', 'module' => 'purchase_orders', 'action' => 'approve', 'description' => 'Approve purchase orders'],
            ['code' => 'purchase_orders.cancel', 'module' => 'purchase_orders', 'action' => 'cancel', 'description' => 'Cancel purchase orders'],
            ['code' => 'inventory.view', 'module' => 'inventory', 'action' => 'view', 'description' => 'View inventory and goods receipts'],
            ['code' => 'inventory.receive', 'module' => 'inventory', 'action' => 'receive', 'description' => 'Receive goods from purchase orders'],
            ['code' => 'inventory.adjust', 'module' => 'inventory', 'action' => 'adjust', 'description' => 'Adjust inventory stock movements'],
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
        $sales = ['dashboard.view', 'sales.dashboard.view', 'customers.view', 'customers.create', 'customers.update', 'contacts.create', 'contacts.update', 'contacts.delete', 'deals.view', 'deals.create', 'deals.update', 'activities.create', 'activities.update', 'quotations.view', 'quotations.create', 'quotations.update', 'quotations.approve', 'quotations.convert'];
        $projects = ['dashboard.view', 'projects.view', 'projects.create', 'projects.update', 'tasks.view', 'tasks.create', 'tasks.update', 'tasks.comment'];
        $procurement = ['suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete', 'purchase_orders.view', 'purchase_orders.create', 'purchase_orders.update', 'purchase_orders.approve', 'purchase_orders.cancel', 'inventory.view', 'inventory.receive', 'inventory.adjust'];
        $phase9Finance = ['credit_debit_notes.view', 'credit_debit_notes.create', 'credit_debit_notes.update', 'credit_debit_notes.approve', 'billing_notes.view', 'billing_notes.create', 'billing_notes.update', 'billing_notes.approve', 'delivery_orders.view', 'delivery_orders.create', 'delivery_orders.update', 'delivery_orders.approve', 'purchase_requests.view', 'purchase_requests.create', 'purchase_requests.update', 'purchase_requests.approve', 'vouchers.view', 'vouchers.create', 'vouchers.update', 'vouchers.approve'];
        $treasury = ['treasury.accounts.view', 'treasury.accounts.manage', 'treasury.reconciliation.view', 'treasury.reconciliation.manage', 'treasury.reports.view', 'petty_cash.view', 'petty_cash.manage', 'petty_cash.approve', 'cheques.view', 'cheques.manage'];
        $accounting = ['accounting.chart_accounts.view', 'accounting.chart_accounts.manage', 'accounting.periods.view', 'accounting.periods.manage', 'accounting.journals.view', 'accounting.journals.create', 'accounting.journals.post', 'accounting.journals.reverse', 'accounting.reports.view'];
        $finance = ['dashboard.view', 'products.view', 'products.manage', 'invoices.view', 'invoices.create', 'invoices.update', 'invoices.void', 'quotations.view', 'quotations.create', 'quotations.update', 'quotations.approve', 'quotations.convert', ...$phase9Finance, ...$treasury, ...$accounting, 'payments.view', 'payments.create', 'payments.reverse', 'tax_reports.view', 'expenses.view', 'expenses.create', 'expenses.update', 'expenses.approve', 'expenses.pay', 'expenses.reject', ...$procurement];
        $sales = array_values(array_unique(array_merge($sales, ['products.view', 'invoices.view'])));

        return [
            'owner' => $all,
            'admin' => array_values(array_diff($all, ['person_id.view_full'])),
            'sales' => $sales,
            'project_manager' => array_values(array_unique(array_merge($projects, ['suppliers.view', 'purchase_orders.view']))),
            'finance' => $finance,
            'member' => ['dashboard.view', 'tasks.view', 'tasks.update', 'tasks.comment'],
            'viewer' => ['dashboard.view'],
        ];
    }
}
