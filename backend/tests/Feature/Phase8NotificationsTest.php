<?php

namespace Tests\Feature;

use App\Mail\ErpNotificationMail;
use App\Models\Customer;
use App\Models\InAppNotification;
use App\Models\Invoice;
use App\Models\NotificationPreference;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Phase8NotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_notification_service_dedupes_in_app_and_email_queue(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $service = app(NotificationService::class);
        $this->assertTrue($service->notify($user, 'test.event', 'same-event', 'ERP Alert', 'First body'));
        $this->assertFalse($service->notify($user, 'test.event', 'same-event', 'ERP Alert', 'Second body'));

        $this->assertSame(1, InAppNotification::where('user_id', $user->id)->count());
        Mail::assertQueued(ErpNotificationMail::class, 1);
    }

    public function test_sent_purchase_order_notifies_approvers_and_shares_unread_count(): void
    {
        Mail::fake();
        $requester = User::factory()->create();
        $approver = User::factory()->create([
            'org_id' => $requester->org_id,
            'branch_id' => $requester->branch_id,
            'division_id' => $requester->division_id,
            'department_id' => $requester->department_id,
        ]);
        $this->attachRole($requester, 'requester', ['purchase_orders.create', 'purchase_orders.view']);
        $this->attachRole($approver, 'approver', ['purchase_orders.approve', 'purchase_orders.view']);
        $supplier = Supplier::create(['org_id' => $requester->org_id, 'supplier_code' => '000001', 'name' => 'Notify Supplier', 'status' => 'active']);

        $this->actingAsOrgUser($requester)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('purchase-orders.store'), [
                'supplier_id' => $supplier->id,
                'status' => 'sent',
                'order_date' => '2026-08-23',
                'expected_date' => '2026-08-30',
                'tax_mode' => 'no_tax',
                'discount_amount' => 0,
                'currency' => 'THB',
                'items' => [[
                    'description' => 'Notify item',
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'unit_price' => 100,
                    'discount_amount' => 0,
                    'tax_rate' => 0,
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $approver->id,
            'type' => 'purchase_order.pending_approval',
        ]);
        Mail::assertQueued(ErpNotificationMail::class, 1);

        $this->actingAsOrgUser($approver)
            ->get(route('purchase-orders.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('notifications.unread_count', 1)
                ->where('notifications.latest.0.title', fn (string $title) => str_contains($title, 'Purchase Order'))
            );
    }

    public function test_notification_preferences_can_disable_channels(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        NotificationPreference::create([
            'org_id' => $user->org_id,
            'user_id' => $user->id,
            'type' => 'test.event',
            'email_enabled' => false,
            'in_app_enabled' => false,
        ]);

        $sent = app(NotificationService::class)->notify($user, 'test.event', 'prefs-event', 'Hidden alert', 'Body');

        $this->assertTrue($sent);
        $this->assertSame(0, InAppNotification::where('user_id', $user->id)->count());
        Mail::assertNothingQueued();
    }

    public function test_notification_preferences_page_updates_channels(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'admin', ['settings.organization.view', 'settings.organization.update']);

        $this->actingAsOrgUser($user)
            ->get(route('settings.notifications.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/NotificationPreferences')
                ->has('preferences', 6)
            );

        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('settings.notifications.update'), [
                'preferences' => [[
                    'type' => 'invoice.overdue',
                    'email_enabled' => false,
                    'in_app_enabled' => true,
                ]],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('notification_preferences', [
            'user_id' => $user->id,
            'type' => 'invoice.overdue',
            'email_enabled' => false,
            'in_app_enabled' => true,
        ]);
    }

    public function test_invite_queues_safe_invitation_email(): void
    {
        Mail::fake();
        $owner = User::factory()->create();
        $role = $this->attachRole($owner, 'owner', ['users.create']);
        $memberRole = Role::create(['org_id' => $owner->org_id, 'code' => 'member', 'name' => 'Member', 'is_system' => true]);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('users.invite'), [
                'name' => 'Invited User',
                'email' => 'phase8-invite@example.com',
                'branch_id' => $owner->branch_id,
                'division_id' => $owner->division_id,
                'department_id' => $owner->department_id,
                'role_id' => $memberRole->id,
            ])
            ->assertRedirect();

        $invited = User::where('email', 'phase8-invite@example.com')->firstOrFail();
        $this->assertDatabaseHas('in_app_notifications', [
            'user_id' => $invited->id,
            'type' => 'user.invite',
        ]);
        Mail::assertQueued(ErpNotificationMail::class, 1);
        $this->assertTrue($owner->roles()->whereKey($role->id)->exists());
    }

    public function test_task_and_project_assignment_queue_notifications(): void
    {
        Mail::fake();
        $manager = User::factory()->create();
        $assignee = User::factory()->create([
            'org_id' => $manager->org_id,
            'branch_id' => $manager->branch_id,
            'division_id' => $manager->division_id,
            'department_id' => $manager->department_id,
        ]);
        $this->attachRole($manager, 'project_manager', ['projects.view', 'projects.create', 'projects.update', 'tasks.create', 'tasks.view']);
        $this->attachRole($assignee, 'member', ['projects.view', 'tasks.view']);
        $project = $this->project($manager);

        $this->actingAsOrgUser($manager)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('tasks.store'), [
                'project_id' => $project->id,
                'title' => 'Assigned task',
                'description' => null,
                'status' => 'todo',
                'priority' => 'normal',
                'assignee_id' => $assignee->id,
                'due_date' => now()->addWeek()->toDateString(),
            ])
            ->assertRedirect();

        $this->actingAsOrgUser($manager)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('projects.members.store', $project), [
                'user_id' => $assignee->id,
                'role' => 'member',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('in_app_notifications', ['user_id' => $assignee->id, 'type' => 'task.assigned']);
        $this->assertDatabaseHas('in_app_notifications', ['user_id' => $assignee->id, 'type' => 'project.member_assigned']);
        Mail::assertQueued(ErpNotificationMail::class, 2);
    }

    public function test_invoice_due_and_overdue_commands_queue_finance_notifications(): void
    {
        Mail::fake();
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['invoices.view']);
        $customer = Customer::create(['org_id' => $finance->org_id, 'owner_id' => $finance->id, 'customer_code' => '000001', 'company_name' => 'Due Customer', 'customer_type' => 'company', 'status' => 'active']);
        $dueSoon = $this->invoice($finance, $customer, 'INV-DUE', 'sent', now()->addDay()->toDateString());
        $overdue = $this->invoice($finance, $customer, 'INV-OLD', 'sent', now()->subDay()->toDateString());

        $this->artisan('invoices:notify-due-soon')
            ->expectsOutput('Queued 1 due-soon notification(s).')
            ->assertExitCode(0);
        $this->artisan('invoices:mark-overdue')
            ->expectsOutput('Marked 1 invoice(s) overdue.')
            ->expectsOutput('Queued 1 overdue notification(s).')
            ->assertExitCode(0);

        $this->assertSame('sent', $dueSoon->fresh()->status);
        $this->assertSame('overdue', $overdue->fresh()->status);
        $this->assertDatabaseHas('in_app_notifications', ['user_id' => $finance->id, 'type' => 'invoice.due_soon']);
        $this->assertDatabaseHas('in_app_notifications', ['user_id' => $finance->id, 'type' => 'invoice.overdue']);
        Mail::assertQueued(ErpNotificationMail::class, 2);
    }

    private function project(User $user): Project
    {
        return Project::create([
            'org_id' => $user->org_id,
            'project_code' => 'PRJ-'.str()->random(6),
            'name' => 'Notification Project',
            'owner_id' => $user->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'progress_percent' => 0,
            'budget_amount' => 1000,
            'currency' => 'THB',
            'created_by' => $user->id,
        ]);
    }

    private function invoice(User $user, Customer $customer, string $number, string $status, string $dueDate): Invoice
    {
        return Invoice::create([
            'org_id' => $user->org_id,
            'invoice_no' => $number,
            'customer_id' => $customer->id,
            'status' => $status,
            'tax_mode' => 'no_tax',
            'issue_date' => now()->toDateString(),
            'due_date' => $dueDate,
            'subtotal' => 1000,
            'total' => 1000,
            'balance_due' => 1000,
            'currency' => 'THB',
            'created_by' => $user->id,
        ]);
    }

    private function attachRole(User $user, string $code, array $permissions): Role
    {
        $role = Role::create(['org_id' => $user->org_id, 'code' => $code, 'name' => ucfirst($code), 'is_system' => true]);
        $user->roles()->attach($role->id);

        foreach ($permissions as $permissionCode) {
            $permission = Permission::firstOrCreate(
                ['code' => $permissionCode],
                ['module' => str($permissionCode)->before('.')->toString(), 'action' => str($permissionCode)->after('.')->toString(), 'description' => $permissionCode]
            );
            $role->permissions()->attach($permission->id);
        }

        return $role;
    }
}
