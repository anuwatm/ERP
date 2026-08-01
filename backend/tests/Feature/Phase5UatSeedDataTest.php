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
                ->where('executiveSummary.sales.customers', 3)
                ->where('executiveSummary.sales.open_deals', 2)
                ->where('executiveSummary.sales.pipeline_value', 300000)
                ->where('executiveSummary.sales.won_deals', 2)
                ->where('executiveSummary.sales.won_value', 195000)
                ->where('executiveSummary.finance.invoiced_revenue', 100000)
                ->where('executiveSummary.finance.cash_in', 25000)
                ->where('executiveSummary.finance.outstanding_ar', 75000)
                ->where('executiveSummary.finance.overdue_ar', 0)
                ->where('executiveSummary.finance.recognized_expense', 25000)
                ->where('executiveSummary.finance.gross_profit', 75000)
                ->where('executiveSummary.delivery.active_projects', 1)
                ->where('executiveSummary.delivery.overdue_tasks', 1)
                ->where('executiveSummary.delivery.project_profit', 75000)
                ->where('executiveSummary.delivery.delivery_risk_count', 1)
                ->where('financeSummary.net_cash_flow', 15000)
                ->missing('executiveSummary.cash_balance')
            );
    }
}
