<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Invoice;
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

    public function test_customer_dialog_fields_round_trip_and_validation_preserves_data(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', ['customers.create', 'customers.update', 'customers.view']);
        $payload = [
            'company_name' => 'Dialog Customer',
            'tax_id' => '0123456789012',
            'customer_type' => 'prospect',
            'status' => 'active',
            'owner_id' => $owner->id,
            'email' => 'customer@example.test',
            'phone' => '021234567',
            'line_id' => 'customer-line',
            'website' => 'example.test',
            'address' => "Building A\nBangkok",
            'source' => 'Referral',
            'note' => 'Customer notes',
        ];
        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('customers.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $customer = Customer::where('company_name', 'Dialog Customer')->firstOrFail();
        $this->assertSame($payload, $customer->only(array_keys($payload)));

        $updated = array_merge($payload, ['company_name' => 'Updated Customer', 'status' => 'inactive', 'note' => 'Updated notes']);
        $this->patch(route('customers.update', $customer), $updated)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($updated, $customer->refresh()->only(array_keys($updated)));
        $this->get(route('customers.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Customers')
            ->where('customers.0.tax_id', $payload['tax_id'])
            ->where('customers.0.address', $payload['address'])
            ->where('customers.0.note', 'Updated notes')
        );

        $this->patch(route('customers.update', $customer), array_merge($updated, [
            'company_name' => '', 'email' => 'invalid', 'note' => str_repeat('x', 2001),
        ]))->assertSessionHasErrors(['company_name', 'email', 'note']);
        $this->assertSame($updated, $customer->refresh()->only(array_keys($updated)));
    }

    public function test_primary_contact_is_unique_per_customer(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', ['contacts.create', 'contacts.update']);
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

        $first = Contact::where('name', 'First Primary')->firstOrFail();
        $this->patch(route('contacts.update', $first), [
            'name' => 'Updated Contact',
            'position' => 'Manager',
            'email' => 'updated@example.test',
            'phone' => '021234567',
            'line_id' => 'contact-line',
            'note' => 'Contact dialog notes',
            'is_primary' => true,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($customer->id, $first->refresh()->customer_id);
        $this->assertSame('Manager', $first->position);
        $this->assertSame('contact-line', $first->line_id);
        $this->assertSame('Contact dialog notes', $first->note);
        $this->assertTrue($first->is_primary);
        $this->assertFalse(Contact::where('name', 'Second Primary')->firstOrFail()->is_primary);
        $this->assertSame(1, Contact::where('customer_id', $customer->id)->where('is_primary', true)->count());

        $this->patch(route('contacts.update', $first), [
            'name' => '', 'email' => 'invalid', 'note' => str_repeat('x', 2001),
        ])->assertSessionHasErrors(['name', 'email', 'note']);
        $this->assertSame('Updated Contact', $first->refresh()->name);
        $this->assertSame('Contact dialog notes', $first->note);
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

    public function test_customer_with_invoice_cannot_be_deleted(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', ['customers.delete']);
        $customer = Customer::create(['org_id' => $owner->org_id, 'customer_code' => '000001', 'company_name' => 'Invoice Customer', 'owner_id' => $owner->id]);
        Invoice::create(['org_id' => $owner->org_id, 'invoice_no' => '000001', 'customer_id' => $customer->id, 'status' => 'sent', 'tax_mode' => 'no_tax', 'issue_date' => '2026-07-29', 'total' => 100, 'balance_due' => 100]);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('customers.destroy', $customer))->assertStatus(422);

        $this->assertFalse($customer->fresh()->trashed());
    }

    public function test_customer_view_permission_does_not_grant_dialog_writes(): void
    {
        $viewer = User::factory()->create();
        $this->attachRole($viewer, 'sales', ['customers.view']);
        $customer = Customer::create(['org_id' => $viewer->org_id, 'customer_code' => 'READ001', 'company_name' => 'Read only', 'owner_id' => $viewer->id]);
        $contact = Contact::create(['org_id' => $viewer->org_id, 'customer_id' => $customer->id, 'name' => 'Read only contact']);

        $this->actingAsOrgUser($viewer)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('customers.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions', ['customers.view'])
                ->has('customers', 1)
            );

        $this->post(route('customers.store'), ['company_name' => 'Forbidden'])->assertForbidden();
        $this->patch(route('customers.update', $customer), ['company_name' => 'Forbidden'])->assertForbidden();
        $this->delete(route('customers.destroy', $customer))->assertForbidden();
        $this->post(route('contacts.store', $customer), ['name' => 'Forbidden'])->assertForbidden();
        $this->patch(route('contacts.update', $contact), ['name' => 'Forbidden'])->assertForbidden();
        $this->assertSame('Read only', $customer->fresh()->company_name);
        $this->assertSame('Read only contact', $contact->fresh()->name);
    }

    public function test_sales_dashboard_separates_currency_and_period_and_excludes_new_deals_from_stale(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', ['sales.dashboard.view', 'deals.view', 'customers.view']);
        $customer = Customer::create(['org_id' => $owner->org_id, 'customer_code' => 'DASH001', 'company_name' => 'Dashboard Customer', 'owner_id' => $owner->id]);
        Deal::create(['org_id' => $owner->org_id, 'title' => 'New THB', 'customer_id' => $customer->id, 'stage' => 'new', 'currency' => 'THB', 'value_amount' => 100, 'owner_id' => $owner->id]);
        Deal::create(['org_id' => $owner->org_id, 'title' => 'New USD', 'customer_id' => $customer->id, 'stage' => 'proposal', 'currency' => 'USD', 'value_amount' => 50, 'owner_id' => $owner->id]);
        $stale = Deal::create(['org_id' => $owner->org_id, 'title' => 'Old THB', 'customer_id' => $customer->id, 'stage' => 'qualified', 'currency' => 'THB', 'value_amount' => 200, 'owner_id' => $owner->id]);
        $stale->forceFill(['created_at' => now()->subDays(10)])->save();
        Deal::create(['org_id' => $owner->org_id, 'title' => 'Won', 'customer_id' => $customer->id, 'stage' => 'won', 'currency' => 'THB', 'value_amount' => 300, 'won_at' => now(), 'owner_id' => $owner->id]);
        Deal::create(['org_id' => $owner->org_id, 'title' => 'Old loss', 'customer_id' => $customer->id, 'stage' => 'lost', 'currency' => 'THB', 'value_amount' => 400, 'lost_at' => now()->subMonths(2), 'owner_id' => $owner->id]);

        $this->actingAsOrgUser($owner)->get(route('sales.dashboard', ['period' => 'month']))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.open_deals', 3)
            ->where('summary.new_deals', 5)
            ->where('summary.won_deals', 1)
            ->where('summary.lost_deals', 0)
            ->where('summary.win_rate', 100)
            ->where('summary.stale_deals', 1)
            ->where('staleDeals.0.title', 'Old THB')
            ->where('staleDeals.0.last_activity_at', null)
            ->has('pipelineByStage', 3)
            ->has('wonValues', 1)
            ->where('wonValues.0.currency', 'THB')
            ->where('wonValues.0.value', 300)
            );
        $this->get(route('deals.index', ['stage' => 'new', 'currency' => 'THB']))
            ->assertInertia(fn (Assert $page) => $page->has('deals', 1)->where('deals.0.title', 'New THB'));
        $this->get(route('deals.index', ['stage' => 'won', 'won_from' => now()->startOfMonth()->toDateString(), 'won_to' => now()->endOfMonth()->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page->has('deals', 1)->where('deals.0.title', 'Won'));
        $this->get(route('deals.index', ['stage' => 'lost', 'lost_from' => now()->startOfMonth()->toDateString(), 'lost_to' => now()->endOfMonth()->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page->has('deals', 0));
        $this->get(route('customers.index', ['customer_id' => $customer->id]))
            ->assertInertia(fn (Assert $page) => $page->has('customers', 1)->where('customers.0.id', $customer->id));
    }

    public function test_sales_followups_require_current_record_ownership(): void
    {
        $sales = User::factory()->create();
        $other = User::factory()->create(['org_id' => $sales->org_id]);
        $this->attachRole($sales, 'sales', ['sales.dashboard.view', 'deals.view']);
        $customer = Customer::create(['org_id' => $sales->org_id, 'customer_code' => 'SCOPE001', 'company_name' => 'Scope', 'owner_id' => $sales->id]);
        $own = Deal::create(['org_id' => $sales->org_id, 'title' => 'Own', 'customer_id' => $customer->id, 'stage' => 'new', 'owner_id' => $sales->id]);
        $moved = Deal::create(['org_id' => $sales->org_id, 'title' => 'Moved', 'customer_id' => $customer->id, 'stage' => 'new', 'owner_id' => $other->id]);
        foreach ([$own, $moved] as $deal) {
            Activity::create(['org_id' => $sales->org_id, 'entity_type' => 'deal', 'entity_id' => $deal->id, 'activity_type' => 'call', 'subject' => $deal->title, 'activity_at' => now(), 'follow_up_at' => now(), 'owner_id' => $sales->id]);
        }

        $this->actingAsOrgUser($sales)->get(route('sales.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.follow_ups_today', 1)
                ->has('followUps', 1)
                ->where('followUps.0.subject', 'Own'));
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
