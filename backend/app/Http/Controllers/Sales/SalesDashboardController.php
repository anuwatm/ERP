<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Deal;
use App\Support\SalesAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SalesDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $customers = SalesAccess::scopeCustomers(Customer::query(), $user);
        $deals = SalesAccess::scopeDeals(Deal::query(), $user);
        $openStages = Deal::OPEN_STAGES;
        $today = now()->toDateString();
        $staleCutoff = now()->subDays(7);

        $pipelineByStage = SalesAccess::scopeDeals(Deal::query(), $user)
            ->select('stage', DB::raw('count(*) as count'), DB::raw('sum(value_amount) as value'))
            ->groupBy('stage')
            ->get()
            ->map(fn (Deal $deal) => [
                'stage' => $deal->stage,
                'count' => (int) $deal->count,
                'value' => (float) $deal->value,
            ]);

        $followUpsToday = Activity::where('activities.org_id', $user->org_id)
            ->whereDate('follow_up_at', $today)
            ->whereNull('completed_at')
            ->when(! SalesAccess::canSeeAll($user), fn ($query) => $query->where('activities.owner_id', $user->id))
            ->count();

        $staleDeals = SalesAccess::scopeDeals(Deal::query(), $user)
            ->whereIn('stage', $openStages)
            ->whereDoesntHave('activities', fn ($query) => $query->where('activity_at', '>=', $staleCutoff))
            ->count();

        $topOwners = SalesAccess::scopeDeals(Deal::query(), $user)
            ->with('owner:id,name,email')
            ->select('owner_id', DB::raw('count(*) as deals_count'), DB::raw('sum(value_amount) as pipeline_value'))
            ->whereIn('stage', $openStages)
            ->groupBy('owner_id')
            ->orderByDesc('pipeline_value')
            ->limit(5)
            ->get()
            ->map(fn (Deal $deal) => [
                'owner' => $deal->owner?->name ?? 'Unassigned',
                'deals_count' => (int) $deal->deals_count,
                'pipeline_value' => (float) $deal->pipeline_value,
            ]);

        return Inertia::render('Sales/Dashboard', [
            'summary' => [
                'customers' => (clone $customers)->count(),
                'active_customers' => (clone $customers)->where('status', 'active')->count(),
                'open_deals' => (clone $deals)->whereIn('stage', $openStages)->count(),
                'pipeline_value' => (float) (clone $deals)->whereIn('stage', $openStages)->sum('value_amount'),
                'won_deals' => (clone $deals)->where('stage', 'won')->count(),
                'lost_deals' => (clone $deals)->where('stage', 'lost')->count(),
                'follow_ups_today' => $followUpsToday,
                'stale_deals' => $staleDeals,
            ],
            'pipelineByStage' => $pipelineByStage,
            'topOwners' => $topOwners,
        ]);
    }
}
