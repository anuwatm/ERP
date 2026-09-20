<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Quotation;
use App\Support\DashboardDate;
use App\Support\SalesAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SalesDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $now = DashboardDate::now();
        $period = in_array($request->query('period'), ['all_time', 'today', 'this_week', 'month', 'year', 'custom'], true)
            ? $request->query('period') : 'month';
        $from = match ($period) {
            'today' => $now->toDateString(),
            'this_week' => $now->startOfWeek()->toDateString(),
            'month' => $now->startOfMonth()->toDateString(),
            'year' => $now->startOfYear()->toDateString(),
            default => null,
        };
        $to = match ($period) {
            'today' => $now->toDateString(),
            'this_week' => $now->endOfWeek()->toDateString(),
            'month' => $now->endOfMonth()->toDateString(),
            'year' => $now->endOfYear()->toDateString(),
            default => null,
        };
        if ($period === 'custom') {
            $dates = $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from']]);
            $from = $dates['from'];
            $to = $dates['to'];
        }
        $filters = ['period' => $period, 'from' => $from, 'to' => $to];
        $canDeals = $user->hasPermissionCode('deals.view');
        $canCustomers = $user->hasPermissionCode('customers.view');
        $canQuotations = $user->hasPermissionCode('quotations.view');
        $deals = fn () => SalesAccess::scopeDeals(Deal::query(), $user);
        $inPeriod = function (Builder $query, string $column) use ($from, $to): Builder {
            return DashboardDate::range($query, $column, $from, $to);
        };
        $staleCutoff = $now->subDays(7)->setTimezone(config('app.timezone'));
        $summary = [
            'customers' => $canCustomers ? SalesAccess::scopeCustomers(Customer::query(), $user)->count() : null,
            'active_customers' => $canCustomers ? SalesAccess::scopeCustomers(Customer::query(), $user)->where('status', 'active')->count() : null,
            'open_deals' => $canDeals ? $deals()->whereIn('stage', Deal::OPEN_STAGES)->count() : null,
            'new_deals' => $canDeals ? $inPeriod($deals(), 'created_at')->count() : null,
            'won_deals' => $canDeals ? $inPeriod($deals()->where('stage', 'won'), 'won_at')->count() : null,
            'lost_deals' => $canDeals ? $inPeriod($deals()->where('stage', 'lost'), 'lost_at')->count() : null,
            'stale_deals' => $canDeals ? $this->staleDeals($deals(), $staleCutoff)->count() : null,
        ];
        $closed = ($summary['won_deals'] ?? 0) + ($summary['lost_deals'] ?? 0);
        $summary['win_rate'] = $canDeals && $closed > 0 ? round($summary['won_deals'] * 100 / $closed, 1) : null;
        $followUps = DashboardDate::range(Activity::query()->where('org_id', $user->org_id), 'follow_up_at', $now->toDateString(), $now->toDateString())
            ->whereNull('completed_at')
            ->when(! SalesAccess::canSeeAll($user), fn ($q) => $q->where('owner_id', $user->id)
                ->where(function ($entities) use ($user) {
                    $entities->where(fn ($activity) => $activity->where('entity_type', 'deal')
                        ->whereExists(fn ($deal) => $deal->selectRaw('1')->from('deals')
                            ->whereColumn('deals.id', 'activities.entity_id')->where('deals.org_id', $user->org_id)
                            ->where('deals.owner_id', $user->id)->whereNull('deals.deleted_at')))
                        ->orWhere(fn ($activity) => $activity->where('entity_type', 'customer')
                            ->whereExists(fn ($customer) => $customer->selectRaw('1')->from('customers')
                                ->whereColumn('customers.id', 'activities.entity_id')->where('customers.org_id', $user->org_id)
                                ->where('customers.owner_id', $user->id)->whereNull('customers.deleted_at')))
                        ->orWhere(fn ($activity) => $activity->where('entity_type', 'contact')
                            ->whereExists(fn ($contact) => $contact->selectRaw('1')->from('contacts')
                                ->join('customers', 'customers.id', '=', 'contacts.customer_id')
                                ->whereColumn('contacts.id', 'activities.entity_id')->where('contacts.org_id', $user->org_id)
                                ->where('customers.org_id', $user->org_id)->where('customers.owner_id', $user->id)
                                ->whereNull('contacts.deleted_at')->whereNull('customers.deleted_at')));
                }));
        $summary['follow_ups_today'] = $canDeals ? (clone $followUps)->count() : null;
        $pipeline = $canDeals ? $deals()->whereIn('stage', Deal::OPEN_STAGES)
            ->select('stage', 'currency', DB::raw('count(*) as deals_count'), DB::raw('sum(value_amount) as amount'))
            ->groupBy('stage', 'currency')->orderBy('stage')->orderBy('currency')->get()
            ->map(fn ($row) => ['stage' => $row->stage, 'currency' => $row->currency ?? 'Unspecified', 'count' => (int) $row->deals_count, 'value' => (float) $row->amount]) : collect();
        $wonValues = $canDeals ? $inPeriod($deals()->where('stage', 'won'), 'won_at')
            ->select('currency', DB::raw('sum(value_amount) as amount'))->groupBy('currency')->orderBy('currency')->get()
            ->map(fn ($row) => ['currency' => $row->currency ?? 'Unspecified', 'value' => (float) $row->amount]) : collect();
        $topOwners = $canDeals ? $deals()->whereIn('stage', Deal::OPEN_STAGES)->with('owner:id,name')
            ->select('owner_id', 'currency', DB::raw('count(*) as deals_count'), DB::raw('sum(value_amount) as amount'))
            ->groupBy('owner_id', 'currency')->orderByDesc('deals_count')->limit(10)->get()
            ->map(fn ($row) => ['owner' => $row->owner?->name ?? 'Unassigned', 'currency' => $row->currency ?? 'Unspecified', 'count' => (int) $row->deals_count, 'value' => (float) $row->amount, 'owner_id' => $row->owner_id]) : collect();
        $quotations = $canQuotations ? Quotation::query()->where('org_id', $user->org_id)
            ->when(! SalesAccess::canSeeAll($user), fn ($q) => $q->whereHas('deal', fn ($deal) => $deal->where('owner_id', $user->id)))
            ->where('status', 'sent')->whereDate('valid_until', '>=', $now->toDateString())
            ->whereDate('valid_until', '<=', $now->addDays(7)->toDateString())
            ->orderBy('valid_until')->limit(5)->get(['id', 'quotation_no', 'valid_until', 'status']) : collect();

        $dueActivities = $canDeals ? (clone $followUps)->orderBy('follow_up_at')->limit(5)->get(['id', 'subject', 'entity_type', 'entity_id', 'follow_up_at']) : collect();
        $contactCustomers = $canCustomers ? Contact::query()->where('org_id', $user->org_id)
            ->whereIn('id', $dueActivities->where('entity_type', 'contact')->pluck('entity_id'))
            ->when(! SalesAccess::canSeeAll($user), fn ($q) => $q->whereHas('customer', fn ($customer) => $customer->where('owner_id', $user->id)))
            ->pluck('customer_id', 'id') : collect();
        $visibleCustomerIds = $canCustomers ? SalesAccess::scopeCustomers(Customer::query(), $user)
            ->whereIn('id', $dueActivities->where('entity_type', 'customer')->pluck('entity_id'))->pluck('id') : collect();

        return Inertia::render('Sales/Dashboard', [
            'filters' => $filters,
            'summary' => $summary,
            'pipelineByStage' => $pipeline,
            'wonValues' => $wonValues,
            'topOwners' => $topOwners,
            'followUps' => $dueActivities->map(fn ($activity) => [
                'id' => $activity->id,
                'subject' => $activity->subject,
                'entity_type' => $activity->entity_type,
                'entity_id' => $activity->entity_id,
                'follow_up_at' => $activity->follow_up_at,
                'customer_id' => $activity->entity_type === 'customer' && $visibleCustomerIds->contains($activity->entity_id) ? $activity->entity_id : ($activity->entity_type === 'contact' ? $contactCustomers->get($activity->entity_id) : null),
            ]),
            'staleDeals' => $canDeals ? $this->staleDeals($deals(), $staleCutoff)->withMax('activities', 'activity_at')->orderBy('created_at')->limit(5)->get(['id', 'title', 'stage', 'created_at'])->map(fn ($deal) => [
                'id' => $deal->id,
                'title' => $deal->title,
                'stage' => $deal->stage,
                'created_at' => $deal->created_at,
                'last_activity_at' => $deal->activities_max_activity_at,
            ]) : [],
            'expiringQuotations' => $quotations,
            'visibility' => ['deals' => $canDeals, 'customers' => $canCustomers, 'quotations' => $canQuotations],
        ]);
    }

    private function staleDeals(Builder $query, CarbonImmutable $cutoff): Builder
    {
        return $query->whereIn('stage', Deal::OPEN_STAGES)->where('created_at', '<=', $cutoff)
            ->whereDoesntHave('activities', fn ($activities) => $activities->where('activity_at', '>=', $cutoff));
    }
}
