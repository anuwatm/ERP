<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Services\CheckpointService;
use Pos\Services\RecoveryFenceService;

Artisan::command('pos:health', function () {
    $pending = DB::table('pos_outbox')->where('result_status', '!=', 'accepted');
    $count = (clone $pending)->count();
    $oldest = (clone $pending)->min('created_at');
    $age = $oldest ? max(0, (int) Carbon::parse($oldest)->diffInMinutes(now())) : 0;
    $free = disk_free_space(storage_path());
    $alert = $count >= config('pos.backlog_alert_count') || $age >= config('pos.backlog_alert_minutes') || $free === false || $free < config('pos.minimum_disk_free_bytes');
    $this->line(json_encode(['status' => $alert ? 'attention' : 'ok', 'pending_events' => $count, 'oldest_pending_minutes' => $age, 'disk_free_bytes' => $free], JSON_THROW_ON_ERROR));

    return $alert ? 1 : 0;
});

Artisan::command('pos:restore-event {event}', function () {
    app(CheckpointService::class)->restore($this->argument('event'));
    $this->info('Archived event restored with its original source identity and accepted result.');
});
Artisan::command('pos:hold-checkpoint {checkpoint} {reason}', function () {
    app(CheckpointService::class)->hold($this->argument('checkpoint'), $this->argument('reason'));
    $this->info('Checkpoint held; archive is blocked.');
});

Artisan::command('pos:recovery-initialize', function () {
    app(RecoveryFenceService::class)->initialize();
    $this->info('Independent recovery anchor initialized. Keep it outside database restore sets.');
});

Artisan::command('pos:archive-checkpoint {checkpoint}', function () {
    $count = app(CheckpointService::class)->archive($this->argument('checkpoint'));
    $this->info('Archived '.$count.' accepted events older than 90 days; source evidence and dedupe retained.');
});

Artisan::command('pos:staff {branch} {email} {--role=cashier} {--pharmacist-qualified}', function () {
    $role = $this->option('role');
    if (! in_array($role, ['cashier', 'manager', 'kitchen', 'pharmacist', 'restaurant'], true) || ! DB::table('pos_branches')->where('id', $this->argument('branch'))->exists()) {
        $this->error('Invalid branch or role.');

        return 1;
    }
    $password = $this->secret('Password (minimum 12 characters)');
    if (strlen($password ?? '') < 12) {
        $this->error('Password too short.');

        return 1;
    }
    if ($this->option('pharmacist-qualified') && ! in_array($role, ['manager', 'pharmacist'], true)) {
        $this->error('Pharmacist qualification requires manager or pharmacist role.');

        return 1;
    }
    $staff = Staff::create(['id' => (string) Str::uuid(), 'branch_id' => $this->argument('branch'), 'name' => $this->ask('Staff name'), 'email' => $this->argument('email'), 'password' => $password, 'role' => $role, 'active' => true]);
    if ($this->option('pharmacist-qualified')) {
        abort_unless(in_array($role, ['manager', 'pharmacist'], true), 422);
        $staff->forceFill(['pharmacist_qualified' => true])->save();
    }
    $this->info('POS staff created: '.$staff->id);

    return 0;
});

Artisan::command('pos:branch {id} {code} {name} {--type=retail}', function () {
    abort_unless(app()->environment(['local', 'testing']), 403);
    $d = validator(['id' => $this->argument('id'), 'code' => $this->argument('code'), 'name' => $this->argument('name'), 'business_type' => $this->option('type')], ['id' => 'required|uuid', 'code' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,30}$/D'], 'name' => 'required|string|max:255', 'business_type' => 'required|in:retail,restaurant,pharmacy'])->validate();
    DB::table('pos_branches')->insert($d + ['created_at' => now(), 'updated_at' => now()]);
    $this->info('Local POS branch created: '.$d['id']);
});

Artisan::command('pos:terminal {id} {branch} {code}', function () {
    abort_unless(app()->environment(['local', 'testing']), 403);
    $d = validator(['id' => $this->argument('id'), 'branch_id' => $this->argument('branch'), 'code' => $this->argument('code')], ['id' => 'required|uuid', 'branch_id' => 'required|uuid|exists:pos_branches,id', 'code' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{1,30}$/D']])->validate();
    DB::table('pos_terminals')->insert($d + ['created_at' => now(), 'updated_at' => now()]);
    $this->info('Local POS terminal created: '.$d['id']);
});
