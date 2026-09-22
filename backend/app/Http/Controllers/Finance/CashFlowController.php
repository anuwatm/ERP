<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CashFlowForecastSnapshot;
use App\Models\ForecastScenario;
use App\Services\Finance\CashFlowForecastService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashFlowController extends Controller
{
    public function __construct(
        protected CashFlowForecastService $cashFlowService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $orgId = $user->org_id;

        $horizon = max(14, min(180, (int) $request->input('horizon', 90)));
        $asOfDate = $request->input('as_of_date')
            ? Carbon::parse($request->input('as_of_date'))
            : Carbon::today();

        $comparison = $this->cashFlowService->getMultiScenarioComparison($orgId, $horizon, $asOfDate, $user);
        $financialHealth = $this->cashFlowService->calculateFinancialHealth($orgId, $asOfDate, $user);
        $scenarios = ForecastScenario::where('org_id', $orgId)->orderBy('id')->get();

        $recentSnapshots = CashFlowForecastSnapshot::where('org_id', $orgId)
            ->with('scenario')
            ->latest('as_of_date')
            ->take(15)
            ->get();

        return Inertia::render('Finance/CashFlow', [
            'comparison' => $comparison,
            'financialHealth' => $financialHealth,
            'scenarios' => $scenarios,
            'recentSnapshots' => $recentSnapshots,
            'filters' => [
                'horizon' => $horizon,
                'as_of_date' => $asOfDate->toDateString(),
            ],
            'canManage' => $user->can('cash_flow.manage'),
        ]);
    }

    public function snapshot(Request $request): RedirectResponse
    {
        $user = $request->user();
        $orgId = $user->org_id;

        $horizon = max(14, min(180, (int) $request->input('horizon', 90)));
        $asOfDate = $request->input('as_of_date')
            ? Carbon::parse($request->input('as_of_date'))
            : Carbon::today();

        $scenarios = $this->cashFlowService->getOrCreateDefaultScenarios($orgId);

        foreach ($scenarios as $scenario) {
            $this->cashFlowService->generateForecast($orgId, $scenario->scenario_key, $horizon, $asOfDate, $user);
        }

        $this->cashFlowService->calculateFinancialHealth($orgId, $asOfDate, $user);

        return back()->with('success', 'บันทึก Snapshot กระแสเงินสดและการเงินเรียบร้อยแล้ว');
    }

    public function updateScenario(Request $request, ForecastScenario $scenario): RedirectResponse
    {
        $user = $request->user();
        if ($scenario->org_id !== $user->org_id) {
            abort(403, 'Unauthorized access to scenario.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'ar_collection_rate' => ['required', 'numeric', 'min:0', 'max:200'],
            'deal_multiplier' => ['required', 'numeric', 'min:0', 'max:200'],
            'ar_delay_days' => ['required', 'integer', 'min:0', 'max:180'],
            'expense_buffer_percent' => ['required', 'numeric', 'min:0', 'max:200'],
            'payroll_buffer_percent' => ['required', 'numeric', 'min:0', 'max:200'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        if (! empty($validated['is_default'])) {
            ForecastScenario::where('org_id', $user->org_id)
                ->where('id', '!=', $scenario->id)
                ->update(['is_default' => false]);
        }

        $scenario->update($validated);

        return back()->with('success', "อัปเดตแบบจำลอง {$scenario->name} เรียบร้อยแล้ว");
    }
}
