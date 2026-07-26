<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\NumberSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase2SalesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_owner_can_create_customer_with_auto_generated_code(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', ['customers.create']);
        NumberSequence::create([
            'org_id' => $owner->org_id,
            'branch_key' => '00000000-0000-0000-0000-000000000000',
            'doc_type' => 'customer',
            'year_key' => 0,
            'last_number' => 0,
        ]);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('customers.store'), [
                'company_name' => 'Acme Co., Ltd.',
                'customer_type' => 'lead',
                'status' => 'active',
                'owner_id' => $owner->id,
                'email' => 'hello@acme.example',
            ])->assertRedirect();

        $customer = Customer::where('org_id', $owner->org_id)->where('company_name', 'Acme Co., Ltd.')->firstOrFail();
        $this->assertSame('000001', $customer->customer_code);
        $this->assertTrue(AuditLog::where('action', 'customer.create')->where('entity_id', $customer->id)->exists());
    }

    public function test_sales_user_only_sees_owned_customers(): void
    {
        $sales = User::factory()->create();
        $other = User::factory()->create(['org_id' => $sales->org_id]);
        $this->attachRole($sales, 'sales', ['customers.view']);
        Customer::create(['org_id' => $sales->org_id, 'customer_code' => '000001', 'company_name' => 'Owned Customer', 'owner_id' => $sales->id]);
        Customer::create(['org_id' => $sales->org_id, 'customer_code' => '000002', 'company_name' => 'Hidden Customer', 'owner_id' => $other->id]);

        $this->actingAsOrgUser($sales)->get(route('customers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Customers')
                ->has('customers', 1)
                ->where('customers.0.company_name', 'Owned Customer')
            );
    }

    public function test_primary_contact_is_unique_per_customer(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', ['contacts.create']);
        $customer = Customer::create(['org_id' => $owner->org_id, 'customer_code' => '000001', 'company_name' => 'Contact Co', 'owner_id' => $owner->id]);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('contacts.store', $customer), [
                'name' => 'First Primary',
                'email' => 'first@example.com',
                'is_primary' => true,
            ])->assertRedirect();

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('contacts.store', $customer), [
                'name' => 'Second Primary',
                'email' => 'second@example.com',
                'is_primary' => true,
            ])->assertRedirect();

        $this->assertFalse(Contact::where('name', 'First Primary')->firstOrFail()->is_primary);
        $this->assertTrue(Contact::where('name', 'Second Primary')->firstOrFail()->is_primary);
        $this->assertSame(1, Contact::where('customer_id', $customer->id)->where('is_primary', true)->count());
    }

    public function test_deal_stage_rules_and_contact_customer_chain(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', ['deals.create']);
        $customer = Customer::create(['org_id' => $owner->org_id, 'customer_code' => '000001', 'company_name' => 'Deal Co', 'owner_id' => $owner->id]);
        $otherCustomer = Customer::create(['org_id' => $owner->org_id, 'customer_code' => '000002', 'company_name' => 'Other Co', 'owner_id' => $owner->id]);
        $wrongContact = Contact::create(['org_id' => $owner->org_id, 'customer_id' => $otherCustomer->id, 'name' => 'Wrong Contact']);

        $payload = [
            'title' => 'Important Deal',
            'customer_id' => $customer->id,
            'stage' => 'lost',
            'value_amount' => 10000,
            'currency' => 'THB',
            'probability' => 20,
            'owner_id' => $owner->id,
        ];

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('deals.store'), $payload)->assertStatus(422);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('deals.store'), array_merge($payload, ['stage' => 'new', 'contact_id' => $wrongContact->id]))->assertStatus(422);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('deals.store'), array_merge($payload, ['stage' => 'won', 'lost_reason' => null]))->assertRedirect();

        $deal = Deal::where('title', 'Important Deal')->firstOrFail();
        $this->assertSame('won', $deal->stage);
        $this->assertNotNull($deal->won_at);
        $this->assertNull($deal->lost_reason);
    }

    public function test_activity_allowlist_follow_up_completion_and_sales_dashboard(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', ['activities.create', 'activities.update', 'sales.dashboard.view']);
        $customer = Customer::create(['org_id' => $owner->org_id, 'customer_code' => '000001', 'company_name' => 'Activity Co', 'owner_id' => $owner->id]);
        $deal = Deal::create(['org_id' => $owner->org_id, 'title' => 'Follow Deal', 'customer_id' => $customer->id, 'stage' => 'proposal', 'value_amount' => 50000, 'owner_id' => $owner->id]);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('activities.store'), [
                'entity_type' => 'invoice',
                'entity_id' => (string) Str::orderedUuid(),
                'activity_type' => 'call',
                'activity_at' => now()->toDateTimeString(),
            ])->assertSessionHasErrors('entity_type');

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('activities.store'), [
                'entity_type' => 'deal',
                'entity_id' => $deal->id,
                'activity_type' => 'call',
                'subject' => 'Follow up call',
                'activity_at' => now()->toDateTimeString(),
                'follow_up_at' => now()->toDateTimeString(),
                'owner_id' => $owner->id,
            ])->assertRedirect();

        $activity = Activity::where('subject', 'Follow up call')->firstOrFail();
        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('activities.complete', $activity))->assertRedirect();

        $this->assertNotNull($activity->refresh()->completed_at);

        $this->actingAsOrgUser($owner)->get(route('sales.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sales/Dashboard')
                ->has('summary')
                ->missing('summary.cash_in')
            );
    }

    private function attachRole(User $user, string $code, array $permissions): Role
    {
        $role = Role::create([
            'org_id' => $user->org_id,
            'code' => $code,
            'name' => Str::headline($code),
            'is_system' => true,
        ]);

        foreach ($permissions as $permissionCode) {
            $parts = explode('.', $permissionCode);
            $permission = Permission::firstOrCreate(
                ['code' => $permissionCode],
                ['module' => $parts[0], 'action' => $parts[count($parts) - 1]]
            );
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $role;
    }
}
