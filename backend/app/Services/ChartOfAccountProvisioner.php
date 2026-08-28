<?php

namespace App\Services;

use App\Models\ChartOfAccount;

class ChartOfAccountProvisioner
{
    private const DEFAULTS = [
        ['code' => '1100', 'name' => 'Cash on Hand', 'account_type' => 'asset'],
        ['code' => '1110', 'name' => 'Bank Accounts', 'account_type' => 'asset'],
        ['code' => '1112', 'name' => 'Petty Cash', 'account_type' => 'asset'],
        ['code' => '1120', 'name' => 'Accounts Receivable', 'account_type' => 'asset'],
        ['code' => '1130', 'name' => 'Input VAT', 'account_type' => 'asset'],
        ['code' => '1140', 'name' => 'Inventory', 'account_type' => 'asset'],
        ['code' => '1150', 'name' => 'Goods Received Not Invoiced', 'account_type' => 'asset'],
        ['code' => '1210', 'name' => 'Fixed Assets', 'account_type' => 'asset'],
        ['code' => '1219', 'name' => 'Accumulated Depreciation', 'account_type' => 'asset'],
        ['code' => '2110', 'name' => 'Accounts Payable', 'account_type' => 'liability'],
        ['code' => '2120', 'name' => 'Cheque Clearing', 'account_type' => 'liability'],
        ['code' => '2130', 'name' => 'Output VAT', 'account_type' => 'liability'],
        ['code' => '3100', 'name' => 'Owner Equity', 'account_type' => 'equity'],
        ['code' => '4100', 'name' => 'Sales Revenue', 'account_type' => 'revenue'],
        ['code' => '5100', 'name' => 'Cost of Goods Sold', 'account_type' => 'expense'],
        ['code' => '5200', 'name' => 'Operating Expenses', 'account_type' => 'expense'],
        ['code' => '5300', 'name' => 'Gain or Loss on Asset Disposal', 'account_type' => 'revenue'],
        ['code' => '5310', 'name' => 'Depreciation Expense', 'account_type' => 'expense'],
    ];

    public function ensure(string $orgId): void
    {
        foreach (self::DEFAULTS as $account) {
            ChartOfAccount::withTrashed()->firstOrCreate(
                ['org_id' => $orgId, 'code' => $account['code']],
                array_merge($account, [
                    'normal_balance' => in_array($account['account_type'], ['asset', 'expense'], true) ? 'debit' : 'credit',
                    'is_postable' => true,
                    'status' => 'active',
                ])
            );
        }
    }
}
