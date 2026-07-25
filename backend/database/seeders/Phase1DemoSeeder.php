<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\OrganizationProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class Phase1DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'owner@example.com')->exists()) {
            return;
        }

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
        $admin->roles()->attach($roles['admin']->id, ['assigned_at' => now(), 'assigned_by' => $owner->id]);

        $inviteToken = Str::random(48);
        $member = $this->createDemoUser($owner, 'Demo Invited Member', 'member@example.com', 'Member', 'invited');
        $member->forceFill([
            'invite_token_hash' => hash('sha256', $inviteToken),
            'invite_expires_at' => now()->addHours(72),
            'invited_at' => now(),
        ])->save();
        $member->roles()->attach($roles['member']->id, ['assigned_at' => now(), 'assigned_by' => $owner->id]);

        $viewer = $this->createDemoUser($owner, 'Demo Inactive Viewer', 'viewer@example.com', 'Viewer', 'inactive');
        $viewer->roles()->attach($roles['viewer']->id, ['assigned_at' => now(), 'assigned_by' => $owner->id]);

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
    }

    private function createDemoUser(User $owner, string $name, string $email, string $position, string $status): User
    {
        return User::create([
            'org_id' => $owner->org_id,
            'branch_id' => $owner->branch_id,
            'division_id' => $owner->division_id,
            'department_id' => $owner->department_id,
            'name' => $name,
            'display_name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'auth_provider' => 'local',
            'status' => $status,
            'position' => $position,
            'email_verified_at' => $status === 'active' ? now() : null,
            'created_by' => $owner->id,
        ]);
    }
}
