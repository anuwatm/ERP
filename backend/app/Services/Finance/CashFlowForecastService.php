<?php

namespace App\Services\Finance;

use App\Models\BankAccount;
use App\Models\CashFlowForecastSnapshot;
use App\Models\Deal;
use App\Models\EmployeePayrollProfile;
use App\Models\Expense;
use App\Models\FinancialMetricLog;
use App\Models\ForecastScenario;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\PayrollRun;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CashFlowForecastService
{
    /**
     * Resolve Organization model from model or org_id string.
     */
    private function resolveOrg(Organization|string $org): Organization
    {
        return $org instanceof Organization ? $org : Organization::findOrFail($org);
    }

    /**
     * Get or seed default scenarios for organization.
     *
     * @return Collection<int, ForecastScenario>
     */
    public function getOrCreateDefaultScenarios(Organization|string $org): Collection
    {
        $org = $this->resolveOrg($org);
        $existing = ForecastScenario::where('org_id', $org->id)->get();
        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $created = collect();
        foreach (ForecastScenario::DEFAULT_SCENARIOS as $data) {
            $created->push(ForecastScenario::create([
                'org_id' => $org->id,
                ...$data,
            ]));
        }

        return $created;
    }

    /**
     * Generate and persist a cash flow forecast snapshot.
     */
    public function generateForecast(
        Organization|string $org,
        string $scenarioKey = 'base',
        int $horizonDays = 90,
        ?Carbon $asOfDate = null,
        ?User $user = null
    ): CashFlowForecastSnapshot {
        $org = $this->resolveOrg($org);
        $scenarios = $this->getOrCreateDefaultScenarios($org);
        $scenario = $scenarios->firstWhere('scenario_key', $scenarioKey)
            ?? $scenarios->firstWhere('is_default', true)
            ?? $scenarios->first();

        $today = $asOfDate ? $asOfDate->copy()->startOfDay() : Carbon::today();
        $endDate = $today->copy()->addDays($horizonDays);

        // 1. Starting Cash
        $startingCash = (float) BankAccount::where('org_id', $org->id)
            ->where('status', 'active')
            ->sum('base_balance');

        // 2. Inflow items
        $inflowItems = $this->calculateInflowItems($org, $scenario, $today, $endDate);

        // 3. Outflow items
        $outflowItems = $this->calculateOutflowItems($org, $scenario, $today, $endDate);

        // 4. Build day-by-day cash curve
        $dailySeries = [];
        $runningCash = $startingCash;
        $lowestCashPoint = $startingCash;
        $lowestCashDate = $today->toDateString();

        $inflowsByDate = collect($inflowItems)->groupBy('date');
        $outflowsByDate = collect($outflowItems)->groupBy('date');

        for ($day = 0; $day <= $horizonDays; $day++) {
            $currentDate = $today->copy()->addDays($day)->toDateString();
            $dayInflow = (float) ($inflowsByDate->get($currentDate)?->sum('amount') ?? 0.0);
            $dayOutflow = (float) ($outflowsByDate->get($currentDate)?->sum('amount') ?? 0.0);

            $runningCash = round($runningCash + $dayInflow - $dayOutflow, 2);

            if ($runningCash < $lowestCashPoint) {
                $lowestCashPoint = $runningCash;
                $lowestCashDate = $currentDate;
            }

            $dailySeries[] = [
                'day' => $day,
                'date' => $currentDate,
                'inflow' => $dayInflow,
                'outflow' => $dayOutflow,
                'net' => round($dayInflow - $dayOutflow, 2),
                'balance' => $runningCash,
            ];
        }

        $totalInflow = round(collect($inflowItems)->sum('amount'), 2);
        $totalOutflow = round(collect($outflowItems)->sum('amount'), 2);
        $netCashFlow = round($totalInflow - $totalOutflow, 2);
        $projectedEndingCash = round($startingCash + $netCashFlow, 2);

        $breakdown = [
            'summary' => [
                'starting_cash' => $startingCash,
                'total_inflow' => $totalInflow,
                'total_outflow' => $totalOutflow,
                'net_cash_flow' => $netCashFlow,
                'ending_cash' => $projectedEndingCash,
                'lowest_cash_point' => $lowestCashPoint,
                'lowest_cash_date' => $lowestCashDate,
            ],
            'categories' => [
                'inflow_ar' => round(collect($inflowItems)->where('type', 'ar_invoice')->sum('amount'), 2),
                'inflow_deals' => round(collect($inflowItems)->where('type', 'deal_pipeline')->sum('amount'), 2),
                'outflow_expenses' => round(collect($outflowItems)->where('type', 'expense')->sum('amount'), 2),
                'outflow_purchase_orders' => round(collect($outflowItems)->where('type', 'purchase_order')->sum('amount'), 2),
                'outflow_payroll' => round(collect($outflowItems)->where('type', 'payroll')->sum('amount'), 2),
                'outflow_buffer' => round(collect($outflowItems)->where('type', 'buffer')->sum('amount'), 2),
            ],
            'daily_series' => $dailySeries,
            'inflow_items' => array_slice($inflowItems, 0, 50),
            'outflow_items' => array_slice($outflowItems, 0, 50),
            'items' => [
                'inflows' => array_slice($inflowItems, 0, 50),
                'outflows' => array_slice($outflowItems, 0, 50),
            ],
        ];

        return CashFlowForecastSnapshot::create([
            'org_id' => $org->id,
            'forecast_scenario_id' => $scenario?->id,
            'scenario_key' => $scenarioKey,
            'as_of_date' => $today->toDateString(),
            'horizon_days' => $horizonDays,
            'starting_cash' => $startingCash,
            'projected_inflow' => $totalInflow,
            'projected_outflow' => $totalOutflow,
            'net_cash_flow' => $netCashFlow,
            'projected_ending_cash' => $projectedEndingCash,
            'lowest_cash_point' => $lowestCashPoint,
            'lowest_cash_date' => $lowestCashDate,
            'breakdown_json' => $breakdown,
            'created_by' => $user?->id,
        ]);
    }

    /**
     * Calculate and log financial health metrics.
     */
    public function calculateFinancialHealth(Organization|string $org, ?Carbon $asOf = null, ?User $user = null): FinancialMetricLog
    {
        $org = $this->resolveOrg($org);
        $asOfDate = ($asOf ?? Carbon::today())->toDateString();
        $ninetyDaysAgo = Carbon::parse($asOfDate)->subDays(90)->toDateString();

        // 1. Current Cash
        $currentCash = (float) BankAccount::where('org_id', $org->id)
            ->where('status', 'active')
            ->sum('base_balance');

        // 2. Current AR
        $currentAr = (float) Invoice::where('org_id', $org->id)
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->where('base_balance_due', '>', 0)
            ->sum('base_balance_due');

        // 3. Current AP (Unpaid Expenses + Open POs)
        $expenseAp = (float) Expense::where('org_id', $org->id)
            ->whereIn('status', ['approved', 'partially_paid'])
            ->where('base_balance_due', '>', 0)
            ->sum('base_balance_due');

        $poAp = (float) PurchaseOrder::where('org_id', $org->id)
            ->whereIn('status', ['approved', 'partially_received'])
            ->sum('base_total');

        $currentAp = round($expenseAp + $poAp, 2);

        // 4. Inventory Value
        $inventoryValue = (float) Product::where('org_id', $org->id)
            ->where('is_active', true)
            ->where('track_inventory', true)
            ->selectRaw('SUM(cost * reorder_point) as est_inv_val')
            ->value('est_inv_val');

        // 5. Current Assets & Liabilities
        $currentAssets = round($currentCash + $currentAr + $inventoryValue, 2);
        $currentLiabilities = max($currentAp, 1.00); // Guard against zero division

        $currentRatio = round($currentAssets / $currentLiabilities, 2);
        $quickRatio = round(($currentCash + $currentAr) / $currentLiabilities, 2);
        $workingCapital = round($currentAssets - $currentLiabilities, 2);

        // 6. DSO (Days Sales Outstanding)
        $salesPast90d = (float) Invoice::where('org_id', $org->id)
            ->whereIn('status', ['sent', 'partially_paid', 'paid', 'overdue'])
            ->where('issue_date', '>=', $ninetyDaysAgo)
            ->sum('base_total');

        $dsoDays = $salesPast90d > 0
            ? round(($currentAr / $salesPast90d) * 90, 1)
            : 0.0;

        // 7. DPO (Days Payable Outstanding)
        $expensesPast90d = (float) Expense::where('org_id', $org->id)
            ->whereIn('status', ['approved', 'partially_paid', 'paid'])
            ->where('expense_date', '>=', $ninetyDaysAgo)
            ->sum('base_amount');

        $dpoDays = $expensesPast90d > 0
            ? round(($currentAp / $expensesPast90d) * 90, 1)
            : 0.0;

        // 8. Monthly Burn Rate & Working Capital Runway
        // Calculate average monthly cash outflow over past 3 months
        $recentPayrollOutflow = (float) PayrollRun::where('org_id', $org->id)
            ->where('status', 'paid')
            ->where('payment_date', '>=', $ninetyDaysAgo)
            ->sum(DB::raw('gross_amount + employer_social_security_amount'));

        $totalOutflowPast90d = $expensesPast90d + $recentPayrollOutflow;
        $monthlyBurnRate = $totalOutflowPast90d > 0
            ? round($totalOutflowPast90d / 3, 2)
            : round(max($currentAp, 1000.00), 2);

        $runwayMonths = $monthlyBurnRate > 0
            ? round($currentCash / $monthlyBurnRate, 1)
            : 99.9;

        $metadata = [
            'current_assets' => $currentAssets,
            'current_liabilities' => $currentLiabilities,
            'sales_past_90d' => $salesPast90d,
            'expenses_past_90d' => $expensesPast90d,
            'recent_payroll_outflow' => $recentPayrollOutflow,
            'benchmark' => [
                'current_ratio' => ['healthy' => 1.5, 'adequate' => 1.0],
                'quick_ratio' => ['healthy' => 1.0, 'adequate' => 0.8],
                'dso_target' => 45.0,
                'dpo_target' => 45.0,
                'runway_months' => ['healthy' => 6.0, 'warning' => 3.0],
            ],
        ];

        return FinancialMetricLog::create([
            'org_id' => $org->id,
            'as_of_date' => $asOfDate,
            'current_ratio' => $currentRatio,
            'quick_ratio' => $quickRatio,
            'dso_days' => $dsoDays,
            'dpo_days' => $dpoDays,
            'working_capital' => $workingCapital,
            'working_capital_runway_months' => $runwayMonths,
            'monthly_burn_rate' => $monthlyBurnRate,
            'current_cash' => $currentCash,
            'current_ar' => $currentAr,
            'current_ap' => $currentAp,
            'inventory_value' => $inventoryValue,
            'metadata_json' => $metadata,
            'created_by' => $user?->id,
        ]);
    }

    /**
     * Get multi-scenario forecast comparison.
     *
     * @return array<string, mixed>
     */
    public function getMultiScenarioComparison(
        Organization|string $org,
        int $horizonDays = 90,
        ?Carbon $asOfDate = null,
        ?User $user = null
    ): array {
        $org = $this->resolveOrg($org);
        $scenarios = $this->getOrCreateDefaultScenarios($org);
        $snapshots = [];
        $curves = [];

        foreach (['base', 'best', 'worst'] as $key) {
            $snapshot = $this->generateForecast($org, $key, $horizonDays, $asOfDate, $user);
            $snapshots[$key] = $snapshot;

            $breakdown = $snapshot->breakdown_json ?? [];
            $curves[$key] = [
                'name' => $snapshot->scenario?->name ?? ucfirst($key),
                'key' => $key,
                'ending_cash' => $snapshot->projected_ending_cash,
                'lowest_cash_point' => $snapshot->lowest_cash_point,
                'lowest_cash_date' => $snapshot->lowest_cash_date ? Carbon::parse($snapshot->lowest_cash_date)->toDateString() : null,
                'points' => collect($breakdown['daily_series'] ?? [])->map(fn ($p) => [
                    'date' => $p['date'],
                    'balance' => $p['balance'],
                    'net' => $p['net'],
                ])->all(),
            ];
        }

        $healthMetrics = FinancialMetricLog::where('org_id', $org->id)
            ->latest('as_of_date')
            ->first() ?? $this->calculateFinancialHealth($org, $asOfDate, $user);

        return [
            'snapshots' => $snapshots,
            'curves' => $curves,
            'health_metrics' => $healthMetrics,
            'scenarios' => $scenarios,
        ];
    }

    /**
     * Calculate inflow projection items.
     *
     * @return list<array<string, mixed>>
     */
    private function calculateInflowItems(
        Organization $org,
        ForecastScenario $scenario,
        Carbon $today,
        Carbon $endDate
    ): array {
        $items = [];
        $arCollectionRate = (float) $scenario->ar_collection_rate;
        $arDelayDays = (int) $scenario->ar_delay_days;
        $dealMultiplier = (float) $scenario->deal_multiplier;

        // 1. AR Invoices
        $invoices = Invoice::where('org_id', $org->id)
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->where('base_balance_due', '>', 0)
            ->get();

        foreach ($invoices as $inv) {
            $targetDate = $inv->due_date ? Carbon::parse($inv->due_date) : $today->copy()->addDays(14);
            $effectiveDate = $targetDate->copy()->addDays($arDelayDays);

            // If overdue, expect within upcoming days based on delay setting
            if ($effectiveDate->lt($today)) {
                $effectiveDate = $today->copy()->addDays(max(3, $arDelayDays));
            }

            if ($effectiveDate->lte($endDate)) {
                $amount = round((float) $inv->base_balance_due * $arCollectionRate, 2);
                if ($amount > 0) {
                    $items[] = [
                        'type' => 'ar_invoice',
                        'id' => $inv->id,
                        'reference' => $inv->invoice_no,
                        'description' => 'Invoice: '.$inv->invoice_no,
                        'date' => $effectiveDate->toDateString(),
                        'original_amount' => (float) $inv->base_balance_due,
                        'amount' => $amount,
                    ];
                }
            }
        }

        // 2. Pipeline Deals (if multiplier > 0)
        if ($dealMultiplier > 0) {
            $deals = Deal::where('org_id', $org->id)
                ->whereIn('stage', Deal::OPEN_STAGES)
                ->where('value_amount', '>', 0)
                ->get();

            foreach ($deals as $deal) {
                $targetDate = $deal->expected_close_date
                    ? Carbon::parse($deal->expected_close_date)
                    : $today->copy()->addDays(30);

                if ($targetDate->lt($today)) {
                    $targetDate = $today->copy()->addDays(7);
                }

                if ($targetDate->lte($endDate)) {
                    $prob = $scenario->deal_probability_weighting
                        ? (float) ($deal->probability ?? $this->stageProbability($deal->stage))
                        : 100.0;

                    $amount = round((float) $deal->value_amount * ($prob / 100.0) * $dealMultiplier, 2);
                    if ($amount > 0) {
                        $items[] = [
                            'type' => 'deal_pipeline',
                            'id' => $deal->id,
                            'reference' => $deal->title,
                            'description' => 'Pipeline Deal: '.$deal->title.' ('.round($prob).'%)',
                            'date' => $targetDate->toDateString(),
                            'original_amount' => (float) $deal->value_amount,
                            'amount' => $amount,
                        ];
                    }
                }
            }
        }

        return $items;
    }

    /**
     * Calculate outflow projection items.
     *
     * @return list<array<string, mixed>>
     */
    private function calculateOutflowItems(
        Organization $org,
        ForecastScenario $scenario,
        Carbon $today,
        Carbon $endDate
    ): array {
        $items = [];
        $apRate = (float) $scenario->ap_payment_rate;
        $expenseBuffer = (float) $scenario->expense_buffer_percent;
        $payrollBuffer = (float) $scenario->payroll_buffer_percent;

        // 1. AP Expenses
        $expenses = Expense::where('org_id', $org->id)
            ->whereIn('status', ['approved', 'partially_paid'])
            ->where('base_balance_due', '>', 0)
            ->get();

        foreach ($expenses as $exp) {
            $targetDate = $exp->expense_date ? Carbon::parse($exp->expense_date) : $today->copy();
            if ($targetDate->lt($today)) {
                $targetDate = $today->copy()->addDays(3);
            }

            if ($targetDate->lte($endDate)) {
                $baseAmount = round((float) $exp->base_balance_due * $apRate, 2);
                $bufferedAmount = round($baseAmount * (1 + ($expenseBuffer / 100)), 2);

                $items[] = [
                    'type' => 'expense',
                    'id' => $exp->id,
                    'reference' => $exp->expense_no,
                    'description' => 'Expense: '.$exp->expense_no.' ('.$exp->title.')',
                    'date' => $targetDate->toDateString(),
                    'amount' => $bufferedAmount,
                ];
            }
        }

        // 2. Purchase Orders
        $pos = PurchaseOrder::where('org_id', $org->id)
            ->whereIn('status', ['approved', 'partially_received'])
            ->get();

        foreach ($pos as $po) {
            $amount = (float) ($po->base_total ?: $po->total);
            $targetDate = $po->expected_date ? Carbon::parse($po->expected_date) : $today->copy()->addDays(14);
            if ($targetDate->lt($today)) {
                $targetDate = $today->copy()->addDays(7);
            }

            if ($targetDate->lte($endDate)) {
                $items[] = [
                    'type' => 'purchase_order',
                    'id' => $po->id,
                    'reference' => $po->po_no,
                    'description' => 'PO: '.$po->po_no,
                    'date' => $targetDate->toDateString(),
                    'amount' => round($amount, 2),
                ];
            }
        }

        // 3. Recurring Payroll
        // Estimate monthly payroll burden from recent run or active employee profiles
        $recentRun = PayrollRun::where('org_id', $org->id)
            ->latest('period_end')
            ->first();

        $monthlyPayroll = $recentRun
            ? (float) ($recentRun->gross_amount + $recentRun->employer_social_security_amount)
            : (float) EmployeePayrollProfile::where('org_id', $org->id)
                ->where('status', 'active')
                ->sum('monthly_salary');

        if ($monthlyPayroll > 0) {
            $bufferedPayroll = round($monthlyPayroll * (1 + ($payrollBuffer / 100)), 2);

            // Project on the 25th of each month within the horizon
            $iterDate = $today->copy()->startOfMonth()->addDays(24);
            if ($iterDate->lt($today)) {
                $iterDate->addMonth();
            }

            while ($iterDate->lte($endDate)) {
                $items[] = [
                    'type' => 'payroll',
                    'id' => 'payroll-'.$iterDate->format('Y-m'),
                    'reference' => 'PAYROLL-'.$iterDate->format('Y-m'),
                    'description' => 'Scheduled Monthly Payroll ('.$iterDate->format('M Y').')',
                    'date' => $iterDate->toDateString(),
                    'amount' => $bufferedPayroll,
                ];
                $iterDate->addMonth();
            }
        }

        return $items;
    }

    private function stageProbability(string $stage): float
    {
        return match ($stage) {
            'new' => 10.0,
            'contacted' => 25.0,
            'qualified' => 40.0,
            'proposal' => 60.0,
            'negotiation' => 80.0,
            'won' => 100.0,
            default => 20.0,
        };
    }
}
