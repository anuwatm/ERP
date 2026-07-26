<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\NumberSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationProvisioner;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Phase1DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->syncPermissionCatalog();

        $owner = User::where('email', 'owner@example.com')->first();

        if (! $owner) {
            $owner = $this->seedPhase1Demo();
        }

        $this->seedPhase2Demo($owner);
    }

    private function seedPhase1Demo(): User
    {
        $request = Request::create('/register', 'POST', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'ERP Phase 1 Seeder',
        ]);

        $owner = app(OrganizationProvisioner::class)->registerOwner([
            'organization_name' => 'Demo ERP Co., Ltd.',
            'name' => 'Demo Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
        ], $request);

        $owner->forceFill(['email_verified_at' => now()])->save();

        $roles = Role::where('org_id', $owner->org_id)->get()->keyBy('code');

        $admin = $this->createDemoUser($owner, 'Demo Admin', 'admin@example.com', 'Admin', 'active');
        $admin->roles()->syncWithoutDetaching([$roles['admin']->id => ['assigned_at' => now(), 'assigned_by' => $owner->id]]);

        $inviteToken = Str::random(48);
        $member = $this->createDemoUser($owner, 'Demo Invited Member', 'member@example.com', 'Member', 'invited');
        $member->forceFill([
            'invite_token_hash' => hash('sha256', $inviteToken),
            'invite_expires_at' => now()->addHours(72),
            'invited_at' => now(),
        ])->save();
        $member->roles()->syncWithoutDetaching([$roles['member']->id => ['assigned_at' => now(), 'assigned_by' => $owner->id]]);

        $viewer = $this->createDemoUser($owner, 'Demo Inactive Viewer', 'viewer@example.com', 'Viewer', 'inactive');
        $viewer->roles()->syncWithoutDetaching([$roles['viewer']->id => ['assigned_at' => now(), 'assigned_by' => $owner->id]]);

        AuditLog::create([
            'org_id' => $owner->org_id,
            'actor_user_id' => $owner->id,
            'action' => 'seed.phase1_demo',
            'entity_type' => 'organization',
            'entity_id' => $owner->org_id,
            'after_json' => [
                'owner' => $owner->email,
                'admin' => $admin->email,
                'invited_member' => $member->email,
                'inactive_viewer' => $viewer->email,
                'dev_invite_url' => route('invites.accept', ['user' => $member->id, 'token' => $inviteToken], absolute: false),
            ],
        ]);

        return $owner;
    }

    private function seedPhase2Demo(User $owner): void
    {
        if (! Customer::where('org_id', $owner->org_id)->where('customer_code', '000001')->exists()) {
            $roles = Role::where('org_id', $owner->org_id)->get()->keyBy('code');
            $sales = $this->createDemoUser($owner, 'Demo Sales', 'sales@example.com', 'Sales Representative', 'active');
            $sales->roles()->syncWithoutDetaching([$roles['sales']->id => ['assigned_at' => now(), 'assigned_by' => $owner->id]]);

            $first = Customer::create([
                'org_id' => $owner->org_id,
                'customer_code' => '000001',
                'company_name' => 'Siam Retail Co., Ltd.',
                'customer_type' => 'prospect',
                'status' => 'active',
                'owner_id' => $sales->id,
                'phone' => '021111111',
                'email' => 'hello@siam-retail.example',
                'source' => 'Referral',
                'created_by' => $owner->id,
            ]);

            $second = Customer::create([
                'org_id' => $owner->org_id,
                'customer_code' => '000002',
                'company_name' => 'Bangkok Services Group',
                'customer_type' => 'lead',
                'status' => 'active',
                'owner_id' => $owner->id,
                'phone' => '022222222',
                'email' => 'contact@bsg.example',
                'source' => 'Website',
                'created_by' => $owner->id,
            ]);

            $contact = Contact::create([
                'org_id' => $owner->org_id,
                'customer_id' => $first->id,
                'name' => 'Kanda Sales Lead',
                'position' => 'Procurement Manager',
                'phone' => '0811111111',
                'email' => 'kanda@siam-retail.example',
                'is_primary' => true,
                'created_by' => $owner->id,
            ]);

            $deal = Deal::create([
                'org_id' => $owner->org_id,
                'title' => 'Retail ERP Starter Package',
                'customer_id' => $first->id,
                'contact_id' => $contact->id,
                'stage' => 'proposal',
                'value_amount' => 180000,
                'currency' => 'THB',
                'probability' => 60,
                'expected_close_date' => now()->addDays(21)->toDateString(),
                'owner_id' => $sales->id,
                'source' => 'Referral',
                'created_by' => $owner->id,
            ]);

            Deal::create([
                'org_id' => $owner->org_id,
                'title' => 'Service Workflow Setup',
                'customer_id' => $second->id,
                'stage' => 'won',
                'value_amount' => 95000,
                'currency' => 'THB',
                'probability' => 100,
                'expected_close_date' => now()->subDays(2)->toDateString(),
                'owner_id' => $owner->id,
                'won_at' => now()->subDays(1),
                'source' => 'Website',
                'created_by' => $owner->id,
            ]);

            Activity::create([
                'org_id' => $owner->org_id,
                'entity_type' => 'deal',
                'entity_id' => $deal->id,
                'activity_type' => 'meeting',
                'subject' => 'Proposal walkthrough',
                'activity_at' => now()->subDays(3),
                'follow_up_at' => now(),
                'owner_id' => $sales->id,
                'created_by' => $owner->id,
            ]);

            AuditLog::create([
                'org_id' => $owner->org_id,
                'actor_user_id' => $owner->id,
                'action' => 'seed.phase2_demo',
                'entity_type' => 'customer',
                'entity_id' => $first->id,
                'after_json' => ['customers' => 2, 'deals' => 2, 'activities' => 1],
            ]);
        }

        NumberSequence::updateOrCreate(
            ['org_id' => $owner->org_id, 'branch_key' => '00000000-0000-0000-0000-000000000000', 'doc_type' => 'customer', 'year_key' => 0],
            ['branch_id' => null, 'year' => null, 'last_number' => max(2, Customer::where('org_id', $owner->org_id)->count())]
        );
    }

    private function syncPermissionCatalog(): void
    {
        foreach (PermissionCatalog::permissions() as $permission) {
            Permission::firstOrCreate(['code' => $permission['code']], $permission);
        }

        $permissions = Permission::whereIn('code', array_column(PermissionCatalog::permissions(), 'code'))->get()->keyBy('code');

        foreach (Role::all() as $role) {
            $defaultCodes = PermissionCatalog::defaults()[$role->code] ?? [];
            $ids = collect($defaultCodes)
                ->filter(fn (string $code) => $permissions->has($code))
                ->map(fn (string $code) => $permissions[$code]->id)
                ->all();

            $role->permissions()->syncWithoutDetaching($ids);
        }
    }

    private function createDemoUser(User $owner, string $name, string $email, string $position, string $status): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'org_id' => $owner->org_id,
                'branch_id' => $owner->branch_id,
                'division_id' => $owner->division_id,
                'department_id' => $owner->department_id,
                'name' => $name,
                'display_name' => $name,
                'password' => Hash::make('password'),
                'auth_provider' => 'local',
                'status' => $status,
                'position' => $position,
                'email_verified_at' => $status === 'active' ? now() : null,
                'created_by' => $owner->id,
            ]
        );
    }
}
