<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase231SalesDialogsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_quick_contact_retry_returns_same_reference_without_duplicate(): void
    {
        $user = $this->user(['contacts.create', 'deals.view']);
        $customer = $this->customer($user);
        $key = (string) Str::uuid();
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->withHeader('X-Form-Submission', $key)->from(route('deals.index'));
        $payload = ['name' => 'Quick contact', 'is_primary' => true, 'note' => 'Keep deal draft'];
        $this->post(route('contacts.store', $customer), $payload)->assertRedirect(route('deals.index'))->assertSessionHasNoErrors();
        $contact = Contact::where('customer_id', $customer->id)->firstOrFail();
        $this->post(route('contacts.store', $customer), $payload)->assertRedirect(route('deals.index'))->assertSessionHas('created_reference.id', $contact->id);
        $this->assertSame(1, Contact::where('customer_id', $customer->id)->count());
        $this->assertDatabaseCount('form_submissions', 1);
        $this->get(route('deals.index'))->assertInertia(fn (Assert $page) => $page
            ->where('flash.created_reference.id', $contact->id)
            ->where('customers.0.contacts.0.id', $contact->id));
        $this->post(route('contacts.store', $customer), array_merge($payload, ['name' => 'Changed after saved']))->assertSessionHasErrors('submission');
        $this->assertSame('Quick contact', $contact->refresh()->name);
    }

    public function test_invalid_submission_does_not_consume_key_and_quick_create_keeps_scope_guards(): void
    {
        $user = $this->user(['contacts.create']);
        $customer = $this->customer($user);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->withHeader('X-Form-Submission', (string) Str::uuid());
        $this->post(route('contacts.store', $customer), ['name' => ''])->assertSessionHasErrors('name');
        $this->assertDatabaseCount('form_submissions', 0);
        $this->post(route('contacts.store', $customer), ['name' => 'Corrected'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('form_submissions', 1);
        $other = $this->customer(User::factory()->create());
        $this->withHeader('X-Form-Submission', (string) Str::uuid())->post(route('contacts.store', $other), ['name' => 'Forbidden'])->assertNotFound();
        $this->assertDatabaseCount('form_submissions', 1);
    }

    public function test_deal_and_activity_creates_are_idempotent_per_draft(): void
    {
        $user = $this->user(['deals.create', 'activities.create']);
        $customer = $this->customer($user);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->withHeader('X-Form-Submission', (string) Str::uuid());
        foreach ([1, 2] as $attempt) {
            $this->post(route('deals.store'), $this->dealPayload($customer))->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('deals', 1);
        $deal = Deal::firstOrFail();
        $this->withHeader('X-Form-Submission', (string) Str::uuid());
        foreach ([1, 2] as $attempt) {
            $this->post(route('activities.store'), ['entity_type' => 'deal', 'entity_id' => $deal->id, 'activity_type' => 'note', 'subject' => 'Retry safe', 'activity_at' => '2026-09-12T01:00:00Z'])->assertRedirect()->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('activities', 1);
        $this->assertDatabaseCount('form_submissions', 2);
    }

    public function test_deal_dialog_round_trip_and_explicit_optional_field_clearing(): void
    {
        $user = $this->user(['deals.view', 'deals.create', 'deals.update']);
        $customer = $this->customer($user);
        $contact = Contact::create(['org_id' => $user->org_id, 'customer_id' => $customer->id, 'name' => 'Contact']);
        $payload = $this->dealPayload($customer) + [
            'contact_id' => $contact->id, 'owner_id' => $user->id,
            'expected_close_date' => '2026-10-10', 'source' => 'Referral', 'note' => 'รายละเอียดดีล',
        ];
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('deals.index'))->post(route('deals.store'), $payload)
            ->assertRedirect(route('deals.index'))->assertSessionHasNoErrors();
        $deal = Deal::where('title', $payload['title'])->firstOrFail();
        $this->assertSame('USD', $deal->currency);
        $this->assertSame('1250.50', $deal->value_amount);
        $this->assertSame(45, $deal->probability);
        $this->get(route('deals.index'))->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Deals')
            ->where('deals.0.contact_id', $contact->id)
            ->where('deals.0.source', 'Referral')
            ->where('deals.0.note', 'รายละเอียดดีล')
        );

        $updated = array_merge($payload, [
            'stage' => 'lost', 'lost_reason' => 'Customer postponed', 'contact_id' => '',
            'expected_close_date' => '', 'source' => '', 'note' => '',
        ]);
        $this->patch(route('deals.update', $deal), $updated)->assertRedirect()->assertSessionHasNoErrors();
        $deal->refresh();
        $this->assertSame('Customer postponed', $deal->lost_reason);
        $this->assertNotNull($deal->lost_at);
        $this->assertNull($deal->won_at);
        foreach (['contact_id', 'expected_close_date', 'source', 'note'] as $field) {
            $this->assertNull($deal->$field, $field);
        }
        $before = $deal->getRawOriginal();
        $this->patch(route('deals.update', $deal), array_merge($updated, ['lost_reason' => '']))->assertStatus(422);
        $this->assertSame($before, $deal->refresh()->getRawOriginal());
        $this->patch(route('deals.update', $deal), array_merge($updated, ['probability' => 101]))->assertSessionHasErrors('probability');
        $this->assertSame($before, $deal->refresh()->getRawOriginal());
    }

    public function test_activity_dialog_preserves_completion_and_owner_while_editing_and_clearing_notes(): void
    {
        $user = $this->user(['deals.view', 'activities.create', 'activities.update']);
        $customer = $this->customer($user);
        $deal = Deal::create($this->dealPayload($customer) + ['org_id' => $user->org_id, 'owner_id' => $user->id]);
        $payload = [
            'entity_type' => 'deal', 'entity_id' => $deal->id, 'activity_type' => 'meeting',
            'subject' => 'Discuss scope', 'body' => 'รายละเอียดการประชุม', 'owner_id' => $user->id,
            'activity_at' => '2026-09-11T02:03:04Z', 'follow_up_at' => '2026-09-12T04:05:06Z',
            'completed_at' => '2026-09-11T03:04:05Z',
        ];
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])
            ->from(route('deals.index'))->post(route('activities.store'), $payload)
            ->assertRedirect(route('deals.index'))->assertSessionHasNoErrors();
        $activity = Activity::where('subject', 'Discuss scope')->firstOrFail();
        $this->get(route('deals.index'))->assertInertia(fn (Assert $page) => $page
            ->where('deals.0.activities.0.body', 'รายละเอียดการประชุม')
            ->where('deals.0.activities.0.owner_id', $user->id)
            ->where('deals.0.activities.0.activity_at', '2026-09-11T02:03:04.000000Z')
        );
        $updated = array_merge($payload, ['subject' => 'Updated scope', 'body' => '', 'follow_up_at' => '']);
        $this->patch(route('activities.update', $activity), $updated)->assertRedirect()->assertSessionHasNoErrors();
        $activity->refresh();
        $this->assertSame('Updated scope', $activity->subject);
        $this->assertNull($activity->body);
        $this->assertNull($activity->follow_up_at);
        $this->assertSame($user->id, $activity->owner_id);
        $this->assertSame($deal->id, $activity->entity_id);
        $this->assertSame('2026-09-11 03:04:05', $activity->completed_at->toDateTimeString());
        $before = $activity->getRawOriginal();
        $this->patch(route('activities.update', $activity), array_merge($updated, ['activity_at' => '', 'body' => str_repeat('x', 4001)]))
            ->assertSessionHasErrors(['activity_at', 'body']);
        $this->assertSame($before, $activity->refresh()->getRawOriginal());
    }

    public function test_read_only_deals_permission_does_not_grant_editor_or_completion_writes(): void
    {
        $user = $this->user(['deals.view']);
        $customer = $this->customer($user);
        $deal = Deal::create($this->dealPayload($customer) + ['org_id' => $user->org_id, 'owner_id' => $user->id]);
        $activity = Activity::create(['org_id' => $user->org_id, 'entity_type' => 'deal', 'entity_id' => $deal->id, 'activity_type' => 'call', 'activity_at' => now()]);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('deals.index'))->assertInertia(fn (Assert $page) => $page->where('auth.permissions', ['deals.view']));
        $this->post(route('deals.store'), $this->dealPayload($customer))->assertForbidden();
        $this->patch(route('deals.update', $deal), $this->dealPayload($customer))->assertForbidden();
        $this->post(route('activities.store'), [])->assertForbidden();
        $this->patch(route('activities.update', $activity), [])->assertForbidden();
        $this->patch(route('activities.complete', $activity))->assertForbidden();
        $this->assertNull($activity->fresh()->completed_at);
    }

    public function test_dialog_requests_cannot_reassign_entities_outside_sales_scope_or_organization(): void
    {
        $user = $this->user(['deals.create', 'deals.update', 'activities.update']);
        $customer = $this->customer($user);
        $otherUser = User::factory()->create(['org_id' => $user->org_id]);
        $otherCustomer = $this->customer($otherUser, 'OTHER');
        $foreignUser = User::factory()->create();
        $foreignCustomer = $this->customer($foreignUser, 'FOREIGN');
        $deal = Deal::create($this->dealPayload($customer) + ['org_id' => $user->org_id, 'owner_id' => $user->id]);
        $hiddenDeal = Deal::create($this->dealPayload($otherCustomer) + ['org_id' => $user->org_id, 'owner_id' => $otherUser->id]);
        $wrongContact = Contact::create(['org_id' => $user->org_id, 'customer_id' => $otherCustomer->id, 'name' => 'Wrong customer']);
        $activity = Activity::create(['org_id' => $user->org_id, 'entity_type' => 'deal', 'entity_id' => $deal->id, 'activity_type' => 'call', 'activity_at' => now()]);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('deals.store'), $this->dealPayload($otherCustomer))->assertForbidden();
        $this->post(route('deals.store'), $this->dealPayload($foreignCustomer))->assertSessionHasErrors('customer_id');
        $this->patch(route('deals.update', $deal), $this->dealPayload($customer) + ['contact_id' => $wrongContact->id])->assertStatus(422);
        $this->patch(route('deals.update', $hiddenDeal), $this->dealPayload($otherCustomer))->assertForbidden();
        $this->patch(route('activities.update', $activity), [
            'entity_type' => 'deal', 'entity_id' => $hiddenDeal->id, 'activity_type' => 'call', 'activity_at' => '2026-09-11T02:03:04Z',
        ])->assertForbidden();
        $this->assertSame($deal->id, $activity->fresh()->entity_id);
        $this->assertNull($deal->fresh()->contact_id);
    }

    private function user(array $codes): User
    {
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'sales', 'name' => 'Sales', 'is_system' => true]);
        foreach ($codes as $code) {
            [$module, $action] = explode('.', $code, 2);
            $permission = Permission::firstOrCreate(['code' => $code], ['module' => $module, 'action' => $action]);
            $role->permissions()->attach($permission->id);
        }
        $user->roles()->attach($role->id);

        return $user;
    }

    private function customer(User $user, string $code = 'DIALOG'): Customer
    {
        return Customer::create(['org_id' => $user->org_id, 'customer_code' => $code, 'company_name' => 'Dialog customer', 'owner_id' => $user->id]);
    }

    private function dealPayload(Customer $customer): array
    {
        return ['title' => 'Dialog opportunity', 'customer_id' => $customer->id, 'stage' => 'proposal', 'value_amount' => '1250.50', 'currency' => 'USD', 'probability' => 45];
    }
}
