<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase5UatSeedDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_uat_seed_data_has_expected_phase5_dashboard_values(): void
    {
        $this->seed(DatabaseSeeder::class);
        $owner = User::where('email', 'owner@example.com')->firstOrFail();

        $this->actingAsOrgUser($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('executiveSummary', null)
                ->where('dashboardWidgets.executive.sales.open_deals', 2)
                ->has('dashboardWidgets.executive.sales.pipeline_by_currency', 1)
                ->has('dashboardWidgets.executive.sales.won_by_currency', 1)
                ->has('dashboardWidgets.finance.revenue')
                ->has('dashboardWidgets.finance.cash_in')
                ->has('dashboardWidgets.finance.ar')
                ->has('dashboardWidgets.finance.expense')
                ->where('dashboardWidgets.delivery.projects.active_projects', 1)
                ->where('dashboardWidgets.delivery.tasks.overdue_tasks', 1)
                ->where('dashboardWidgets.executive.project_risks.count', 1)
                ->where('dashboardWidgets.finance.revenue.invoiced_revenue', 100000)
                ->where('dashboardWidgets.finance.cash_in.receipts', 30000)
                ->where('dashboardWidgets.finance.cash_in.reversals', 5000)
                ->where('dashboardWidgets.finance.ar.outstanding_ar', 75000)
                ->where('dashboardWidgets.finance.expense.recognized_expense', 25000)
            );
    }
}
